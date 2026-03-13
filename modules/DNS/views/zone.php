<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-globe me-2"></i><?= htmlspecialchars($zone['domain']) ?>
        </h1>
        <p class="text-muted mb-0">Serial: <?= htmlspecialchars($zone['serial']) ?> &bull; <?= count($records) ?> records</p>
    </div>
    <a href="/dns" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Zones</a>
</div>

<!-- Add Record Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add DNS Record</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="/dns/<?= $zone['id'] ?>/records">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="@ or subdomain" required>
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" id="type" name="type" required>
                        <?php foreach ($recordTypes as $type): ?>
                            <option value="<?= $type ?>"><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="content" class="form-label">Content</label>
                    <input type="text" class="form-control" id="content" name="content" placeholder="IP or value" required>
                </div>
                <div class="col-md-2">
                    <label for="ttl" class="form-label">TTL</label>
                    <select class="form-select" id="ttl" name="ttl">
                        <option value="300">5 min</option>
                        <option value="900">15 min</option>
                        <option value="1800">30 min</option>
                        <option value="3600" selected>1 hour</option>
                        <option value="14400">4 hours</option>
                        <option value="43200">12 hours</option>
                        <option value="86400">1 day</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label for="priority" class="form-label">Priority</label>
                    <input type="number" class="form-control" id="priority" name="priority" value="0" min="0" max="65535">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Records Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>DNS Records</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="filterRecordType" style="width: 120px;" onchange="filterRecords()">
                <option value="">All Types</option>
                <?php foreach ($recordTypes as $type): ?>
                    <option value="<?= $type ?>"><?= $type ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
            <div class="text-center py-5">
                <p class="text-muted">No DNS records. Add your first record above.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="recordsTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Content</th>
                            <th>TTL</th>
                            <th>Priority</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr data-type="<?= htmlspecialchars($record['type']) ?>" id="record-<?= $record['id'] ?>">
                                <td>
                                    <code><?= htmlspecialchars($record['name']) ?></code>
                                    <?php if ($record['name'] === '@'): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($zone['domain']) ?>)</small>
                                    <?php else: ?>
                                        <small class="text-muted">.<?= htmlspecialchars($zone['domain']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= match($record['type']) {
                                        'A' => 'primary', 'AAAA' => 'info', 'CNAME' => 'success',
                                        'MX' => 'warning', 'TXT' => 'secondary', 'NS' => 'dark',
                                        'SRV' => 'purple', 'CAA' => 'danger', default => 'light'
                                    } ?>"><?= htmlspecialchars($record['type']) ?></span>
                                </td>
                                <td>
                                    <!-- Inline edit form -->
                                    <form method="POST" action="/dns/<?= $zone['id'] ?>/records/<?= $record['id'] ?>" class="d-inline edit-form" style="display:none !important;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="content" value="<?= htmlspecialchars($record['content']) ?>" class="form-control" style="min-width:150px;">
                                            <input type="number" name="ttl" value="<?= $record['ttl'] ?>" class="form-control" style="width:70px;">
                                            <input type="number" name="priority" value="<?= $record['priority'] ?>" class="form-control" style="width:60px;">
                                            <button type="submit" class="btn btn-success"><i class="bi bi-check"></i></button>
                                            <button type="button" class="btn btn-secondary" onclick="cancelEdit(<?= $record['id'] ?>)"><i class="bi bi-x"></i></button>
                                        </div>
                                    </form>
                                    <span class="record-content"><?= htmlspecialchars($record['content']) ?></span>
                                </td>
                                <td><span class="record-ttl"><?= $record['ttl'] ?>s</span></td>
                                <td><span class="record-priority"><?= $record['priority'] ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm record-actions">
                                        <button class="btn btn-outline-primary" onclick="editRecord(<?= $record['id'] ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="/dns/<?= $zone['id'] ?>/records/<?= $record['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this record?')">
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

<script>
function editRecord(id) {
    const row = document.getElementById('record-' + id);
    row.querySelector('.edit-form').style.display = 'inline !important' ;
    row.querySelector('.edit-form').style.cssText = 'display: inline !important';
    row.querySelector('.record-content').style.display = 'none';
    row.querySelector('.record-ttl').style.display = 'none';
    row.querySelector('.record-priority').style.display = 'none';
    row.querySelector('.record-actions').style.display = 'none';
}

function cancelEdit(id) {
    const row = document.getElementById('record-' + id);
    row.querySelector('.edit-form').style.cssText = 'display: none !important';
    row.querySelector('.record-content').style.display = '';
    row.querySelector('.record-ttl').style.display = '';
    row.querySelector('.record-priority').style.display = '';
    row.querySelector('.record-actions').style.display = '';
}

function filterRecords() {
    const type = document.getElementById('filterRecordType').value;
    document.querySelectorAll('#recordsTable tbody tr').forEach(row => {
        row.style.display = (!type || row.dataset.type === type) ? '' : 'none';
    });
}
</script>
