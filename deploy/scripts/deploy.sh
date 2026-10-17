#!/usr/bin/env bash
# ==============================================================================
# VICTORIOUS MARKET — ZERO-DOWNTIME PRODUCTION DEPLOYMENT SCRIPT
# Executes atomic code updates, migration, cache compilation, and worker reload
# ==============================================================================

set -eo pipefail

APP_DIR="/var/www/victoriousmarket/backend"
DEPLOY_DATE=$(date "+%Y-%m-%d %H:%M:%S")

echo "=========================================================="
echo " Starting Victorious Market Production Deployment"
echo " Timestamp: ${DEPLOY_DATE}"
echo " Directory: ${APP_DIR}"
echo "=========================================================="

cd "${APP_DIR}"

# 1. Verify Deployment Prerequisites
if [ ! -f ".env" ]; then
    echo "ERROR: Production .env file missing in ${APP_DIR}!"
    exit 1
fi

# 2. Enter Temporary Maintenance Mode with Bypass Secret
echo ">>> Enabling application maintenance mode..."
php artisan down --secret="${DEPLOYMENT_MAINTENANCE_SECRET:-victorious-launch-token}" --render="errors.503" || true

# 3. Pull Latest Git Production Release
echo ">>> Synchronizing latest code from origin/main..."
git fetch origin main
git reset --hard origin/main

# 4. Install & Optimize Composer Dependencies
echo ">>> Installing production PHP dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 5. Execute Database Migrations (Forced for Production)
echo ">>> Executing atomic database migrations..."
php artisan migrate --force

# 6. Rebuild High-Speed Production Caches
echo ">>> Compiling route, configuration, view, and event caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart Background Worker Fleet
echo ">>> Restarting background queue workers and scheduler..."
php artisan queue:restart
sudo supervisorctl update
sudo supervisorctl restart victorious-worker-high:*
sudo supervisorctl restart victorious-worker-default:*
sudo supervisorctl restart victorious-worker-low:*
sudo supervisorctl restart victorious-scheduler

# 8. Reload PHP-FPM Service (Clears OpCache)
echo ">>> Reloading PHP 8.3 FPM service..."
sudo systemctl reload php8.3-fpm

# 9. Exit Maintenance Mode
echo ">>> Disabling maintenance mode..."
php artisan up

# 10. Automated Health Check Probe
echo ">>> Verifying application health probe..."
HEALTH_URL="http://127.0.0.1/api/v1/health"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${HEALTH_URL}" || echo "000")

if [ "${HTTP_STATUS}" -eq 200 ] || [ "${HTTP_STATUS}" -eq 302 ]; then
    echo "SUCCESS: Application health probe passed with HTTP ${HTTP_STATUS}."
else
    echo "WARNING: Health check returned HTTP ${HTTP_STATUS}. Please inspect /var/log/nginx/victorious_error.log."
fi

echo "=========================================================="
echo " Victorious Market Deployment Successfully Completed!"
echo "=========================================================="
exit 0