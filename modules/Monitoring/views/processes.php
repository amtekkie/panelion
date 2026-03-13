<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Process Manager</h1>
        <p class="text-muted mb-0"><?= count($processes) ?> processes (sorted by memory)</p>
    </div>
    <a href="/monitoring" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Monitoring</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0"><i class="bi bi-cpu me-2"></i>Running Processes</h5>
        <a href="/monitoring/processes" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th>User</th><th>PID</th><th>CPU%</th><th>MEM%</th><th>VSZ</th><th>RSS</th><th>Stat</th><th>Start</th><th>Time</th><th>Command</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($processes as $proc): ?>
                        <tr>
                            <td><small><?= htmlspecialchars($proc['user']) ?></small></td>
                            <td><code><?= $proc['pid'] ?></code></td>
                            <td><strong><?= $proc['cpu'] ?></strong></td>
                            <td><?= $proc['mem'] ?></td>
                            <td><small><?= $proc['vsz'] ?></small></td>
                            <td><small><?= $proc['rss'] ?></small></td>
                            <td><code><?= $proc['stat'] ?></code></td>
                            <td><small><?= $proc['start'] ?></small></td>
                            <td><small><?= $proc['time'] ?></small></td>
                            <td><small class="text-truncate d-inline-block" style="max-width:250px" title="<?= htmlspecialchars($proc['command']) ?>"><?= htmlspecialchars($proc['command']) ?></small></td>
                            <td>
                                <?php if ((int)$proc['pid'] > 1): ?>
                                    <button class="btn btn-sm btn-outline-danger kill-proc" data-pid="<?= $proc['pid'] ?>" title="Kill"><i class="bi bi-x-lg"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.kill-proc').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Kill process ' + this.dataset.pid + '?')) return;
        Panelion.ajax('/monitoring/processes/kill', {
            method: 'POST',
            data: { pid: this.dataset.pid, csrf_token: '<?= $_SESSION['csrf_token'] ?>' }
        }).then(r => {
            if (r.success) location.reload();
            else Panelion.toast(r.message, 'danger');
        });
    });
});
</script>
