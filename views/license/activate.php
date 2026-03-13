<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-shield-shaded fs-1 text-primary me-2"></i>
                    <h1 class="fw-bold mb-0">Panelion</h1>
                </div>
                <p class="text-muted">License Activation Required</p>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h4 class="mb-3 text-center"><i class="bi bi-key me-2"></i>Activate License</h4>
                    <p class="text-muted text-center small mb-4">Enter your license key to unlock all features. You can purchase a license at <a href="<?= htmlspecialchars($productUrl ?? 'https://tektove.com/shop/saas/panelion/') ?>" target="_blank">tektove.com</a>.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/license/activate" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                        <div class="mb-3">
                            <label for="license_key" class="form-label">License Key</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="text" class="form-control font-monospace" id="license_key" name="license_key"
                                       placeholder="XXXX-XXXX-XXXX-XXXX" required autofocus
                                       pattern="[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}"
                                       maxlength="19" style="letter-spacing: 1px;">
                            </div>
                            <div class="form-text">Format: XXXX-XXXX-XXXX-XXXX</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small">Server Domain</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($domain ?? '') ?>" readonly>
                            <div class="form-text">This license will be bound to this server.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check2-circle me-2"></i>Activate License
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                <small class="text-muted">Panelion v<?= PANELION_VERSION ?></small>
                <?php if ($loggedIn ?? false): ?>
                    <br><small><a href="/logout" class="text-muted">Sign Out</a></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-format license key with dashes
document.getElementById('license_key')?.addEventListener('input', function(e) {
    let v = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().substring(0, 16);
    let parts = [];
    for (let i = 0; i < v.length; i += 4) {
        parts.push(v.substring(i, i + 4));
    }
    this.value = parts.join('-');
});
</script>
