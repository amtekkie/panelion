<?php
function fmtBytes($b) { if ($b === 0) return '0 B'; $u = ['B','KB','MB','GB','TB']; $i = floor(log(max(1,$b), 1024)); return round($b/pow(1024,$i),1).' '.$u[$i]; }
$cpuColor = $stats['cpu'] > 80 ? 'danger' : ($stats['cpu'] > 50 ? 'warning' : 'success');
$memColor = $stats['memory']['percent'] > 80 ? 'danger' : ($stats['memory']['percent'] > 50 ? 'warning' : 'success');
$diskColor = $stats['disk']['percent'] > 80 ? 'danger' : ($stats['disk']['percent'] > 50 ? 'warning' : 'success');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Server Monitoring</h1>
        <p class="text-muted mb-0"><?= htmlspecialchars($stats['hostname']) ?> — <?= htmlspecialchars($stats['os']) ?></p>
    </div>
    <div>
        <a href="/monitoring/logs" class="btn btn-outline-secondary me-2"><i class="bi bi-journal-text me-1"></i>Logs</a>
        <a href="/monitoring/processes" class="btn btn-outline-secondary"><i class="bi bi-cpu me-1"></i>Processes</a>
    </div>
</div>

<!-- System Overview -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">CPU Usage</h6>
                <div class="display-5 fw-bold text-<?= $cpuColor ?>"><?= $stats['cpu'] ?>%</div>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar bg-<?= $cpuColor ?>" style="width: <?= $stats['cpu'] ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Memory</h6>
                <div class="display-5 fw-bold text-<?= $memColor ?>"><?= $stats['memory']['percent'] ?>%</div>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar bg-<?= $memColor ?>" style="width: <?= $stats['memory']['percent'] ?>%"></div>
                </div>
                <small class="text-muted"><?= fmtBytes($stats['memory']['used']) ?> / <?= fmtBytes($stats['memory']['total']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Disk</h6>
                <div class="display-5 fw-bold text-<?= $diskColor ?>"><?= $stats['disk']['percent'] ?>%</div>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar bg-<?= $diskColor ?>" style="width: <?= $stats['disk']['percent'] ?>%"></div>
                </div>
                <small class="text-muted"><?= fmtBytes($stats['disk']['used']) ?> / <?= fmtBytes($stats['disk']['total']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Uptime</h6>
                <div class="display-5 fw-bold text-info"><?= $stats['uptime'] ?></div>
                <small class="text-muted">Kernel: <?= htmlspecialchars($stats['kernel']) ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Load Average & Services -->
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-speedometer me-2"></i>Load Average</h5></div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 mb-0"><?= $stats['load']['1min'] ?></div>
                        <small class="text-muted">1 min</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 mb-0"><?= $stats['load']['5min'] ?></div>
                        <small class="text-muted">5 min</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 mb-0"><?= $stats['load']['15min'] ?></div>
                        <small class="text-muted">15 min</small>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Processes</span><strong><?= $stats['processes'] ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear me-2"></i>Service Status</h5></div>
            <div class="card-body p-0">
                <?php if (empty($services)): ?>
                    <div class="text-center py-4 text-muted">No services detected.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Service</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($services as $name => $status): ?>
                                    <tr>
                                        <td><i class="bi bi-server me-2"></i><strong><?= htmlspecialchars($name) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'inactive' ? 'secondary' : 'danger') ?>">
                                                <i class="bi bi-<?= $status === 'active' ? 'check-circle' : 'x-circle' ?> me-1"></i><?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Processes & Network -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-cpu me-2"></i>Top Processes (by CPU)</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>User</th><th>PID</th><th>CPU%</th><th>MEM%</th><th>Command</th></tr></thead>
                        <tbody>
                            <?php foreach ($topProcesses as $proc): ?>
                                <tr>
                                    <td><small><?= htmlspecialchars($proc['user']) ?></small></td>
                                    <td><code><?= $proc['pid'] ?></code></td>
                                    <td><strong><?= $proc['cpu'] ?></strong></td>
                                    <td><?= $proc['mem'] ?></td>
                                    <td><small class="text-truncate d-inline-block" style="max-width:300px"><?= htmlspecialchars($proc['command']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-ethernet me-2"></i>Network Interfaces</h5></div>
            <div class="card-body p-0">
                <?php if (empty($networkStats)): ?>
                    <div class="text-center py-4 text-muted">No network data available.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Interface</th><th>RX</th><th>TX</th></tr></thead>
                            <tbody>
                                <?php foreach ($networkStats as $iface => $data): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($iface) ?></strong></td>
                                        <td><i class="bi bi-arrow-down text-success me-1"></i><?= fmtBytes($data['rx_bytes']) ?></td>
                                        <td><i class="bi bi-arrow-up text-primary me-1"></i><?= fmtBytes($data['tx_bytes']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh stats every 15 seconds
setInterval(() => {
    Panelion.ajax('/monitoring/api').then(data => {
        // Could update gauges dynamically - for now, reload
        // location.reload();
    });
}, 15000);
</script>
