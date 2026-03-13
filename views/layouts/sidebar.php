<aside class="bg-dark text-white" id="sidebar-wrapper" style="min-width: 260px; max-width: 260px; min-height: 100vh;">
    <div class="sidebar-heading p-3 d-flex align-items-center border-bottom border-secondary">
        <i class="bi bi-shield-shaded fs-3 text-primary me-2"></i>
        <span class="fs-5 fw-bold">Panelion</span>
    </div>
    <div class="list-group list-group-flush">
        <?php
        $currentPath = '/' . trim($_GET['route'] ?? '', '/');
        $menuItems = [
            ['url' => '/dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'match' => ['/dashboard', '/']],
            ['url' => '/domains', 'icon' => 'bi-globe', 'label' => 'Domains', 'match' => ['/domains']],
            ['url' => '/files', 'icon' => 'bi-folder2-open', 'label' => 'File Manager', 'match' => ['/files']],
            ['url' => '/databases', 'icon' => 'bi-database', 'label' => 'Databases', 'match' => ['/databases']],
            ['url' => '/applications', 'icon' => 'bi-app-indicator', 'label' => 'Applications', 'match' => ['/applications']],
            ['url' => '/email', 'icon' => 'bi-envelope', 'label' => 'Email', 'match' => ['/email']],
            ['url' => '/dns', 'icon' => 'bi-diagram-3', 'label' => 'DNS Zone Editor', 'match' => ['/dns']],
            ['url' => '/ssl', 'icon' => 'bi-shield-lock', 'label' => 'SSL/TLS', 'match' => ['/ssl']],
            ['url' => '/ftp', 'icon' => 'bi-hdd-network', 'label' => 'FTP Accounts', 'match' => ['/ftp']],
            ['url' => '/cron', 'icon' => 'bi-clock-history', 'label' => 'Cron Jobs', 'match' => ['/cron']],
            ['url' => '/backup', 'icon' => 'bi-cloud-download', 'label' => 'Backups', 'match' => ['/backup']],
            ['url' => '/webserver', 'icon' => 'bi-server', 'label' => 'Web Server', 'match' => ['/webserver']],
            ['url' => '/firewall', 'icon' => 'bi-bricks', 'label' => 'Firewall', 'match' => ['/firewall']],
            ['url' => '/monitoring', 'icon' => 'bi-graph-up', 'label' => 'Monitoring', 'match' => ['/monitoring']],
        ];

        $adminItems = [
            ['url' => '/users', 'icon' => 'bi-people', 'label' => 'User Management', 'match' => ['/users']],
            ['url' => '/settings', 'icon' => 'bi-gear', 'label' => 'Server Settings', 'match' => ['/settings']],
        ];

        foreach ($menuItems as $item):
            $isActive = false;
            foreach ($item['match'] as $match) {
                if (strpos($currentPath, $match) === 0) {
                    $isActive = true;
                    break;
                }
            }
        ?>
            <a href="<?= $item['url'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 <?= $isActive ? 'active-menu' : '' ?>">
                <i class="bi <?= $item['icon'] ?> me-2"></i> <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <div class="sidebar-divider border-top border-secondary my-2 mx-3"></div>
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.7rem;">Administration</small>
            <?php foreach ($adminItems as $item):
                $isActive = false;
                foreach ($item['match'] as $match) {
                    if (strpos($currentPath, $match) === 0) {
                        $isActive = true;
                        break;
                    }
                }
            ?>
                <a href="<?= $item['url'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 <?= $isActive ? 'active-menu' : '' ?>">
                    <i class="bi <?= $item['icon'] ?> me-2"></i> <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Server info at bottom -->
    <div class="mt-auto p-3 border-top border-secondary" style="position: absolute; bottom: 0; width: 100%;">
        <small class="text-muted d-block">Panelion v<?= PANELION_VERSION ?></small>
        <small class="text-muted d-block"><?= htmlspecialchars(gethostname() ?: 'server') ?></small>
    </div>
</aside>
