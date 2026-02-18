#!/bin/bash

# Sirindu SSL/HTTPS Diagnostic Script
# Run this script to diagnose SSL/HTTPS issues

echo "======================================"
echo "   Sirindu SSL Diagnostic Tool"
echo "======================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if in correct directory
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}Error: docker-compose.yml not found. Please run from project root.${NC}"
    exit 1
fi

echo -e "${BLUE}[1/10] Checking .env configuration...${NC}"
echo "---------------------------------------"

# Check APP_URL
APP_URL=$(grep "^APP_URL=" .env 2>/dev/null | cut -d '=' -f2)
APP_ENV=$(grep "^APP_ENV=" .env 2>/dev/null | cut -d '=' -f2)
APP_DEBUG=$(grep "^APP_DEBUG=" .env 2>/dev/null | cut -d '=' -f2)
ASSET_URL=$(grep "^ASSET_URL=" .env 2>/dev/null | cut -d '=' -f2)

if [ -z "$APP_URL" ]; then
    echo -e "${RED}✗ APP_URL not set in .env${NC}"
else
    if [[ $APP_URL == https://* ]]; then
        echo -e "${GREEN}✓ APP_URL: $APP_URL (HTTPS - Good!)${NC}"
    else
        echo -e "${RED}✗ APP_URL: $APP_URL (HTTP - Should be HTTPS!)${NC}"
        echo -e "${YELLOW}  Fix: Change to APP_URL=https://your-domain.com${NC}"
    fi
fi

if [ -z "$APP_ENV" ]; then
    echo -e "${RED}✗ APP_ENV not set${NC}"
elif [ "$APP_ENV" = "production" ]; then
    echo -e "${GREEN}✓ APP_ENV: $APP_ENV${NC}"
else
    echo -e "${YELLOW}⚠ APP_ENV: $APP_ENV (Should be 'production' for HTTPS to work)${NC}"
fi

if [ -z "$APP_DEBUG" ]; then
    echo -e "${YELLOW}⚠ APP_DEBUG not set${NC}"
elif [ "$APP_DEBUG" = "false" ]; then
    echo -e "${GREEN}✓ APP_DEBUG: $APP_DEBUG${NC}"
else
    echo -e "${YELLOW}⚠ APP_DEBUG: $APP_DEBUG (Should be 'false' in production)${NC}"
fi

if [ -z "$ASSET_URL" ]; then
    echo -e "${YELLOW}⚠ ASSET_URL not set (optional but recommended)${NC}"
else
    echo -e "${GREEN}✓ ASSET_URL: $ASSET_URL${NC}"
fi

echo ""
echo -e "${BLUE}[2/10] Checking Docker containers status...${NC}"
echo "---------------------------------------"
docker-compose ps

echo ""
echo -e "${BLUE}[3/10] Testing Laravel config inside container...${NC}"
echo "---------------------------------------"

# Check if container is running
if ! docker-compose ps | grep -q "app.*Up"; then
    echo -e "${RED}✗ App container is not running!${NC}"
    echo -e "${YELLOW}  Fix: Run 'docker-compose up -d'${NC}"
else
    echo -e "${GREEN}✓ App container is running${NC}"

    echo ""
    echo "Testing config('app.url'):"
    APP_URL_CONFIG=$(docker-compose exec -T app php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo config('app.url');" 2>/dev/null)

    if [[ $APP_URL_CONFIG == https://* ]]; then
        echo -e "${GREEN}✓ config('app.url') = $APP_URL_CONFIG${NC}"
    else
        echo -e "${RED}✗ config('app.url') = $APP_URL_CONFIG (Should be HTTPS!)${NC}"
        echo -e "${YELLOW}  Fix: Clear cache and rebuild${NC}"
    fi

    echo ""
    echo "Testing url('/'):"
    URL_SLASH=$(docker-compose exec -T app php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo url('/');" 2>/dev/null)

    if [[ $URL_SLASH == https://* ]]; then
        echo -e "${GREEN}✓ url('/') = $URL_SLASH${NC}"
    else
        echo -e "${RED}✗ url('/') = $URL_SLASH (Should be HTTPS!)${NC}"
    fi

    echo ""
    echo "Testing route('login'):"
    ROUTE_LOGIN=$(docker-compose exec -T app php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo route('login');" 2>/dev/null)

    if [[ $ROUTE_LOGIN == https://* ]]; then
        echo -e "${GREEN}✓ route('login') = $ROUTE_LOGIN${NC}"
    else
        echo -e "${RED}✗ route('login') = $ROUTE_LOGIN (Should be HTTPS!)${NC}"
    fi
fi

echo ""
echo -e "${BLUE}[4/10] Checking cached config files...${NC}"
echo "---------------------------------------"

if docker-compose exec -T app test -f bootstrap/cache/config.php; then
    echo -e "${YELLOW}⚠ Config cache exists: bootstrap/cache/config.php${NC}"
    echo -e "  Last modified: $(docker-compose exec -T app stat -c %y bootstrap/cache/config.php 2>/dev/null)"
    echo -e "${YELLOW}  Recommendation: Clear and rebuild cache${NC}"
else
    echo -e "${GREEN}✓ No config cache (or not found)${NC}"
fi

if docker-compose exec -T app test -f bootstrap/cache/routes-v7.php; then
    echo -e "${YELLOW}⚠ Route cache exists: bootstrap/cache/routes-v7.php${NC}"
    echo -e "  Last modified: $(docker-compose exec -T app stat -c %y bootstrap/cache/routes-v7.php 2>/dev/null)"
else
    echo -e "${GREEN}✓ No route cache (or not found)${NC}"
fi

echo ""
echo -e "${BLUE}[5/10] Checking TrustProxies middleware...${NC}"
echo "---------------------------------------"

PROXIES=$(grep "protected \$proxies" app/Http/Middleware/TrustProxies.php 2>/dev/null | head -1)
if echo "$PROXIES" | grep -q "\*"; then
    echo -e "${GREEN}✓ TrustProxies set to trust all proxies ('*')${NC}"
else
    echo -e "${YELLOW}⚠ TrustProxies: $PROXIES${NC}"
    echo -e "${YELLOW}  Recommendation: Set to '*' to trust all proxies${NC}"
fi

echo ""
echo -e "${BLUE}[6/10] Checking AppServiceProvider boot() method...${NC}"
echo "---------------------------------------"

if grep -q "forceScheme('https')" app/Providers/AppServiceProvider.php; then
    echo -e "${GREEN}✓ HTTPS force scheme is configured in AppServiceProvider${NC}"
    grep -A 5 "forceScheme" app/Providers/AppServiceProvider.php | head -7
else
    echo -e "${RED}✗ HTTPS force scheme NOT configured${NC}"
    echo -e "${YELLOW}  This is required when behind reverse proxy${NC}"
fi

echo ""
echo -e "${BLUE}[7/10] Testing actual HTTP response...${NC}"
echo "---------------------------------------"

if command -v curl &> /dev/null; then
    DOMAIN=$(echo $APP_URL | sed 's|https://||' | sed 's|http://||')

    echo "Fetching login page and checking form action..."
    FORM_ACTION=$(curl -Lks "https://$DOMAIN/login" 2>/dev/null | grep -o 'action="[^"]*"' | head -1)

    if [ -z "$FORM_ACTION" ]; then
        echo -e "${YELLOW}⚠ Could not fetch form action (site might be down or protected)${NC}"
    else
        echo "Form action found: $FORM_ACTION"

        if echo "$FORM_ACTION" | grep -q 'https://'; then
            echo -e "${GREEN}✓ Form action uses HTTPS${NC}"
        else
            echo -e "${RED}✗ Form action uses HTTP${NC}"
            echo -e "${YELLOW}  This is the mixed content warning issue!${NC}"
        fi
    fi
else
    echo -e "${YELLOW}⚠ curl not available, skipping HTTP test${NC}"
fi

echo ""
echo -e "${BLUE}[8/10] Checking Nginx configuration (if accessible)...${NC}"
echo "---------------------------------------"

# Check if running nginx in docker
if docker-compose ps | grep -q "nginx"; then
    echo "Docker nginx container found"
    docker-compose exec -T nginx cat /etc/nginx/conf.d/default.conf 2>/dev/null | grep -A 5 "location" | head -10
else
    echo -e "${YELLOW}⚠ Nginx not running in Docker (probably external reverse proxy)${NC}"
    echo -e "${YELLOW}  Make sure external Nginx forwards these headers:${NC}"
    echo "  - X-Forwarded-For"
    echo "  - X-Forwarded-Proto (IMPORTANT for HTTPS detection)"
    echo "  - X-Forwarded-Host"
fi

echo ""
echo -e "${BLUE}[9/10] Checking storage permissions...${NC}"
echo "---------------------------------------"

STORAGE_PERM=$(docker-compose exec -T app stat -c %a storage 2>/dev/null)
if [ "$STORAGE_PERM" = "775" ] || [ "$STORAGE_PERM" = "777" ]; then
    echo -e "${GREEN}✓ Storage directory permissions: $STORAGE_PERM${NC}"
else
    echo -e "${YELLOW}⚠ Storage directory permissions: $STORAGE_PERM${NC}"
    echo -e "${YELLOW}  Recommended: 775 or 777${NC}"
fi

echo ""
echo -e "${BLUE}[10/10] Summary & Recommendations...${NC}"
echo "======================================="

echo ""
echo -e "${YELLOW}Common Issues and Fixes:${NC}"
echo ""

echo "1. If form actions still use HTTP:"
echo "   cd /var/www/sirindu2"
echo "   docker-compose exec app php artisan config:clear"
echo "   docker-compose exec app php artisan route:clear"
echo "   docker-compose exec app php artisan view:clear"
echo "   docker-compose exec app php artisan config:cache"
echo "   docker-compose exec app php artisan route:cache"
echo "   docker-compose restart app nginx"
echo ""

echo "2. If APP_URL is wrong in .env:"
echo "   sed -i 's|APP_URL=http://|APP_URL=https://|g' .env"
echo "   # Then clear cache (see #1)"
echo ""

echo "3. If external Nginx reverse proxy is the issue:"
echo "   Add to your reverse proxy config:"
echo "   proxy_set_header X-Forwarded-Proto \$scheme;"
echo "   proxy_set_header X-Forwarded-Host \$host;"
echo ""

echo "4. Nuclear option (clear everything and rebuild):"
echo "   docker-compose down"
echo "   rm -rf storage/framework/cache/* bootstrap/cache/*.php"
echo "   docker-compose up -d"
echo "   sleep 15"
echo "   docker-compose exec app php artisan config:cache"
echo "   docker-compose exec app php artisan route:cache"
echo ""

echo -e "${GREEN}Diagnostic complete!${NC}"
echo ""
echo "For detailed troubleshooting, see: SSL_TROUBLESHOOTING.md"
