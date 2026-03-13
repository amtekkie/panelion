<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Backups</h1>
        <p class="text-muted mb-0">Create, manage, and restore backups</p>
    </div>
</div>

<!-- Quick Backup -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Create Backup</h5></div>
    <div class="card-body">
        <form method="POST" action="/backups" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="col-md-3">
                <label class="form-label">Backup Type</label>
                <select class="form-select" name="type">
                    <option value="full">Full Backup (Files + DB + Email)</option>
                    <option value="files">Files Only</option>
                    <option value="databases">Databases Only</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Description (optional)</label>
                <input type="text" class="form-control" name="description" placeholder="Pre-update backup...">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Start backup? This may take a few minutes.')">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Create Backup Now
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Total Backups</div>
                        <div class="stat-value"><?= count($backups) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-archive"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Total Size</div>
                        <div class="stat-value"><?= formatBackupSize(array_sum(array_column($backups, 'size'))) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-hdd"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Schedules</div>
                        <div class="stat-value"><?= count($schedules) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup List -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-archive me-2"></i>Backup History</h5></div>
    <div class="card-body p-0">
        <?php if (empty($backups)): ?>
            <div class="text-center py-5">
                <i class="bi bi-archive display-4 text-muted"></i>
                <p class="text-muted mt-3">No backups yet. Create your first backup above.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Date</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?><th>Owner</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-file-zip me-1"></i>
                                    <strong><?= htmlspecialchars($backup['filename']) ?></strong>
                                    <?php if ($backup['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($backup['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= match($backup['type']) { 'full' => 'primary', 'files' => 'info', 'databases' => 'warning', default => 'secondary' } ?>">
                                        <?= ucfirst($backup['type']) ?>
                                    </span>
                                </td>
                                <td><?= formatBackupSize($backup['size']) ?></td>
                                <td>
                                    <span class="badge bg-<?= match($backup['status']) { 'completed' => 'success', 'in_progress' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>">
                                        <?= ucfirst(str_replace('_', ' ', $backup['status'])) ?>
                                    </span>
                                </td>
                                <td><small><?= date('M j, Y g:i A', strtotime($backup['created_at'])) ?></small></td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?= htmlspecialchars($backup['username'] ?? '') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($backup['status'] === 'completed'): ?>
                                            <a href="/backups/<?= $backup['id'] ?>/download" class="btn btn-outline-primary" title="Download"><i class="bi bi-download"></i></a>
                                            <form method="POST" action="/backups/<?= $backup['id'] ?>/restore" class="d-inline" onsubmit="return confirm('Restore from this backup? Current files will be overwritten.')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-outline-success" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="/backups/<?= $backup['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this backup?')">
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

<!-- Backup Schedules -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Backup Schedules</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addSchedule">
            <i class="bi bi-plus-lg me-1"></i> Add Schedule
        </button>
    </div>

    <div class="collapse" id="addSchedule">
        <div class="card-body border-bottom bg-light">
            <form method="POST" action="/backups/schedules">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Frequency</label>
                        <select class="form-select" name="frequency" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="full">Full</option>
                            <option value="files">Files</option>
                            <option value="databases">DB</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-control" name="time" value="02:00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Keep for (days)</label>
                        <input type="number" class="form-control" name="retention" value="7" min="1" max="365">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Create Schedule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($schedules)): ?>
            <div class="text-center py-4 text-muted">No backup schedules configured.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Frequency</th><th>Type</th><th>Time</th><th>Retention</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($schedules as $sched): ?>
                            <tr>
                                <td><strong><?= ucfirst($sched['frequency']) ?></strong></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($sched['type']) ?></span></td>
                                <td><?= htmlspecialchars($sched['time']) ?></td>
                                <td><?= $sched['retention'] ?> days</td>
                                <td><span class="badge bg-<?= $sched['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($sched['status']) ?></span></td>
                                <td>
                                    <form method="POST" action="/backups/schedules/<?= $sched['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this schedule?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
function formatBackupSize($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log(max(1, $bytes), 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
?>
