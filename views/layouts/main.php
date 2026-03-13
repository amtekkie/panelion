<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - Panelion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/panelion.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php require PANELION_ROOT . '/views/layouts/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2">
                <div class="d-flex align-items-center">
                    <button class="btn btn-link text-dark" id="menu-toggle">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <nav aria-label="breadcrumb" class="ms-3">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
                            <?php if (!empty($breadcrumbs)): ?>
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item <?= isset($crumb['active']) ? 'active' : '' ?>">
                                        <?php if (isset($crumb['url'])): ?>
                                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($crumb['label']) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-link text-dark position-relative" data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="width: 300px;">
                            <h6 class="dropdown-header">Notifications</h6>
                            <div class="dropdown-item text-muted small">No new notifications</div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-link text-dark d-flex align-items-center" data-bs-toggle="dropdown">
                            <div class="avatar-circle me-2"><?= strtoupper(substr($user['username'] ?? 'A', 0, 1)) ?></div>
                            <span class="d-none d-md-inline"><?= htmlspecialchars($user['username'] ?? 'Admin') ?></span>
                            <i class="bi bi-chevron-down ms-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="/settings/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Flash Messages -->
            <?php if ($flash = ($app->session()->flash('success'))): ?>
                <div class="alert alert-success alert-dismissible fade show m-4 mb-0" role="alert">
                    <?= htmlspecialchars($flash) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($flash = ($app->session()->flash('error'))): ?>
                <div class="alert alert-danger alert-dismissible fade show m-4 mb-0" role="alert">
                    <?= htmlspecialchars($flash) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="container-fluid p-4">
                <?= $content ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="/assets/js/panelion.js"></script>
    <?php if (!empty($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
