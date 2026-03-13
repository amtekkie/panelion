<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">License</h1>
        <p class="text-muted mb-0">Manage your Panelion license</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>License Information</h5>
            </div>
            <div class="card-body">
                <?php if ($licenseData): ?>
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted" style="width: 200px;">Status</th>
                                    <td>
                                        <?php if ($isValid): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Invalid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">License Key</th>
                                    <td class="font-monospace">
                                        <?php
                                            $key = $licenseData['license_key'] ?? '';
                                            echo htmlspecialchars(substr($key, 0, 4) . '-****-****-' . substr($key, -4));
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Domain</th>
                                    <td><?= htmlspecialchars($licenseData['domain'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Email</th>
                                    <td><?= htmlspecialchars($licenseData['email'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Expiry</th>
                                    <td>
                                        <?php
                                            $expiry = $licenseData['expiry'] ?? 'lifetime';
                                            if ($expiry === 'lifetime') {
                                                echo '<span class="text-success">Lifetime</span>';
                                            } else {
                                                echo htmlspecialchars($expiry);
                                                if (strtotime($expiry) < time()) {
                                                    echo ' <span class="badge bg-danger">Expired</span>';
                                                }
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Activated</th>
                                    <td><?= htmlspecialchars($licenseData['activated_at'] ?? '-') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr>
                    <form method="POST" action="/settings/license/deactivate" onsubmit="return confirm('Are you sure you want to deactivate this license? All panel features will be locked until a new license is activated.')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-x-circle me-1"></i>Deactivate License
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-key fs-1 text-muted"></i>
                        <p class="text-muted mt-2 mb-3">No license is currently active.</p>
                        <a href="/license" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Activate License
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>About</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Your license allows you to run Panelion on one server. The license is bound to this server's domain.</p>
                <dl class="mb-0">
                    <dt class="text-muted small">Server Domain</dt>
                    <dd class="small"><?= htmlspecialchars($domain) ?></dd>
                    <dt class="text-muted small">Panelion Version</dt>
                    <dd class="small mb-0">v<?= PANELION_VERSION ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
