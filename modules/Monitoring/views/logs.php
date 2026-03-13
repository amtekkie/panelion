<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Server Logs</h1>
        <p class="text-muted mb-0"><?= htmlspecialchars($logPath) ?></p>
    </div>
    <a href="/monitoring" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Monitoring</a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="/monitoring/logs" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Log File</label>
                <select class="form-select" name="file" onchange="this.form.submit()">
                    <?php foreach ($allowedLogs as $key => $path): ?>
                        <option value="<?= $key ?>" <?= $logFile === $key ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $key)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Lines</label>
                <select class="form-select" name="lines" onchange="this.form.submit()">
                    <?php foreach ([50, 100, 200, 500] as $n): ?>
                        <option value="<?= $n ?>" <?= $lines === $n ? 'selected' : '' ?>><?= $n ?> lines</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <pre class="mb-0 p-3" style="background:#1e1e2e;color:#cdd6f4;max-height:600px;overflow:auto;font-size:0.8rem;white-space:pre-wrap;word-break:break-all;"><?= htmlspecialchars($content) ?></pre>
    </div>
</div>
