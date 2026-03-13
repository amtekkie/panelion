<div class="page-header">
    <h2>Create User</h2>
    <p>Create a new hosting account</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="/users/store" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required pattern="[a-zA-Z][a-zA-Z0-9_]{2,31}" maxlength="32">
                            <small class="text-muted">Letters, numbers, underscore. 3-32 chars.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" required minlength="12">
                                <button type="button" class="btn btn-outline-secondary btn-generate-password" data-target="password">
                                    <i class="bi bi-key"></i> Generate
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="user">User</option>
                                <option value="reseller">Reseller</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Package</label>
                            <select name="package_id" class="form-select" id="packageSelect">
                                <option value="">Custom</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold">User Groups</h6>
                    <div class="row g-2">
                        <?php foreach ($groups ?? [] as $group): ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="groups[]" value="<?= $group['id'] ?>" id="group_<?= $group['id'] ?>" <?= $group['is_default'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="group_<?= $group['id'] ?>">
                                    <?= htmlspecialchars($group['display_name']) ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($group['description'] ?? '') ?></small>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>
                    <h6 class="fw-bold">Resource Limits</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Max Domains</label>
                            <input type="number" name="max_domains" class="form-control" value="1" min="-1">
                            <small class="text-muted">-1 for unlimited</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Databases</label>
                            <input type="number" name="max_databases" class="form-control" value="1" min="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Email Accounts</label>
                            <input type="number" name="max_email_accounts" class="form-control" value="5" min="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Disk Quota (bytes)</label>
                            <input type="number" name="max_disk_quota" class="form-control" value="1073741824" min="-1">
                            <small class="text-muted">1073741824 = 1 GB</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bandwidth (bytes)</label>
                            <input type="number" name="max_bandwidth" class="form-control" value="10737418240" min="-1">
                            <small class="text-muted">10737418240 = 10 GB</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Create User</button>
                        <a href="/users" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
