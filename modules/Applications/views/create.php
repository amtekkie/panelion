<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Create Application</h1>
        <p class="text-muted mb-0">Deploy a new application</p>
    </div>
    <a href="/applications" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Application Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/applications">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Application Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                               pattern="[a-zA-Z0-9_-]+" placeholder="my-app"
                               title="Only letters, numbers, hyphens, and underscores">
                        <div class="form-text">Used as identifier. Only letters, numbers, hyphens, and underscores.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Application Type <span class="text-danger">*</span></label>
                        <div class="row g-2" id="runtimeGrid">
                            <?php foreach ($runtimes as $key => $rt): ?>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="type" id="type_<?= $key ?>" value="<?= $key ?>" <?= $key === 'nodejs' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary w-100 text-start py-3" for="type_<?= $key ?>">
                                        <i class="bi bi-<?= $rt['icon'] ?> me-2"></i>
                                        <strong><?= $rt['name'] ?></strong>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3" id="versionGroup">
                        <label for="version" class="form-label">Version</label>
                        <select class="form-select" id="version" name="version">
                            <!-- Populated by JS -->
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="domain_id" class="form-label">Domain <span class="text-danger">*</span></label>
                        <select class="form-select" id="domain_id" name="domain_id" required>
                            <option value="">Select a domain...</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="path" class="form-label">URL Path</label>
                        <div class="input-group">
                            <span class="input-group-text">domain.com</span>
                            <input type="text" class="form-control" id="path" name="path" value="/" placeholder="/">
                        </div>
                        <div class="form-text">Leave as "/" for the root domain, or specify a path like "/app"</div>
                    </div>

                    <div class="mb-3" id="portGroup">
                        <label for="port" class="form-label">Port</label>
                        <input type="number" class="form-control" id="port" name="port" min="3000" max="65535" placeholder="Auto-assigned">
                        <div class="form-text">Leave empty for auto-assignment. Must be between 3000-65535.</div>
                    </div>

                    <div class="mb-3" id="startupGroup">
                        <label for="startup_command" class="form-label">Startup Command</label>
                        <input type="text" class="form-control font-monospace" id="startup_command" name="startup_command" placeholder="e.g., npm start, python app.py">
                        <div class="form-text">Command to start your application. Leave empty for default.</div>
                    </div>

                    <div class="mb-3">
                        <label for="env_vars" class="form-label">Environment Variables</label>
                        <textarea class="form-control font-monospace" id="env_vars" name="env_vars" rows="4" placeholder="KEY=value&#10;DATABASE_URL=mysql://...&#10;SECRET_KEY=your-secret"></textarea>
                        <div class="form-text">One per line in KEY=value format.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-rocket me-1"></i> Create Application
                        </button>
                        <a href="/applications" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Runtime Info</h6>
            </div>
            <div class="card-body" id="runtimeInfo">
                <p class="text-muted">Select an application type to see details.</p>
            </div>
        </div>
    </div>
</div>

<script>
const runtimeVersions = <?= json_encode(array_map(fn($r) => $r['versions'], $runtimes)) ?>;
const runtimeInfo = {
    php: 'PHP applications run directly through the web server (Nginx/Apache) with PHP-FPM. No separate process management needed.',
    nodejs: 'Node.js applications run as background processes managed by systemd. WebSocket support included.',
    python: 'Python applications run in isolated virtual environments. Supports Flask, Django, FastAPI, and more.',
    ruby: 'Ruby applications support Rails, Sinatra, and other frameworks. Bundler integration included.',
    go: 'Go applications are compiled and run as standalone binaries. High performance and low memory usage.',
    rust: 'Rust applications are compiled to native binaries. Excellent performance characteristics.',
    java: 'Java applications run on the JVM. Supports Spring Boot, Quarkus, and other frameworks.',
    'static': 'Static sites are served directly by the web server. Perfect for HTML/CSS/JS, React, Vue, Angular builds.',
    docker: 'Docker applications run in containers with docker-compose. Full isolation and reproducibility.'
};

function updateVersions() {
    const type = document.querySelector('input[name="type"]:checked')?.value;
    const versionSelect = document.getElementById('version');
    const versions = runtimeVersions[type] || [];

    versionSelect.innerHTML = versions.map(v => `<option value="${v}">${v}</option>`).join('');

    // Show/hide fields based on type
    const isServerApp = !['php', 'static'].includes(type);
    document.getElementById('portGroup').style.display = isServerApp ? '' : 'none';
    document.getElementById('startupGroup').style.display = isServerApp ? '' : 'none';
    document.getElementById('versionGroup').style.display = type !== 'docker' ? '' : 'none';

    // Update info panel
    document.getElementById('runtimeInfo').innerHTML = `<p>${runtimeInfo[type] || ''}</p>`;
}

document.querySelectorAll('input[name="type"]').forEach(radio => {
    radio.addEventListener('change', updateVersions);
});

updateVersions();
</script>
