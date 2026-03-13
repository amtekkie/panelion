<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-<?= $runtimes[$app['type']]['icon'] ?? 'app' ?> me-2"></i>
            <?= htmlspecialchars($app['name']) ?>
        </h1>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($runtimes[$app['type']]['name'] ?? $app['type']) ?>
            <?= $app['version'] ? ' ' . htmlspecialchars($app['version']) : '' ?>
            &bull; <?= htmlspecialchars($app['domain'] ?? 'No domain') ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!in_array($app['type'], ['php', 'static'])): ?>
            <?php if ($app['live_status'] !== 'running'): ?>
                <form method="POST" action="/applications/<?= $app['id'] ?>/start">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="btn btn-success"><i class="bi bi-play me-1"></i> Start</button>
                </form>
            <?php else: ?>
                <form method="POST" action="/applications/<?= $app['id'] ?>/stop">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="btn btn-warning"><i class="bi bi-stop me-1"></i> Stop</button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/applications/<?= $app['id'] ?>/restart">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn btn-info"><i class="bi bi-arrow-clockwise me-1"></i> Restart</button>
            </form>
        <?php endif; ?>
        <a href="/applications" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>
</div>

<!-- Status Overview -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Status</h6>
                <?php if ($app['live_status'] === 'running' || $app['live_status'] === 'active'): ?>
                    <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-circle-fill me-1" style="font-size:0.6em"></i> Running</span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-circle-fill me-1" style="font-size:0.6em"></i> Stopped</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Runtime</h6>
                <span class="fs-5"><i class="bi bi-<?= $runtimes[$app['type']]['icon'] ?? 'app' ?> me-1"></i> <?= htmlspecialchars($runtimes[$app['type']]['name'] ?? $app['type']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Port</h6>
                <span class="fs-5"><?= $app['port'] ?: '—' ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Domain</h6>
                <span class="fs-6"><?= htmlspecialchars($app['domain'] ?? '—') ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- App Details -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Application Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:40%">Name</td>
                        <td><strong><?= htmlspecialchars($app['name']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Type</td>
                        <td><?= htmlspecialchars($runtimes[$app['type']]['name'] ?? $app['type']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Version</td>
                        <td><?= htmlspecialchars($app['version'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Directory</td>
                        <td><code><?= htmlspecialchars($app['directory'] ?? '') ?></code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">URL Path</td>
                        <td><code><?= htmlspecialchars($app['path'] ?? '/') ?></code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Port</td>
                        <td><?= $app['port'] ?: 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Startup Command</td>
                        <td><code><?= htmlspecialchars($app['startup_command'] ?: 'default') ?></code></td>
                    </tr>
                    <?php if ($_SESSION['role'] === 'admin' && !empty($app['username'])): ?>
                        <tr>
                            <td class="text-muted">Owner</td>
                            <td><?= htmlspecialchars($app['username']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted">Created</td>
                        <td><?= date('M j, Y g:i A', strtotime($app['created_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Environment Variables -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Environment Variables</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($app['env_vars'])): ?>
                    <pre class="bg-light p-3 rounded mb-0"><code><?= htmlspecialchars($app['env_vars']) ?></code></pre>
                <?php else: ?>
                    <p class="text-muted mb-0">No environment variables configured.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logs -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>Application Logs</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div class="terminal-output" id="logOutput" style="height: 500px; overflow-y: auto; padding: 1rem; background: #1a1a2e; color: #00ff88; font-family: 'Courier New', monospace; font-size: 0.85rem; white-space: pre-wrap;"><?= htmlspecialchars($app['logs'] ?? 'No logs available.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Danger Zone -->
<div class="card border-danger mt-4">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>Delete Application</strong>
                <p class="text-muted mb-0">Permanently remove this application, its configuration, and process. This cannot be undone.</p>
            </div>
            <form method="POST" action="/applications/<?= $app['id'] ?>/delete" onsubmit="return confirm('Are you absolutely sure? This will permanently delete the application and its configuration.')">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete Application
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function refreshLogs() {
    fetch('/applications/<?= $app['id'] ?>/logs')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('logOutput').textContent = data.logs;
                const el = document.getElementById('logOutput');
                el.scrollTop = el.scrollHeight;
            }
        });
}

// Auto-scroll logs to bottom
const logEl = document.getElementById('logOutput');
logEl.scrollTop = logEl.scrollHeight;

// Auto-refresh logs every 10 seconds
setInterval(refreshLogs, 10000);
</script>
