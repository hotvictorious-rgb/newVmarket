# 👑 Victorious MARKET - Shared Hosting & cPanel Deployment Blueprint

This document is the production deployment guide for hosting **Victorious MARKET (`newVmarket`)** on shared hosting environments (cPanel, DirectAdmin, Whogohost, Namecheap, Hostinger, etc.). Business-model policy is maintained separately in [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md). This guide does not certify that the retailer-model migration is complete.

---

## 1. The Core Architecture: 2-Directory Isolation Rule

On shared hosting, the web-accessible root is usually `public_html/`. 

> [!CAUTION]
> **Never upload the entire Laravel project directly into `public_html/`!**
> Doing so could expose `.env`, database credentials, and secret API keys to the public internet.

Instead, enforce the **2-Directory Isolation Rule**:

```
/home/your_cpanel_username/
├── core/                               <-- Inaccessible from browser (SECURE)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env                            <-- 100% Protected
│   └── artisan
│
└── public_html/                        <-- Browser accessible root
    ├── assets/                         <-- CSS, JS, brand icons (vm_icon.jpg)
    ├── .htaccess                       <-- Hardened rewrite & security headers
    └── index.php                       <-- Front controller pointing to ../core
```

---

## 2. Server & PHP Prerequisites

In cPanel, open **Select PHP Version** (or **MultiPHP Manager**):
1. **PHP Version:** Select **PHP 8.2** or **PHP 8.3**.
2. **Extensions Checklist:** Ensure the following extensions are enabled:
   - `opcache` *(Essential for 5x faster execution)*
   - `pdo_mysql`
   - `bcmath` *(Required for decimal-safe financial calculations; do not assume legacy marketplace allocation percentages apply to retailer transactions)*
   - `mbstring`
   - `openssl`
   - `fileinfo`
   - `ctype`
   - `curl`
   - `json`
   - `xml`

---

## 3. Database Setup in cPanel

1. Navigate to **cPanel > MySQL® Database Wizard**.
2. Create a new database: e.g. `cpaneluser_vmarket`.
3. Create a new user: e.g. `cpaneluser_dbuser` with a strong password.
4. Assign the user to the database and tick **"ALL PRIVILEGES"**.

---

## 4. Automated Packaging (One-Click)

Before uploading, use the automated packager to separate `core` and `public_html`:

### On Windows:
```powershell
.\scripts\package-shared-hosting.ps1
```

### On Linux / macOS:
```bash
bash scripts/package-shared-hosting.sh
```

This creates a `dist_shared_hosting/` folder containing:
- `core/` (ready to upload to `/home/username/core`)
- `public_html/` (ready to upload to `/home/username/public_html`)
- Pre-patched `index.php` pointing to `../core/vendor/autoload.php` and `../core/bootstrap/app.php`.

---

## 5. Production `.env` Configuration

In `/home/username/core/.env`, configure:

```ini
APP_NAME="Victorious MARKET"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_vmarket
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=YOUR_STRONG_PASSWORD

# Shared Hosting Queue, Cache & Session Settings
SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database

# Public Hotline & Support (Used by Announcement Bar)
VM_HOTLINE="09118949035"
```

---

## 6. One-Command Optimization: `vmarket:optimize-shared`

Once files are uploaded, open **cPanel Terminal** (or SSH) and navigate to `core`:

```bash
cd /home/username/core

# 1. Run database migrations and seeders
php artisan migrate --force

# 2. Run the specialized shared hosting optimizer
php artisan vmarket:optimize-shared
```

This command automatically:
- Verifies directory write permissions on `storage/` and `bootstrap/cache`.
- Validates the `jobs` database table.
- Links `storage` to `public_html/storage`.
- Compiles `config`, `routes`, and `blade views` into fast memory-mapped cache files.

---

## 7. cPanel Cron Jobs Configuration

Because shared hosting does not allow 24/7 background worker daemons, Victorious MARKET uses the **Database Queue Driver** triggered every minute by cPanel Cron.

In **cPanel > Cron Jobs**, set the schedule to **Once Per Minute (`* * * * *`)** and add:

### Job 1: Asynchronous Queue Worker (OTP dispatches, customer receipts, settlements)
```bash
* * * * * cd /home/username/core && php artisan queue:work database --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

### Job 2: Ecosystem Scheduler (7-day product freshness countdown, audit rotation)
```bash
* * * * * cd /home/username/core && php artisan schedule:run >> /dev/null 2>&1
```

*(Alternatively, use the standalone runner:* `* * * * * /usr/local/bin/php /home/username/core/cron-runner.php >> /dev/null 2>&1`*)*

---

## 8. Boost with Cloudflare CDN (Free Plan)

To make shared hosting load as fast as a dedicated enterprise server:
1. Add your domain to [Cloudflare](https://www.cloudflare.com) (Free Plan).
2. Update domain nameservers at your registrar to Cloudflare's nameservers.
3. In Cloudflare **Speed > Optimization**:
   - Enable **Auto Minify** (HTML, CSS, JS).
   - Enable **Brotli Compression**.
4. In Cloudflare **Caching > Cache Rules**:
   - Set Cache Eligibility for static paths: `/assets/*` and `/storage/*` to **Cache Everything** with an Edge TTL of **1 Month**.
5. **Result:** 70%–80% of traffic (CSS, logos, product photos) is served directly from Cloudflare's global edge nodes without consuming your shared hosting CPU.

---

## 9. Security Verification Checklist

After deploying, verify these test cases:

| Check | Expected Result |
| :--- | :--- |
| Visit `https://yourdomain.com/.env` | **403 Forbidden** or **404 Not Found** (Never shows code) |
| Visit `https://yourdomain.com/admin/login` | Renders Super Admin login with Victorious logo |
| Visit `https://yourdomain.com/customer/login` | Renders Customer login with phone/email fields |
| Verify announcement bar | Shows `Call or Whatsapp 09118949035 to order` |
| Place test order | Database queue records job; cron worker processes it within 60s |
