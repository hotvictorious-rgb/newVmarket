> **Active policy notice (22 October 2026):** Current business behaviour is governed by [Active Business Rules](https://github.com/hotvictorious-rgb/newVmarket/blob/main/docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). Older implementation findings, proposed workflows and historical decisions remain for traceability but do not override current policy. Supplier locations may be anywhere within approved Uyo LGA; pre-payment checks are non-binding; verified Paystack payment precedes binding supplier allocation; pickup is a support-managed exception only; and logistics-company payables use configured company/corridor rates plus bulky surcharges, never the retired 90% formula. Documentation-only update; runtime conformity is not certified.

# VICTORIOUS MARKET — PRODUCTION LAUNCH & INFRASTRUCTURE MANUAL
**Brand Promise:** Your Trusted Online Market for Quality Products.  
**Operating Philosophy:** Trust First. Quality Always. Accountability at Every Stage.

---

## Master Architecture Overview

Victorious Market is an end-to-end, multi-sided Nigerian digital commerce ecosystem comprising:
1. **The Laravel 11 Financial Core & API Gateway (`newVmarket`)**:
   - Manages the dual-ledger accounting system, Paystack Escrow holding & disbursement, 5-stage doorstep handshakes, dynamic delivery fee matrices, and Super Admin Command Tower.
2. **Customer Mobile Application (`customerAPP`)**:
   - Shopper storefront, order checkout, real-time OTP delivery verification, and doorstep returns/claims.
3. **Vendor Merchant Application (`vendorAPP`)**:
   - Inventory catalog, product dispatch handover, financial ledger, and bank account settlement withdrawals.
4. **Logistics & Rider Application (`riderAPP`)**:
   - Active corridor delivery manifests, merchant pickup verifications, OTP doorstep customer handovers, and wallet payouts.

---

## 1. Cloud VPS & Server Provisioning

### Recommended Server Specifications
- **Operating System:** Ubuntu 24.04 LTS (x64)
- **Compute:** 4 vCPU / 8 GB RAM (DigitalOcean Droplet, AWS EC2 t4g.xlarge, or Linode)
- **Storage:** 80 GB NVMe SSD + Cloudflare R2 Object Storage for product images
- **Network:** 1 Gbps with static public IP address

### Step 1: Base System & PHP 8.3 Installation
Connect via SSH to your server as `root` or `sudo` user:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip ufw redis-server supervisor certbot python3-certbot-nginx

# Add Ondřej Surý PHP PPA for PHP 8.3
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and all required extensions
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-gd php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis php8.3-sqlite3
```

### Step 2: Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 3: Install & Secure MySQL 8.0
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Create production database and user
sudo mysql -u root -p
```
Run within MySQL console:
```sql
CREATE DATABASE victorious_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'victorious_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON victorious_prod.* TO 'victorious_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 2. Code Deployment & Directory Setup

```bash
# Create application root directory
sudo mkdir -p /var/www/victoriousmarket
sudo chown -R $USER:www-data /var/www/victoriousmarket

# Clone repository
git clone https://github.com/hotvictorious-rgb/newVmarket.git /var/www/victoriousmarket

# Navigate to backend
cd /var/www/victoriousmarket/backend

# Configure production environment
cp ../deploy/production.env.example .env
nano .env   # Fill in production database, Paystack live keys, Termii API key

# Install dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Set storage and bootstrap permissions
sudo chown -R www-data:www-data /var/www/victoriousmarket/backend/storage /var/www/victoriousmarket/backend/bootstrap/cache
sudo chmod -R 775 /var/www/victoriousmarket/backend/storage /var/www/victoriousmarket/backend/bootstrap/cache

# Run database migrations and seed core reference data
php artisan migrate --force
php artisan db:seed --class=LegalDocumentSeeder --force
```

---

## 3. Nginx & SSL Configuration

```bash
# Copy Nginx server block
sudo cp /var/www/victoriousmarket/deploy/nginx/victoriousmarket.conf /etc/nginx/sites-available/victoriousmarket.conf

# Enable site
sudo ln -s /etc/nginx/sites-available/victoriousmarket.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Obtain Let's Encrypt Wildcard / Multi-Domain SSL Certificate
sudo certbot --nginx -d victoriousmarket.com.ng -d api.victoriousmarket.com.ng -d shop.victoriousmarket.com.ng

# Restart Nginx
sudo systemctl restart nginx
```

---

## 4. Supervisor Queue Workers & Background Scheduler

```bash
# Copy Supervisor configuration
sudo cp /var/www/victoriousmarket/deploy/supervisor/victoriousmarket-workers.conf /etc/supervisor/conf.d/victoriousmarket-workers.conf

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all

# Check status of workers
sudo supervisorctl status
```

---

## 5. Live Third-Party Gateway Switchover Checklist

### Paystack Live Activation
1. Complete Corporate KYC on Paystack Dashboard (CAC Documents, Corporate Bank Account, Directors ID).
2. Toggle dashboard from **Test Mode** to **Live Mode**.
3. Obtain:
   - `PAYSTACK_PUBLIC_KEY` (starts with `pk_live_`)
   - `PAYSTACK_SECRET_KEY` (starts with `sk_live_`)
4. Register Webhook URL:
   - URL: `https://api.victoriousmarket.com.ng/api/v1/payments/paystack/webhook`
   - Enable events: `charge.success`, `transfer.success`, `transfer.failed`, `transfer.reversed`.
   - Copy Webhook Secret Hash to `PAYSTACK_WEBHOOK_SECRET` in `.env`.
5. Enable **Transfers** in Paystack Dashboard under Settings > Transfers to allow automated merchant & rider wallet payouts.

### Termii Live SMS & Sender ID
1. Top up Termii Live Naira Wallet.
2. Request alphanumeric Sender ID: `VMARKET`.
3. Set default route to **DND-Priority / Corporate Route** so OTPs deliver instantly to customers on MTN, Airtel, Glo, and 9mobile regardless of DND status.
4. Set `TERMII_API_KEY` and `TERMII_SENDER_ID=VMARKET` in `.env`.

### Firebase Cloud Messaging (FCM Push)
1. In Firebase Console, create project `victorious-market-prod`.
2. Generate a Service Account Private Key:
   - Go to Project Settings > Service Accounts > Generate New Private Key.
   - Save the JSON file on the server as `/var/www/victoriousmarket/backend/storage/credentials/firebase-service-account.json`.
   - Set ownership: `sudo chown www-data:www-data /var/www/victoriousmarket/backend/storage/credentials/firebase-service-account.json`.

---

## 6. Mobile Application Release Build Commands

All 3 companion mobile applications support dynamic environment injection via `--dart-define=API_BASE_URL=...`.

### A. Customer App (`customerAPP`)
```bash
cd c:\Users\USER\Downloads\customerAPP

# Clean and fetch packages
flutter clean && flutter pub get

# Build Production Android App Bundle (.aab for Google Play Store)
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.victoriousmarket.com.ng/api/v1

# Build Standalone Production APK (.apk for Direct Download / Testing)
flutter build apk --release --dart-define=API_BASE_URL=https://api.victoriousmarket.com.ng/api/v1
```

### B. Vendor Merchant App (`vendorAPP`)
```bash
cd c:\Users\USER\Downloads\vendorAPP

# Clean and fetch packages
flutter clean && flutter pub get

# Build Production Android App Bundle (.aab for Google Play Store)
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.victoriousmarket.com.ng/api/v1

# Build Standalone Production APK (.apk for Direct Download / Testing)
flutter build apk --release --dart-define=API_BASE_URL=https://api.victoriousmarket.com.ng/api/v1
```

### C. Logistics Rider App (`riderAPP`)
```bash
cd c:\Users\USER\Downloads\riderAPP

# Clean and fetch packages
flutter clean && flutter pub get

# Build Production Android App Bundle (.aab for Google Play Store)
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.victoriousmarket.com.ng/api/v1

# Build Standalone Production APK (.apk for Direct Download / Testing)
flutter build apk --release --dart-define=API_BASE_URL=https://api.victoriousmarket.com.ng/api/v1
```

---

## 7. Zero-Downtime Deployment Workflow

To roll out subsequent updates and bug fixes smoothly:
```bash
# Make script executable
chmod +x /var/www/victoriousmarket/deploy/scripts/deploy.sh

# Run automated deployment
/var/www/victoriousmarket/deploy/scripts/deploy.sh
```
This script handles:
1. Graceful maintenance mode activation.
2. Git synchronization.
3. Composer optimized autoloader compilation.
4. Database migrations (`php artisan migrate --force`).
5. Route, Config, View, and Event cache prewarming.
6. Worker restarts (`php artisan queue:restart` and `supervisorctl`).
7. PHP-FPM OpCache reload.
8. Application maintenance mode deactivation and automated health-check curl probe.

---

## 8. Disaster Recovery & Off-Site Backup Integrity Drill

Victorious Market features built-in AES-256 encrypted database dumps and automated Google Drive synchronization:
```bash
# Run manual full backup with SHA-256 verification
php artisan backup:run

# Verify backup archive integrity
php artisan backup:verify
```