<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Firewall</h1>
        <p class="text-muted mb-0">Manage firewall rules and IP blocking</p>
    </div>
    <span class="badge bg-<?= str_contains($firewallStatus, 'active') || str_contains($firewallStatus, 'running') ? 'success' : 'warning' ?> fs-6">
        <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars(ucfirst($backend)) ?> — <?= str_contains($firewallStatus, 'active') || str_contains($firewallStatus, 'running') ? 'Active' : 'Check Status' ?>
    </span>
</div>

<!-- Add Rule -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add Firewall Rule</h5></div>
    <div class="card-body">
        <form method="POST" action="/firewall/rules">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Action</label>
                    <select class="form-select" name="action">
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Protocol</label>
                    <select class="form-select" name="protocol">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                        <option value="both">TCP+UDP</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Port</label>
                    <input type="text" class="form-control" name="port" required placeholder="80 or 3000:3010">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Source IP</label>
                    <input type="text" class="form-control" name="source" value="any" placeholder="any or 10.0.0.1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Direction</label>
                    <select class="form-select" name="direction">
                        <option value="in">Inbound</option>
                        <option value="out">Outbound</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <input type="number" class="form-control" name="priority" value="100" min="1" max="999">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="description" placeholder="Web server, API access, etc.">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-shield-plus me-1"></i> Add Rule</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Active Rules -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Firewall Rules (<?= count($rules) ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($rules)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-shield display-4"></i>
                <p class="mt-3">No custom firewall rules. Add one above.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th>Action</th><th>Protocol</th><th>Port</th><th>Source</th><th>Dir</th><th>Description</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $rule): ?>
                            <tr class="<?= $rule['status'] !== 'active' ? 'text-muted' : '' ?>">
                                <td><?= $rule['priority'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>">
                                        <i class="bi bi-<?= $rule['action'] === 'allow' ? 'check-lg' : 'x-lg' ?> me-1"></i>
                                        <?= strtoupper($rule['action']) ?>
                                    </span>
                                </td>
                                <td><code><?= strtoupper($rule['protocol']) ?></code></td>
                                <td><strong><?= htmlspecialchars($rule['port']) ?></strong></td>
                                <td><code><?= htmlspecialchars($rule['source']) ?></code></td>
                                <td>
                                    <span class="badge bg-secondary"><?= $rule['direction'] === 'in' ? 'IN' : 'OUT' ?></span>
                                </td>
                                <td><small><?= htmlspecialchars($rule['description'] ?? '') ?></small></td>
                                <td>
                                    <span class="badge bg-<?= $rule['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($rule['status']) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary toggle-rule" data-id="<?= $rule['id'] ?>" title="Toggle">
                                            <i class="bi bi-<?= $rule['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                        </button>
                                        <form method="POST" action="/firewall/rules/<?= $rule['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this rule?')">
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

<!-- Block IP -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-ban me-2"></i>Block IP Address</h5></div>
            <div class="card-body">
                <form method="POST" action="/firewall/block">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label">IP Address / CIDR</label>
                        <input type="text" class="form-control" name="ip" required placeholder="192.168.1.100 or 10.0.0.0/24">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" class="form-control" name="reason" placeholder="Brute force attempt, spam...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry</label>
                        <select class="form-select" name="expiry">
                            <option value="">Permanent</option>
                            <option value="1">1 Hour</option>
                            <option value="6">6 Hours</option>
                            <option value="24">24 Hours</option>
                            <option value="168">1 Week</option>
                            <option value="720">30 Days</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger w-100"><i class="bi bi-ban me-1"></i> Block IP</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-x me-2"></i>Blocked IPs (<?= count($blockedIps) ?>)</h5></div>
            <div class="card-body p-0">
                <?php if (empty($blockedIps)): ?>
                    <div class="text-center py-4 text-muted">No blocked IPs.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>IP</th><th>Reason</th><th>Blocked</th><th>Expires</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($blockedIps as $blocked): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($blocked['ip_address']) ?></code></td>
                                        <td><small><?= htmlspecialchars($blocked['reason'] ?? '-') ?></small></td>
                                        <td><small><?= date('M j, g:i A', strtotime($blocked['created_at'])) ?></small></td>
                                        <td>
                                            <?php if ($blocked['expires_at']): ?>
                                                <small><?= date('M j, g:i A', strtotime($blocked['expires_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Permanent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="/firewall/unblock/<?= $blocked['id'] ?>" onsubmit="return confirm('Unblock this IP?')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-unlock"></i></button>
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
    </div>
</div>

<!-- Common Ports Reference -->
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Common Ports Reference</h5></div>
    <div class="card-body">
        <div class="row g-2">
            <?php
            $commonPorts = [
                ['22', 'SSH'], ['80', 'HTTP'], ['443', 'HTTPS'], ['21', 'FTP'],
                ['25', 'SMTP'], ['587', 'SMTP TLS'], ['993', 'IMAPS'], ['995', 'POP3S'],
                ['3306', 'MySQL'], ['5432', 'PostgreSQL'], ['27017', 'MongoDB'], ['6379', 'Redis'],
                ['53', 'DNS'], ['2083', 'Panelion'], ['8080', 'HTTP Alt'], ['3000', 'Node.js']
            ];
            foreach ($commonPorts as $p): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="border rounded p-2 text-center small">
                        <strong><?= $p[0] ?></strong><br><span class="text-muted"><?= $p[1] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-rule').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        Panelion.ajax(`/firewall/rules/${id}/toggle`, {
            method: 'POST',
            data: { csrf_token: '<?= $_SESSION['csrf_token'] ?>' }
        }).then(r => {
            if (r.success) location.reload();
        });
    });
});
</script>
