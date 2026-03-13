<style>
/* Drag and drop overlay */
#dropZone { position: relative; }
#dropOverlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(13, 110, 253, 0.15); border: 3px dashed #0d6efd;
    z-index: 9998; pointer-events: none;
}
#dropOverlay.active { display: flex; align-items: center; justify-content: center; }
#dropOverlay .drop-msg {
    background: #0d6efd; color: #fff; padding: 1.5rem 3rem; border-radius: 12px;
    font-size: 1.2rem; font-weight: 600;
}
/* Context menu */
#contextMenu {
    display: none; position: fixed; z-index: 10000; min-width: 200px;
    background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.15); padding: 6px 0;
}
#contextMenu .ctx-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 16px; cursor: pointer;
    color: var(--bs-body-color, #212529); text-decoration: none; font-size: 0.9rem;
}
#contextMenu .ctx-item:hover { background: var(--bs-primary-bg-subtle, #e7f1ff); }
#contextMenu .ctx-item.text-danger:hover { background: #f8d7da; }
#contextMenu .ctx-divider { height: 1px; background: var(--bs-border-color, #dee2e6); margin: 4px 0; }
/* Upload progress */
#uploadProgress { display: none; }
#uploadProgress.active { display: block; }
</style>

<div id="dropZone">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">File Manager</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <?php foreach ($breadcrumb as $i => $crumb): ?>
                    <?php if ($i === count($breadcrumb) - 1): ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['name']) ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="/filemanager?path=<?= urlencode($crumb['path']) ?>"><?= htmlspecialchars($crumb['name']) ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-upload me-1"></i> Upload
        </button>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i> New
        </button>
    </div>
</div>

<!-- Drag & Drop Upload Progress -->
<div id="uploadProgress" class="card mb-3 border-primary">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span id="uploadStatusText">Uploading files...</span>
            <span class="ms-auto badge bg-primary" id="uploadCount">0/0</span>
        </div>
        <div class="progress mt-2" style="height: 4px;">
            <div class="progress-bar" id="uploadBar" style="width: 0%"></div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body py-2 d-flex gap-2 align-items-center">
        <div class="input-group input-group-sm" style="max-width: 400px;">
            <span class="input-group-text"><i class="bi bi-folder2"></i></span>
            <input type="text" class="form-control" id="pathInput" value="<?= htmlspecialchars($currentPath) ?>" placeholder="/">
            <button class="btn btn-outline-primary" onclick="navigateTo(document.getElementById('pathInput').value)">Go</button>
        </div>
        <div class="ms-auto d-flex gap-1">
            <button class="btn btn-sm btn-outline-secondary" id="btnSelectAll" onclick="toggleSelectAll()">
                <i class="bi bi-check-all"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" id="btnDeleteSelected" onclick="deleteSelected()" disabled>
                <i class="bi bi-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- File List -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="fileTable">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" class="form-check-input" id="selectAllCb" onchange="toggleSelectAll()"></th>
                        <th>Name</th>
                        <th style="width: 100px;">Size</th>
                        <th style="width: 150px;">Modified</th>
                        <th style="width: 80px;">Perms</th>
                        <th style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="file-row" data-path="<?= htmlspecialchars($item['path']) ?>" data-name="<?= htmlspecialchars($item['name']) ?>" data-isdir="<?= $item['is_dir'] ? '1' : '0' ?>" data-perms="<?= htmlspecialchars($item['permissions']) ?>" oncontextmenu="showContextMenu(event, this)">
                            <td>
                                <?php if ($item['name'] !== '..'): ?>
                                    <input type="checkbox" class="form-check-input file-checkbox" value="<?= htmlspecialchars($item['path']) ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['is_dir']): ?>
                                    <a href="/filemanager?path=<?= urlencode(($currentPath === '/' ? '' : $currentPath) . '/' . $item['name']) ?>" class="text-decoration-none">
                                        <i class="bi bi-folder-fill text-warning me-1"></i>
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                    </a>
                                <?php else: ?>
                                    <i class="bi bi-<?= getFileIcon($item['name']) ?> me-1"></i>
                                    <?= htmlspecialchars($item['name']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$item['is_dir']): ?>
                                    <small><?= formatFileSize($item['size']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">&mdash;</small>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= date('M j, Y H:i', $item['modified']) ?></small></td>
                            <td><code class="small"><?= $item['permissions'] ?></code></td>
                            <td>
                                <?php if ($item['name'] !== '..'): ?>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!$item['is_dir']): ?>
                                            <a href="/filemanager/edit?path=<?= urlencode($item['path']) ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a href="/filemanager/download?path=<?= urlencode($item['path']) ?>" class="btn btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-info" onclick="renameFile('<?= htmlspecialchars(addslashes($item['path'])) ?>', '<?= htmlspecialchars(addslashes($item['name'])) ?>')" title="Rename"><i class="bi bi-pen"></i></button>
                                        <?php if (in_array(strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)), ['zip', 'gz', 'tgz', 'bz2'])): ?>
                                            <button class="btn btn-outline-warning" onclick="extractFile('<?= htmlspecialchars(addslashes($item['path'])) ?>')" title="Extract"><i class="bi bi-box-arrow-up"></i></button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-danger" onclick="deleteFile('<?= htmlspecialchars(addslashes($item['path'])) ?>')" title="Delete"><i class="bi bi-trash"></i></button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div><!-- /dropZone -->

