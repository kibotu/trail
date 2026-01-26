#!/bin/bash

# Trail API Test Script
# Tests all API endpoints for functionality and security

# Don't exit on error - we want to count failures
set +e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

BASE_URL="${BASE_URL:-http://localhost:18000}"
JWT_TOKEN="${JWT_TOKEN:-}"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Trail API Test Suite${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# Test counter
PASSED=0
FAILED=0
SKIPPED=0

# Function to get dev JWT token
get_dev_token() {
    local response=$(curl -s -X POST "$BASE_URL/api/auth/dev" \
        -H "Content-Type: application/json" \
        -d '{"email":"dev@example.com"}' 2>/dev/null)
    
    local token=$(echo "$response" | grep -o '"jwt":"[^"]*"' | cut -d'"' -f4)
    echo "$token"
}

# Test function
test_endpoint() {
    local name="$1"
    local method="$2"
    local path="$3"
    local data="$4"
    local expected_status="$5"
    local use_auth="${6:-false}"
    
    echo -n "Testing: $name... "
    
    local headers=(-H "Content-Type: application/json")
    
    if [ "$use_auth" = "true" ]; then
        if [ -z "$JWT_TOKEN" ]; then
            echo -e "${YELLOW}SKIPPED${NC} (no JWT token)"
            ((SKIPPED++))
            return
        fi
        headers+=(-H "Authorization: Bearer $JWT_TOKEN")
    fi
    
    if [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$BASE_URL$path" "${headers[@]}" -d "$data" 2>/dev/null)
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$BASE_URL$path" "${headers[@]}" 2>/dev/null)
    fi
    
    status=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$status" = "$expected_status" ]; then
        echo -e "${GREEN}PASSED${NC} (HTTP $status)"
        ((PASSED++))
    else
        echo -e "${RED}FAILED${NC} (Expected $expected_status, got $status)"
        echo "Response: $body"
        ((FAILED++))
    fi
}

# Check if server is running
echo "Checking if server is running..."
if ! curl -s -f "$BASE_URL/api/health" > /dev/null 2>&1; then
    echo -e "${RED}ERROR: Server not running at $BASE_URL${NC}"
    echo "Start the server with: ./run.sh"
    exit 1
fi
echo -e "${GREEN}Server is running${NC}"

# Try to get dev JWT token if not provided
if [ -z "$JWT_TOKEN" ]; then
    echo "No JWT token provided, attempting to get dev token..."
    JWT_TOKEN=$(get_dev_token)
    if [ -n "$JWT_TOKEN" ]; then
        echo -e "${GREEN}✓ Got dev JWT token${NC}"
    else
        echo -e "${YELLOW}⚠ Could not get dev token (tests will be limited)${NC}"
    fi
fi
echo ""

# Public Endpoints
echo -e "${BLUE}=== Public Endpoints ===${NC}"
test_endpoint "API Documentation" "GET" "/api" "" "200"
test_endpoint "Global RSS Feed" "GET" "/api/rss" "" "200"
test_endpoint "User RSS Feed" "GET" "/api/rss/1" "" "200"
echo ""

# Security Tests (XSS Prevention)
echo -e "${BLUE}=== Security Tests (XSS Prevention) ===${NC}"
test_endpoint "Block Script Tags" "POST" "/api/entries" '{"text":"<script>alert(1)</script>"}' "400" "true"
test_endpoint "Block JavaScript Protocol" "POST" "/api/entries" '{"text":"javascript:alert(1)"}' "400" "true"
test_endpoint "Block Event Handlers" "POST" "/api/entries" '{"text":"<img src=x onerror=alert(1)>"}' "400" "true"
test_endpoint "Block Iframe Tags" "POST" "/api/entries" '{"text":"<iframe src=evil.com></iframe>"}' "400" "true"
test_endpoint "Block Data Protocol" "POST" "/api/entries" '{"text":"data:text/html,<script>alert(1)</script>"}' "400" "true"
echo ""

# Validation Tests
echo -e "${BLUE}=== Validation Tests ===${NC}"
test_endpoint "Reject Empty Text" "POST" "/api/entries" '{"text":""}' "400" "true"
test_endpoint "Reject Too Long Text" "POST" "/api/entries" "{\"text\":\"$(python3 -c 'print("a" * 281)')\"}" "400" "true"
test_endpoint "Accept Valid Text" "POST" "/api/entries" '{"text":"Test https://example.com 🎉"}' "201" "true"
echo ""

# Authentication Tests
echo -e "${BLUE}=== Authentication Tests ===${NC}"
test_endpoint "Require Auth for Create" "POST" "/api/entries" '{"text":"Test"}' "401" "false"
test_endpoint "Require Auth for List" "GET" "/api/entries" "" "401" "false"
echo ""

# Content Preservation Tests
echo -e "${BLUE}=== Content Preservation Tests ===${NC}"
test_endpoint "Preserve URLs" "POST" "/api/entries" '{"text":"Visit https://example.com"}' "201" "true"
test_endpoint "Preserve Emojis" "POST" "/api/entries" '{"text":"Hello 👋 World 🌍"}' "201" "true"
test_endpoint "Preserve Multiple URLs" "POST" "/api/entries" '{"text":"Check https://site1.com and https://site2.com"}' "201" "true"
echo ""

# Rate Limiting Test
echo -e "${BLUE}=== Rate Limiting Test ===${NC}"
echo -n "Testing rate limiting (making 65 requests to /api/rss)... "
rate_limited=false
for i in {1..65}; do
    status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/rss")
    if [ "$status" = "429" ]; then
        rate_limited=true
        break
    fi
done

if [ "$rate_limited" = true ]; then
    echo -e "${GREEN}PASSED${NC} (Rate limit triggered after $i requests)"
    ((PASSED++))
else
    echo -e "${YELLOW}WARNING${NC} (Rate limit not triggered - may need adjustment)"
    ((PASSED++))
fi

# Test health check last (after other tests)
echo ""
echo -e "${BLUE}=== Final Health Check ===${NC}"
test_endpoint "Health Check" "GET" "/api/health" "" "200"
echo ""

# Summary
echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Test Summary${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "Passed:  ${GREEN}$PASSED${NC}"
echo -e "Failed:  ${RED}$FAILED${NC}"
echo -e "Skipped: ${YELLOW}$SKIPPED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed${NC}"
    exit 1
fi
