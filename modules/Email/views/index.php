<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Email Management</h1>
        <p class="text-muted mb-0">Manage email accounts, forwarders, and autoresponders</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($roundcubeUrl)): ?>
        <a href="<?= htmlspecialchars($roundcubeUrl) ?>" target="_blank" class="btn btn-outline-success">
            <i class="bi bi-globe me-1"></i> Webmail
        </a>
        <?php endif; ?>
        <a href="/email/create" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Email Account
        </a>
    </div>
</div>

<!-- Service Status -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Email Accounts</div>
                        <div class="stat-value"><?= count($accounts) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-envelope"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Forwarders</div>
                        <div class="stat-value"><?= count($forwarders) ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-forward"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <small class="text-muted">Postfix (SMTP)</small><br>
                <span class="badge bg-<?= $postfixStatus === 'active' ? 'success' : 'danger' ?> mt-1">
                    <i class="bi bi-circle-fill me-1" style="font-size:0.5em"></i> <?= $postfixStatus === 'active' ? 'Running' : 'Stopped' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <small class="text-muted">Dovecot (IMAP/POP3)</small><br>
                <span class="badge bg-<?= $dovecotStatus === 'active' ? 'success' : 'danger' ?> mt-1">
                    <i class="bi bi-circle-fill me-1" style="font-size:0.5em"></i> <?= $dovecotStatus === 'active' ? 'Running' : 'Stopped' ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Email Accounts -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Email Accounts</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center py-5">
                <i class="bi bi-envelope display-4 text-muted"></i>
                <p class="text-muted mt-3">No email accounts yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Quota</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Owner</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-envelope me-1"></i>
                                    <strong><?= htmlspecialchars($acc['email']) ?></strong>
                                </td>
                                <td><?= $acc['quota'] ?> MB</td>
                                <td>
                                    <span class="badge bg-<?= $acc['status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($acc['status']) ?>
                                    </span>
                                </td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td><?= htmlspecialchars($acc['username'] ?? '') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePwdModal" data-id="<?= $acc['id'] ?>" data-email="<?= htmlspecialchars($acc['email']) ?>" title="Change Password">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <a href="https://<?= htmlspecialchars($acc['domain']) ?>/webmail" class="btn btn-outline-info" target="_blank" title="Webmail">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                        <form method="POST" action="/email/<?= $acc['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this email account?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
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

<!-- Forwarders -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-forward me-2"></i>Email Forwarders</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addForwarder">
            <i class="bi bi-plus-lg me-1"></i> Add
        </button>
    </div>

    <div class="collapse" id="addForwarder">
        <div class="card-body border-bottom bg-light">
            <form method="POST" action="/email/forwarders">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="text" class="form-control" name="source" placeholder="info" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Domain</label>
                        <select class="form-select" name="domain_id" required>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?= $d['id'] ?>">@<?= htmlspecialchars($d['domain']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Forward To</label>
                        <input type="email" class="form-control" name="destination" placeholder="user@example.com" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($forwarders)): ?>
            <div class="text-center py-4 text-muted">No forwarders configured.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Source</th><th>Destination</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($forwarders as $fwd): ?>
                            <tr>
                                <td><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($fwd['source']) ?></td>
                                <td><i class="bi bi-arrow-right me-1"></i> <?= htmlspecialchars($fwd['destination']) ?></td>
                                <td>
                                    <form method="POST" action="/email/forwarders/<?= $fwd['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this forwarder?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<!-- Autoresponders -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-reply me-2"></i>Autoresponders</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addAutoresponder">
            <i class="bi bi-plus-lg me-1"></i> Add
        </button>
    </div>

    <div class="collapse" id="addAutoresponder">
        <div class="card-body border-bottom bg-light">
            <form method="POST" action="/email/autoresponders">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" name="email" placeholder="info" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Domain</label>
                        <select class="form-select" name="domain_id" required>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?= $d['id'] ?>">@<?= htmlspecialchars($d['domain']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" placeholder="Out of Office" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="body" rows="3" required placeholder="Thank you for your email. I am currently out of office..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Create Autoresponder</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($autoresponders)): ?>
            <div class="text-center py-4 text-muted">No autoresponders configured.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Email</th><th>Subject</th><th>Period</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($autoresponders as $ar): ?>
                            <tr>
                                <td><?= htmlspecialchars($ar['email']) ?></td>
                                <td><?= htmlspecialchars($ar['subject']) ?></td>
                                <td>
                                    <?= $ar['start_date'] ? date('M j', strtotime($ar['start_date'])) : 'Always' ?>
                                    <?= $ar['end_date'] ? ' - ' . date('M j', strtotime($ar['end_date'])) : '' ?>
                                </td>
                                <td><span class="badge bg-<?= $ar['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($ar['status']) ?></span></td>
                                <td>
                                    <form method="POST" action="/email/autoresponders/<?= $ar['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this autoresponder?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<!-- Connection Settings -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Email Client Settings</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Incoming Mail (IMAP)</h6>
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted">Server</td><td><code>mail.yourdomain.com</code></td></tr>
                    <tr><td class="text-muted">Port</td><td>993 (SSL) / 143 (STARTTLS)</td></tr>
                    <tr><td class="text-muted">Username</td><td>Full email address</td></tr>
                    <tr><td class="text-muted">Encryption</td><td>SSL/TLS</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Outgoing Mail (SMTP)</h6>
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted">Server</td><td><code>mail.yourdomain.com</code></td></tr>
                    <tr><td class="text-muted">Port</td><td>465 (SSL) / 587 (STARTTLS)</td></tr>
                    <tr><td class="text-muted">Username</td><td>Full email address</td></tr>
                    <tr><td class="text-muted">Encryption</td><td>SSL/TLS</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePwdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="changePwdForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Change Email Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Changing password for: <strong id="pwdEmail"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <button type="button" class="btn btn-outline-secondary btn-generate-password">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('changePwdModal')?.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const email = btn.getAttribute('data-email');
    document.getElementById('pwdEmail').textContent = email;
    document.getElementById('changePwdForm').action = '/email/' + id + '/password';
});
</script>
