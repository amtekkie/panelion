# Panelion — Web Hosting Control Panel

A feature-rich, open-source web hosting control panel built in PHP. Manage domains, databases, email, DNS, SSL, applications, and more — all from a modern web interface.

## Features

### Core Management
- **Domain Management** — Add/remove domains, subdomains, vhost generation (Nginx/Apache)
- **Database Management** — MySQL, MariaDB, PostgreSQL, MongoDB, Redis, SQLite
- **Email Accounts** — Full email management with Postfix/Dovecot, forwarders, autoresponders
- **DNS Zone Editor** — BIND integration, zone file management, all record types (A, AAAA, CNAME, MX, TXT, SRV, CAA, etc.)
- **SSL/TLS Certificates** — Let's Encrypt auto-install, custom certificate upload, CSR generation
- **FTP Accounts** — vsftpd/ProFTPd/Pure-FTPd support with quotas

### Application Hosting
- **Multi-Runtime Support** — PHP (7.4-8.4), Node.js (18/20/22), Python (3.8-3.12), Ruby, Go, Rust, Java, Docker
- **Process Management** — Start/stop/restart apps via systemd
- **Reverse Proxy** — Automatic Nginx reverse proxy configuration
- **Environment Variables** — Per-application environment configuration

### Server Administration
- **File Manager** — Web-based file browser with code editor, upload, compress/extract, permissions
- **Backup System** — Full/incremental backups, scheduling (daily/weekly/monthly), restore, download
- **Firewall** — UFW/iptables/firewalld management, IP blocking with expiry
- **Server Monitoring** — Real-time CPU, memory, disk, load average, process list, network stats
- **Cron Job Manager** — Schedule management with presets and cron expression editor
- **Log Viewer** — System, web server, mail, and application logs

### Security
- **Two-Factor Authentication** — TOTP-based 2FA
- **CSRF Protection** — All forms protected with CSRF tokens
- **Rate Limiting** — Login attempt limiting with lockout
- **Input Sanitization** — XSS and injection prevention
- **Command Whitelisting** — System commands restricted to approved list
- **Secure Sessions** — HttpOnly, Secure, SameSite cookies

### Administration
- **Multi-User Support** — Admin and user roles with permission-based access
- **Hosting Packages** — Configurable resource limits per user
- **API Access** — REST API with Bearer token and API key authentication
- **Service Manager** — Start/stop/restart system services from the panel
- **Activity Logging** — Complete audit trail of all actions

## Requirements

- **OS:** Ubuntu 20.04+, Debian 11+, CentOS 8+, AlmaLinux 8+, Rocky Linux 8+
- **PHP:** 8.1+ with extensions: PDO, MySQL, mbstring, xml, curl, zip, gd, intl
- **Web Server:** Nginx (recommended) or Apache
- **Database:** MySQL 8.0+ or MariaDB 10.5+
- **RAM:** 1 GB minimum, 2 GB+ recommended
- **Disk:** 10 GB minimum

## Installation

### Quick Install

```bash
# Download and run the installer
git clone https://github.com/your-repo/panelion.git /usr/local/panelion
cd /usr/local/panelion
chmod +x install.sh
sudo ./install.sh
```

The installer will:
1. Detect your OS (Ubuntu/Debian/CentOS/AlmaLinux/Rocky)
2. Install Nginx, PHP 8.2, MariaDB, and optional services
3. Create the database and import the schema
4. Generate a self-signed SSL certificate
5. Configure the firewall
6. Display login credentials

### Manual Installation

1. **Install dependencies:**
   ```bash
   # Ubuntu/Debian
   sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
       php8.2-curl php8.2-zip php8.2-gd php8.2-intl mariadb-server

   # CentOS/AlmaLinux
   sudo dnf install nginx php-fpm php-mysqlnd php-mbstring php-xml \
       php-curl php-zip php-gd php-intl mariadb-server
   ```

2. **Clone the repository:**
   ```bash
   sudo git clone https://github.com/your-repo/panelion.git /usr/local/panelion
   ```

