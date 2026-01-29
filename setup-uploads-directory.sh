#!/bin/bash
# One-time setup script to create uploads directory and .htaccess on production server
# Run this ONCE after fixing sync.sh to ensure the directory structure exists

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$SCRIPT_DIR/backend"

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Setup Uploads Directory              ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Load FTP credentials
SECRETS_FILE="$BACKEND_DIR/secrets.yml"
if [ ! -f "$SECRETS_FILE" ]; then
    echo -e "${RED}✗ Error: secrets.yml not found${NC}"
    exit 1
fi

FTP_HOST=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "host:" | awk '{print $2}')
FTP_PORT=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "port:" | awk '{print $2}')
FTP_USER=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "user:" | awk '{print $2}')
FTP_PASSWORD=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "password:" | awk '{print $2}')
FTP_REMOTE_PATH=$(grep -A 5 "^ftp:" "$SECRETS_FILE" | grep "remote_path:" | awk '{print $2}')

FTP_PORT=${FTP_PORT:-21}
FTP_REMOTE_PATH=${FTP_REMOTE_PATH:-/}

echo -e "${GREEN}✓${NC} Configuration loaded"
echo -e "  FTP Host: ${BLUE}$FTP_HOST:$FTP_PORT${NC}"
echo ""

# Check if lftp is installed
if ! command -v lftp &> /dev/null; then
    echo -e "${RED}✗ Error: lftp is not installed${NC}"
    exit 1
fi

# Create temporary lftp script
LFTP_SCRIPT=$(mktemp)
trap "rm -f $LFTP_SCRIPT" EXIT

cat > "$LFTP_SCRIPT" <<EOF
set ftp:ssl-allow no
set ssl:verify-certificate no
open -u $FTP_USER,$FTP_PASSWORD $FTP_HOST

# Navigate to remote path
cd $FTP_REMOTE_PATH/public

# Create uploads directory structure if it doesn't exist
!echo "Creating uploads directory structure..."
mkdir -p uploads
cd uploads
mkdir -p images

# Upload .htaccess for uploads directory security
lcd $BACKEND_DIR/public/uploads
put .htaccess -o .htaccess

!echo "✓ Directory structure created"
!echo "✓ Security .htaccess uploaded"

bye
EOF

echo -e "${YELLOW}Setting up uploads directory on production...${NC}"
echo ""

if lftp -f "$LFTP_SCRIPT"; then
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║   Setup Complete! ✓                    ║${NC}"
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo ""
    echo -e "  Created: ${BLUE}public/uploads/images/${NC}"
    echo -e "  Uploaded: ${BLUE}public/uploads/.htaccess${NC}"
    echo ""
    echo -e "${YELLOW}IMPORTANT:${NC}"
    echo -e "  • User uploads will now be preserved during sync"
    echo -e "  • The uploads directory is excluded from sync.sh"
    echo -e "  • Scripts cannot execute in uploads directory"
    echo ""
else
    echo ""
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo -e "${RED}║   Setup Failed ✗                       ║${NC}"
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    exit 1
fi
