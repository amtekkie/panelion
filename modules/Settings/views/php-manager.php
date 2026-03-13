<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-filetype-php me-2"></i>PHP Manager</h1>
        <p class="text-muted mb-0">Manage PHP versions, modules, and default runtime</p>
    </div>
    <a href="/settings" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Settings</a>
</div>

<!-- Default PHP Version -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-star me-2"></i>Default PHP Version</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">This is the PHP version Panelion runs on and the default for all web server configurations.</p>
        <form method="POST" action="/settings/php/default" class="row align-items-end g-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="col-auto">
                <label class="form-label fw-bold">PHP Version</label>
                <select name="version" class="form-select" style="width: 200px;">
                    <?php foreach ($versions as $ver): ?>
                        <option value="<?= htmlspecialchars($ver) ?>" <?= $ver === $defaultPhp ? 'selected' : '' ?>>
                            PHP <?= htmlspecialchars($ver) ?> <?= $ver === $defaultPhp ? '(current)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Set Default</button>
            </div>
        </form>
    </div>
</div>

<!-- Installed PHP Versions -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-stack me-2"></i>Installed PHP Versions</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($versions as $ver):
                $data = $versionData[$ver] ?? ['installed_modules' => [], 'available_modules' => [], 'fpm_running' => false];
            ?>
            <div class="col-lg-6 col-xl-4">
                <div class="card border h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <span class="fw-bold">
                            <i class="bi bi-filetype-php text-primary me-1"></i>PHP <?= htmlspecialchars($ver) ?>
                            <?php if ($ver === $defaultPhp): ?>
                                <span class="badge bg-warning text-dark ms-1">Default</span>
                            <?php endif; ?>
                        </span>
                        <span class="badge bg-<?= $data['fpm_running'] ? 'success' : 'danger' ?>">
                            FPM <?= $data['fpm_running'] ? 'Running' : 'Stopped' ?>
                        </span>
                    </div>
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted"><?= count($data['installed_modules']) ?> modules loaded</small>
                            <button class="btn btn-sm btn-outline-primary" onclick="showModules('<?= $ver ?>')">
                                <i class="bi bi-puzzle me-1"></i>Manage Modules
                            </button>
                        </div>
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-success" onclick="fpmAction('php<?= $ver ?>-fpm', 'restart')">
                                <i class="bi bi-arrow-clockwise me-1"></i>Restart FPM
                            </button>
                            <button class="btn btn-outline-secondary" onclick="fpmAction('php<?= $ver ?>-fpm', '<?= $data['fpm_running'] ? 'stop' : 'start' ?>')">
                                <?= $data['fpm_running'] ? '<i class="bi bi-stop-fill me-1"></i>Stop' : '<i class="bi bi-play-fill me-1"></i>Start' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Module Manager Modal -->
<div class="modal fade" id="moduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-puzzle me-2"></i>PHP <span id="moduleVersion"></span> — Modules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#installedTab">Installed</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#availableTab">Available to Install</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="installedTab">
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="searchInstalled" placeholder="Filter installed modules...">
                        </div>
                        <div id="installedModules" style="max-height: 400px; overflow-y: auto;">
                            <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="availableTab">
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="searchAvailable" placeholder="Filter available modules...">
                        </div>
                        <div id="availableModules" style="max-height: 400px; overflow-y: auto;">
                            <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentVersion = '';
const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';
const protectedModules = ['common', 'cli', 'fpm', 'core', 'standard', 'date', 'pcre', 'reflection', 'spl'];

function showModules(ver) {
    currentVersion = ver;
    document.getElementById('moduleVersion').textContent = ver;
    document.getElementById('installedModules').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
    document.getElementById('availableModules').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
    new bootstrap.Modal(document.getElementById('moduleModal')).show();

    Panelion.ajax('/settings/php/modules?version=' + ver)
        .then(data => {
            renderInstalled(data.installed || []);
            renderAvailable(data.available || []);
        })
        .catch(() => {
            document.getElementById('installedModules').innerHTML = '<p class="text-danger">Failed to load modules.</p>';
        });
}

function renderInstalled(modules) {
    if (modules.length === 0) {
        document.getElementById('installedModules').innerHTML = '<p class="text-muted">No modules found.</p>';
        return;
    }
    let html = '<div class="list-group list-group-flush" id="installedList">';
    modules.forEach(mod => {
        const isProtected = protectedModules.includes(mod);
        html += `<div class="list-group-item d-flex justify-content-between align-items-center py-1 module-item" data-name="${mod}">
            <span><i class="bi bi-check-circle-fill text-success me-2"></i>${mod}</span>
            ${isProtected ? '<span class="badge bg-secondary">Core</span>' :
            `<button class="btn btn-sm btn-outline-danger" onclick="removeModule('${mod}')" title="Remove"><i class="bi bi-trash"></i></button>`}
        </div>`;
    });
    html += '</div>';
    document.getElementById('installedModules').innerHTML = html;
}

function renderAvailable(modules) {
    if (modules.length === 0) {
        document.getElementById('availableModules').innerHTML = '<p class="text-muted">No additional modules available.</p>';
        return;
    }
    let html = '<div class="list-group list-group-flush" id="availableList">';
    modules.forEach(mod => {
        html += `<div class="list-group-item d-flex justify-content-between align-items-center py-1 module-item" data-name="${mod}">
            <span><i class="bi bi-circle text-muted me-2"></i>${mod}</span>
            <button class="btn btn-sm btn-outline-success" onclick="installModule('${mod}')" title="Install"><i class="bi bi-plus-lg"></i></button>
        </div>`;
    });
    html += '</div>';
    document.getElementById('availableModules').innerHTML = html;
}

function installModule(mod) {
    if (!confirm(`Install php${currentVersion}-${mod}? PHP-FPM will be restarted.`)) return;
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';

    Panelion.ajax('/settings/php/module/install', {
        method: 'POST',
        data: { version: currentVersion, module: mod, csrf_token: csrfToken }
    }).then(r => {
        Panelion.toast(r.message, r.success ? 'success' : 'danger');
        if (r.success) showModules(currentVersion);
    }).catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i>';
    });
}

function removeModule(mod) {
    if (!confirm(`Remove php${currentVersion}-${mod}? PHP-FPM will be restarted.`)) return;
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';

    Panelion.ajax('/settings/php/module/remove', {
        method: 'POST',
        data: { version: currentVersion, module: mod, csrf_token: csrfToken }
    }).then(r => {
        Panelion.toast(r.message, r.success ? 'success' : 'danger');
        if (r.success) showModules(currentVersion);
    }).catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash"></i>';
    });
}

function fpmAction(service, action) {
    if (!confirm(`${action} ${service}?`)) return;
    Panelion.ajax('/settings/services/action', {
        method: 'POST',
        data: { service, action, csrf_token: csrfToken }
    }).then(r => {
        Panelion.toast(r.message, r.success ? 'success' : 'danger');
        if (r.success) setTimeout(() => location.reload(), 1500);
    });
}

// Filter modules
['searchInstalled', 'searchAvailable'].forEach(id => {
    document.getElementById(id).addEventListener('input', function() {
        const q = this.value.toLowerCase();
        const listId = id === 'searchInstalled' ? 'installedList' : 'availableList';
        const list = document.getElementById(listId);
        if (!list) return;
        list.querySelectorAll('.module-item').forEach(el => {
            el.style.display = el.dataset.name.includes(q) ? '' : 'none';
        });
    });
});
</script>
