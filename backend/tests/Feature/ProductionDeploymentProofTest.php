<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionDeploymentProofTest extends TestCase
{
    protected string $deployPath;
    protected string $customerAppPath;
    protected string $vendorAppPath;
    protected string $riderAppPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deployPath = base_path('../deploy');
        $this->customerAppPath = 'C:/Users/USER/Downloads/customerAPP';
        $this->vendorAppPath = 'C:/Users/USER/Downloads/vendorAPP';
        $this->riderAppPath = 'C:/Users/USER/Downloads/riderAPP';
    }

    /**
     * PROOF 1: Production Deployment Bundle Completeness & Structure
     */
    public function test_proof_deployment_bundle_files_exist_and_readable(): void
    {
        $expectedFiles = [
            $this->deployPath . '/production.env.example',
            $this->deployPath . '/nginx/victoriousmarket.conf',
            $this->deployPath . '/supervisor/victoriousmarket-workers.conf',
            $this->deployPath . '/scripts/deploy.sh',
            $this->deployPath . '/PRODUCTION_DEPLOYMENT_MANUAL.md',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists($file, "Required deployment artifact [{$file}] must exist.");
            $this->assertGreaterThan(0, filesize($file), "Deployment artifact [{$file}] cannot be empty.");
        }
    }

    /**
     * PROOF 2: Production Environment Variables Validation
     */
    public function test_proof_production_env_contains_all_mandatory_bank_grade_keys(): void
    {
        $envFile = $this->deployPath . '/production.env.example';
        $content = File::get($envFile);

        $requiredKeys = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'APP_DEBUG',
            'APP_URL',
            'API_URL',
            'MERCHANT_PORTAL_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'CACHE_DRIVER',
            'FILESYSTEM_DISK',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
            'REDIS_HOST',
            'REDIS_PORT',
            'REDIS_DB',
            'REDIS_CACHE_DB',
            'REDIS_QUEUE_DB',
            'PAYSTACK_PUBLIC_KEY',
            'PAYSTACK_SECRET_KEY',
            'PAYSTACK_WEBHOOK_SECRET',
            'TERMII_API_KEY',
            'TERMII_SENDER_ID',
            'FIREBASE_PROJECT_ID',
            'FIREBASE_CREDENTIALS_PATH',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_BUCKET',
            'AWS_ENDPOINT',
            'AWS_URL',
            'TURNSTILE_SITE_KEY',
            'TURNSTILE_SECRET_KEY',
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'GOOGLE_REDIRECT_URI',
            'BACKUP_ENCRYPTION_KEY',
            'BACKUP_ARCHIVE_PASSWORD',
            'GOOGLE_DRIVE_BACKUP_FOLDER_ID',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertMatchesRegularExpression(
                "/^{$key}=/m",
                $content,
                "production.env.example must define mandatory key [{$key}]."
            );
        }

        // Verify production security values
        $this->assertStringContainsString('APP_ENV=production', $content);
        $this->assertStringContainsString('APP_DEBUG=false', $content);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $content);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $content);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $content);
        $this->assertStringContainsString('TERMII_SENDER_ID=VMARKET', $content);
    }

    /**
     * PROOF 3: Nginx Gateway Hardening Directives
     */
    public function test_proof_nginx_configuration_satisfies_all_security_standards(): void
    {
        $confFile = $this->deployPath . '/nginx/victoriousmarket.conf';
        $content = File::get($confFile);

        // Domain verification
        $this->assertStringContainsString('server_name victoriousmarket.com.ng api.victoriousmarket.com.ng shop.victoriousmarket.com.ng;', $content);
        
        // HTTP to HTTPS redirection
        $this->assertStringContainsString('return 301 https://$host$request_uri;', $content);

        // SSL Protocols & HTTP/2
        $this->assertStringContainsString('listen 443 ssl http2;', $content);
        $this->assertStringContainsString('ssl_protocols TLSv1.2 TLSv1.3;', $content);
        $this->assertStringContainsString('ssl_prefer_server_ciphers on;', $content);

        // Security headers
        $this->assertStringContainsString('add_header X-Frame-Options "SAMEORIGIN"', $content);
        $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff"', $content);
        $this->assertStringContainsString('add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"', $content);

        // Body size for product and inspection photos
        $this->assertStringContainsString('client_max_body_size 15M;', $content);

        // FastCGI PHP 8.3 FPM socket and buffer tuning
        $this->assertStringContainsString('fastcgi_pass unix:/run/php/php8.3-fpm.sock;', $content);
        $this->assertStringContainsString('fastcgi_buffer_size 32k;', $content);
        $this->assertStringContainsString('fastcgi_buffers 16 16k;', $content);

        // Rate limiting zones & sensitive path shields
        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=general_api:10m rate=100r/m;', $content);
        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=auth_throttle:10m rate=10r/m;', $content);
        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=pay_throttle:10m rate=30r/m;', $content);
        $this->assertStringContainsString('location ~ ^/(storage/credentials/|\.env|\.git)', $content);
    }

    /**
     * PROOF 4: Supervisor Multi-Tier Background Queue Fleet
     */
    public function test_proof_supervisor_fleet_covers_all_queues_and_cron(): void
    {
        $confFile = $this->deployPath . '/supervisor/victoriousmarket-workers.conf';
        $content = File::get($confFile);

        // Tier 1: Critical payments, OTP, SMS, Webhooks
        $this->assertStringContainsString('[program:victorious-worker-high]', $content);
        $this->assertStringContainsString('--queue=high,sms,otp,webhooks', $content);
        $this->assertStringContainsString('numprocs=4', $content);
        $this->assertStringContainsString('user=www-data', $content);

        // Tier 2: Default orders and notifications
        $this->assertStringContainsString('[program:victorious-worker-default]', $content);
        $this->assertStringContainsString('--queue=default,orders,notifications', $content);

        // Tier 3: Low priority backups and maintenance
        $this->assertStringContainsString('[program:victorious-worker-low]', $content);
        $this->assertStringContainsString('--queue=backups,reports,maintenance', $content);

        // Tier 4: Background scheduler
        $this->assertStringContainsString('[program:victorious-scheduler]', $content);
        $this->assertStringContainsString('command=php /var/www/victoriousmarket/backend/artisan schedule:work', $content);
    }

    /**
     * PROOF 5: Zero-Downtime Deployment Script Logic
     */
    public function test_proof_zero_downtime_deploy_script_covers_atomic_rollout(): void
    {
        $scriptFile = $this->deployPath . '/scripts/deploy.sh';
        $content = File::get($scriptFile);

        // Robust error handling
        $this->assertStringContainsString('set -eo pipefail', $content);

        // Maintenance mode with token bypass
        $this->assertStringContainsString('php artisan down --secret=', $content);

        // Production dependency optimization
        $this->assertStringContainsString('composer install --no-dev --prefer-dist --optimize-autoloader', $content);

        // Database migration forced for production
        $this->assertStringContainsString('php artisan migrate --force', $content);

        // High-speed cache prewarming
        $this->assertStringContainsString('php artisan config:cache', $content);
        $this->assertStringContainsString('php artisan route:cache', $content);
        $this->assertStringContainsString('php artisan view:cache', $content);
        $this->assertStringContainsString('php artisan event:cache', $content);

        // Worker fleet restart
        $this->assertStringContainsString('php artisan queue:restart', $content);
        $this->assertStringContainsString('supervisorctl restart victorious-worker-high:*', $content);

        // OpCache flush via PHP-FPM reload
        $this->assertStringContainsString('systemctl reload php8.3-fpm', $content);

        // Bring application back online & health check
        $this->assertStringContainsString('php artisan up', $content);
        $this->assertStringContainsString('curl -s -o /dev/null -w "%{http_code}" "${HEALTH_URL}"', $content);
    }

    /**
     * PROOF 6: Flutter Companion Apps Dynamic Environment Injection & Base URL Parity
     */
    public function test_proof_all_three_flutter_apps_support_dart_define_api_base_url(): void
    {
        // 1. Customer App
        $customerEndpoints = $this->customerAppPath . '/lib/core/constants/api_endpoints.dart';
        $this->assertFileExists($customerEndpoints);
        $customerCode = File::get($customerEndpoints);
        $this->assertStringContainsString("String.fromEnvironment(", $customerCode);
        $this->assertStringContainsString("'API_BASE_URL'", $customerCode);

        // 2. Vendor App
        $vendorEndpoints = $this->vendorAppPath . '/lib/core/constants/api_endpoints.dart';
        $this->assertFileExists($vendorEndpoints);
        $vendorCode = File::get($vendorEndpoints);
        $this->assertStringContainsString("String.fromEnvironment(", $vendorCode);
        $this->assertStringContainsString("'API_BASE_URL'", $vendorCode);

        // 3. Rider App
        $riderEndpoints = $this->riderAppPath . '/lib/core/constants/api_endpoints.dart';
        $this->assertFileExists($riderEndpoints);
        $riderCode = File::get($riderEndpoints);
        $this->assertStringContainsString("String.fromEnvironment(", $riderCode);
        $this->assertStringContainsString("'API_BASE_URL'", $riderCode);
    }

    /**
     * PROOF 7: API Endpoint Contract Parity Between Flutter Apps and Backend Routes
     */
    public function test_proof_critical_api_endpoints_declared_in_mobile_apps_resolve_in_backend(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(function ($route) {
            return $route->uri();
        })->toArray();

        // Critical Customer App endpoints
        $this->assertContains('api/v1/auth/login', $routes);
        $this->assertContains('api/v1/auth/register', $routes);
        $this->assertContains('api/v1/customer/orders', $routes);
        $this->assertContains('api/v1/customer/orders/checkout', $routes);
        $this->assertContains('api/v1/customer/orders/verify-payment', $routes);
        $this->assertContains('api/v1/customer/orders/{order_number}', $routes);
        $this->assertContains('api/v1/customer/orders/{order_number}/confirm-delivery', $routes);
        $this->assertContains('api/v1/customer/cashback', $routes);
        $this->assertContains('api/v1/customer/cashback/summary', $routes);

        // Critical Vendor App endpoints
        $this->assertContains('api/v1/vendor/dashboard', $routes);
        $this->assertContains('api/v1/vendor/orders', $routes);
        $this->assertContains('api/v1/vendor/orders/{id}/ready-for-handover', $routes);
        $this->assertContains('api/v1/vendor/payouts', $routes);
        $this->assertContains('api/v1/vendor/bank/update', $routes);
        $this->assertContains('api/v1/vendor/products', $routes);

        // Critical Rider App endpoints
        $this->assertContains('api/v1/rider/dashboard', $routes);
        $this->assertContains('api/v1/rider/orders/active', $routes);
        $this->assertContains('api/v1/rider/orders/mark-arrived', $routes);
        $this->assertContains('api/v1/rider/orders/verify-pickup-otp', $routes);
        $this->assertContains('api/v1/rider/orders/verify-delivery-otp', $routes);
        $this->assertContains('api/v1/rider/toggle-duty', $routes);
        $this->assertContains('api/v1/rider/device-token', $routes);
    }
}