<!-- Drop Overlay -->
<div id="dropOverlay"><div class="drop-msg"><i class="bi bi-cloud-upload me-2"></i>Drop files or folders here to upload</div></div>

<!-- Context Menu -->
<div id="contextMenu">
    <div class="ctx-item" id="ctxOpen"><i class="bi bi-folder2-open"></i> Open</div>
    <div class="ctx-item" id="ctxEdit"><i class="bi bi-pencil-square"></i> Edit</div>
    <div class="ctx-item" id="ctxDownload"><i class="bi bi-download"></i> Download</div>
    <div class="ctx-divider"></div>
    <div class="ctx-item" id="ctxRename"><i class="bi bi-pen"></i> Rename</div>
    <div class="ctx-item" id="ctxCopy"><i class="bi bi-clipboard"></i> Copy Path</div>
    <div class="ctx-item" id="ctxCompress"><i class="bi bi-file-zip"></i> Compress</div>
    <div class="ctx-item" id="ctxExtract" style="display:none;"><i class="bi bi-box-arrow-up"></i> Extract</div>
    <div class="ctx-item" id="ctxPerms"><i class="bi bi-shield-lock"></i> Permissions</div>
    <div class="ctx-divider"></div>
    <div class="ctx-item text-danger" id="ctxDelete"><i class="bi bi-trash"></i> Delete</div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/filemanager/upload" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="path" value="<?= htmlspecialchars($currentPath) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Files</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select files to upload</label>
                        <input type="file" class="form-control" name="files[]" multiple required>
                    </div>
                    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>You can also drag &amp; drop files/folders directly onto the file list.</p>
                    <p class="text-muted small">Uploading to: <code><?= htmlspecialchars($currentPath) ?></code></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload me-1"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create File/Folder Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/filemanager/create">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="path" value="<?= htmlspecialchars($currentPath) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Create New</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="file" id="typeFile" checked>
                                <label class="form-check-label" for="typeFile"><i class="bi bi-file-earmark me-1"></i> File</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="folder" id="typeFolder">
                                <label class="form-check-label" for="typeFolder"><i class="bi bi-folder me-1"></i> Folder</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required pattern="[a-zA-Z0-9._-]+" placeholder="filename.txt">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div class="modal fade" id="permsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">File: <strong id="permsFileName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Permissions (octal)</label>
                    <input type="text" class="form-control" id="permsInput" placeholder="0755" maxlength="4" pattern="[0-7]{3,4}">
                    <div class="form-text">E.g. 0755 (rwxr-xr-x), 0644 (rw-r--r--)</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quick Set</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('permsInput').value='0644'">0644</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('permsInput').value='0755'">0755</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('permsInput').value='0775'">0775</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('permsInput').value='0777'">0777</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('permsInput').value='0600'">0600</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('permsInput').value='0700'">0700</button>
                    </div>
                </div>
                <div class="form-check" id="permsRecursiveRow">
                    <input class="form-check-input" type="checkbox" id="permsRecursive">
                    <label class="form-check-label" for="permsRecursive">Apply recursively (folders)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyPermissions()">Apply</button>
            </div>
        </div>
    </div>
</div>

<?php
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match($ext) {
        'php' => 'filetype-php',
        'html', 'htm' => 'filetype-html',
        'css' => 'filetype-css',
        'js' => 'filetype-js',
        'json' => 'filetype-json',
        'xml' => 'filetype-xml',
        'py' => 'filetype-py',
        'rb' => 'filetype-rb',
        'java' => 'filetype-java',
        'sql' => 'filetype-sql',
        'md' => 'filetype-md',
        'txt', 'log' => 'file-text',
        'pdf' => 'file-pdf',
        'doc', 'docx' => 'file-word',
        'xls', 'xlsx' => 'file-excel',
        'ppt', 'pptx' => 'file-ppt',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp' => 'file-image',
        'mp4', 'avi', 'mov', 'mkv' => 'file-play',
        'mp3', 'wav', 'flac' => 'file-music',
        'zip', 'tar', 'gz', 'rar', '7z', 'bz2' => 'file-zip',
        'sh', 'bash' => 'terminal',
        'conf', 'cfg', 'ini', 'env', 'yml', 'yaml' => 'gear',
        default => 'file-earmark'
    };
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}
?>

