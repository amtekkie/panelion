<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-shield-shaded fs-1 text-primary me-2"></i>
                    <h1 class="fw-bold mb-0">Panelion</h1>
                </div>
                <p class="text-muted">Two-Factor Authentication</p>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="mb-3 text-center">Enter Verification Code</h5>
                    <p class="text-muted text-center small">Enter the 6-digit code from your authenticator app.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="/two-factor">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                        <div class="mb-4">
                            <input type="text" class="form-control form-control-lg text-center"
                                   name="code" maxlength="6" pattern="[0-9]{6}"
                                   required autofocus autocomplete="one-time-code"
                                   placeholder="000000">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Verify
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/login" class="text-muted small">Back to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
