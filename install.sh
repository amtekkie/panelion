#!/bin/bash
###############################################################################
# Panelion - Web Hosting Control Panel Installer
# Supports: Ubuntu 20.04+, Debian 11+, CentOS 8+, AlmaLinux 8+, Rocky 8+
###############################################################################

set -e

PANELION_VERSION="1.0.0"
PANELION_DIR="/usr/local/panelion"
PANELION_USER="panelion"
PANELION_PORT=2083
PANEL_DB="panelion"
PANEL_DB_USER="panelion"
GITHUB_REPO="https://github.com/PanelionProject/panelion.git"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step()  { echo -e "\n${BLUE}==>${NC} $1"; }

# ── Check root ──
if [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root (sudo)"
    exit 1
fi

# ── Detect OS ──
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID="$ID"
        OS_VERSION="$VERSION_ID"
        OS_NAME="$PRETTY_NAME"
    else
        log_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi

    case "$OS_ID" in
        ubuntu|debian)
            OS_FAMILY="debian"
            PKG_MANAGER="apt-get"
            PKG_INSTALL="apt-get install -y"
            PKG_UPDATE="apt-get update -y"
            ;;
        centos|almalinux|rocky|rhel|fedora)
            OS_FAMILY="rhel"
            PKG_MANAGER="dnf"
            PKG_INSTALL="dnf install -y"
            PKG_UPDATE="dnf update -y"
            ;;
        *)
            log_error "Unsupported OS: $OS_ID"
            exit 1
            ;;
    esac

    log_info "Detected: $OS_NAME ($OS_FAMILY)"
}

# ── Banner ──
show_banner() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════╗"
    echo "║           Panelion Installer v${PANELION_VERSION}           ║"
    echo "║        Web Hosting Control Panel             ║"
    echo "╚══════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# ── Generate secure password ──
generate_password() {
    openssl rand -base64 24 | tr -d '/+=' | head -c 24
}

# ── Install base packages ──
install_base() {
    log_step "Installing base packages..."

    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_UPDATE
        DEBIAN_FRONTEND=noninteractive $PKG_INSTALL \
            curl wget git unzip zip tar software-properties-common \
            openssl ca-certificates gnupg lsb-release cron \
            acl quota rsync
    else
        $PKG_UPDATE
        $PKG_INSTALL epel-release 2>/dev/null || true
        $PKG_INSTALL \
            curl wget git unzip zip tar openssl ca-certificates \
            cronie acl quota rsync policycoreutils-python-utils 2>/dev/null || true
    fi
}

# ── Install Nginx ──
install_nginx() {
    log_step "Installing Nginx..."

    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_INSTALL nginx
    else
        $PKG_INSTALL nginx
    fi

    systemctl enable nginx
    systemctl start nginx
    log_info "Nginx installed."
}

# ── Install PHP ──
install_php() {
    log_step "Installing PHP 8.2..."

    if [ "$OS_FAMILY" = "debian" ]; then
        # Add PHP PPA for latest versions
        add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
        apt-get update -y
        $PKG_INSTALL \
            php8.2-fpm php8.2-cli php8.2-mysql php8.2-pgsql php8.2-sqlite3 \
            php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd \
            php8.2-intl php8.2-bcmath php8.2-soap php8.2-redis php8.2-imagick \
            php8.2-imap php8.2-ldap php8.2-opcache php8.2-readline
        PHP_FPM_SERVICE="php8.2-fpm"
        PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
    else
        $PKG_INSTALL https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %{rhel}).rpm 2>/dev/null || true
        dnf module reset php -y 2>/dev/null || true
        dnf module enable php:remi-8.2 -y 2>/dev/null || true
        $PKG_INSTALL \
            php php-fpm php-cli php-mysqlnd php-pgsql php-mbstring \
            php-xml php-curl php-zip php-gd php-intl php-bcmath \
            php-soap php-redis php-imagick php-imap php-ldap php-opcache
        PHP_FPM_SERVICE="php-fpm"
        PHP_FPM_SOCK="/run/php-fpm/www.sock"
    fi

    systemctl enable $PHP_FPM_SERVICE
    systemctl start $PHP_FPM_SERVICE
    log_info "PHP installed."
}

