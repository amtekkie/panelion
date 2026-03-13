-- Panelion Database Schema
-- Web Hosting Control Panel

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ========================================
-- Core Tables
-- ========================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(32) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) DEFAULT '',
    `last_name` VARCHAR(100) DEFAULT '',
    `role` ENUM('admin', 'reseller', 'user') DEFAULT 'user',
    `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    `permissions` JSON DEFAULT NULL,
    `two_factor_secret` VARCHAR(64) DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `api_key` VARCHAR(64) DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `max_domains` INT DEFAULT 1,
    `max_databases` INT DEFAULT 1,
    `max_email_accounts` INT DEFAULT 5,
    `max_ftp_accounts` INT DEFAULT 5,
    `max_disk_quota` BIGINT DEFAULT 1073741824,  -- 1GB in bytes
    `max_bandwidth` BIGINT DEFAULT 10737418240,   -- 10GB in bytes
    `disk_used` BIGINT DEFAULT 0,
    `bandwidth_used` BIGINT DEFAULT 0,
    `package_id` INT UNSIGNED DEFAULT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    INDEX `idx_parent` (`parent_id`),
    FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `max_domains` INT DEFAULT 1,
    `max_subdomains` INT DEFAULT 5,
    `max_databases` INT DEFAULT 1,
    `max_email_accounts` INT DEFAULT 5,
    `max_ftp_accounts` INT DEFAULT 5,
    `max_disk_quota` BIGINT DEFAULT 1073741824,
    `max_bandwidth` BIGINT DEFAULT 10737418240,
    `max_addon_domains` INT DEFAULT 0,
    `max_parked_domains` INT DEFAULT 0,
    `features` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Domain Management
-- ========================================

CREATE TABLE IF NOT EXISTS `domains` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain` VARCHAR(255) NOT NULL UNIQUE,
    `type` ENUM('primary', 'addon', 'subdomain', 'alias', 'parked') DEFAULT 'primary',
    `parent_domain_id` INT UNSIGNED DEFAULT NULL,
    `document_root` VARCHAR(500) DEFAULT NULL,
    `php_version` VARCHAR(10) DEFAULT '8.2',
    `ssl_enabled` TINYINT(1) DEFAULT 0,
    `ssl_auto_renew` TINYINT(1) DEFAULT 1,
    `status` ENUM('active', 'suspended', 'pending_dns') DEFAULT 'active',
    `webserver_config` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_domain` (`domain`),
    INDEX `idx_type` (`type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- DNS Management
-- ========================================

CREATE TABLE IF NOT EXISTS `dns_zones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain_id` INT UNSIGNED DEFAULT NULL,
    `domain` VARCHAR(255) NOT NULL,
    `ttl` INT DEFAULT 3600,
    `soa_email` VARCHAR(255) DEFAULT NULL,
    `serial` INT UNSIGNED DEFAULT 1,
    `refresh` INT DEFAULT 10800,
    `retry` INT DEFAULT 3600,
    `expire` INT DEFAULT 604800,
    `minimum_ttl` INT DEFAULT 3600,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dns_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR', 'SOA') NOT NULL,
    `content` VARCHAR(1024) NOT NULL,
    `ttl` INT DEFAULT 3600,
    `priority` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_zone` (`zone_id`),
    INDEX `idx_type` (`type`),
    FOREIGN KEY (`zone_id`) REFERENCES `dns_zones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Database Management
-- ========================================

