#!/bin/bash
# Verify PHP extensions match between Docker and production

set -e

echo "================================================"
echo "PHP Extensions Verification"
echo "================================================"
echo ""

# Required extensions for the application
REQUIRED_EXTENSIONS=(
    "curl"
    "openssl"
    "mysqli"
    "pdo_mysql"
    "mbstring"
    "json"
    "gd"
    "exif"
    "zip"
    "bcmath"
    "intl"
    "opcache"
    "ctype"
    "fileinfo"
    "sodium"
)

# Recommended extensions (available on production)
RECOMMENDED_EXTENSIONS=(
    "xml"
    "dom"
    "SimpleXML"
    "iconv"
    "soap"
    "calendar"
    "gettext"
)

echo "Checking Docker PHP extensions..."
echo ""

# Function to check if extension is loaded
check_extension() {
    local ext=$1
    if docker compose exec -T backend php -m 2>/dev/null | grep -qi "^${ext}$"; then
        echo "✅ $ext"
        return 0
    else
        echo "❌ $ext (MISSING)"
        return 1
    fi
}

# Check required extensions
echo "Required Extensions:"
echo "-------------------"
missing_required=0
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! check_extension "$ext"; then
        ((missing_required++))
    fi
done

echo ""
echo "Recommended Extensions:"
echo "----------------------"
missing_recommended=0
for ext in "${RECOMMENDED_EXTENSIONS[@]}"; do
    if ! check_extension "$ext"; then
        ((missing_recommended++))
    fi
done

echo ""
echo "================================================"
echo "Summary"
echo "================================================"
echo "Required extensions missing: $missing_required"
echo "Recommended extensions missing: $missing_recommended"
echo ""

if [ $missing_required -gt 0 ]; then
    echo "❌ CRITICAL: Some required extensions are missing!"
    echo "   Update Dockerfile and rebuild: docker compose build"
    exit 1
elif [ $missing_recommended -gt 0 ]; then
    echo "⚠️  WARNING: Some recommended extensions are missing."
    echo "   Consider adding them to Dockerfile for full production parity."
    exit 0
else
    echo "✅ SUCCESS: All extensions match production!"
    exit 0
fi
