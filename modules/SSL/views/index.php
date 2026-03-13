<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">SSL/TLS Certificates</h1>
        <p class="text-muted mb-0">Manage SSL certificates for your domains</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/ssl/csr" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-code me-1"></i> Generate CSR</a>
        <a href="/ssl/upload" class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i> Upload Certificate</a>
    </div>
</div>

<!-- Quick Install Let's Encrypt -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Install Free SSL (Let's Encrypt)</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="/ssl/letsencrypt" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="col-md-5">
                <label class="form-label">Domain</label>
                <select class="form-select" name="domain_id" required>
                    <option value="">Select domain...</option>
                    <?php foreach ($domains as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['domain']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="include_www" value="1" id="includeWww" checked>
                    <label class="form-check-label" for="includeWww">Include www subdomain</label>
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-shield-check me-1"></i> Install Let's Encrypt SSL
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Active Certs</div>
                        <div class="stat-value"><?= count(array_filter($certificates, fn($c) => $c['status'] === 'active')) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Expiring Soon</div>
                        <div class="stat-value"><?= count(array_filter($certificates, fn($c) => ($c['days_left'] ?? 999) <= 30 && ($c['days_left'] ?? 0) > 0)) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Expired</div>
                        <div class="stat-value"><?= count(array_filter($certificates, fn($c) => ($c['days_left'] ?? 0) <= 0)) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-shield-x"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Auto-Renew</div>
                        <div class="stat-value"><?= count(array_filter($certificates, fn($c) => $c['auto_renew'])) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Certificates Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-lock me-2"></i>Installed Certificates</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($certificates)): ?>
            <div class="text-center py-5">
                <i class="bi bi-shield display-4 text-muted"></i>
                <p class="text-muted mt-3">No SSL certificates installed yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Type</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Auto-Renew</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Owner</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-lock me-1 text-<?= $cert['expiry_class'] ?? 'secondary' ?>"></i>
                                    <strong><?= htmlspecialchars($cert['domain'] ?? 'Unknown') ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $cert['type'] === 'letsencrypt' ? 'success' : 'primary' ?>">
                                        <?= $cert['type'] === 'letsencrypt' ? "Let's Encrypt" : 'Custom' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cert['expiry_date']): ?>
                                        <span class="text-<?= $cert['expiry_class'] ?>">
                                            <?= date('M j, Y', strtotime($cert['expiry_date'])) ?>
                                        </span>
                                        <br><small class="text-muted">
                                            <?= $cert['days_left'] > 0 ? $cert['days_left'] . ' days left' : 'Expired' ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $cert['status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($cert['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cert['auto_renew']): ?>
                                        <i class="bi bi-check-circle text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?= htmlspecialchars($cert['username'] ?? '') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($cert['type'] === 'letsencrypt'): ?>
                                            <form method="POST" action="/ssl/<?= $cert['id'] ?>/renew" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-outline-success" title="Renew"><i class="bi bi-arrow-clockwise"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="/ssl/<?= $cert['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this certificate?')">
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
