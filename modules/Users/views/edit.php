<div class="page-header">
    <h2>Edit User: <?= htmlspecialchars($editUser['username']) ?></h2>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="/users/update/<?= $editUser['id'] ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($editUser['username']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" minlength="12">
                                <button type="button" class="btn btn-outline-secondary btn-generate-password" data-target="password"><i class="bi bi-key"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="reseller" <?= $editUser['role'] === 'reseller' ? 'selected' : '' ?>>Reseller</option>
                                <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($editUser['first_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($editUser['last_name']) ?>">
                        </div>
                    </div>

                    <hr><h6 class="fw-bold">User Groups</h6>
                    <div class="row g-2 mb-3">
                        <?php foreach ($groups ?? [] as $group): ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="groups[]" value="<?= $group['id'] ?>" id="group_<?= $group['id'] ?>" <?= in_array($group['id'], $userGroupIds ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="group_<?= $group['id'] ?>">
                                    <?= htmlspecialchars($group['display_name']) ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($group['description'] ?? '') ?></small>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <hr><h6 class="fw-bold">Resource Limits</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Max Domains</label>
                            <input type="number" name="max_domains" class="form-control" value="<?= $editUser['max_domains'] ?>" min="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Databases</label>
                            <input type="number" name="max_databases" class="form-control" value="<?= $editUser['max_databases'] ?>" min="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Email Accounts</label>
                            <input type="number" name="max_email_accounts" class="form-control" value="<?= $editUser['max_email_accounts'] ?>" min="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Disk Quota</label>
                            <input type="number" name="max_disk_quota" class="form-control" value="<?= $editUser['max_disk_quota'] ?>" min="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bandwidth</label>
                            <input type="number" name="max_bandwidth" class="form-control" value="<?= $editUser['max_bandwidth'] ?>" min="-1">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Update User</button>
                        <a href="/users" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
