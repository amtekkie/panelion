<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Panel Settings</h1>
        <p class="text-muted mb-0">Configure Panelion panel settings</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- General Settings -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear me-2"></i>General Settings</h5></div>
            <div class="card-body">
                <form method="POST" action="/settings">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="group" value="general">
                    <div class="mb-3">
                        <label class="form-label">Panel Name</label>
                        <input type="text" class="form-control" name="settings[panel_name]" value="<?= htmlspecialchars($settings['general']['panel_name'] ?? 'Panelion') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Language</label>
                        <select class="form-select" name="settings[language]">
                            <option value="en" <?= ($settings['general']['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="es" <?= ($settings['general']['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                            <option value="fr" <?= ($settings['general']['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                            <option value="de" <?= ($settings['general']['language'] ?? '') === 'de' ? 'selected' : '' ?>>German</option>
                            <option value="pt" <?= ($settings['general']['language'] ?? '') === 'pt' ? 'selected' : '' ?>>Portuguese</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Timezone</label>
                        <input type="text" class="form-control" name="settings[timezone]" value="<?= htmlspecialchars($settings['general']['timezone'] ?? 'UTC') ?>" placeholder="UTC">
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="settings[maintenance_mode]" value="1" <?= ($settings['general']['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">Maintenance Mode</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save General Settings</button>
                </form>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Security Settings</h5></div>
            <div class="card-body">
                <form method="POST" action="/settings">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="group" value="security">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Max Login Attempts</label>
                            <input type="number" class="form-control" name="settings[max_login_attempts]" value="<?= htmlspecialchars($settings['security']['max_login_attempts'] ?? '5') ?>" min="3" max="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lockout Duration (minutes)</label>
                            <input type="number" class="form-control" name="settings[lockout_duration]" value="<?= htmlspecialchars($settings['security']['lockout_duration'] ?? '15') ?>" min="5" max="60">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" name="settings[session_timeout]" value="<?= htmlspecialchars($settings['security']['session_timeout'] ?? '30') ?>" min="5" max="480">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Min Password Length</label>
                            <input type="number" class="form-control" name="settings[min_password_length]" value="<?= htmlspecialchars($settings['security']['min_password_length'] ?? '8') ?>" min="6" max="32">
                        </div>
                        <div class="col-12 form-check form-switch ms-1">
                            <input class="form-check-input" type="checkbox" name="settings[force_2fa]" value="1" <?= ($settings['security']['force_2fa'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label">Force 2FA for all users</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Security Settings</button>
                </form>
            </div>
        </div>

        <!-- Email/Notification Settings -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Notification Settings</h5></div>
            <div class="card-body">
                <form method="POST" action="/settings">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="group" value="notifications">
                    <div class="mb-3">
                        <label class="form-label">Admin Email</label>
                        <input type="email" class="form-control" name="settings[admin_email]" value="<?= htmlspecialchars($settings['notifications']['admin_email'] ?? '') ?>" placeholder="admin@example.com">
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="settings[email_on_login]" value="1" <?= ($settings['notifications']['email_on_login'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">Email on admin login</label>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="settings[email_on_disk_warning]" value="1" <?= ($settings['notifications']['email_on_disk_warning'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">Disk usage warning (>90%)</label>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="settings[email_on_ssl_expiry]" value="1" <?= ($settings['notifications']['email_on_ssl_expiry'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">SSL certificate expiry warning</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Notification Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Server Info Sidebar -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Server Information</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted">Panelion Version</td><td><strong>1.0.0</strong></td></tr>
                        <tr><td class="text-muted">PHP Version</td><td><?= htmlspecialchars($phpVersion) ?></td></tr>
                        <tr><td class="text-muted">Web Server</td><td><?= htmlspecialchars($serverSoftware) ?></td></tr>
                        <tr><td class="text-muted">Database</td><td><?= htmlspecialchars($mysqlVersion) ?></td></tr>
                        <tr><td class="text-muted">OS</td><td><?= php_uname('s') . ' ' . php_uname('r') ?></td></tr>
                        <tr><td class="text-muted">Hostname</td><td><?= htmlspecialchars(gethostname()) ?></td></tr>
                        <tr><td class="text-muted">Server IP</td><td><?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? 'N/A') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5></div>
            <div class="card-body d-grid gap-2">
                <a href="/monitoring" class="btn btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i>Server Monitoring</a>
                <a href="/monitoring/logs" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>View Logs</a>
                <a href="/firewall" class="btn btn-outline-warning"><i class="bi bi-shield me-1"></i>Firewall</a>
                <a href="/backups" class="btn btn-outline-success"><i class="bi bi-cloud-arrow-up me-1"></i>Backups</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear me-2"></i>Service Manager</h5></div>
            <div class="card-body" id="serviceManager">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
// Load service statuses
fetch('/settings/services')
    .then(r => r.json())
    .then(services => {
        let html = '';
        for (const [key, svc] of Object.entries(services)) {
            const isActive = svc.status === 'active';
            html += `<div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                <div><i class="bi bi-${isActive ? 'check-circle text-success' : 'x-circle text-danger'} me-2"></i>${svc.label}</div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-success" onclick="svcAction('${key}', 'restart')"><i class="bi bi-arrow-clockwise"></i></button>
                    <button class="btn btn-outline-${isActive ? 'danger' : 'success'}" onclick="svcAction('${key}', '${isActive ? 'stop' : 'start'}')">${isActive ? 'Stop' : 'Start'}</button>
                </div>
            </div>`;
        }
        document.getElementById('serviceManager').innerHTML = html || '<p class="text-muted">No services found.</p>';
    });

function svcAction(service, action) {
    if (!confirm(`${action} ${service}?`)) return;
    Panelion.ajax('/settings/services/action', {
        method: 'POST',
        data: { service, action, csrf_token: '<?= $_SESSION['csrf_token'] ?>' }
    }).then(r => {
        Panelion.toast(r.message, r.success ? 'success' : 'danger');
        setTimeout(() => location.reload(), 1500);
    });
}
</script>