CREATE TABLE IF NOT EXISTS `user_databases` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `db_name` VARCHAR(64) NOT NULL,
    `db_type` ENUM('mysql', 'mariadb', 'postgresql', 'mongodb', 'sqlite') DEFAULT 'mysql',
    `db_server` VARCHAR(255) DEFAULT '127.0.0.1',
    `db_port` INT DEFAULT 3306,
    `size` BIGINT DEFAULT 0,
    `charset` VARCHAR(32) DEFAULT 'utf8mb4',
    `collation` VARCHAR(64) DEFAULT 'utf8mb4_unicode_ci',
    `status` ENUM('active', 'suspended') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    UNIQUE KEY `uk_db` (`db_name`, `db_type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `database_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `db_username` VARCHAR(64) NOT NULL,
    `db_type` ENUM('mysql', 'mariadb', 'postgresql', 'mongodb') DEFAULT 'mysql',
    `db_server` VARCHAR(255) DEFAULT '127.0.0.1',
    `privileges` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `database_user_grants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `database_id` INT UNSIGNED NOT NULL,
    `database_user_id` INT UNSIGNED NOT NULL,
    `privileges` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`database_id`) REFERENCES `user_databases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`database_user_id`) REFERENCES `database_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Email Management
-- ========================================

CREATE TABLE IF NOT EXISTS `email_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `quota` BIGINT DEFAULT 104857600,  -- 100MB
    `quota_used` BIGINT DEFAULT 0,
    `status` ENUM('active', 'suspended') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_domain` (`domain_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_forwarders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain_id` INT UNSIGNED NOT NULL,
    `source` VARCHAR(255) NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_autoresponders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SSL Certificates
-- ========================================

CREATE TABLE IF NOT EXISTS `ssl_certificates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain_id` INT UNSIGNED NOT NULL,
    `type` ENUM('letsencrypt', 'custom', 'self_signed') DEFAULT 'letsencrypt',
    `certificate_path` VARCHAR(500) DEFAULT NULL,
    `key_path` VARCHAR(500) DEFAULT NULL,
    `ca_bundle` TEXT DEFAULT NULL,
    `issuer` VARCHAR(255) DEFAULT NULL,
    `expiry_date` DATETIME DEFAULT NULL,
    `auto_renew` TINYINT(1) DEFAULT 1,
    `status` ENUM('active', 'expired', 'pending', 'revoked') DEFAULT 'pending',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_domain` (`domain_id`),
    INDEX `idx_expiry` (`expiry_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- FTP Accounts
-- ========================================

CREATE TABLE IF NOT EXISTS `ftp_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `home_directory` VARCHAR(500) NOT NULL,
    `quota` BIGINT DEFAULT 0,
    `status` ENUM('active', 'suspended') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Application Management
-- ========================================

CREATE TABLE IF NOT EXISTS `applications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `domain_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('php', 'node', 'python', 'ruby', 'go', 'rust', 'java', 'static', 'docker') NOT NULL,
    `runtime_version` VARCHAR(20) DEFAULT NULL,
    `entry_point` VARCHAR(255) DEFAULT NULL,
    `port` INT DEFAULT NULL,
    `environment` JSON DEFAULT NULL,
    `process_manager` ENUM('pm2', 'supervisor', 'systemd') DEFAULT 'systemd',
    `instances` INT DEFAULT 1,
    `max_memory` VARCHAR(20) DEFAULT '256M',
    `auto_restart` TINYINT(1) DEFAULT 1,
    `status` ENUM('running', 'stopped', 'error', 'deploying') DEFAULT 'stopped',
    `git_repo` VARCHAR(500) DEFAULT NULL,
    `git_branch` VARCHAR(100) DEFAULT 'main',
    `build_command` TEXT DEFAULT NULL,
    `start_command` TEXT DEFAULT NULL,
    `install_command` TEXT DEFAULT NULL,
    `app_root` VARCHAR(500) DEFAULT NULL,
    `log_file` VARCHAR(500) DEFAULT NULL,
    `pid_file` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Cron Jobs
-- ========================================

CREATE TABLE IF NOT EXISTS `cron_jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `minute` VARCHAR(20) DEFAULT '*',
    `hour` VARCHAR(20) DEFAULT '*',
    `day` VARCHAR(20) DEFAULT '*',
    `month` VARCHAR(20) DEFAULT '*',
    `weekday` VARCHAR(20) DEFAULT '*',
    `command` TEXT NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_run` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Backups
-- ========================================

CREATE TABLE IF NOT EXISTS `backups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('full', 'files', 'database', 'email', 'incremental') DEFAULT 'full',
    `filename` VARCHAR(500) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `size` BIGINT DEFAULT 0,
    `status` ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    `includes` JSON DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backup_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `frequency` ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
    `day_of_week` TINYINT DEFAULT 0,
    `hour` TINYINT DEFAULT 2,
    `retention_days` INT DEFAULT 30,
    `type` ENUM('full', 'files', 'database', 'incremental') DEFAULT 'full',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_run` DATETIME DEFAULT NULL,
    `next_run` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Firewall Rules
-- ========================================

CREATE TABLE IF NOT EXISTS `firewall_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `direction` ENUM('in', 'out') DEFAULT 'in',
    `action` ENUM('allow', 'deny') DEFAULT 'deny',
    `protocol` ENUM('tcp', 'udp', 'both') DEFAULT 'tcp',
    `port` VARCHAR(20) DEFAULT NULL,
    `source` VARCHAR(45) DEFAULT 'any',
    `destination_ip` VARCHAR(45) DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `priority` INT DEFAULT 100,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_priority` (`priority`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `blocked_by` ENUM('manual', 'fail2ban', 'auto') DEFAULT 'manual',
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Security & Logging
-- ========================================

CREATE TABLE IF NOT EXISTS `login_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('success', 'failed', '2fa_required') DEFAULT 'failed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_identifier` (`identifier`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE,
    `permissions` JSON DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(50) DEFAULT NULL,
    `resource_id` INT UNSIGNED DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- User Groups & Permissions
-- ========================================

CREATE TABLE IF NOT EXISTS `user_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `permissions` JSON DEFAULT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_group_members` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_group` (`user_id`, `group_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `user_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Server Settings
-- ========================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    `type` ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Default Data
-- ========================================

-- Default admin account (password: ChangeMeNow!2024)
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `max_domains`, `max_databases`, `max_email_accounts`, `max_disk_quota`, `max_bandwidth`)
VALUES ('admin', 'admin@panelion.local', '$2y$10$LPT07Qjjiv9ZvovPAmVCJ.5Mz6ijrArxbEgsD5.5F08rQmdE6GJfm', 'System', 'Administrator', 'admin', 'active', -1, -1, -1, -1, -1);

-- Default hosting packages
INSERT INTO `packages` (`name`, `description`, `max_domains`, `max_subdomains`, `max_databases`, `max_email_accounts`, `max_ftp_accounts`, `max_disk_quota`, `max_bandwidth`) VALUES
('Starter', 'Basic hosting package', 1, 5, 1, 5, 2, 1073741824, 10737418240),
('Professional', 'Professional hosting package', 5, 25, 5, 25, 10, 5368709120, 53687091200),
('Business', 'Business hosting package', 10, 50, 10, 50, 25, 10737418240, 107374182400),
('Enterprise', 'Unlimited hosting package', -1, -1, -1, -1, -1, -1, -1);

-- Default user groups
INSERT INTO `user_groups` (`name`, `display_name`, `description`, `permissions`, `is_default`) VALUES
('admin', 'Administrator', 'Full access to all features', '["*"]', 0),
('reseller', 'Reseller', 'Can create and manage users and their resources', '["users.view","users.create","users.edit","users.delete","domains.manage","databases.manage","email.manage","ftp.manage","dns.manage","ssl.manage","backup.manage","filemanager.access","cron.manage","apps.manage"]', 0),
('user', 'Normal User', 'Standard hosting user', '["domains.view","databases.view","email.view","email.create","ftp.view","ftp.create","dns.view","ssl.view","backup.create","backup.view","filemanager.access","cron.view","cron.create"]', 1);

-- Default settings
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
('panel_name', 'Panelion', 'string', 'Panel display name'),
('panel_theme', 'default', 'string', 'UI theme'),
('webserver', 'nginx', 'string', 'Primary web server'),
('default_php', '8.2', 'string', 'Default PHP version'),
('nameserver1', 'ns1.panelion.local', 'string', 'Primary nameserver'),
('nameserver2', 'ns2.panelion.local', 'string', 'Secondary nameserver'),
('backup_enabled', '1', 'bool', 'Enable automatic backups'),
('backup_retention', '30', 'int', 'Backup retention in days'),
('ssl_auto', '1', 'bool', 'Auto-issue SSL certificates'),
('allow_user_php_version', '1', 'bool', 'Allow users to change PHP version'),
('allow_user_ssh', '0', 'bool', 'Allow users SSH access'),
('max_upload_size', '512', 'int', 'Maximum upload size in MB'),
('roundcube_url', '/roundcube', 'string', 'Roundcube webmail URL'),
('installed', '0', 'bool', 'Panel installation status');

SET FOREIGN_KEY_CHECKS = 1;
