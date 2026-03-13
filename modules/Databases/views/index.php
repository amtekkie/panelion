<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Databases</h2>
        <p>Manage MySQL, PostgreSQL, MongoDB, Redis &amp; SQLite databases</p>
    </div>
    <a href="/databases/create" class="btn btn-primary"><i class="bi bi-database-add me-2"></i>Create Database</a>
</div>

<!-- Database Service Status -->
<div class="row g-3 mb-4">
    <?php foreach ($services as $svc => $running): ?>
    <div class="col-md-3">
        <div class="card p-2 text-center">
            <span class="service-status <?= $running ? 'online' : 'offline' ?>"></span>
            <strong><?= ucfirst($svc) ?></strong>: <?= $running ? 'Running' : 'Stopped' ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Databases List -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Databases</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <?php if ($user['role'] === 'admin'): ?><th>Owner</th><?php endif; ?>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($databases)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No databases created.</td></tr>
                    <?php else: ?>
                        <?php foreach ($databases as $db): ?>
                        <tr>
                            <td><i class="bi bi-database text-primary me-1"></i><strong><?= htmlspecialchars($db['db_name']) ?></strong></td>
                            <td><span class="badge bg-info"><?= strtoupper($db['db_type']) ?></span></td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <td><?= htmlspecialchars($db['username']) ?></td>
                            <?php endif; ?>
                            <td><?= $db['size'] > 0 ? round($db['size'] / 1048576, 2) . ' MB' : '-' ?></td>
                            <td><span class="badge-status status-<?= $db['status'] ?>"><?= $db['status'] ?></span></td>
                            <td class="text-muted small"><?= date('M j, Y', strtotime($db['created_at'])) ?></td>
                            <td>
                                <?php if (in_array($db['db_type'], ['mysql', 'mariadb'])): ?>
                                    <a href="/databases/phpmyadmin" class="btn btn-outline-info btn-sm" title="phpMyAdmin"><i class="bi bi-table"></i></a>
                                <?php endif; ?>
                                <form method="POST" action="/databases/delete/<?= $db['id'] ?>" class="d-inline"
                                      onsubmit="return confirm('Delete this database? This cannot be undone!')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Database Users -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Database Users</h5>
            <a href="/databases/users" class="btn btn-outline-primary btn-sm">Manage Users</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Username</th><th>Type</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($dbUsers, 0, 10) as $dbu): ?>
                    <tr>
                        <td><?= htmlspecialchars($dbu['db_username']) ?></td>
                        <td><span class="badge bg-secondary"><?= strtoupper($dbu['db_type']) ?></span></td>
                        <td>
                            <form method="POST" action="/databases/users/delete/<?= $dbu['id'] ?>" class="d-inline"
                                  onsubmit="return confirm('Delete this database user?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
