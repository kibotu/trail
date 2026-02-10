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

# Extract app base URL from secrets.yml
APP_BASE_URL=$(grep -A 5 "^app:" "$SECRETS_FILE" | grep "base_url:" | awk '{print $2}')
if [ -z "$APP_BASE_URL" ]; then
    echo -e "${RED}✗ Error: base_url not found in secrets.yml${NC}"
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
echo -e "  Base URL: ${BLUE}$APP_BASE_URL${NC}"
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
echo -e "  ├── public/      → $APP_BASE_URL/"
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
#   FTP_ROOT/public/     -> web root ($APP_BASE_URL/)
#   FTP_ROOT/src/        -> application code (not public)
#   FTP_ROOT/templates/  -> HTML templates (not public)
#   FTP_ROOT/vendor/     -> pre-built dependencies (not public)
#   FTP_ROOT/../migrations/ -> database migrations (not public, parent dir)
#
# --reverse: upload (local to remote)
# --delete: remove files on remote that don't exist locally
# --verbose: show progress
# --exclude-glob: exclude development/test files
# CRITICAL: Exclude user uploads directory to prevent data loss!
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
    --exclude-glob cache/ \\
    --exclude-glob cache \\
    --exclude-glob storage/ \\
    --exclude-glob storage \\
    --exclude-glob public/uploads/images/ \\
    --exclude-glob public/uploads/images \\
    --exclude-glob 'public/uploads/images/*' \\
    --exclude-glob .env \\
    --exclude-glob phpunit.xml \\
    --exclude-glob phpunit.xml.dist \\
    --exclude-glob Makefile \\
    --exclude-glob '*.sh' \\
    --exclude-glob '*.example' \\
    --exclude-glob composer.json \\
    --exclude-glob composer.lock \\
    --exclude-glob public/admin/dev-login.php \\
    --exclude-glob public/run-migrations-temp.php \\
    --exclude-glob .DS_Store \\
    --exclude-glob .sync-manifest.txt \\
    --exclude-glob .travis.yml \\
    --exclude-glob .hhconfig \\
    --exclude-glob psalm.xml \\
    --exclude-glob '*.md' \\
    --exclude-glob '*.log' \\
    --exclude-glob '*.tmp'

# Upload migrations directory (from project root to FTP root parent level)
lcd $SCRIPT_DIR
mirror --reverse --verbose migrations

bye
EOF

echo -e "${BLUE}Connecting to FTP server...${NC}"
echo ""

# Execute lftp
if lftp -f "$LFTP_SCRIPT"; then
    echo ""
    echo -e "${GREEN}✓${NC} Files uploaded successfully"
    echo ""
else
    echo ""
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo -e "${RED}║   Deployment Failed ✗                  ║${NC}"
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    exit 1
fi

# Step 4: Run migrations
echo -e "${YELLOW}[4/4]${NC} Running database migrations..."

# Extract database credentials from secrets.yml
DB_HOST=$(grep -A 10 "^database:" "$SECRETS_FILE" | grep "host:" | awk '{print $2}')
DB_PORT=$(grep -A 10 "^database:" "$SECRETS_FILE" | grep "port:" | awk '{print $2}')
DB_NAME=$(grep -A 10 "^database:" "$SECRETS_FILE" | grep "name:" | awk '{print $2}')
DB_USER=$(grep -A 10 "^database:" "$SECRETS_FILE" | grep "user:" | awk '{print $2}')
DB_PASSWORD=$(grep -A 10 "^database:" "$SECRETS_FILE" | grep "password:" | awk '{print $2}' | tr -d '"')

# Default values
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}

# Check for pending migrations
MIGRATIONS_DIR="$SCRIPT_DIR/migrations"
if [ ! -d "$MIGRATIONS_DIR" ]; then
    echo -e "${YELLOW}⚠${NC} No migrations directory found (skipping)"
else
    # Create temporary migration runner script
    MIGRATION_RUNNER="$BACKEND_DIR/public/run-migrations-temp.php"
    cat > "$MIGRATION_RUNNER" <<'PHPEOF'
<?php
// Temporary migration runner - auto-generated by sync.sh
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Trail\Config\Config;
use Trail\Database\Database;

