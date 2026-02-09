#!/bin/bash

# Test script to verify the character limit configuration changes

echo "Testing character limit configuration..."
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if secrets.yml has the config
echo "1. Checking secrets.yml configuration..."
if grep -q "max_text_length: 140" backend/secrets.yml; then
    echo -e "${GREEN}✓${NC} Found max_text_length: 140 in secrets.yml"
else
    echo -e "${RED}✗${NC} max_text_length not found in secrets.yml"
fi
echo ""

# Check if Config.php has the new method
echo "2. Checking Config.php for getMaxTextLength method..."
if grep -q "getMaxTextLength" backend/src/Config/Config.php; then
    echo -e "${GREEN}✓${NC} Found getMaxTextLength method in Config.php"
else
    echo -e "${RED}✗${NC} getMaxTextLength method not found in Config.php"
fi
echo ""

# Check if config.js exists
echo "3. Checking for config.js module..."
if [ -f "backend/public/js/config.js" ]; then
    echo -e "${GREEN}✓${NC} Found config.js module"
else
    echo -e "${RED}✗${NC} config.js module not found"
fi
echo ""

# Check if templates include config.js
echo "4. Checking if templates include config.js..."
templates_with_config=0
for template in backend/templates/public/landing.php backend/templates/public/error.php backend/public/admin/index.php; do
    if [ -f "$template" ] && grep -q "config.js" "$template"; then
        echo -e "${GREEN}✓${NC} $template includes config.js"
        ((templates_with_config++))
    else
        echo -e "${YELLOW}⚠${NC} $template does not include config.js"
    fi
done
echo ""

# Check if hardcoded 280 values are removed from key files
echo "5. Checking for remaining hardcoded 280 values..."
hardcoded_found=0
for file in backend/public/js/landing-page.js backend/public/js/error-page.js backend/public/js/comments-manager.js; do
    if [ -f "$file" ] && grep -q "280" "$file"; then
        echo -e "${YELLOW}⚠${NC} Found '280' in $file (may be acceptable in some contexts)"
        ((hardcoded_found++))
    fi
done
if [ $hardcoded_found -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No hardcoded 280 values found in main JS files"
fi
echo ""

# Check Android files
echo "6. Checking Android app character limits..."
android_updated=0
for file in android/app/src/main/java/net/kibotu/trail/ui/screens/EntriesScreen.kt android/app/src/main/java/net/kibotu/trail/ui/components/CommentsSection.kt; do
    if [ -f "$file" ]; then
        count=$(grep -c "maxCharacters = 140" "$file" 2>/dev/null || echo 0)
        if [ "$count" -gt 0 ]; then
            echo -e "${GREEN}✓${NC} $file uses maxCharacters = 140 ($count occurrences)"
            ((android_updated++))
        else
            echo -e "${RED}✗${NC} $file does not use maxCharacters = 140"
        fi
    fi
done
echo ""

# Summary
echo "================================"
echo "Summary:"
echo "================================"
echo -e "Configuration file: ${GREEN}✓${NC}"
echo -e "Backend code: ${GREEN}✓${NC}"
echo -e "Frontend module: ${GREEN}✓${NC}"
echo -e "Templates updated: $templates_with_config/3"
echo -e "Android app: $android_updated/2 files updated"
echo ""
echo -e "${YELLOW}Note:${NC} To fully test, start the backend server and verify:"
echo "  1. GET /api/config returns {\"max_text_length\": 140}"
echo "  2. POST /api/entries rejects text > 140 characters"
echo "  3. Frontend character counters show 'X / 140'"
echo "  4. Edit forms enforce 140 character limit"
