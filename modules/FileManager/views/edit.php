<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-pencil-square me-2"></i>Edit: <?= htmlspecialchars($filename) ?>
        </h1>
        <p class="text-muted mb-0">Path: <code><?= htmlspecialchars($path) ?></code></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/filemanager?path=<?= urlencode(dirname($path)) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-file-code me-1"></i>
            <strong><?= htmlspecialchars($filename) ?></strong>
            <span class="badge bg-secondary ms-2"><?= strtoupper($extension ?: 'txt') ?></span>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnWordWrap" onclick="toggleWordWrap()">
                <i class="bi bi-text-wrap me-1"></i> Wrap
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnMinimap" onclick="toggleMinimap()">
                <i class="bi bi-layout-sidebar-reverse me-1"></i> Minimap
            </button>
            <button class="btn btn-sm btn-primary" id="btnSave" onclick="saveFile()">
                <i class="bi bi-save me-1"></i> Save
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="monacoEditor" style="height: 650px; border: none;"></div>
        <form method="POST" action="/filemanager/save" id="editForm" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="path" value="<?= htmlspecialchars($path) ?>">
            <textarea name="content" id="hiddenContent"><?= htmlspecialchars($content) ?></textarea>
        </form>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <small class="text-muted">
            Line: <span id="cursorLine">1</span>, Col: <span id="cursorCol">1</span> |
            Lines: <span id="lineCount">0</span> |
            <span id="fileLanguage"><?= strtoupper($extension ?: 'txt') ?></span>
        </small>
        <div class="d-flex gap-2">
            <a href="/filemanager?path=<?= urlencode(dirname($path)) ?>" class="btn btn-sm btn-light">Cancel</a>
            <button class="btn btn-sm btn-primary" onclick="saveFile()">
                <i class="bi bi-save me-1"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Monaco Editor from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
<script>
(function() {
    const extensionMap = {
        'php': 'php', 'html': 'html', 'htm': 'html', 'css': 'css',
        'js': 'javascript', 'ts': 'typescript', 'json': 'json',
        'xml': 'xml', 'sql': 'sql', 'py': 'python', 'rb': 'ruby',
        'java': 'java', 'c': 'c', 'cpp': 'cpp', 'h': 'c',
        'cs': 'csharp', 'go': 'go', 'rs': 'rust', 'swift': 'swift',
        'sh': 'shell', 'bash': 'shell', 'yml': 'yaml', 'yaml': 'yaml',
        'md': 'markdown', 'txt': 'plaintext', 'log': 'plaintext',
        'ini': 'ini', 'conf': 'ini', 'cfg': 'ini', 'env': 'ini',
        'dockerfile': 'dockerfile', 'vue': 'html', 'jsx': 'javascript',
        'tsx': 'typescript', 'scss': 'scss', 'less': 'less', 'sass': 'scss'
    };

    const ext = '<?= strtolower($extension ?? "txt") ?>';
    const language = extensionMap[ext] || 'plaintext';
    let wordWrap = 'off';
    let minimapEnabled = true;
    let monacoEditor = null;

    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' }});
    require(['vs/editor/editor.main'], function() {
        const content = document.getElementById('hiddenContent').value;

        monacoEditor = monaco.editor.create(document.getElementById('monacoEditor'), {
            value: content,
            language: language,
            theme: 'vs-dark',
            fontSize: 14,
            fontFamily: "'Cascadia Code', 'Fira Code', 'Consolas', 'Courier New', monospace",
            minimap: { enabled: minimapEnabled },
            wordWrap: wordWrap,
            automaticLayout: true,
            scrollBeyondLastLine: false,
            renderWhitespace: 'boundary',
            tabSize: 4,
            insertSpaces: true,
            lineNumbers: 'on',
            roundedSelection: true,
            folding: true,
            bracketPairColorization: { enabled: true },
            padding: { top: 10 }
        });

        // Update status bar
        document.getElementById('lineCount').textContent = monacoEditor.getModel().getLineCount();
        document.getElementById('fileLanguage').textContent = language.toUpperCase();

        monacoEditor.onDidChangeCursorPosition(function(e) {
            document.getElementById('cursorLine').textContent = e.position.lineNumber;
            document.getElementById('cursorCol').textContent = e.position.column;
        });

        monacoEditor.getModel().onDidChangeContent(function() {
            document.getElementById('lineCount').textContent = monacoEditor.getModel().getLineCount();
        });

        // Ctrl+S to save
        monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
            saveFile();
        });

        window._monacoEditor = monacoEditor;
    });

    window.saveFile = function() {
        if (!window._monacoEditor) return;
        document.getElementById('hiddenContent').value = window._monacoEditor.getValue();
        document.getElementById('editForm').submit();
    };

    window.toggleWordWrap = function() {
        if (!window._monacoEditor) return;
        wordWrap = wordWrap === 'off' ? 'on' : 'off';
        window._monacoEditor.updateOptions({ wordWrap: wordWrap });
        document.getElementById('btnWordWrap').classList.toggle('active', wordWrap === 'on');
    };

    window.toggleMinimap = function() {
        if (!window._monacoEditor) return;
        minimapEnabled = !minimapEnabled;
        window._monacoEditor.updateOptions({ minimap: { enabled: minimapEnabled } });
        document.getElementById('btnMinimap').classList.toggle('active', minimapEnabled);
    };
})();
</script>