try {
    $config = Config::load(__DIR__ . '/../secrets.yml');
    $db = Database::getInstance($config);
    
    // Enable buffered queries to prevent "unbuffered queries" errors
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    // Get applied migrations
    $stmt = $db->query("SELECT migration_name FROM trail_migrations ORDER BY applied_at");
    $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all migration files (try multiple possible locations)
    $possiblePaths = [
        __DIR__ . '/../../migrations',           // FTP root parallel to public
        __DIR__ . '/../migrations',              // Backend root
        dirname(dirname(__DIR__)) . '/migrations' // Absolute from public
    ];
    
    $migrationsDir = null;
    foreach ($possiblePaths as $path) {
        if (is_dir($path)) {
            $migrationsDir = $path;
            break;
        }
    }
    
    if ($migrationsDir === null) {
        echo "✗ Migrations directory not found. Tried:\n";
        foreach ($possiblePaths as $path) {
            echo "  - $path\n";
        }
        exit(1);
    }
    
    echo "Using migrations directory: $migrationsDir\n";
    $migrationFiles = glob($migrationsDir . '/*.sql');
    if ($migrationFiles === false) {
        echo "✗ Failed to read migrations directory\n";
        exit(1);
    }
    sort($migrationFiles);
    
    $pendingMigrations = [];
    foreach ($migrationFiles as $file) {
        $filename = basename($file);
        if (!in_array($filename, $appliedMigrations)) {
            $pendingMigrations[] = $file;
        }
    }
    
    if (empty($pendingMigrations)) {
        echo "✓ No pending migrations\n";
        exit(0);
    }
    
    echo "Found " . count($pendingMigrations) . " pending migration(s):\n";
    
    foreach ($pendingMigrations as $file) {
        $filename = basename($file);
        echo "  → Running: $filename\n";
        
        $sql = file_get_contents($file);
        
        // Remove comment lines first
        $lines = explode("\n", $sql);
        $cleanedLines = array_filter($lines, function($line) {
            $trimmed = trim($line);
            return !empty($trimmed) && !preg_match('/^--/', $trimmed);
        });
        $cleanedSql = implode("\n", $cleanedLines);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $cleanedSql)),
            function($stmt) {
                return !empty($stmt);
            }
        );
        
        // Check if migration contains DDL statements (CREATE, ALTER, DROP, TRUNCATE)
        // DDL causes implicit commits in MySQL, so we can't use transactions
        $containsDDL = preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE)\s+/im', $cleanedSql);
        
        if (!$containsDDL) {
            $db->beginTransaction();
        }
        
        try {
            $stmtCount = 0;
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        // Use query() for SELECT statements to properly consume results
                        if (preg_match('/^\s*SELECT\s+/i', $statement)) {
                            $result = $db->query($statement);
                            $result->fetchAll(); // Consume all results to avoid buffering issues
                            $result->closeCursor();
                        } else {
                            $db->exec($statement);
                        }
                        $stmtCount++;
                    } catch (PDOException $e) {
                        echo "      ✗ SQL Error: " . $e->getMessage() . "\n";
                        echo "      Statement: " . substr($statement, 0, 100) . "...\n";
                        throw $e;
                    }
                }
            }
            
            echo "      Executed $stmtCount SQL statement(s)\n";
            
            // Record migration as applied
            $stmt = $db->prepare("INSERT INTO trail_migrations (migration_name) VALUES (?)");
            $stmt->execute([$filename]);
            
            if (!$containsDDL) {
                $db->commit();
            }
            echo "    ✓ Applied: $filename\n";
        } catch (Exception $e) {
            if (!$containsDDL && $db->inTransaction()) {
                $db->rollBack();
            }
            echo "    ✗ Failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    echo "\n✓ All migrations completed successfully\n";
    exit(0);
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
PHPEOF

    # Upload migration runner
    MIGRATION_UPLOAD_SCRIPT=$(mktemp)
    trap "rm -f $MIGRATION_UPLOAD_SCRIPT" EXIT
    
    cat > "$MIGRATION_UPLOAD_SCRIPT" <<EOF
set ftp:ssl-allow no
set ssl:verify-certificate no
open -u $FTP_USER,$FTP_PASSWORD $FTP_HOST
cd $FTP_REMOTE_PATH/public
put $MIGRATION_RUNNER -o run-migrations-temp.php
bye
EOF
    
    lftp -f "$MIGRATION_UPLOAD_SCRIPT" > /dev/null 2>&1
    
    # Run migrations via HTTP
    echo -e "${BLUE}Executing migrations on production server...${NC}"
    MIGRATION_OUTPUT=$(curl -s "$APP_BASE_URL/run-migrations-temp.php")
    echo "$MIGRATION_OUTPUT"
    
    # Check if migrations succeeded
    if echo "$MIGRATION_OUTPUT" | grep -q "✓"; then
        echo -e "${GREEN}✓${NC} Migrations completed"
        
        # Clean up migration runner
        CLEANUP_SCRIPT=$(mktemp)
        trap "rm -f $CLEANUP_SCRIPT" EXIT
        
        cat > "$CLEANUP_SCRIPT" <<EOF
set ftp:ssl-allow no
set ssl:verify-certificate no
open -u $FTP_USER,$FTP_PASSWORD $FTP_HOST
cd $FTP_REMOTE_PATH/public
rm run-migrations-temp.php
bye
EOF
        
        lftp -f "$CLEANUP_SCRIPT" > /dev/null 2>&1
        rm -f "$MIGRATION_RUNNER"
    else
        echo -e "${RED}✗${NC} Migration failed"
        echo -e "${YELLOW}  Check the output above for details${NC}"
        exit 1
    fi
fi
echo ""

echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Deployment Successful! 🚀            ║${NC}"
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo ""
echo -e "  Backend URL: ${BLUE}$APP_BASE_URL${NC}"
echo ""
