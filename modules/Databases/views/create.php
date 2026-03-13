<div class="page-header">
    <h2>Create Database</h2>
    <p>Create a new database with optional user</p>
</div>

<div class="row"><div class="col-lg-8"><div class="card"><div class="card-body">
    <form method="POST" action="/databases/store">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="mb-3">
            <label class="form-label">Database Type</label>
            <select name="db_type" class="form-select" id="dbType">
                <?php if ($supportedTypes['mysql'] ?? false): ?><option value="mysql">MySQL</option><?php endif; ?>
                <?php if ($supportedTypes['mariadb'] ?? false): ?><option value="mariadb">MariaDB</option><?php endif; ?>
                <?php if ($supportedTypes['postgresql'] ?? false): ?><option value="postgresql">PostgreSQL</option><?php endif; ?>
                <?php if ($supportedTypes['mongodb'] ?? false): ?><option value="mongodb">MongoDB</option><?php endif; ?>
                <?php if ($supportedTypes['sqlite'] ?? false): ?><option value="sqlite">SQLite</option><?php endif; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Database Name <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><?= htmlspecialchars($user['username']) ?>_</span>
                <input type="text" name="db_name" class="form-control" required pattern="[a-zA-Z0-9_]{2,32}">
            </div>
        </div>

        <hr><h6 class="fw-bold">Database User (Optional)</h6>

        <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text"><?= htmlspecialchars($user['username']) ?>_</span>
                <input type="text" name="db_username" class="form-control" pattern="[a-zA-Z0-9_]{2,32}">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="db_password" id="dbPassword" class="form-control">
                <button type="button" class="btn btn-outline-secondary btn-generate-password" data-target="dbPassword">
                    <i class="bi bi-key"></i> Generate
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Create Database</button>
        <a href="/databases" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div></div></div></div>
