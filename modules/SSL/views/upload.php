<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Upload SSL Certificate</h1>
        <p class="text-muted mb-0">Install a custom SSL certificate</p>
    </div>
    <a href="/ssl" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-upload me-2"></i>Certificate Upload</h5></div>
            <div class="card-body">
                <form method="POST" action="/ssl/custom">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Domain <span class="text-danger">*</span></label>
                        <select class="form-select" name="domain_id" required>
                            <option value="">Select domain...</option>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['domain']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Certificate (PEM) <span class="text-danger">*</span></label>
                        <textarea class="form-control font-monospace" name="certificate" rows="8" required
                                  placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Private Key (PEM) <span class="text-danger">*</span></label>
                        <textarea class="form-control font-monospace" name="private_key" rows="8" required
                                  placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CA Bundle / Chain (Optional)</label>
                        <textarea class="form-control font-monospace" name="ca_bundle" rows="6"
                                  placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i> Install Certificate</button>
                        <a href="/ssl" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>SSL Help</h6></div>
            <div class="card-body">
                <p>To install a custom SSL certificate, you need:</p>
                <ol>
                    <li><strong>Certificate</strong> — The signed certificate from your CA</li>
                    <li><strong>Private Key</strong> — The private key generated with your CSR</li>
                    <li><strong>CA Bundle</strong> — Intermediate certificates (optional but recommended)</li>
                </ol>
                <p class="text-muted">All files must be in PEM format. You can generate a CSR from the <a href="/ssl/csr">CSR Generator</a>.</p>
            </div>
        </div>
    </div>
</div>
