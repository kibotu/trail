#!/bin/bash
# Trail Backend FTP Sync Script
# Builds backend and uploads only production-necessary files via FTP using lftp mirror

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$SCRIPT_DIR/backend"

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Trail Backend FTP Sync Tool         ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Load configuration from secrets.yml
SECRETS_FILE="$BACKEND_DIR/secrets.yml"
if [ ! -f "$SECRETS_FILE" ]; then
    echo -e "${RED}✗ Error: secrets.yml not found${NC}"
    echo -e "${YELLOW}  Please create $SECRETS_FILE from config.yml.example${NC}"
    exit 1
fi

# Extract FTP credentials from secrets.yml
FTP_HOST=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "host:" | awk '{print $2}')
FTP_PORT=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "port:" | awk '{print $2}')
FTP_USER=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "user:" | awk '{print $2}')
FTP_PASSWORD=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "password:" | awk '{print $2}')
FTP_REMOTE_PATH=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "remote_path:" | awk '{print $2}')

# Default values
FTP_PORT=${FTP_PORT:-21}
FTP_REMOTE_PATH=${FTP_REMOTE_PATH:-/}

echo -e "${GREEN}✓${NC} Configuration loaded"
echo -e "  FTP Host: ${BLUE}$FTP_HOST:$FTP_PORT${NC}"
echo -e "  Remote Path: ${BLUE}$FTP_REMOTE_PATH${NC}"
echo ""

# Check if backend directory exists
if [ ! -d "$BACKEND_DIR" ]; then
    echo -e "${RED}✗ Error: Backend directory not found at $BACKEND_DIR${NC}"
    exit 1
fi

# Check if lftp is installed
if ! command -v lftp &> /dev/null; then
    echo -e "${RED}✗ Error: lftp is not installed${NC}"
    echo -e "${YELLOW}  Install with: brew install lftp (macOS) or apt-get install lftp (Linux)${NC}"
    exit 1
fi

cd "$BACKEND_DIR"

# Step 0: Security verification
echo -e "${YELLOW}[0/4]${NC} Verifying security configuration..."
if [ -f "$BACKEND_DIR/verify-security.sh" ]; then
    if bash "$BACKEND_DIR/verify-security.sh"; then
        echo -e "${GREEN}✓${NC} Security verification passed"
    else
        echo -e "${RED}✗${NC} Security verification failed"
        echo -e "${YELLOW}  Fix security issues before deploying${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}⚠${NC} Security verification script not found (skipping)"
fi
echo ""

# Step 1: Install/update Composer dependencies
echo -e "${YELLOW}[1/4]${NC} Installing Composer dependencies..."
if ! command -v composer &> /dev/null; then
    echo -e "${RED}✗ Error: Composer not found${NC}"
    echo -e "${YELLOW}  Please install Composer: https://getcomposer.org/${NC}"
    exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction
echo -e "${GREEN}✓${NC} Dependencies installed"
echo ""

# Step 2: Verify vendor directory
echo -e "${YELLOW}[2/3]${NC} Verifying vendor directory..."
if [ ! -d "$BACKEND_DIR/vendor" ]; then
    echo -e "${RED}✗ Error: vendor/ directory not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} vendor/ directory verified"
echo ""

# Step 3: Upload via lftp mirror
echo -e "${YELLOW}[3/3]${NC} Uploading to FTP server..."
echo -e "${BLUE}Production Structure:${NC}"
echo -e "  FTP Root (not public)"
echo -e "  ├── public/      → https://trail.services.kibotu.net/"
echo -e "  ├── src/"
echo -e "  ├── templates/"
echo -e "  └── vendor/      (pre-built, no composer on prod)"
echo ""

# Create temporary lftp script
LFTP_SCRIPT=$(mktemp)
trap "rm -f $LFTP_SCRIPT" EXIT

cat > "$LFTP_SCRIPT" <<EOF
set ftp:ssl-allow no
set ssl:verify-certificate no
open -u $FTP_USER,$FTP_PASSWORD $FTP_HOST

# Change to remote directory (FTP root)
cd $FTP_REMOTE_PATH

# Change to local backend directory
lcd $BACKEND_DIR

# Mirror (sync) only production files to remote
# Production structure:
#   FTP_ROOT/public/    -> web root (https://trail.services.kibotu.net/)
#   FTP_ROOT/src/       -> application code (not public)
#   FTP_ROOT/templates/ -> HTML templates (not public)
#   FTP_ROOT/vendor/    -> pre-built dependencies (not public)
#
# --reverse: upload (local to remote)
# --delete: remove files on remote that don't exist locally
# --verbose: show progress
# --exclude-glob: exclude development/test files
mirror --reverse --delete --verbose \\
    --exclude-glob .git/ \\
    --exclude-glob .git \\
    --exclude-glob .gitignore \\
    --exclude-glob .phpunit.cache/ \\
    --exclude-glob .phpunit.cache \\
    --exclude-glob tests/ \\
    --exclude-glob tests \\
    --exclude-glob '*/test/' \\
    --exclude-glob '*/test' \\
    --exclude-glob '*/tests/' \\
    --exclude-glob '*/tests' \\
    --exclude-glob docker/ \\
    --exclude-glob docker \\
    --exclude-glob cache/ \\
    --exclude-glob cache \\
    --exclude-glob .env \\
    --exclude-glob .env.docker \\
    --exclude-glob docker-compose.yml \\
    --exclude-glob docker-compose.prod.yml \\
    --exclude-glob Dockerfile \\
    --exclude-glob phpunit.xml \\
    --exclude-glob phpunit.xml.dist \\
    --exclude-glob Makefile \\
    --exclude-glob '*.sh' \\
    --exclude-glob .dockerignore \\
    --exclude-glob '*.example' \\
    --exclude-glob composer.json \\
    --exclude-glob composer.lock \\
    --exclude-glob public/admin/dev-login.php \\
    --exclude-glob .DS_Store \\
    --exclude-glob .sync-manifest.txt \\
    --exclude-glob .travis.yml \\
    --exclude-glob .hhconfig \\
    --exclude-glob psalm.xml \\
    --exclude-glob '*.md' \\
    --exclude-glob '*.log' \\
    --exclude-glob '*.tmp'

bye
EOF

echo -e "${BLUE}Connecting to FTP server...${NC}"
echo ""

# Execute lftp
if lftp -f "$LFTP_SCRIPT"; then
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║   Deployment Successful! 🚀            ║${NC}"
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo ""
    echo -e "  Backend URL: ${BLUE}https://trail.services.kibotu.net${NC}"
    echo ""
else
    echo ""
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo -e "${RED}║   Deployment Failed ✗                  ║${NC}"
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    exit 1
fi
