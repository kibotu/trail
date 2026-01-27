#!/bin/bash
# Trail Comprehensive Test & Security Script
# Combines deployment checks, security tests, and API tests

set +e  # Don't exit on error - we want to count failures

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$SCRIPT_DIR/backend"
SECRETS_FILE="$BACKEND_DIR/secrets.yml"

# Test counters
TOTAL_TESTS=0
PASSED=0
FAILED=0
WARNINGS=0

# Default server URL
SERVER_URL="${1:-https://trail.services.kibotu.net}"

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Trail Comprehensive Test Suite      ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""
echo -e "Server: ${CYAN}$SERVER_URL${NC}"
echo ""

# ============================================
# SECTION 1: DEPLOYMENT READINESS CHECKS
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   1. Deployment Readiness              ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Check secrets.yml
echo -n "Checking secrets.yml... "
if [ -f "$SECRETS_FILE" ]; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} (not found)"
    FAILED=$((FAILED + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# Check composer
echo -n "Checking Composer... "
if command -v composer &> /dev/null; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} (not installed)"
    FAILED=$((FAILED + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# Check vendor directory
echo -n "Checking vendor/ directory... "
if [ -d "$BACKEND_DIR/vendor" ]; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${YELLOW}⚠${NC} (not found - run composer install)"
    WARNINGS=$((WARNINGS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# Check lftp
echo -n "Checking lftp client... "
if command -v lftp &> /dev/null; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${YELLOW}⚠${NC} (not installed)"
    WARNINGS=$((WARNINGS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

echo ""

# ============================================
# SECTION 2: LOCAL SECURITY CHECKS
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   2. Local Security Configuration      ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Check public .htaccess
echo -n "Checking public/.htaccess... "
if [ -f "$BACKEND_DIR/public/.htaccess" ]; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} (missing)"
    FAILED=$((FAILED + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# Check security headers in .htaccess
if [ -f "$BACKEND_DIR/public/.htaccess" ]; then
    echo -n "Checking X-Frame-Options... "
    if grep -q "X-Frame-Options" "$BACKEND_DIR/public/.htaccess"; then
        echo -e "${GREEN}✓${NC}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${YELLOW}⚠${NC} (missing)"
        WARNINGS=$((WARNINGS + 1))
    fi
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -n "Checking X-Content-Type-Options... "
    if grep -q "X-Content-Type-Options" "$BACKEND_DIR/public/.htaccess"; then
        echo -e "${GREEN}✓${NC}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${YELLOW}⚠${NC} (missing)"
        WARNINGS=$((WARNINGS + 1))
    fi
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -n "Checking directory listing protection... "
    if grep -q "Options -Indexes" "$BACKEND_DIR/public/.htaccess"; then
        echo -e "${GREEN}✓${NC}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${YELLOW}⚠${NC} (missing)"
        WARNINGS=$((WARNINGS + 1))
    fi
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
fi

echo ""

# ============================================
# SECTION 3: SERVER CONNECTIVITY
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   3. Server Connectivity               ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Check if curl is available
if ! command -v curl &> /dev/null; then
    echo -e "${RED}✗ Error: curl not found${NC}"
    echo -e "${YELLOW}  Install: brew install curl${NC}"
    exit 1
fi

# Test server is reachable
echo -n "Testing server connectivity... "
if curl -s -f --max-time 10 "$SERVER_URL/" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} (server not reachable)"
    FAILED=$((FAILED + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

echo ""

# ============================================
# SECTION 4: DIRECTORY ACCESS PROTECTION
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   4. Directory Access Protection       ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

test_url() {
    local url="$1"
    local expected_code="$2"
    local description="$3"
    
    echo -n "Testing: $description... "
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$url" --max-time 10 2>/dev/null || echo "000")
    
    if [ "$http_code" = "$expected_code" ]; then
        echo -e "${GREEN}✓${NC} ($http_code)"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo -e "${RED}✗${NC} (Expected: $expected_code, Got: $http_code)"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

# Test sensitive directories
test_url "$SERVER_URL/vendor/" "403" "vendor/ blocked"
test_url "$SERVER_URL/src/" "403" "src/ blocked"
test_url "$SERVER_URL/templates/" "403" "templates/ blocked"

echo ""

# ============================================
# SECTION 5: SENSITIVE FILE PROTECTION
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   5. Sensitive File Protection         ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

test_url "$SERVER_URL/secrets.yml" "403" "secrets.yml blocked"
test_url "$SERVER_URL/.git" "403" ".git blocked"
test_url "$SERVER_URL/.gitignore" "403" ".gitignore blocked"
test_url "$SERVER_URL/composer.json" "403" "composer.json blocked"
test_url "$SERVER_URL/composer.lock" "403" "composer.lock blocked"
test_url "$SERVER_URL/.htaccess" "403" ".htaccess blocked"
test_url "$SERVER_URL/phpunit.xml" "403" "phpunit.xml blocked"

echo ""

# ============================================
# SECTION 6: BACKUP FILE PROTECTION
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   6. Backup File Protection            ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

test_url "$SERVER_URL/config.php.bak" "403" ".bak files blocked"
test_url "$SERVER_URL/index.php.old" "403" ".old files blocked"
test_url "$SERVER_URL/database.sql" "403" ".sql files blocked"
test_url "$SERVER_URL/error.log" "403" ".log files blocked"

echo ""

# ============================================
# SECTION 7: PUBLIC ENDPOINTS
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   7. Public Endpoints                  ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

# Homepage redirects to /api, so accept both 200 and 302
echo -n "Testing: Homepage accessible... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))
http_code=$(curl -s -o /dev/null -w "%{http_code}" "$SERVER_URL/" --max-time 10 2>/dev/null || echo "000")
if [ "$http_code" = "200" ] || [ "$http_code" = "302" ]; then
    echo -e "${GREEN}✓${NC} ($http_code)"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} (Expected: 200 or 302, Got: $http_code)"
    FAILED=$((FAILED + 1))
fi
test_url "$SERVER_URL/api" "200" "API docs accessible"
test_url "$SERVER_URL/admin/login.php" "200" "Admin login accessible"
test_url "$SERVER_URL/api/health" "200" "Health check endpoint"
test_url "$SERVER_URL/api/rss" "200" "RSS feed accessible"

echo ""

# ============================================
# SECTION 8: SECURITY HEADERS
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   8. Security Headers                  ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

test_header() {
    local url="$1"
    local header_name="$2"
    local description="$3"
    
    echo -n "Testing: $description... "
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    header_value=$(curl -s -I "$url" --max-time 10 2>/dev/null | grep -i "^$header_name:" | head -1 | cut -d' ' -f2- | tr -d '\r\n')
    
    if [ -n "$header_value" ]; then
        echo -e "${GREEN}✓${NC}"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo -e "${YELLOW}⚠${NC} (not found)"
        WARNINGS=$((WARNINGS + 1))
        return 1
    fi
}

test_header "$SERVER_URL/" "X-Frame-Options" "X-Frame-Options"
test_header "$SERVER_URL/" "X-Content-Type-Options" "X-Content-Type-Options"
test_header "$SERVER_URL/" "X-XSS-Protection" "X-XSS-Protection"
test_header "$SERVER_URL/" "Content-Security-Policy" "Content-Security-Policy"
test_header "$SERVER_URL/" "Referrer-Policy" "Referrer-Policy"

echo ""

# ============================================
# SECTION 9: INFORMATION LEAKAGE
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   9. Information Leakage               ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

echo -n "Testing: X-Powered-By hidden... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))
powered_by=$(curl -s -I "$SERVER_URL/" --max-time 10 2>/dev/null | grep -i "^X-Powered-By:" | head -1)
if [ -z "$powered_by" ]; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${YELLOW}⚠${NC} ($powered_by)"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# ============================================
# SECTION 10: HTTPS CONFIGURATION
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   10. HTTPS Configuration              ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

if [[ "$SERVER_URL" == https://* ]]; then
    echo -n "Testing: HTTPS enabled... "
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    if curl -s -I "$SERVER_URL/" --max-time 10 2>/dev/null | grep -q "HTTP"; then
        echo -e "${GREEN}✓${NC}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗${NC}"
        FAILED=$((FAILED + 1))
    fi
    
    echo -n "Testing: HTTP to HTTPS redirect... "
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    http_url="${SERVER_URL/https:/http:}"
    redirect_location=$(curl -s -I "$http_url/" --max-time 10 2>/dev/null | grep -i "^Location:" | head -1 | tr -d '\r\n')
    if [[ "$redirect_location" == *"https://"* ]]; then
        echo -e "${GREEN}✓${NC}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${YELLOW}⚠${NC}"
        WARNINGS=$((WARNINGS + 1))
    fi
else
    echo -e "${YELLOW}⚠${NC} Testing HTTP server (HTTPS recommended)"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# ============================================
# SECTION 11: ATTACK VECTOR PROTECTION
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   11. Attack Vector Protection         ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

echo -n "Testing: Directory traversal... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))
http_code=$(curl -s -o /dev/null -w "%{http_code}" "$SERVER_URL/../../../etc/passwd" --max-time 10 2>/dev/null || echo "000")
if [ "$http_code" = "403" ] || [ "$http_code" = "404" ]; then
    echo -e "${GREEN}✓${NC} (Blocked: $http_code)"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} (Not blocked: $http_code)"
    FAILED=$((FAILED + 1))
fi

echo -n "Testing: Directory listing disabled... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))
response=$(curl -s "$SERVER_URL/public/" --max-time 10 2>/dev/null || echo "")
if [[ "$response" == *"Index of"* ]] || [[ "$response" == *"Directory listing"* ]]; then
    echo -e "${RED}✗${NC}"
    FAILED=$((FAILED + 1))
else
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
fi

echo ""

# ============================================
# SECTION 12: API AUTHENTICATION
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   12. API Authentication               ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

echo -n "Testing: API requires auth... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))
api_response=$(curl -s "$SERVER_URL/api/entries" --max-time 10 2>/dev/null || echo "")
if [[ "$api_response" == *"unauthorized"* ]] || [[ "$api_response" == *"authentication"* ]] || [[ "$api_response" == *"token"* ]]; then
    echo -e "${GREEN}✓${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "${YELLOW}⚠${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# ============================================
# FINAL SUMMARY
# ============================================
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Test Results Summary                 ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo ""

echo -e "Total Tests: ${CYAN}$TOTAL_TESTS${NC}"
echo -e "Passed:      ${GREEN}$PASSED${NC}"
echo -e "Failed:      ${RED}$FAILED${NC}"
echo -e "Warnings:    ${YELLOW}$WARNINGS${NC}"
echo ""

# Calculate score
if [ $TOTAL_TESTS -gt 0 ]; then
    SCORE=$((PASSED * 100 / TOTAL_TESTS))
    echo -e "Security Score: ${CYAN}$SCORE%${NC}"
    echo ""
fi

# Final verdict
if [ $FAILED -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
        echo -e "${GREEN}║   All Tests Passed! ✓                  ║${NC}"
        echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
        echo ""
        echo -e "${GREEN}Your server is properly configured!${NC}"
        exit 0
    else
        echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
        echo -e "${YELLOW}║   Tests Passed with Warnings           ║${NC}"
        echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
        echo ""
        echo -e "${YELLOW}Review warnings above for improvements${NC}"
        exit 0
    fi
else
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo -e "${RED}║   Tests Failed ✗                       ║${NC}"
    echo -e "${RED}╔════════════════════════════════════════╗${NC}"
    echo ""
    echo -e "${RED}Critical issues found!${NC}"
    echo -e "${YELLOW}Fix failed tests before production deployment${NC}"
    echo ""
    echo -e "Recommendations:"
    echo -e "1. Ensure all .htaccess files are uploaded"
    echo -e "2. Check Apache configuration"
    echo -e "3. Verify mod_rewrite is enabled"
    echo -e "4. Review server error logs"
    echo ""
    exit 1
fi
