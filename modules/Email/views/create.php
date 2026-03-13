<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Create Email Account</h1>
        <p class="text-muted mb-0">Add a new email account to your domain</p>
    </div>
    <a href="/email" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-envelope-plus me-2"></i>New Email Account</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/email">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="username" name="username" required
                                   pattern="[a-z0-9._-]+" placeholder="user">
                            <span class="input-group-text">@</span>
                            <select class="form-select" name="domain_id" required>
                                <option value="">Select domain...</option>
                                <?php foreach ($domains as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['domain']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text">Lowercase letters, numbers, dots, hyphens, underscores only.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <button type="button" class="btn btn-outline-secondary btn-generate-password">
                                <i class="bi bi-shuffle me-1"></i> Generate
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>

                    <div class="mb-3">
                        <label for="quota" class="form-label">Mailbox Quota (MB)</label>
                        <input type="number" class="form-control" id="quota" name="quota" value="1024" min="50" max="102400">
                        <div class="form-text">Storage limit for this mailbox in megabytes.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Create Account
                        </button>
                        <a href="/email" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Email Setup</h6>
            </div>
            <div class="card-body">
                <p>After creating an email account, the user can access email via:</p>
                <ul>
                    <li><strong>Webmail</strong> — Access from browser</li>
                    <li><strong>IMAP</strong> — Port 993 (SSL)</li>
                    <li><strong>POP3</strong> — Port 995 (SSL)</li>
                    <li><strong>SMTP</strong> — Port 465 (SSL) or 587 (STARTTLS)</li>
                </ul>
                <p class="text-muted mb-0">Server: <code>mail.yourdomain.com</code><br>Username: full email address</p>
            </div>
        </div>
    </div>
</div>
