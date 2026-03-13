<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Subdomains: <?= htmlspecialchars($domain['domain']) ?></h2>
    </div>
    <a href="/domains" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Create Subdomain</h5>
                <form method="POST" action="/domains/subdomains/<?= $domain['id'] ?>/create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="input-group">
                        <input type="text" name="subdomain" class="form-control" placeholder="sub" required
                               pattern="[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?">
                        <span class="input-group-text">.<?= htmlspecialchars($domain['domain']) ?></span>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Existing Subdomains</h5>
                <?php if (empty($subdomains)): ?>
                    <p class="text-muted">No subdomains yet.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Subdomain</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($subdomains as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['domain']) ?></td>
                                <td><span class="badge-status status-<?= $sub['status'] ?>"><?= $sub['status'] ?></span></td>
                                <td>
                                    <form method="POST" action="/domains/delete/<?= $sub['id'] ?>" class="d-inline"
                                          onsubmit="return confirm('Delete this subdomain?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
