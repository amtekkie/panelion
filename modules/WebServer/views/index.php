<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Web Server Management</h2>
        <p>Manage <?= ucfirst($webserver) ?> web server configuration</p>
    </div>
    <div>
        <form method="POST" action="/webserver/restart" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" class="btn btn-warning" data-confirm="Restart the web server?">
                <i class="bi bi-arrow-clockwise me-2"></i>Restart <?= ucfirst($webserver) ?>
            </button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <div class="mb-2">
                <span class="service-status <?= $is_running ? 'online' : 'offline' ?>"></span>
                <strong><?= ucfirst($webserver) ?></strong>
            </div>
            <span class="badge bg-<?= $is_running ? 'success' : 'danger' ?>"><?= $is_running ? 'Running' : 'Stopped' ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <div class="mb-2"><i class="bi bi-hdd-stack text-primary fs-4"></i></div>
            <strong><?= count($vhosts) ?></strong> Virtual Hosts
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <div class="mb-2"><i class="bi bi-filetype-php text-info fs-4"></i></div>
            <strong><?= count($php_versions) ?></strong> PHP Versions
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Virtual Hosts</h5>
                <?php if (empty($vhosts)): ?>
                    <p class="text-muted">No virtual hosts found.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Config File</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($vhosts as $vh): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($vh['name']) ?></code></td>
                                <td><span class="badge bg-<?= $vh['enabled'] ? 'success' : 'secondary' ?>"><?= $vh['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Installed PHP Versions</h5>
                <div class="list-group list-group-flush">
                    <?php foreach ($php_versions as $pv): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-filetype-php text-primary me-2"></i>PHP <?= htmlspecialchars($pv) ?></span>
                            <span class="service-status <?= \Panelion\Core\SystemCommand::isServiceRunning("php{$pv}-fpm") ? 'online' : 'offline' ?>"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="/webserver/php" class="btn btn-outline-primary btn-sm mt-3"><i class="bi bi-gear me-1"></i>PHP Configuration</a>
            </div>
        </div>
    </div>
</div>
