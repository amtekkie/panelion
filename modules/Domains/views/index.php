<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Domains</h2>
        <p>Manage your domains and subdomains</p>
    </div>
    <a href="/domains/create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Domain</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Type</th>
                        <th>Document Root</th>
                        <th>SSL</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($domains)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No domains configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($domains as $d): ?>
                        <tr>
                            <td>
                                <i class="bi bi-globe text-primary me-1"></i>
                                <strong><?= htmlspecialchars($d['domain']) ?></strong>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($d['username']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= $d['type'] ?></span></td>
                            <td><code class="small"><?= htmlspecialchars($d['document_root']) ?></code></td>
                            <td>
                                <?php if ($d['ssl_enabled']): ?>
                                    <i class="bi bi-lock-fill text-success" title="SSL Active"></i>
                                <?php else: ?>
                                    <i class="bi bi-unlock text-muted" title="No SSL"></i>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-status status-<?= $d['status'] ?>"><?= $d['status'] ?></span></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/domains/edit/<?= $d['id'] ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="/domains/subdomains/<?= $d['id'] ?>" class="btn btn-outline-info" title="Subdomains"><i class="bi bi-diagram-2"></i></a>
                                    <a href="/dns/zone/<?= htmlspecialchars($d['domain']) ?>" class="btn btn-outline-secondary" title="DNS"><i class="bi bi-diagram-3"></i></a>
                                    <form method="POST" action="/domains/delete/<?= $d['id'] ?>" class="d-inline"
                                          onsubmit="return confirm('Delete this domain?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