3. **Create the database:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE panelion CHARACTER SET utf8mb4;"
   mysql -u root -p panelion < /usr/local/panelion/database/schema.sql
   ```

4. **Configure the application:**
   ```bash
   # Edit config/app.php with your database credentials
   sudo nano /usr/local/panelion/config/app.php
   ```

5. **Set permissions:**
   ```bash
   sudo chown -R www-data:www-data /usr/local/panelion
   sudo chmod -R 750 /usr/local/panelion
   sudo chmod -R 770 /usr/local/panelion/storage
   ```

6. **Configure Nginx** (see `install.sh` for the full vhost config)

7. **Access the panel:**
   - URL: `https://your-server-ip:2083`
   - Default login: `admin` / `ChangeMeNow!2024`
   - **Change the default password immediately!**

## Directory Structure

```
panelion/
├── config/             # Application configuration
│   └── app.php
├── core/               # Framework core
│   ├── App.php         # Application bootstrap & routing
│   ├── Router.php      # URL router
│   ├── Controller.php  # Base controller
│   ├── Database.php    # PDO database wrapper
│   ├── Auth.php        # Authentication
│   ├── Session.php     # Session management
│   ├── Security.php    # Security utilities
│   ├── Logger.php      # File-based logger
│   ├── SystemCommand.php # System command executor
│   └── API.php         # REST API handler
├── database/
│   └── schema.sql      # Database schema (20+ tables)
├── modules/            # Feature modules
│   ├── Dashboard/      # Dashboard & overview
│   ├── Users/          # User management
│   ├── Domains/        # Domain management
│   ├── WebServer/      # Web server config
│   ├── Databases/      # Database management
│   ├── Applications/   # App deployment
│   ├── DNS/            # DNS zone management
│   ├── Email/          # Email accounts
│   ├── SSL/            # SSL certificates
│   ├── FileManager/    # File browser & editor
│   ├── Backup/         # Backup & restore
│   ├── Firewall/       # Firewall rules
│   ├── Monitoring/     # Server monitoring
│   ├── Cron/           # Cron job management
│   ├── FTP/            # FTP accounts
│   └── Settings/       # Panel & profile settings
├── public/             # Web root
│   ├── index.php       # Main entry point
│   ├── api.php         # API entry point
│   └── assets/         # CSS, JS, images
├── views/              # Shared views & layouts
│   ├── layouts/        # Main, sidebar, auth layouts
│   ├── auth/           # Login, 2FA pages
│   └── errors/         # 404, 500 pages
├── storage/            # Logs, cache, sessions
├── install.sh          # Automated installer
└── README.md
```

## API Usage

Panelion provides a REST API accessible at `/api.php`.

```bash
# Authenticate
curl -X POST https://server:2083/api.php/auth/login \
  -d '{"username":"admin","password":"yourpassword"}'

# List domains (with API key)
curl https://server:2083/api.php/domains \
  -H "Authorization: Bearer YOUR_API_KEY"

# Create domain
curl -X POST https://server:2083/api.php/domains \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"domain":"example.com"}'
```

## Default Ports

| Service      | Port   |
|-------------|--------|
| Panelion    | 2083   |
| HTTP        | 80     |
| HTTPS       | 443    |
| SSH         | 22     |
| FTP         | 21     |
| SMTP        | 25/587 |
| IMAP        | 993    |
| POP3        | 995    |
| MySQL       | 3306   |
| PostgreSQL  | 5432   |
| DNS         | 53     |

## Security Recommendations

1. **Change the default admin password** immediately after installation
2. **Enable 2FA** for all admin accounts
3. **Use Let's Encrypt** to replace the self-signed certificate
4. **Configure fail2ban** for SSH and panel login protection
5. **Keep the system updated** with regular OS and package updates
6. **Review firewall rules** and only allow necessary ports
7. **Enable automatic backups** with off-site storage

## License

MIT License — see [LICENSE](LICENSE) for details.
