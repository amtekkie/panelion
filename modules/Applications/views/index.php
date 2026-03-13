<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Applications</h1>
        <p class="text-muted mb-0">Manage your web applications and services</p>
    </div>
    <a href="/applications/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Application
    </a>
</div>

<!-- Runtime Overview -->
<div class="row g-3 mb-4">
    <?php
    $typeCounts = [];
    foreach ($applications as $app) {
        $typeCounts[$app['type']] = ($typeCounts[$app['type']] ?? 0) + 1;
    }
    $activeCount = count(array_filter($applications, fn($a) => $a['live_status'] === 'running' || $a['live_status'] === 'active'));
    ?>
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Total Apps</div>
                        <div class="stat-value"><?= count($applications) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Running</div>
                        <div class="stat-value"><?= $activeCount ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Stopped</div>
                        <div class="stat-value"><?= count($applications) - $activeCount ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-stop-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Runtime Types</div>
                        <div class="stat-value"><?= count($typeCounts) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-code-slash"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Applications</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="filterType" style="width: 150px;">
                <option value="">All Types</option>
                <?php foreach ($runtimes as $key => $rt): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($rt['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" id="filterStatus" style="width: 130px;">
                <option value="">All Status</option>
                <option value="running">Running</option>
                <option value="stopped">Stopped</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($applications)): ?>
            <div class="text-center py-5">
                <i class="bi bi-grid-3x3-gap display-4 text-muted"></i>
                <p class="text-muted mt-3">No applications yet. Create your first application to get started.</p>
                <a href="/applications/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create Application
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="appsTable">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Type</th>
                            <th>Domain</th>
                            <th>Port</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Owner</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr data-type="<?= htmlspecialchars($app['type']) ?>" data-status="<?= htmlspecialchars($app['live_status']) ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-<?= $runtimes[$app['type']]['icon'] ?? 'app' ?> me-2 fs-5"></i>
                                        <div>
                                            <strong><?= htmlspecialchars($app['name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($app['directory'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($runtimes[$app['type']]['name'] ?? $app['type']) ?></span>
                                    <?php if ($app['version']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($app['version']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($app['domain'] ?? '—') ?></td>
                                <td><?= $app['port'] ? $app['port'] : '—' ?></td>
                                <td>
                                    <?php if ($app['live_status'] === 'running' || $app['live_status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:0.5em"></i> Running</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-circle-fill me-1" style="font-size:0.5em"></i> Stopped</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?= htmlspecialchars($app['username'] ?? '') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/applications/<?= $app['id'] ?>" class="btn btn-outline-primary" title="Manage">
                                            <i class="bi bi-gear"></i>
                                        </a>
                                        <?php if (!in_array($app['type'], ['php', 'static'])): ?>
                                            <?php if ($app['live_status'] !== 'running'): ?>
                                                <form method="POST" action="/applications/<?= $app['id'] ?>/start" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-outline-success" title="Start"><i class="bi bi-play"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="/applications/<?= $app['id'] ?>/stop" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-outline-warning" title="Stop"><i class="bi bi-stop"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="/applications/<?= $app['id'] ?>/restart" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-outline-info" title="Restart"><i class="bi bi-arrow-clockwise"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="/applications/<?= $app['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this application? This cannot be undone.')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('filterType')?.addEventListener('change', filterApps);
document.getElementById('filterStatus')?.addEventListener('change', filterApps);

function filterApps() {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    document.querySelectorAll('#appsTable tbody tr').forEach(row => {
        const matchType = !type || row.dataset.type === type;
        const matchStatus = !status || row.dataset.status === status;
        row.style.display = (matchType && matchStatus) ? '' : 'none';
    });
}
</script>
