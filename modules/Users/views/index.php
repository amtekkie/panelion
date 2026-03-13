<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>User Management</h2>
        <p>Manage hosting accounts and users</p>
    </div>
    <a href="/users/create" class="btn btn-primary"><i class="bi bi-person-plus me-2"></i>Create User</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Package</th>
                        <th>Domains</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-2" style="width:28px;height:28px;font-size:0.7rem;">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'reseller' ? 'warning' : 'primary') ?>"><?= $u['role'] ?></span></td>
                            <td><?= htmlspecialchars($u['package_name'] ?? 'Custom') ?></td>
                            <td><?= $u['max_domains'] == -1 ? '∞' : $u['max_domains'] ?></td>
                            <td><span class="badge-status status-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
                            <td class="text-muted small"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/users/edit/<?= $u['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <a href="/users/suspend/<?= $u['id'] ?>" class="btn btn-outline-warning btn-sm" title="Suspend"
                                           data-confirm="Suspend this user?"><i class="bi bi-pause-circle"></i></a>
                                    <?php else: ?>
                                        <a href="/users/unsuspend/<?= $u['id'] ?>" class="btn btn-outline-success btn-sm" title="Unsuspend">
                                            <i class="bi bi-play-circle"></i></a>
                                    <?php endif; ?>
                                    <?php if ($u['id'] !== $user['id']): ?>
                                        <form method="POST" action="/users/delete/<?= $u['id'] ?>" class="d-inline"
                                              onsubmit="return confirm('Permanently delete this user and all data?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
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
