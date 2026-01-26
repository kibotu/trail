#!/bin/bash

# Trail Service - Simple Setup & Update Script
# Usage: ./run.sh

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
DB_NAME="d0459744"
DB_USER="d0459744"
DB_PASSWORD="Mo+nb!_R33wMTt,.f)OZ"

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     Trail Service - Setup Script      ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Check if .env exists
if [ ! -f "../.env" ]; then
    echo -e "${RED}✗ Error: .env file not found${NC}"
    echo -e "${YELLOW}  Creating from .env.example...${NC}"
    cp ../.env.example ../.env
    echo -e "${YELLOW}  Please edit .env with your credentials and run again${NC}"
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}✗ Error: Docker is not running${NC}"
    echo -e "${YELLOW}  Please start Docker Desktop and try again${NC}"
    exit 1
fi

echo -e "${CYAN}📦 Starting Docker services...${NC}"
docker compose up -d

echo ""
echo -e "${CYAN}⏳ Waiting for services to be healthy...${NC}"
sleep 5

# Install Composer dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo ""
    echo -e "${CYAN}📦 Installing Composer dependencies...${NC}"
    docker compose exec web composer install --no-interaction
    echo -e "${GREEN}✓ Dependencies installed${NC}"
fi

# Wait for database to be ready
MAX_TRIES=30
TRIES=0
while ! docker compose exec -T db mysqladmin ping -h localhost -u root -p"${DB_PASSWORD}" > /dev/null 2>&1; do
    TRIES=$((TRIES+1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo -e "${RED}✗ Database failed to start${NC}"
        exit 1
    fi
    echo -e "${YELLOW}  Waiting for database... ($TRIES/$MAX_TRIES)${NC}"
    sleep 2
done

echo -e "${GREEN}✓ Database is ready${NC}"
echo ""

# Check if sessions table exists
echo -e "${CYAN}🔍 Checking database schema...${NC}"
SESSIONS_EXISTS=$(docker compose exec -T db mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SHOW TABLES LIKE 'trail_sessions';" 2>/dev/null || echo "")

if [ -z "$SESSIONS_EXISTS" ]; then
    echo -e "${YELLOW}⚙️  Running database migrations...${NC}"
    
    # Run initial schema if needed
    USERS_EXISTS=$(docker compose exec -T db mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SHOW TABLES LIKE 'trail_users';" 2>/dev/null || echo "")
    if [ -z "$USERS_EXISTS" ]; then
        echo -e "${YELLOW}  → Running 001_initial_schema.sql${NC}"
        docker compose exec -T db mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < ../migrations/001_initial_schema.sql 2>/dev/null || true
    fi
    
    # Run sessions migration
    echo -e "${YELLOW}  → Running 002_add_sessions_table.sql${NC}"
    docker compose exec -T db mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < ../migrations/002_add_sessions_table.sql 2>/dev/null || true
    
    echo -e "${GREEN}✓ Migrations completed${NC}"
else
    echo -e "${GREEN}✓ Database schema is up to date${NC}"
fi

echo ""

# Check for admin users
echo -e "${CYAN}👤 Checking for admin users...${NC}"
ADMIN_COUNT=$(docker compose exec -T db mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM trail_users WHERE is_admin = 1;" 2>/dev/null || echo "0")

if [ "$ADMIN_COUNT" -eq "0" ]; then
    echo -e "${YELLOW}⚠️  No admin users found${NC}"
    echo -e "${YELLOW}  To make yourself admin, run:${NC}"
    echo -e "${CYAN}  docker compose exec db mysql -u $DB_USER -p'$DB_PASSWORD' $DB_NAME -e \"UPDATE trail_users SET is_admin = 1 WHERE email = 'your@email.com';\"${NC}"
else
    echo -e "${GREEN}✓ Found $ADMIN_COUNT admin user(s)${NC}"
fi

echo ""

# Check file permissions
echo -e "${CYAN}🔐 Checking file permissions...${NC}"
docker compose exec web chown -R www-data:www-data /var/www/html/public/admin/ 2>/dev/null || true
docker compose exec web chown -R www-data:www-data /var/www/html/public/helpers/ 2>/dev/null || true
echo -e "${GREEN}✓ Permissions set${NC}"

echo ""

# Show service status
echo -e "${CYAN}📊 Service Status:${NC}"
docker compose ps

echo ""
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║         🎉 Setup Complete! 🎉         ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}📱 Access URLs:${NC}"
echo ""
echo -e "  ${GREEN}🌐 Backend API:${NC}"
echo -e "     ${BLUE}http://localhost:18000${NC}"
echo ""
echo -e "  ${GREEN}🔐 Admin Login:${NC}"
echo -e "     ${BLUE}http://localhost:18000/admin/login.php${NC}"
echo ""
echo -e "  ${GREEN}📊 Admin Dashboard:${NC}"
echo -e "     ${BLUE}http://localhost:18000/admin/${NC}"
echo ""
echo -e "  ${GREEN}🗄️  phpMyAdmin:${NC}"
echo -e "     ${BLUE}http://localhost:18080${NC}"
echo -e "     User: ${CYAN}$DB_USER${NC}"
echo -e "     Pass: ${CYAN}$DB_PASSWORD${NC}"
echo ""
echo -e "  ${GREEN}🗃️  MariaDB Direct:${NC}"
echo -e "     ${BLUE}localhost:13306${NC}"
echo -e "     Database: ${CYAN}$DB_NAME${NC}"
echo ""
echo -e "  ${GREEN}📡 RSS Feeds:${NC}"
echo -e "     ${BLUE}http://localhost:18000/rss${NC} (all entries)"
echo -e "     ${BLUE}http://localhost:18000/rss/{user_id}${NC} (per user)"
echo ""
echo -e "${YELLOW}📝 Quick Commands:${NC}"
echo ""
echo -e "  ${CYAN}# View logs${NC}"
echo -e "  docker compose logs -f web"
echo ""
echo -e "  ${CYAN}# Restart services${NC}"
echo -e "  docker compose restart"
echo ""
echo -e "  ${CYAN}# Stop services${NC}"
echo -e "  docker compose down"
echo ""
echo -e "  ${CYAN}# Make user admin${NC}"
echo -e "  docker compose exec db mysql -u $DB_USER -p'$DB_PASSWORD' $DB_NAME -e \"UPDATE trail_users SET is_admin = 1 WHERE email = 'your@email.com';\""
echo ""
echo -e "${GREEN}✨ Ready to use!${NC}"
echo ""
