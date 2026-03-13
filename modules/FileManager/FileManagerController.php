<?php
namespace Panelion\Modules\FileManager;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class FileManagerController extends Controller
{
    private $db;
    private $cmd;
    private $baseDir;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cmd = SystemCommand::getInstance();
    }

    private function getBaseDir()
    {
        $user = $this->app->auth()->user();

        // Use configured user_data path (works on Windows and Linux)
        $userDataPath = $this->app->config('paths.user_data');
        if ($userDataPath && is_dir($userDataPath)) {
            if ($user['role'] === 'admin') {
                return $userDataPath;
            }
            $userDir = $userDataPath . DIRECTORY_SEPARATOR . $user['username'];
            if (!is_dir($userDir)) {
                mkdir($userDir, 0755, true);
            }
            return $userDir;
        }

        // Fallback to /home for Linux servers
        if ($user['role'] === 'admin') {
            return '/home';
        }
        return "/home/{$user['username']}";
    }

    private function resolvePath($path)
    {
        $baseDir = $this->getBaseDir();
        $path = $path ?: '/';

        // Normalize and prevent directory traversal
        $fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
        if ($fullPath === false) {
            // Path doesn't exist yet, construct it safely
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
            // Remove any .. components
            $sep = DIRECTORY_SEPARATOR;
            $parts = array_filter(explode($sep, str_replace(['/', '\\'], $sep, $fullPath)), fn($p) => $p !== '..' && $p !== '.');
            $fullPath = (PHP_OS_FAMILY === 'Windows' ? '' : '/') . implode($sep, $parts);
        }

        // Normalize both for comparison
        $normalBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir);
        $normalFull = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);

        // Ensure we're within the base directory
        if (strpos($normalFull, $normalBase) !== 0) {
            return null;
        }

        return $fullPath;
    }

    public function index()
    {
        $path = $this->input('path', '/');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null) {
            $this->app->session()->flash('danger', 'Access denied.');
            $this->redirect('/filemanager');
            return;
        }

        $items = [];
        if (is_dir($fullPath)) {
            $entries = scandir($fullPath);
            foreach ($entries as $entry) {
                if ($entry === '.') continue;
                $entryPath = $fullPath . '/' . $entry;
                $isDir = is_dir($entryPath);
                $items[] = [
                    'name' => $entry,
                    'path' => trim(str_replace($this->getBaseDir(), '', $entryPath), '/'),
                    'is_dir' => $isDir,
                    'size' => $isDir ? 0 : filesize($entryPath),
                    'modified' => filemtime($entryPath),
                    'permissions' => substr(sprintf('%o', fileperms($entryPath)), -4),
                    'owner' => function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($entryPath))['name'] ?? fileowner($entryPath)) : fileowner($entryPath)
                ];
            }

            // Sort: directories first, then by name
            usort($items, function($a, $b) {
                if ($a['name'] === '..') return -1;
                if ($b['name'] === '..') return 1;
                if ($a['is_dir'] !== $b['is_dir']) return $b['is_dir'] - $a['is_dir'];
                return strcasecmp($a['name'], $b['name']);
            });
        }

        // Build breadcrumb
        $breadcrumb = [['name' => 'Home', 'path' => '/']];
        if ($path !== '/') {
            $pathParts = array_filter(explode('/', $path));
            $cumulative = '';
            foreach ($pathParts as $part) {
                $cumulative .= '/' . $part;
                $breadcrumb[] = ['name' => $part, 'path' => $cumulative];
            }
        }

        $this->view('FileManager/views/index', [
            'title' => 'File Manager',
            'items' => $items,
            'currentPath' => $path,
            'breadcrumb' => $breadcrumb,
            'baseDir' => $this->getBaseDir()
        ]);
    }

    public function upload()
    {
        $this->validateCSRF();
        $path = $this->input('path', '/');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !is_dir($fullPath)) {
            $this->app->session()->flash('danger', 'Invalid upload directory.');
            $this->redirect('/filemanager?path=' . urlencode($path));
            return;
        }

        if (empty($_FILES['files'])) {
            $this->app->session()->flash('danger', 'No files selected.');
            $this->redirect('/filemanager?path=' . urlencode($path));
            return;
        }

        $uploaded = 0;
        $errors = 0;

        // Handle multiple files
        $files = $_FILES['files'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            if ($error !== UPLOAD_ERR_OK) {
                $errors++;
                continue;
            }

            // Sanitize filename - prevent path traversal
            $safeName = basename($name);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $safeName);

            $destination = $fullPath . '/' . $safeName;
            if (move_uploaded_file($tmpName, $destination)) {
                $uploaded++;
            } else {
                $errors++;
            }
        }

        $msg = "{$uploaded} file(s) uploaded successfully.";
        if ($errors > 0) $msg .= " {$errors} file(s) failed.";

        $this->app->session()->flash($errors > 0 ? 'warning' : 'success', $msg);
        $this->redirect('/filemanager?path=' . urlencode($path));
    }

    public function createFile()
    {
        $this->validateCSRF();
        $path = $this->input('path', '/');
        $name = $this->input('name');
        $type = $this->input('type', 'file'); // file or folder

        if (empty($name) || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            $this->app->session()->flash('danger', 'Invalid name. Use only letters, numbers, dots, hyphens, and underscores.');
            $this->redirect('/filemanager?path=' . urlencode($path));
            return;
        }

        $fullPath = $this->resolvePath($path . '/' . $name);
        if ($fullPath === null) {
            $this->app->session()->flash('danger', 'Access denied.');
            $this->redirect('/filemanager?path=' . urlencode($path));
            return;
        }

        if (file_exists($fullPath)) {
            $this->app->session()->flash('danger', 'File or folder already exists.');
            $this->redirect('/filemanager?path=' . urlencode($path));
            return;
        }

        try {
            if ($type === 'folder') {
                mkdir($fullPath, 0755, true);
            } else {
                touch($fullPath);
            }

            $this->app->session()->flash('success', ucfirst($type) . " '{$name}' created.");
        } catch (\Exception $e) {
            $this->app->session()->flash('danger', 'Failed to create ' . $type . '.');
        }

        $this->redirect('/filemanager?path=' . urlencode($path));
    }

    public function rename()
    {
        $this->validateCSRF();
        $path = $this->input('path');
        $newName = $this->input('new_name');

        if (empty($newName) || !preg_match('/^[a-zA-Z0-9._-]+$/', $newName)) {
            $this->json(['success' => false, 'message' => 'Invalid name.']);
            return;
        }

        $fullPath = $this->resolvePath($path);
        if ($fullPath === null || !file_exists($fullPath)) {
            $this->json(['success' => false, 'message' => 'File not found.']);
            return;
        }

        $newPath = dirname($fullPath) . '/' . $newName;
        $newResolved = $this->resolvePath(dirname($path) . '/' . $newName);
        if ($newResolved === null) {
            $this->json(['success' => false, 'message' => 'Access denied.']);
            return;
        }

        if (file_exists($newPath)) {
            $this->json(['success' => false, 'message' => 'A file with that name already exists.']);
            return;
        }

        if (rename($fullPath, $newPath)) {
            $this->json(['success' => true, 'message' => 'Renamed successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to rename.']);
        }
    }

    public function deleteFile()
    {
        $this->validateCSRF();
        $path = $this->input('path');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !file_exists($fullPath)) {
            $this->json(['success' => false, 'message' => 'File not found.']);
            return;
        }

        // Prevent deleting the base directory
        if ($fullPath === $this->getBaseDir()) {
            $this->json(['success' => false, 'message' => 'Cannot delete home directory.']);
            return;
        }

        try {
            if (is_dir($fullPath)) {
                $this->cmd->execute("rm -rf " . escapeshellarg($fullPath));
            } else {
                unlink($fullPath);
            }
            $this->json(['success' => true, 'message' => 'Deleted successfully.']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Failed to delete.']);
        }
    }

    public function edit()
    {
        $path = $this->input('path');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !is_file($fullPath)) {
            $this->app->session()->flash('danger', 'File not found.');
            $this->redirect('/filemanager');
            return;
        }

        // Check file size (max 2MB for editing)
        if (filesize($fullPath) > 2 * 1024 * 1024) {
            $this->app->session()->flash('danger', 'File too large to edit (max 2MB).');
            $this->redirect('/filemanager?path=' . urlencode(dirname($path)));
            return;
        }

        $content = file_get_contents($fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        $this->view('FileManager/views/edit', [
            'title' => 'Edit File - ' . basename($path),
            'path' => $path,
            'filename' => basename($path),
            'content' => $content,
            'extension' => $extension
        ]);
    }

    public function save()
    {
        $this->validateCSRF();
        $path = $this->input('path');
        $content = $this->input('content', '');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !is_file($fullPath)) {
            $this->app->session()->flash('danger', 'File not found.');
            $this->redirect('/filemanager');
            return;
        }

        if (file_put_contents($fullPath, $content) !== false) {
            $this->app->session()->flash('success', 'File saved.');
        } else {
            $this->app->session()->flash('danger', 'Failed to save file.');
        }

        $this->redirect('/filemanager/edit?path=' . urlencode($path));
    }

    public function download()
    {
        $path = $this->input('path');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !is_file($fullPath)) {
            $this->app->session()->flash('danger', 'File not found.');
            $this->redirect('/filemanager');
            return;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: must-revalidate');
        readfile($fullPath);
        exit;
    }

    public function compress()
    {
        $this->validateCSRF();
        $path = $this->input('path');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !file_exists($fullPath)) {
            $this->json(['success' => false, 'message' => 'File not found.']);
            return;
        }

        $archiveName = basename($fullPath) . '.tar.gz';
        $archivePath = dirname($fullPath) . '/' . $archiveName;

        $parentDir = dirname($fullPath);
        $baseName = basename($fullPath);

        $result = $this->cmd->execute("cd " . escapeshellarg($parentDir) . " && tar -czf " . escapeshellarg($archiveName) . " " . escapeshellarg($baseName) . " 2>&1");

        if (file_exists($archivePath)) {
            $this->json(['success' => true, 'message' => "Compressed to {$archiveName}."]);
        } else {
            $this->json(['success' => false, 'message' => 'Compression failed.']);
        }
    }

    public function extract()
    {
        $this->validateCSRF();
        $path = $this->input('path');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !is_file($fullPath)) {
            $this->json(['success' => false, 'message' => 'Archive not found.']);
            return;
        }

        $dir = dirname($fullPath);
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($ext === 'gz' || str_ends_with($fullPath, '.tar.gz') || str_ends_with($fullPath, '.tgz')) {
            $result = $this->cmd->execute("cd " . escapeshellarg($dir) . " && tar -xzf " . escapeshellarg(basename($fullPath)) . " 2>&1");
        } elseif ($ext === 'zip') {
            $result = $this->cmd->execute("cd " . escapeshellarg($dir) . " && unzip -o " . escapeshellarg(basename($fullPath)) . " 2>&1");
        } elseif ($ext === 'bz2') {
            $result = $this->cmd->execute("cd " . escapeshellarg($dir) . " && tar -xjf " . escapeshellarg(basename($fullPath)) . " 2>&1");
        } else {
            $this->json(['success' => false, 'message' => 'Unsupported archive format.']);
            return;
        }

        $this->json(['success' => true, 'message' => 'Extracted successfully.']);
    }

    public function permissions()
    {
        $this->validateCSRF();
        $path = $this->input('path');
        $perms = $this->input('permissions');
        $recursive = $this->input('recursive') === '1';
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !file_exists($fullPath)) {
            $this->json(['success' => false, 'message' => 'File not found.']);
            return;
        }

        if (!preg_match('/^[0-7]{3,4}$/', $perms)) {
            $this->json(['success' => false, 'message' => 'Invalid permissions format.']);
            return;
        }

        $flag = $recursive && is_dir($fullPath) ? '-R' : '';
        $result = $this->cmd->execute("chmod {$flag} {$perms} " . escapeshellarg($fullPath) . " 2>&1");

        $this->json(['success' => true, 'message' => 'Permissions updated.']);
    }

    public function copy()
    {
        $this->validateCSRF();
        $source = $this->input('source');
        $destination = $this->input('destination');

        $srcPath = $this->resolvePath($source);
        $dstPath = $this->resolvePath($destination);

        if ($srcPath === null || $dstPath === null || !file_exists($srcPath)) {
            $this->json(['success' => false, 'message' => 'Invalid path.']);
            return;
        }

        $flag = is_dir($srcPath) ? '-r' : '';
        $result = $this->cmd->execute("cp {$flag} " . escapeshellarg($srcPath) . " " . escapeshellarg($dstPath) . " 2>&1");

        $this->json(['success' => true, 'message' => 'Copied successfully.']);
    }

    public function move()
    {
        $this->validateCSRF();
        $source = $this->input('source');
        $destination = $this->input('destination');

        $srcPath = $this->resolvePath($source);
        $dstPath = $this->resolvePath($destination);

        if ($srcPath === null || $dstPath === null || !file_exists($srcPath)) {
            $this->json(['success' => false, 'message' => 'Invalid path.']);
            return;
        }

        $result = $this->cmd->execute("mv " . escapeshellarg($srcPath) . " " . escapeshellarg($dstPath) . " 2>&1");

        $this->json(['success' => true, 'message' => 'Moved successfully.']);
    }

    public function uploadAjax()
    {
        $this->validateCSRF();
        $path = $this->input('path', '/');
        $relativePath = $this->input('relative_path', '');
        $fullPath = $this->resolvePath($path);

        if ($fullPath === null || !is_dir($fullPath)) {
            $this->json(['success' => false, 'message' => 'Invalid upload directory.']);
            return;
        }

        if (empty($_FILES['file'])) {
            $this->json(['success' => false, 'message' => 'No file provided.']);
            return;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Upload error.']);
            return;
        }

        // If a relative path is provided (from folder drag-drop), create subdirectories
        $targetDir = $fullPath;
        if ($relativePath) {
            $relDir = dirname($relativePath);
            if ($relDir !== '.' && $relDir !== '') {
                // Sanitize each path segment
                $segments = explode('/', str_replace('\\', '/', $relDir));
                $safeParts = [];
                foreach ($segments as $seg) {
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $seg);
                    if ($safe !== '' && $safe !== '.' && $safe !== '..') {
                        $safeParts[] = $safe;
                    }
                }
                if (!empty($safeParts)) {
                    $subDir = implode(DIRECTORY_SEPARATOR, $safeParts);
                    $targetDir = $fullPath . DIRECTORY_SEPARATOR . $subDir;
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    // Verify we're still within base dir
                    $resolvedTarget = realpath($targetDir);
                    $normalBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBaseDir());
                    if ($resolvedTarget === false || strpos(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedTarget), $normalBase) !== 0) {
                        $this->json(['success' => false, 'message' => 'Access denied.']);
                        return;
                    }
                }
            }
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($relativePath));
        } else {
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        }

        $destination = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $this->json(['success' => true, 'message' => 'Uploaded: ' . $safeName]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to save file.']);
        }
    }
}