# ── Install MariaDB ──
install_mariadb() {
    log_step "Installing MariaDB..."

    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_INSTALL mariadb-server mariadb-client
    else
        $PKG_INSTALL mariadb-server mariadb
    fi

    systemctl enable mariadb
    systemctl start mariadb

    MYSQL_ROOT_PASS=$(generate_password)

    # Secure installation
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';" 2>/dev/null || true
    mysql -u root -p"${MYSQL_ROOT_PASS}" -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
    mysql -u root -p"${MYSQL_ROOT_PASS}" -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
    mysql -u root -p"${MYSQL_ROOT_PASS}" -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    log_info "MariaDB installed. Root password: ${MYSQL_ROOT_PASS}"
}

# ── Create Panelion database ──
setup_database() {
    log_step "Setting up Panelion database..."

    PANEL_DB_PASS=$(generate_password)

    mysql -u root -p"${MYSQL_ROOT_PASS}" -e "
        CREATE DATABASE IF NOT EXISTS \`${PANEL_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${PANEL_DB_USER}'@'localhost' IDENTIFIED BY '${PANEL_DB_PASS}';
        GRANT ALL PRIVILEGES ON \`${PANEL_DB}\`.* TO '${PANEL_DB_USER}'@'localhost';
        FLUSH PRIVILEGES;
    "

    # Import schema
    if [ -f "${PANELION_DIR}/database/schema.sql" ]; then
        mysql -u root -p"${MYSQL_ROOT_PASS}" "${PANEL_DB}" < "${PANELION_DIR}/database/schema.sql"
        log_info "Database schema imported."
    fi

    # Update admin password
    ADMIN_PASS=$(generate_password)
    ADMIN_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT, ['cost' => 12]);")
    mysql -u root -p"${MYSQL_ROOT_PASS}" "${PANEL_DB}" -e "
        UPDATE users SET password = '${ADMIN_HASH}' WHERE username = 'admin';
    " 2>/dev/null || true

    log_info "Panel database created."
}

# ── Install optional services ──
install_optional_services() {
    log_step "Installing optional services..."

    # BIND DNS
    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_INSTALL bind9 bind9-utils 2>/dev/null || true
    else
        $PKG_INSTALL bind bind-utils 2>/dev/null || true
    fi

    # Postfix + Dovecot
    if [ "$OS_FAMILY" = "debian" ]; then
        DEBIAN_FRONTEND=noninteractive $PKG_INSTALL postfix dovecot-imapd dovecot-pop3d 2>/dev/null || true
    else
        $PKG_INSTALL postfix dovecot 2>/dev/null || true
    fi

    # vsftpd
    $PKG_INSTALL vsftpd 2>/dev/null || true

    # Certbot
    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_INSTALL certbot python3-certbot-nginx 2>/dev/null || true
    else
        $PKG_INSTALL certbot python3-certbot-nginx 2>/dev/null || true
    fi

    # Fail2ban
    $PKG_INSTALL fail2ban 2>/dev/null || true

    # Firewall
    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_INSTALL ufw 2>/dev/null || true
    fi

    # Redis
    if [ "$OS_FAMILY" = "debian" ]; then
        $PKG_INSTALL redis-server 2>/dev/null || true
    else
        $PKG_INSTALL redis 2>/dev/null || true
    fi

    # Roundcube Webmail
    log_info "Installing Roundcube Webmail..."
    if [ "$OS_FAMILY" = "debian" ]; then
        DEBIAN_FRONTEND=noninteractive $PKG_INSTALL roundcube roundcube-plugins roundcube-mysql 2>/dev/null || true
    else
        $PKG_INSTALL roundcubemail 2>/dev/null || true
    fi

    # Configure Roundcube Nginx server block
    if [ -d /etc/nginx/conf.d ]; then
        cat > /etc/nginx/conf.d/roundcube.conf << 'RCNGINX_EOF'
server {
    listen 2096 ssl;
    server_name _;

    ssl_certificate /etc/ssl/panelion/server.crt;
    ssl_certificate_key /etc/ssl/panelion/server.key;

    root /usr/share/roundcube;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
    location ~ ^/(config|temp|logs)/ { deny all; }
}
RCNGINX_EOF
    fi

    # Update Panelion settings with Roundcube URL
    mysql -u root -p"${MYSQL_ROOT_PASS}" ${PANEL_DB} -e \
        "INSERT INTO settings (setting_key, setting_value, setting_group) VALUES ('roundcube_url', 'https://\$(hostname -I | awk \"{print \\\$1}\"):2096', 'email') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);" 2>/dev/null || true

    log_info "Optional services installed."
}

# ── Deploy Panelion files ──
deploy_panelion() {
    log_step "Deploying Panelion..."

    # Create panelion user
    id -u $PANELION_USER &>/dev/null || useradd -r -d $PANELION_DIR -s /usr/sbin/nologin $PANELION_USER

    # Create directories
    mkdir -p $PANELION_DIR
    mkdir -p /var/panelion/backups
    mkdir -p /var/log/panelion

    # Deploy from GitHub or local source
    SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
    if [ -d "${SCRIPT_DIR}/core" ]; then
        log_info "Deploying from local source..."
        cp -a "${SCRIPT_DIR}/." "${PANELION_DIR}/"
    else
        log_info "Cloning from GitHub..."
        if ! command -v git &>/dev/null; then
            if [ "$PKG_MANAGER" = "apt" ]; then
                apt-get install -y git
            else
                yum install -y git
            fi
        fi
        git clone "${GITHUB_REPO}" "${PANELION_DIR}"
    fi

    # Create storage directories
    mkdir -p ${PANELION_DIR}/storage/{logs,cache,sessions,backups,users}

    # Set permissions
    chown -R ${PANELION_USER}:${PANELION_USER} ${PANELION_DIR}
    chown -R ${PANELION_USER}:${PANELION_USER} /var/panelion
    chown -R ${PANELION_USER}:${PANELION_USER} /var/log/panelion
    chmod -R 750 ${PANELION_DIR}
    chmod -R 770 ${PANELION_DIR}/storage

    log_info "Files deployed to ${PANELION_DIR}"
}

# ── Configure Panelion ──
configure_panelion() {
    log_step "Configuring Panelion..."

    # Update database config
    CONFIG_FILE="${PANELION_DIR}/config/app.php"
    if [ -f "$CONFIG_FILE" ]; then
        sed -i "s/'host' => '127.0.0.1'/'host' => '127.0.0.1'/" "$CONFIG_FILE"
        sed -i "s/'name' => 'panelion'/'name' => '${PANEL_DB}'/" "$CONFIG_FILE"
        sed -i "s/'user' => 'panelion'/'user' => '${PANEL_DB_USER}'/" "$CONFIG_FILE"
        sed -i "s/'pass' => ''/'pass' => '${PANEL_DB_PASS}'/" "$CONFIG_FILE"
    fi

    log_info "Configuration updated."
}

# ── Configure Nginx for Panelion ──
configure_nginx() {
    log_step "Configuring Nginx for Panelion..."

    PHP_SOCK="${PHP_FPM_SOCK:-/run/php/php8.2-fpm.sock}"

    cat > /etc/nginx/sites-available/panelion.conf << NGINX_EOF
server {
    listen ${PANELION_PORT} ssl http2;
    listen [::]:${PANELION_PORT} ssl http2;
    server_name _;

    root ${PANELION_DIR}/public;
    index index.php;

    # SSL - self-signed initially
    ssl_certificate /etc/ssl/panelion/panelion.crt;
    ssl_certificate_key /etc/ssl/panelion/panelion.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logs
    access_log /var/log/nginx/panelion-access.log;
    error_log /var/log/nginx/panelion-error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(env|git|htaccess|htpasswd) {
        deny all;
    }

    location ~ ^/(core|config|modules|views|storage|database)/ {
        deny all;
    }

    client_max_body_size 512M;
}
NGINX_EOF

    # Enable site
    if [ "$OS_FAMILY" = "debian" ]; then
        ln -sf /etc/nginx/sites-available/panelion.conf /etc/nginx/sites-enabled/
        rm -f /etc/nginx/sites-enabled/default
    else
        cp /etc/nginx/sites-available/panelion.conf /etc/nginx/conf.d/panelion.conf
    fi

    # Generate self-signed SSL certificate
    mkdir -p /etc/ssl/panelion
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/ssl/panelion/panelion.key \
        -out /etc/ssl/panelion/panelion.crt \
        -subj "/C=US/ST=State/L=City/O=Panelion/CN=$(hostname)" 2>/dev/null

    # Test and reload Nginx
    nginx -t && systemctl reload nginx

    log_info "Nginx configured on port ${PANELION_PORT}."
}

# ── Configure Firewall ──
configure_firewall() {
    log_step "Configuring firewall..."

    if command -v ufw &>/dev/null; then
        ufw allow 22/tcp    # SSH
        ufw allow 80/tcp    # HTTP
        ufw allow 443/tcp   # HTTPS
        ufw allow ${PANELION_PORT}/tcp  # Panelion
        ufw allow 21/tcp    # FTP
        ufw allow 25/tcp    # SMTP
        ufw allow 587/tcp   # SMTP TLS
        ufw allow 993/tcp   # IMAPS
        ufw allow 995/tcp   # POP3S
        ufw allow 53        # DNS
        ufw --force enable
    elif command -v firewall-cmd &>/dev/null; then
        firewall-cmd --permanent --add-port=22/tcp
        firewall-cmd --permanent --add-port=80/tcp
        firewall-cmd --permanent --add-port=443/tcp
        firewall-cmd --permanent --add-port=${PANELION_PORT}/tcp
        firewall-cmd --permanent --add-port=21/tcp
        firewall-cmd --permanent --add-port=25/tcp
        firewall-cmd --permanent --add-port=587/tcp
        firewall-cmd --permanent --add-port=993/tcp
        firewall-cmd --permanent --add-port=995/tcp
        firewall-cmd --permanent --add-port=53/tcp
        firewall-cmd --permanent --add-port=53/udp
        firewall-cmd --reload
    fi

    log_info "Firewall configured."
}

# ── Configure sudoers ──
configure_sudoers() {
    log_step "Configuring sudo permissions..."

    cat > /etc/sudoers.d/panelion << 'SUDOERS_EOF'
# Panelion Panel sudo permissions
panelion ALL=(ALL) NOPASSWD: /usr/sbin/useradd, /usr/sbin/userdel, /usr/sbin/usermod
panelion ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /usr/bin/mkdir
panelion ALL=(ALL) NOPASSWD: /usr/bin/systemctl start *, /usr/bin/systemctl stop *, /usr/bin/systemctl restart *, /usr/bin/systemctl reload *, /usr/bin/systemctl is-active *
panelion ALL=(ALL) NOPASSWD: /usr/bin/certbot
panelion ALL=(ALL) NOPASSWD: /usr/sbin/nginx, /usr/sbin/apache2ctl
panelion ALL=(ALL) NOPASSWD: /usr/bin/mysql, /usr/bin/mysqldump, /usr/bin/pg_dump
panelion ALL=(ALL) NOPASSWD: /usr/bin/crontab
panelion ALL=(ALL) NOPASSWD: /usr/sbin/ufw, /usr/bin/firewall-cmd
panelion ALL=(ALL) NOPASSWD: /usr/bin/chpasswd
panelion ALL=(ALL) NOPASSWD: /usr/bin/setquota
panelion ALL=(ALL) NOPASSWD: /usr/bin/kill
panelion ALL=(ALL) NOPASSWD: /usr/bin/rsync
panelion ALL=(ALL) NOPASSWD: /usr/sbin/rndc
SUDOERS_EOF

    chmod 440 /etc/sudoers.d/panelion
    log_info "Sudo permissions configured."
}

# ── Create backup script ──
create_backup_script() {
    log_step "Creating backup helper script..."

    mkdir -p /usr/local/panelion/scripts
    cat > /usr/local/panelion/scripts/backup.sh << 'BACKUP_EOF'
#!/bin/bash
# Panelion automated backup script
# Usage: backup.sh <username> <type> <retention_days>

USERNAME="$1"
TYPE="${2:-full}"
RETENTION="${3:-7}"
BACKUP_DIR="/var/panelion/backups/${USERNAME}"
TIMESTAMP=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_FILE="${BACKUP_DIR}/${USERNAME}_${TYPE}_${TIMESTAMP}.tar.gz"

mkdir -p "$BACKUP_DIR"

TMP_DIR=$(mktemp -d /tmp/panelion_backup_XXXXXX)

if [ "$TYPE" = "full" ] || [ "$TYPE" = "files" ]; then
    [ -d "/home/${USERNAME}" ] && cp -a "/home/${USERNAME}" "${TMP_DIR}/files"
fi

if [ "$TYPE" = "full" ] || [ "$TYPE" = "databases" ]; then
    mkdir -p "${TMP_DIR}/databases"
    mysql -u root -e "SHOW DATABASES" 2>/dev/null | grep "^${USERNAME}_" | while read db; do
        mysqldump --single-transaction "$db" > "${TMP_DIR}/databases/${db}.sql" 2>/dev/null
    done
fi

cd "$TMP_DIR" && tar -czf "$BACKUP_FILE" . 2>/dev/null
rm -rf "$TMP_DIR"

# Cleanup old backups
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +${RETENTION} -delete 2>/dev/null

echo "Backup completed: $BACKUP_FILE"
BACKUP_EOF

    chmod +x /usr/local/panelion/scripts/backup.sh
    log_info "Backup script created."
}

# ── Print summary ──
print_summary() {
    SERVER_IP=$(hostname -I | awk '{print $1}')

    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              Panelion Installation Complete!                ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${BLUE}Panel URL:${NC}        https://${SERVER_IP}:${PANELION_PORT}"
    echo -e "  ${BLUE}Admin Username:${NC}   admin"
    echo -e "  ${BLUE}Admin Password:${NC}   ${ADMIN_PASS}"
    echo ""
    echo -e "  ${BLUE}MySQL Root Pass:${NC}  ${MYSQL_ROOT_PASS}"
    echo -e "  ${BLUE}Panel DB User:${NC}    ${PANEL_DB_USER}"
    echo -e "  ${BLUE}Panel DB Pass:${NC}    ${PANEL_DB_PASS}"
    echo ""
    echo -e "  ${BLUE}Install Dir:${NC}      ${PANELION_DIR}"
    echo -e "  ${BLUE}Backup Dir:${NC}       /var/panelion/backups"
    echo -e "  ${BLUE}Log Dir:${NC}          ${PANELION_DIR}/storage/logs"
    echo ""
    echo -e "  ${YELLOW}⚠ Save these credentials securely! They won't be shown again.${NC}"
    echo -e "  ${YELLOW}⚠ The SSL certificate is self-signed. Replace with Let's Encrypt.${NC}"
    echo ""

    # Save credentials to file
    cat > /root/.panelion_credentials << CREDS_EOF
# Panelion Installation Credentials
# Created: $(date)
# DELETE THIS FILE AFTER NOTING DOWN THE CREDENTIALS

Panel URL:        https://${SERVER_IP}:${PANELION_PORT}
Admin Username:   admin
Admin Password:   ${ADMIN_PASS}

MySQL Root:       ${MYSQL_ROOT_PASS}
Panel DB User:    ${PANEL_DB_USER}
Panel DB Pass:    ${PANEL_DB_PASS}
CREDS_EOF
    chmod 600 /root/.panelion_credentials
    echo -e "  ${GREEN}Credentials saved to /root/.panelion_credentials${NC}"
    echo ""
}

# ── Main ──
main() {
    show_banner
    detect_os

    echo ""
    echo "This will install Panelion and all required services."
    echo "Supported: Nginx, PHP 8.2, MariaDB, BIND, Postfix, Dovecot, vsftpd, Certbot, Roundcube"
    echo ""
    read -p "Continue with installation? (y/N): " CONFIRM
    if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
        echo "Installation cancelled."
        exit 0
    fi

    install_base
    install_nginx
    install_php
    install_mariadb
    deploy_panelion
    setup_database
    configure_panelion
    install_optional_services
    configure_nginx
    configure_firewall
    configure_sudoers
    create_backup_script

    print_summary
}

main "$@"
