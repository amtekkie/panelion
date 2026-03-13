<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Cron Jobs</h1>
        <p class="text-muted mb-0">Schedule automated tasks</p>
    </div>
</div>

<!-- Add Cron Job -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create Cron Job</h5></div>
    <div class="card-body">
        <form method="POST" action="/cron">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Schedule</label>
                    <select class="form-select" id="schedulePreset">
                        <option value="">Custom schedule</option>
                        <option value="every_minute">Every Minute</option>
                        <option value="every_5min">Every 5 Minutes</option>
                        <option value="every_15min">Every 15 Minutes</option>
                        <option value="every_30min">Every 30 Minutes</option>
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily (midnight)</option>
                        <option value="weekly">Weekly (Sunday)</option>
                        <option value="monthly">Monthly (1st)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cron Expression</label>
                    <input type="text" class="form-control font-monospace" name="schedule" id="scheduleInput" required placeholder="* * * * *">
                    <small class="text-muted">min hour day month weekday</small>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Command</label>
                    <input type="text" class="form-control font-monospace" name="command" required placeholder="/usr/bin/php /home/user/script.php">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Description (optional)</label>
                    <input type="text" class="form-control" name="description" placeholder="Database cleanup, cache purge, etc.">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-clock me-1"></i> Create Job</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Job List -->
<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Scheduled Jobs (<?= count($jobs) ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($jobs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-clock display-4 text-muted"></i>
                <p class="text-muted mt-3">No cron jobs scheduled yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Schedule</th><th>Command</th><th>Description</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?><th>Owner</th><?php endif; ?>
                            <th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr class="<?= $job['status'] !== 'active' ? 'text-muted' : '' ?>">
                                <td><code class="fs-6"><?= htmlspecialchars($job['schedule']) ?></code></td>
                                <td><code class="small"><?= htmlspecialchars($job['command']) ?></code></td>
                                <td><small><?= htmlspecialchars($job['description'] ?? '') ?></small></td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><small><?= htmlspecialchars($job['username'] ?? '') ?></small></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-<?= $job['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($job['status']) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="/cron/<?= $job['id'] ?>/toggle" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $job['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $job['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                                                <i class="bi bi-<?= $job['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/cron/<?= $job['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this cron job?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<!-- Cron Reference -->
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Cron Schedule Reference</h5></div>
    <div class="card-body">
        <pre class="mb-0 small bg-light p-3 rounded">┌───────────── minute (0 - 59)
│ ┌───────────── hour (0 - 23)
│ │ ┌───────────── day of month (1 - 31)
│ │ │ ┌───────────── month (1 - 12)
│ │ │ │ ┌───────────── day of week (0 - 6, Sunday = 0)
│ │ │ │ │
* * * * *

Examples:
  */5 * * * *     Every 5 minutes
  0 */2 * * *     Every 2 hours
  0 0 * * *       Daily at midnight
  0 3 * * 1       Every Monday at 3 AM
  0 0 1 * *       1st of every month</pre>
    </div>
</div>

<script>
const presets = {
    'every_minute': '* * * * *',
    'every_5min': '*/5 * * * *',
    'every_15min': '*/15 * * * *',
    'every_30min': '*/30 * * * *',
    'hourly': '0 * * * *',
    'daily': '0 0 * * *',
    'weekly': '0 0 * * 0',
    'monthly': '0 0 1 * *'
};
document.getElementById('schedulePreset').addEventListener('change', function() {
    if (this.value && presets[this.value]) {
        document.getElementById('scheduleInput').value = presets[this.value];
    }
});
</script>
