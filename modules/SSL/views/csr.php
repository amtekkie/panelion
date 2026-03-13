<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Generate CSR</h1>
        <p class="text-muted mb-0">Create a Certificate Signing Request</p>
    </div>
    <a href="/ssl" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-file-earmark-code me-2"></i>CSR Details</h5></div>
            <div class="card-body">
                <form method="POST" action="/ssl/csr">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Domain (Common Name) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="domain" required placeholder="example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="admin@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organization <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="organization" required placeholder="My Company Ltd">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="org_unit" placeholder="IT Department">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city" required placeholder="New York">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State/Province <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" required placeholder="New York">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Country Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="country" required placeholder="US" maxlength="2" pattern="[A-Za-z]{2}">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-gear me-1"></i> Generate CSR</button>
                        <a href="/ssl" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
