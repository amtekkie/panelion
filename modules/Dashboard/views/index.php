<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Dashboard</h2>
        <p>Welcome back, <?= htmlspecialchars($user['first_name'] ?: $user['username']) ?>!</p>
    </div>
    <div class="text-muted small">
        <i class="bi bi-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card bg-primary text-white p-3">
            <i class="bi bi-globe stat-icon"></i>
            <div class="stat-value"><?= $total_domains ?? 0 ?></div>
            <div class="stat-label">Domains</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card bg-success text-white p-3">
            <i class="bi bi-database stat-icon"></i>
            <div class="stat-value"><?= $total_databases ?? 0 ?></div>
            <div class="stat-label">Databases</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card bg-info text-white p-3">
            <i class="bi bi-envelope stat-icon"></i>
            <div class="stat-value"><?= $total_emails ?? 0 ?></div>
            <div class="stat-label">Email Accounts</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card bg-warning text-white p-3">
            <i class="bi bi-app-indicator stat-icon"></i>
            <div class="stat-value"><?= $total_apps ?? 0 ?></div>
            <div class="stat-label">Applications</div>
        </div>
    </div>
</div>

<?php if ($is_admin ?? false): ?>
<!-- Admin: Additional Stats -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card bg-purple text-white p-3">
            <i class="bi bi-people stat-icon"></i>
            <div class="stat-value"><?= $total_users ?? 0 ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card bg-teal text-white p-3">
            <i class="bi bi-person-check stat-icon"></i>
            <div class="stat-value"><?= $active_users ?? 0 ?></div>
            <div class="stat-label">Active Users</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- System Resources -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card p-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-cpu me-2 text-primary"></i>CPU Usage</h6>
            <?php
                $cpuLoad = $system['cpu']['load_1'] ?? 0;
                $cpuCores = $system['cpu']['cores'] ?? 1;
                $cpuPercent = min(100, round(($cpuLoad / $cpuCores) * 100, 1));
                $cpuClass = $cpuPercent < 60 ? 'low' : ($cpuPercent < 85 ? 'medium' : 'high');
            ?>
            <div class="d-flex justify-content-between mb-1">
                <small>Load: <?= $cpuLoad ?></small>
                <small class="fw-bold"><?= $cpuPercent ?>%</small>
            </div>
            <div class="usage-bar">
                <div class="usage-bar-fill <?= $cpuClass ?>" style="width: <?= $cpuPercent ?>%"></div>
            </div>
            <small class="text-muted mt-2 d-block"><?= $cpuCores ?> core(s) | Load: <?= ($system['cpu']['load_1'] ?? 0) ?> / <?= ($system['cpu']['load_5'] ?? 0) ?> / <?= ($system['cpu']['load_15'] ?? 0) ?></small>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-memory me-2 text-success"></i>Memory Usage</h6>
            <?php
                $memPercent = $system['memory']['percent'] ?? 0;
                $memClass = $memPercent < 60 ? 'low' : ($memPercent < 85 ? 'medium' : 'high');
                $memUsed = ($system['memory']['used'] ?? 0);
                $memTotal = ($system['memory']['total'] ?? 1);
            ?>
            <div class="d-flex justify-content-between mb-1">
                <small><?= formatBytes($memUsed) ?> / <?= formatBytes($memTotal) ?></small>
                <small class="fw-bold"><?= $memPercent ?>%</small>
            </div>
            <div class="usage-bar">
                <div class="usage-bar-fill <?= $memClass ?>" style="width: <?= $memPercent ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-hdd me-2 text-info"></i>Disk Usage</h6>
            <?php
                $diskPercent = $system['disk']['percent'] ?? 0;
                $diskClass = $diskPercent < 60 ? 'low' : ($diskPercent < 85 ? 'medium' : 'high');
            ?>
            <div class="d-flex justify-content-between mb-1">
                <small><?= formatBytes($system['disk']['used'] ?? 0) ?> / <?= formatBytes($system['disk']['total'] ?? 0) ?></small>
                <small class="fw-bold"><?= $diskPercent ?>%</small>
            </div>
            <div class="usage-bar">
                <div class="usage-bar-fill <?= $diskClass ?>" style="width: <?= $diskPercent ?>%"></div>
            </div>
            <small class="text-muted mt-2 d-block">Free: <?= formatBytes($system['disk']['free'] ?? 0) ?></small>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Quick Actions</h5>
        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/domains/create" class="quick-action">
                    <i class="bi bi-globe"></i>
                    <span>Add Domain</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/databases/create" class="quick-action">
                    <i class="bi bi-database-add"></i>
                    <span>Create Database</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/email/accounts" class="quick-action">
                    <i class="bi bi-envelope-plus"></i>
                    <span>Add Email</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/files" class="quick-action">
                    <i class="bi bi-folder-plus"></i>
                    <span>File Manager</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/applications/create" class="quick-action">
                    <i class="bi bi-code-square"></i>
                    <span>New App</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/backup/create" class="quick-action">
                    <i class="bi bi-cloud-upload"></i>
                    <span>Backup</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/ssl" class="quick-action">
                    <i class="bi bi-shield-lock"></i>
                    <span>SSL Certificate</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/cron" class="quick-action">
                    <i class="bi bi-clock"></i>
                    <span>Cron Jobs</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/ftp" class="quick-action">
                    <i class="bi bi-hdd-network"></i>
                    <span>FTP Account</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/dns" class="quick-action">
                    <i class="bi bi-diagram-3"></i>
                    <span>DNS Editor</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/monitoring" class="quick-action">
                    <i class="bi bi-graph-up"></i>
                    <span>Monitoring</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="/applications/installer" class="quick-action">
                    <i class="bi bi-download"></i>
                    <span>App Installer</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php if ($is_admin ?? false): ?>
    <!-- Service Status -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Service Status</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <?php foreach ($services as $key => $svc): ?>
                            <tr>
                                <td>
                                    <span class="service-status <?= $svc['running'] ? 'online' : 'offline' ?>"></span>
                                    <?= htmlspecialchars($svc['label']) ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($svc['running']): ?>
                                        <span class="badge bg-success">Running</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Stopped</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Logins -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Recent Logins</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logins ?? [] as $login): ?>
                            <tr>
                                <td><?= htmlspecialchars($login['username'] ?? 'Unknown') ?></td>
                                <td><code><?= htmlspecialchars($login['ip_address']) ?></code></td>
                                <td>
                                    <span class="badge <?= $login['status'] === 'success' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= htmlspecialchars($login['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($login['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- User Disk & Bandwidth -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Resource Usage</h5>
                <?php
                    $diskPercent = $disk_quota > 0 ? round(($disk_used / $disk_quota) * 100, 1) : 0;
                    $diskClass = $diskPercent < 60 ? 'low' : ($diskPercent < 85 ? 'medium' : 'high');
                    $bwPercent = $bandwidth_quota > 0 ? round(($bandwidth_used / $bandwidth_quota) * 100, 1) : 0;
                    $bwClass = $bwPercent < 60 ? 'low' : ($bwPercent < 85 ? 'medium' : 'high');
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Disk Space</small>
                        <small><?= formatBytes($disk_used) ?> / <?= $disk_quota == -1 ? 'Unlimited' : formatBytes($disk_quota) ?></small>
                    </div>
                    <div class="usage-bar">
                        <div class="usage-bar-fill <?= $diskClass ?>" style="width: <?= min(100, $diskPercent) ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Bandwidth</small>
                        <small><?= formatBytes($bandwidth_used) ?> / <?= $bandwidth_quota == -1 ? 'Unlimited' : formatBytes($bandwidth_quota) ?></small>
                    </div>
                    <div class="usage-bar">
                        <div class="usage-bar-fill <?= $bwClass ?>" style="width: <?= min(100, $bwPercent) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Domains -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Your Domains</h5>
                <?php if (empty($domains)): ?>
                    <p class="text-muted">No domains configured yet.</p>
                    <a href="/domains/create" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>Add Domain</a>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($domains as $domain): ?>
                                <tr>
                                    <td>
                                        <?php if ($domain['ssl_enabled']): ?>
                                            <i class="bi bi-lock-fill text-success me-1"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($domain['domain']) ?>
                                    </td>
                                    <td><span class="badge-status status-<?= $domain['status'] ?>"><?= $domain['status'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Server Info -->
<div class="row g-3 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Server Information</h5>
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Hostname</small>
                        <span><?= htmlspecialchars(gethostname() ?: 'N/A') ?></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Uptime</small>
                        <span><?= $system['uptime']['human'] ?? 'N/A' ?></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">PHP Version</small>
                        <span><?= phpversion() ?></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Panelion Version</small>
                        <span>v<?= PANELION_VERSION ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    if ($bytes == -1) return 'Unlimited';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}
?>

<script>
// Auto-refresh stats every 30 seconds
setInterval(function() {
    Panelion.ajax('/dashboard/stats')
        .then(data => {
            // Update stats dynamically if needed
        })
        .catch(() => {});
}, 30000);
</script>
