<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Create DNS Zone</h1>
        <p class="text-muted mb-0">Add a new DNS zone with default records</p>
    </div>
    <a href="/dns" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New DNS Zone</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/dns">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-3">
                        <label for="domain" class="form-label">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="domain" name="domain" required placeholder="example.com"
                               pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}">
                        <div class="form-text">Enter the root domain (e.g., example.com). Do not include www.</div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Default records will be created:</strong>
                        <ul class="mb-0 mt-2">
                            <li>A record for @ → Server IP</li>
                            <li>CNAME record for www → domain</li>
                            <li>MX record for mail handling</li>
                            <li>NS records (ns1, ns2)</li>
                            <li>SPF TXT record</li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create Zone</button>
                        <a href="/dns" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-question-circle me-2"></i>DNS Setup Guide</h6>
            </div>
            <div class="card-body">
                <p>After creating a DNS zone, you need to point your domain's nameservers to this server:</p>
                <ol>
                    <li>Log in to your domain registrar</li>
                    <li>Update nameservers to:<br>
                        <code>ns1.yourdomain.com</code><br>
                        <code>ns2.yourdomain.com</code>
                    </li>
                    <li>Wait for DNS propagation (up to 48 hours)</li>
                </ol>
                <p class="text-muted mb-0">You can add, edit, and delete DNS records after creating the zone.</p>
            </div>
        </div>
    </div>
</div>