<script>
const currentPath = '<?= addslashes($currentPath) ?>';
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
const archiveExts = ['zip', 'gz', 'tgz', 'bz2', 'tar'];

/* ── Navigation ── */
function navigateTo(path) {
    window.location.href = '/filemanager?path=' + encodeURIComponent(path);
}

/* ── AJAX helpers ── */
function fmPost(url, data) {
    data.csrf_token = csrfToken;
    return Panelion.ajax(url, {
        method: 'POST',
        body: JSON.stringify(data),
        headers: { 'Content-Type': 'application/json' }
    });
}

/* ── File Actions ── */
function renameFile(path, oldName) {
    const newName = prompt('Enter new name:', oldName);
    if (!newName || newName === oldName) return;
    fmPost('/filemanager/rename', { path, new_name: newName }).then(d => {
        if (d.success) location.reload(); else alert(d.message);
    });
}

function deleteFile(path) {
    if (!confirm('Delete this item? This cannot be undone.')) return;
    fmPost('/filemanager/delete', { path }).then(d => {
        if (d.success) location.reload(); else alert(d.message);
    });
}

function compressFile(path) {
    fmPost('/filemanager/compress', { path }).then(d => {
        if (d.success) location.reload(); else alert(d.message);
    });
}

function extractFile(path) {
    fmPost('/filemanager/extract', { path }).then(d => {
        if (d.success) location.reload(); else alert(d.message);
    });
}

