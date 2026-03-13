<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">My Profile</h1>
        <p class="text-muted mb-0">Manage your account settings</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Profile Info -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-person me-2"></i>Account Information</h5></div>
            <div class="card-body">
                <form method="POST" action="/settings/profile">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control" value="<?= date('F j, Y', strtotime($user['created_at'])) ?>" disabled>
                        </div>
                    </div>
                    <hr>
                    <h6>Change Password</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" minlength="8">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2FA -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</h5></div>
            <div class="card-body">
                <?php if ($user['two_factor_enabled']): ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>2FA Enabled</span>
                            <p class="text-muted mt-2 mb-0">Your account is protected with two-factor authentication.</p>
                        </div>
                        <form method="POST" action="/settings/2fa/disable" onsubmit="return confirm('Disable 2FA? This will reduce your account security.')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-shield-x me-1"></i>Disable 2FA</button>
                        </form>
                    </div>
                    <?php if ($user['two_factor_secret']): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <strong>Secret Key:</strong> <code><?= htmlspecialchars($user['two_factor_secret']) ?></code>
                            <br><small class="text-muted">Enter this in your authenticator app (Google Authenticator, Authy, etc.)</small>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-warning fs-6"><i class="bi bi-exclamation-triangle me-1"></i>2FA Disabled</span>
                            <p class="text-muted mt-2 mb-0">Enable two-factor authentication for extra security.</p>
                        </div>
                        <form method="POST" action="/settings/2fa/enable">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="btn btn-success"><i class="bi bi-shield-check me-1"></i>Enable 2FA</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Tokens -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>API Tokens</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addToken"><i class="bi bi-plus-lg me-1"></i>New Token</button>
            </div>
            <div class="collapse" id="addToken">
                <div class="card-body border-bottom bg-light">
                    <form method="POST" action="/settings/api-tokens" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="col-8">
                            <label class="form-label">Token Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="deployment, monitoring, cli...">
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary w-100">Generate</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($apiTokens)): ?>
                    <div class="text-center py-4 text-muted">No API tokens.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Name</th><th>Created</th><th>Last Used</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($apiTokens as $token): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($token['name']) ?></strong></td>
                                        <td><small><?= date('M j, Y', strtotime($token['created_at'])) ?></small></td>
                                        <td><small><?= $token['last_used_at'] ? date('M j, Y g:i A', strtotime($token['last_used_at'])) : 'Never' ?></small></td>
                                        <td>
                                            <form method="POST" action="/settings/api-tokens/<?= $token['id'] ?>/delete" onsubmit="return confirm('Delete this token?')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar: Login History -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Login History</h5></div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <?php if (empty($loginHistory)): ?>
                    <div class="text-center py-4 text-muted">No login history.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($loginHistory as $log): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= ucfirst($log['status']) ?></span>
                                    <small class="text-muted"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></small>
                                </div>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($log['ip_address']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
