#!/bin/bash

# Training Platform API - Production Deployment Script
# This script automates the deployment process for the Laravel application

set -e

echo "========================================="
echo "Training Platform API - Deployment"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if running in production environment
if [ "$APP_ENV" != "production" ]; then
    echo -e "${YELLOW}WARNING: APP_ENV is not set to production${NC}"
    echo "Please run: export APP_ENV=production"
    exit 1
fi

echo -e "${GREEN}Step 1: Installing dependencies...${NC}"
composer install --optimize-autoloader --no-dev --no-interaction

echo ""
echo -e "${GREEN}Step 2: Running database migrations...${NC}"
php artisan migrate --force --no-interaction

echo ""
echo -e "${GREEN}Step 3: Clearing and caching configurations...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo ""
echo -e "${GREEN}Step 4: Creating storage symlink...${NC}"
php artisan storage:link || echo "Storage link already exists"

echo ""
echo -e "${GREEN}Step 5: Optimizing application...${NC}"
php artisan optimize

echo ""
echo -e "${GREEN}Step 6: Setting file permissions...${NC}"
chmod -R 755 storage bootstrap/cache || true
chown -R www-data:www-data storage bootstrap/cache || true

echo ""
echo -e "${GREEN}Step 7: Running queue workers (if needed)...${NC}"
# Uncomment if using queue workers
# php artisan queue:restart

echo ""
echo "========================================="
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "========================================="
echo ""
echo "Next steps:"
echo "1. Verify the application is working: curl https://your-domain.com/api/v1/health"
echo "2. Check logs: tail -f storage/logs/laravel.log"
echo "3. Monitor queue workers: php artisan queue:work --daemon"
echo ""
