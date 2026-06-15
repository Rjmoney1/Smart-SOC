# Installation Guide

## Prerequisites

| Requirement | Version |
|-------------|---------|
| Ubuntu      | 22.04 LTS (recommended) |
| PHP         | 8.2+ |
| MySQL       | 8.0+ |
| Python      | 3.10+ |
| Apache      | 2.4+ |

---

## Method 1: Docker (Fastest)

```bash
# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER && newgrp docker

# Clone project
git clone https://github.com/yourusername/cyber-ai-platform.git
cd cyber-ai-platform

# Setup environment
cp .env .env.backup
nano .env
# Set: GEMINI_API_KEY, DB_PASS, MAIL_*, TELEGRAM_*

# Start containers
cd docker
docker compose up -d

# Verify
docker compose ps
docker compose logs web
```

Access: `http://localhost`

---

## Method 2: Manual Installation on Ubuntu

### 1. System Dependencies

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
    php8.2-curl php8.2-json php8.2-mbstring php8.2-xml php8.2-zip

# Apache
sudo apt install -y apache2
sudo a2enmod rewrite php8.2
sudo systemctl enable apache2 && sudo systemctl start apache2

# MySQL
sudo apt install -y mysql-server
sudo systemctl enable mysql && sudo systemctl start mysql

# Python
sudo apt install -y python3.11 python3.11-venv python3-pip
```

### 2. MySQL Setup

```bash
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p << 'EOF'
CREATE DATABASE cyber_ai_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cyberai'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON cyber_ai_platform.* TO 'cyberai'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import schema and seed data
mysql -u cyberai -p cyber_ai_platform < database/schema.sql
mysql -u cyberai -p cyber_ai_platform < database/seed.sql
```

### 3. PHP Application Setup

```bash
# Clone to web root
sudo git clone https://github.com/yourusername/cyber-ai-platform.git /var/www/html/cyberai
cd /var/www/html/cyberai

# Configure environment
cp .env.example .env
sudo nano .env
# Fill in: DB_HOST, DB_USER, DB_PASS, DB_NAME, GEMINI_API_KEY, APP_URL, etc.

# Set permissions
sudo chown -R www-data:www-data /var/www/html/cyberai
sudo chmod -R 755 /var/www/html/cyberai
sudo chmod -R 777 /var/www/html/cyberai/logs
```

### 4. Apache Virtual Host

```bash
sudo nano /etc/apache2/sites-available/cyberai.conf
```

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/cyberai
    
    <Directory /var/www/html/cyberai>
        AllowOverride All
        Options -Indexes +FollowSymLinks
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/cyberai_error.log
    CustomLog ${APACHE_LOG_DIR}/cyberai_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite cyberai.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### 5. Python Engine Setup

```bash
cd /var/www/html/cyberai/python-engine

# Create virtual environment
python3.11 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Test the engine
python scheduler.py

# Run as systemd service (production)
sudo nano /etc/systemd/system/cyberai-engine.service
```

```ini
[Unit]
Description=CyberAI Python Security Engine
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/cyberai/python-engine
ExecStart=/var/www/html/cyberai/python-engine/venv/bin/python scheduler.py
Restart=always
RestartSec=10
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable cyberai-engine
sudo systemctl start cyberai-engine
sudo systemctl status cyberai-engine
```

### 6. Packet Sniffer (requires root)

```bash
# For packet sniffing, run separately as root
sudo /var/www/html/cyberai/python-engine/venv/bin/python \
    /var/www/html/cyberai/python-engine/packet_sniffer.py
```

---

## Post-Installation

1. Visit `http://your-server/dashboard/login.php`
2. Login with `admin` / `Admin@123`
3. Go to **Settings** → Add your Gemini API key
4. Configure email/Telegram alerts if needed
5. **Change all default passwords immediately**

---

## Gemini API Setup

1. Visit: https://makersuite.google.com/app/apikey
2. Click "Create API Key"
3. Copy the key
4. In platform: Settings → Gemini API Key → paste key → Test → Save

---

## Firewall Configuration

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

---

## SSL with Let's Encrypt

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d your-domain.com
sudo systemctl reload apache2
```
