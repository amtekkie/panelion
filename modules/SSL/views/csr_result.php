<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">CSR Generated</h1>
        <p class="text-muted mb-0">Certificate Signing Request for <?= htmlspecialchars($domain) ?></p>
    </div>
    <a href="/ssl" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to SSL</a>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Important:</strong> Save your private key securely! You will need it to install the certificate. It cannot be recovered later.
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">CSR (Certificate Signing Request)</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="Panelion.copyToClipboard(document.getElementById('csrText').value)">
                    <i class="bi bi-clipboard me-1"></i> Copy
                </button>
            </div>
            <div class="card-body">
                <textarea class="form-control font-monospace" id="csrText" rows="12" readonly><?= htmlspecialchars($csr) ?></textarea>
                <div class="form-text">Submit this CSR to your Certificate Authority (CA) to get your SSL certificate.</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Private Key</h5>
                <button class="btn btn-sm btn-outline-light" onclick="Panelion.copyToClipboard(document.getElementById('keyText').value)">
                    <i class="bi bi-clipboard me-1"></i> Copy
                </button>
            </div>
            <div class="card-body">
                <textarea class="form-control font-monospace" id="keyText" rows="12" readonly><?= htmlspecialchars($private_key) ?></textarea>
                <div class="form-text text-danger">Keep this private key safe and secret. You'll need it when installing your certificate.</div>
            </div>
        </div>
    </div>
</div>
