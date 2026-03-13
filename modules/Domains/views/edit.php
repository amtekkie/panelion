<div class="page-header">
    <h2>Edit Domain: <?= htmlspecialchars($domain['domain']) ?></h2>
</div>
<div class="row"><div class="col-lg-8"><div class="card"><div class="card-body">
    <form method="POST" action="/domains/update/<?= $domain['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="mb-3">
            <label class="form-label">Domain</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($domain['domain']) ?>" disabled>
        </div>
        <div class="mb-3">
            <label class="form-label">Document Root</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($domain['document_root']) ?>" disabled>
        </div>
        <div class="mb-3">
            <label class="form-label">PHP Version</label>
            <select name="php_version" class="form-select">
                <?php foreach ($phpVersions as $v): ?>
                    <option value="<?= $v ?>" <?= $v === $domain['php_version'] ? 'selected' : '' ?>>PHP <?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Update</button>
        <a href="/domains" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div></div></div></div>
