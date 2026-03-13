<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">DNS Management</h1>
        <p class="text-muted mb-0">Manage DNS zones and records</p>
    </div>
    <a href="/dns/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New DNS Zone
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Total Zones</div>
                        <div class="stat-value"><?= count($zones) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-globe"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Active Zones</div>
                        <div class="stat-value"><?= count(array_filter($zones, fn($z) => $z['status'] === 'active')) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value"><?= array_sum(array_column($zones, 'record_count')) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-list-ul"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-globe me-2"></i>DNS Zones</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($zones)): ?>
            <div class="text-center py-5">
                <i class="bi bi-globe display-4 text-muted"></i>
                <p class="text-muted mt-3">No DNS zones configured.</p>
                <a href="/dns/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create DNS Zone</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Records</th>
                            <th>Serial</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Owner</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td>
                                    <a href="/dns/<?= $zone['id'] ?>" class="text-decoration-none fw-bold">
                                        <i class="bi bi-globe me-1"></i> <?= htmlspecialchars($zone['domain']) ?>
                                    </a>
                                </td>
                                <td><span class="badge bg-secondary"><?= $zone['record_count'] ?></span></td>
                                <td><small class="text-muted"><?= htmlspecialchars($zone['serial']) ?></small></td>
                                <td>
                                    <?php if ($zone['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?= htmlspecialchars($zone['username'] ?? '') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/dns/<?= $zone['id'] ?>" class="btn btn-outline-primary" title="Manage Records">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" action="/dns/<?= $zone['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this DNS zone and all its records?')">
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