/* ── Select All / Delete Selected ── */
function toggleSelectAll() {
    const cb = document.getElementById('selectAllCb');
    document.querySelectorAll('.file-checkbox').forEach(c => c.checked = cb.checked);
    updateDeleteBtn();
}
function updateDeleteBtn() {
    const checked = document.querySelectorAll('.file-checkbox:checked').length;
    document.getElementById('btnDeleteSelected').disabled = checked === 0;
}
function deleteSelected() {
    const selected = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(c => c.value);
    if (!selected.length || !confirm('Delete ' + selected.length + ' item(s)?')) return;
    Promise.all(selected.map(path => fmPost('/filemanager/delete', { path }))).then(() => location.reload());
}
document.querySelectorAll('.file-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteBtn));

/* ── Context Menu ── */
let ctxTarget = null;
function showContextMenu(e, row) {
    if (row.dataset.name === '..') return;
    e.preventDefault();
    ctxTarget = row;
    const menu = document.getElementById('contextMenu');
    const isDir = row.dataset.isdir === '1';
    const path = row.dataset.path;
    const name = row.dataset.name;
    const ext = name.split('.').pop().toLowerCase();

    document.getElementById('ctxOpen').style.display = isDir ? '' : 'none';
    document.getElementById('ctxEdit').style.display = isDir ? 'none' : '';
    document.getElementById('ctxDownload').style.display = isDir ? 'none' : '';
    document.getElementById('ctxExtract').style.display = archiveExts.includes(ext) ? '' : 'none';

    // Position
    menu.style.display = 'block';
    let x = e.clientX, y = e.clientY;
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    if (x + mw > window.innerWidth) x = window.innerWidth - mw - 5;
    if (y + mh > window.innerHeight) y = window.innerHeight - mh - 5;
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
}

document.addEventListener('click', () => { document.getElementById('contextMenu').style.display = 'none'; });
document.addEventListener('contextmenu', (e) => {
    if (!e.target.closest('.file-row')) document.getElementById('contextMenu').style.display = 'none';
});

document.getElementById('ctxOpen').addEventListener('click', () => {
    if (ctxTarget) navigateTo((currentPath === '/' ? '' : currentPath) + '/' + ctxTarget.dataset.name);
});
document.getElementById('ctxEdit').addEventListener('click', () => {
    if (ctxTarget) window.location.href = '/filemanager/edit?path=' + encodeURIComponent(ctxTarget.dataset.path);
});
document.getElementById('ctxDownload').addEventListener('click', () => {
    if (ctxTarget) window.location.href = '/filemanager/download?path=' + encodeURIComponent(ctxTarget.dataset.path);
});
document.getElementById('ctxRename').addEventListener('click', () => {
    if (ctxTarget) renameFile(ctxTarget.dataset.path, ctxTarget.dataset.name);
});
document.getElementById('ctxCopy').addEventListener('click', () => {
    if (ctxTarget) { navigator.clipboard.writeText(ctxTarget.dataset.path); Panelion.toast('Path copied!', 'info'); }
});
document.getElementById('ctxCompress').addEventListener('click', () => {
    if (ctxTarget) compressFile(ctxTarget.dataset.path);
});
document.getElementById('ctxExtract').addEventListener('click', () => {
    if (ctxTarget) extractFile(ctxTarget.dataset.path);
});
document.getElementById('ctxDelete').addEventListener('click', () => {
    if (ctxTarget) deleteFile(ctxTarget.dataset.path);
});
document.getElementById('ctxPerms').addEventListener('click', () => {
    if (!ctxTarget) return;
    document.getElementById('permsFileName').textContent = ctxTarget.dataset.name;
    document.getElementById('permsInput').value = ctxTarget.dataset.perms;
    document.getElementById('permsRecursiveRow').style.display = ctxTarget.dataset.isdir === '1' ? '' : 'none';
    document.getElementById('permsRecursive').checked = false;
    const modal = new bootstrap.Modal(document.getElementById('permsModal'));
    modal.show();
});

/* ── Permissions ── */
function applyPermissions() {
    if (!ctxTarget) return;
    const perms = document.getElementById('permsInput').value;
    const recursive = document.getElementById('permsRecursive').checked ? '1' : '0';
    fmPost('/filemanager/permissions', { path: ctxTarget.dataset.path, permissions: perms, recursive }).then(d => {
        if (d.success) { bootstrap.Modal.getInstance(document.getElementById('permsModal')).hide(); location.reload(); }
        else alert(d.message);
    });
}

/* ── Drag and Drop Upload ── */
(function() {
    const overlay = document.getElementById('dropOverlay');
    const progress = document.getElementById('uploadProgress');
    const statusText = document.getElementById('uploadStatusText');
    const countEl = document.getElementById('uploadCount');
    const bar = document.getElementById('uploadBar');
    let dragCounter = 0;

    document.addEventListener('dragenter', (e) => { e.preventDefault(); dragCounter++; overlay.classList.add('active'); });
    document.addEventListener('dragleave', (e) => { e.preventDefault(); dragCounter--; if (dragCounter <= 0) { dragCounter = 0; overlay.classList.remove('active'); } });
    document.addEventListener('dragover', (e) => e.preventDefault());
    document.addEventListener('drop', async (e) => {
        e.preventDefault();
        dragCounter = 0;
        overlay.classList.remove('active');

        const files = [];
        const items = e.dataTransfer.items;

        if (items && items.length) {
            // Use webkitGetAsEntry for recursive folder traversal
            const entries = [];
            for (let i = 0; i < items.length; i++) {
                const entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
                if (entry) entries.push(entry);
            }
            if (entries.length) {
                await traverseEntries(entries, '', files);
            }
        }

        if (!files.length) {
            // Fallback for browsers without webkitGetAsEntry
            for (let i = 0; i < e.dataTransfer.files.length; i++) {
                files.push({ file: e.dataTransfer.files[i], relativePath: e.dataTransfer.files[i].name });
            }
        }

        if (files.length) uploadFiles(files);
    });

    async function traverseEntries(entries, basePath, result) {
        for (const entry of entries) {
            if (entry.isFile) {
                const file = await new Promise(resolve => entry.file(resolve));
                result.push({ file, relativePath: basePath + entry.name });
            } else if (entry.isDirectory) {
                const dirReader = entry.createReader();
                const children = await new Promise(resolve => {
                    const all = [];
                    const readBatch = () => {
                        dirReader.readEntries(entries => {
                            if (entries.length === 0) { resolve(all); return; }
                            all.push(...entries);
                            readBatch();
                        });
                    };
                    readBatch();
                });
                await traverseEntries(children, basePath + entry.name + '/', result);
            }
        }
    }

    async function uploadFiles(files) {
        progress.classList.add('active');
        let done = 0;
        const total = files.length;

        for (const { file, relativePath } of files) {
            statusText.textContent = 'Uploading: ' + relativePath;
            countEl.textContent = done + '/' + total;
            bar.style.width = ((done / total) * 100) + '%';

            const fd = new FormData();
            fd.append('file', file);
            fd.append('path', currentPath);
            fd.append('relative_path', relativePath);
            fd.append('csrf_token', csrfToken);

            try {
                await fetch('/filemanager/upload-ajax', { method: 'POST', body: fd });
            } catch(e) { /* continue */ }
            done++;
        }

        bar.style.width = '100%';
        countEl.textContent = total + '/' + total;
        statusText.textContent = 'Upload complete!';
        setTimeout(() => location.reload(), 800);
    }
})();
</script>
