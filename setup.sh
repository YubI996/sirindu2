#!/bin/bash

# Sirindu Quick Setup Script
# This script helps you quickly setup Sirindu on Ubuntu Server with Docker

set -e

echo "======================================"
echo "   Sirindu Docker Setup Script"
echo "======================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo -e "${RED}Please do not run this script as root${NC}"
    exit 1
fi

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

echo -e "${YELLOW}[1/7] Checking prerequisites...${NC}"

# Check Docker
if ! command_exists docker; then
    echo -e "${RED}Docker is not installed. Please install Docker first.${NC}"
    echo "Visit: https://docs.docker.com/engine/install/ubuntu/"
    exit 1
else
    echo -e "${GREEN}✓ Docker found: $(docker --version)${NC}"
fi

# Check Docker Compose
if ! command_exists docker-compose; then
    echo -e "${RED}Docker Compose is not installed. Please install Docker Compose first.${NC}"
    echo "Visit: https://docs.docker.com/compose/install/"
    exit 1
else
    echo -e "${GREEN}✓ Docker Compose found: $(docker-compose --version)${NC}"
fi

echo ""
echo -e "${YELLOW}[2/7] Setting up .env file...${NC}"

if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        cp .env.docker .env
        echo -e "${GREEN}✓ Created .env from .env.docker${NC}"
    else
        cp .env.example .env
        echo -e "${GREEN}✓ Created .env from .env.example${NC}"
    fi

    echo -e "${YELLOW}⚠ Please edit .env file and set:${NC}"
    echo "  - DB_PASSWORD (use strong password!)"
    echo "  - APP_URL (your server IP/domain)"
    echo ""
    read -p "Press Enter after editing .env file..."
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi

echo ""
echo -e "${YELLOW}[3/7] Setting permissions...${NC}"
chmod -R 755 storage bootstrap/cache 2>/dev/null || true
echo -e "${GREEN}✓ Permissions set${NC}"

echo ""
echo -e "${YELLOW}[4/7] Building Docker images...${NC}"
docker-compose build

echo ""
echo -e "${YELLOW}[5/7] Starting containers...${NC}"
docker-compose up -d

echo ""
echo -e "${YELLOW}[6/7] Waiting for database to be ready...${NC}"
sleep 20

echo ""
echo -e "${YELLOW}[7/7] Setting up Laravel...${NC}"

# Generate app key
echo "Generating application key..."
docker-compose exec -T app php artisan key:generate

# Run migrations
echo "Running database migrations..."
docker-compose exec -T app php artisan migrate --force

# Cache configuration
echo "Caching configuration..."
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

# Set permissions
docker-compose exec -T app chmod -R 775 storage bootstrap/cache
docker-compose exec -T app chown -R www-data:www-data storage bootstrap/cache

echo ""
echo -e "${GREEN}======================================"
echo "   Setup Complete! 🎉"
echo "======================================${NC}"
echo ""
echo "Your application is now running at:"
echo -e "${GREEN}http://localhost:8000${NC}"
echo ""
echo "phpMyAdmin (database management):"
echo -e "${GREEN}http://localhost:8080${NC}"
echo ""
echo "To view logs:"
echo "  docker-compose logs -f"
echo ""
echo "To stop services:"
echo "  docker-compose down"
echo ""
echo "For more information, see DEPLOYMENT.md"
echo ""
