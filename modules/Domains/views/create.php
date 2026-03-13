<div class="page-header">
    <h2>Add Domain</h2>
    <p>Add a new domain to your hosting account</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="/domains/store">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="mb-3">
                        <label class="form-label">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" name="domain" class="form-control" required
                               placeholder="example.com" pattern="[a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}">
                        <small class="text-muted">Enter the domain without www. (e.g., example.com)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PHP Version</label>
                        <select name="php_version" class="form-select">
                            <?php foreach ($phpVersions as $v): ?>
                                <option value="<?= $v ?>" <?= $v === '8.2' ? 'selected' : '' ?>>PHP <?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>DNS Configuration:</strong> Point your domain's A record to this server's IP address.
                        A default DNS zone will be created automatically.
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Add Domain</button>
                    <a href="/domains" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
