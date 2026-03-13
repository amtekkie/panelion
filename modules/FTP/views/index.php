<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">FTP Accounts</h1>
        <p class="text-muted mb-0">Manage FTP access to your files</p>
    </div>
    <span class="badge bg-info fs-6"><i class="bi bi-server me-1"></i><?= htmlspecialchars(ucfirst($ftpService)) ?></span>
</div>

<!-- Create FTP Account -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create FTP Account</h5></div>
    <div class="card-body">
        <form method="POST" action="/ftp">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><?= htmlspecialchars($_SESSION['username'] ?? '') ?>_</span>
                        <input type="text" class="form-control" name="username" required pattern="[a-z0-9_]{3,32}" placeholder="ftpuser">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="password" id="ftpPassword" required minlength="8">
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('ftpPassword').value = Panelion.generatePassword(16)"><i class="bi bi-key"></i></button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Directory</label>
                    <input type="text" class="form-control" name="directory" placeholder="public_html">
                    <small class="text-muted">Relative to home</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quota (MB)</label>
                    <input type="number" class="form-control" name="quota" value="0" min="0">
                    <small class="text-muted">0 = unlimited</small>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i> Create</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Account List -->
<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-people me-2"></i>FTP Accounts (<?= count($accounts) ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center py-5">
                <i class="bi bi-folder2-open display-4 text-muted"></i>
                <p class="text-muted mt-3">No FTP accounts yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Directory</th>
                            <th>Quota</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?><th>Owner</th><?php endif; ?>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td><i class="bi bi-person me-1"></i><strong><?= htmlspecialchars($acc['username']) ?></strong></td>
                                <td><code class="small"><?= htmlspecialchars($acc['directory']) ?></code></td>
                                <td><?= $acc['quota'] > 0 ? $acc['quota'] . ' MB' : '<span class="text-muted">Unlimited</span>' ?></td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?= htmlspecialchars($acc['owner'] ?? '') ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-<?= $acc['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($acc['status']) ?>
                                    </span>
                                </td>
                                <td><small><?= date('M j, Y', strtotime($acc['created_at'])) ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pwdModal<?= $acc['id'] ?>" title="Change Password"><i class="bi bi-key"></i></button>
                                        <form method="POST" action="/ftp/<?= $acc['id'] ?>/toggle" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $acc['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $acc['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                                                <i class="bi bi-<?= $acc['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/ftp/<?= $acc['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete FTP account <?= htmlspecialchars($acc['username']) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>

                                    <!-- Password Modal -->
                                    <div class="modal fade" id="pwdModal<?= $acc['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm">
                                            <div class="modal-content">
                                                <form method="POST" action="/ftp/<?= $acc['id'] ?>/password">
                                                    <div class="modal-header"><h5 class="modal-title">Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <p class="small text-muted"><?= htmlspecialchars($acc['username']) ?></p>
                                                        <input type="text" class="form-control" name="password" required minlength="8" placeholder="New password">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-primary">Update</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
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

<!-- Connection Info -->
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>FTP Connection Settings</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-muted">Host</label>
                <div class="fw-bold"><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? gethostname()) ?></div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted">FTP Port</label>
                <div class="fw-bold">21</div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted">SFTP Port</label>
                <div class="fw-bold">22</div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted">FTPS Port</label>
                <div class="fw-bold">990</div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Encryption</label>
                <div class="fw-bold">FTPS / SFTP recommended</div>
            </div>
        </div>
    </div>
</div>
