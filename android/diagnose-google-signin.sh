#!/bin/bash

echo "================================"
echo "Google Sign-In Diagnostics"
echo "================================"
echo ""

# Check google-services.json
echo "1. Checking google-services.json configuration..."
echo "---"

if [ -f "app/google-services.json" ]; then
    echo "✓ google-services.json found"
    
    # Check for Android OAuth client (client_type: 1)
    ANDROID_CLIENT=$(grep -A 2 '"client_type": 1' app/google-services.json)
    if [ -n "$ANDROID_CLIENT" ]; then
        echo "✓ Android OAuth client (type 1) found"
        echo ""
        echo "Android OAuth client details:"
        grep -A 5 '"client_type": 1' app/google-services.json | grep -E 'client_id|package_name|certificate_hash'
    else
        echo "❌ PROBLEM: No Android OAuth client (type 1) found!"
        echo ""
        echo "Your google-services.json only has:"
        grep -B 2 '"client_type"' app/google-services.json | grep 'client_type'
        echo ""
        echo "⚠️  This is why sign-in fails - you need an Android OAuth client!"
    fi
    
    echo ""
    
    # Check for Web OAuth client (client_type: 3)
    WEB_CLIENT=$(grep -A 2 '"client_type": 3' app/google-services.json | grep 'client_id' | head -1)
    if [ -n "$WEB_CLIENT" ]; then
        echo "✓ Web OAuth client (type 3) found"
        echo "$WEB_CLIENT"
    fi
else
    echo "❌ google-services.json not found!"
fi

echo ""
echo "2. Checking package name..."
echo "---"
PACKAGE_NAME=$(grep 'package_name' app/google-services.json | head -1 | sed 's/.*: "\(.*\)".*/\1/')
echo "Package name: $PACKAGE_NAME"
if [ "$PACKAGE_NAME" = "net.kibotu.trail" ]; then
    echo "✓ Package name is correct"
else
    echo "❌ Package name mismatch!"
fi

echo ""
echo "3. Checking SHA-1 fingerprints..."
echo "---"

# Debug keystore
DEBUG_KEYSTORE="app/certificates/debug.jks"
if [ -f "$DEBUG_KEYSTORE" ]; then
    echo "Debug keystore SHA-1:"
    keytool -list -v -keystore "$DEBUG_KEYSTORE" -alias debug -storepass "d,8Qp@r]h\$(X7Xr)" 2>/dev/null | grep "SHA1:" | awk '{print $2}'
else
    echo "Debug keystore not found"
fi

# Check if SHA-1 is in google-services.json
CERT_HASH=$(grep 'certificate_hash' app/google-services.json 2>/dev/null)
if [ -n "$CERT_HASH" ]; then
    echo ""
    echo "SHA-1 in google-services.json:"
    echo "$CERT_HASH"
else
    echo ""
    echo "❌ No certificate_hash found in google-services.json"
    echo "   This confirms Android OAuth client is missing!"
fi

echo ""
echo "4. Checking device setup..."
echo "---"

# Check if device is connected
if command -v adb &> /dev/null; then
    DEVICE=$(adb devices | grep -v "List" | grep "device" | wc -l)
    if [ $DEVICE -gt 0 ]; then
        echo "✓ Device connected"
        
        # Check Google accounts on device
        echo ""
        echo "Checking Google accounts on device..."
        adb shell dumpsys account | grep -A 2 "Account {name=.*@.*\.com, type=com.google}" | head -20
        
        # Check Google Play Services version
        echo ""
        echo "Google Play Services version:"
        adb shell dumpsys package com.google.android.gms | grep versionName | head -1
    else
        echo "⚠️  No device connected"
    fi
else
    echo "⚠️  adb not found in PATH"
fi

echo ""
echo "================================"
echo "DIAGNOSIS SUMMARY"
echo "================================"
echo ""

if [ -z "$ANDROID_CLIENT" ]; then
    echo "🔴 CRITICAL ISSUE FOUND:"
    echo ""
    echo "Your google-services.json is missing the Android OAuth client!"
    echo ""
    echo "This is why you're getting 'No Google accounts found' error."
    echo "The Credential Manager API requires an Android OAuth client (type 1)."
    echo ""
    echo "FIX:"
    echo "1. Go to: https://console.cloud.google.com/apis/credentials?project=kibotu-trail"
    echo "2. Create OAuth client ID → Android"
    echo "3. Package name: net.kibotu.trail"
    echo "4. SHA-1: 79:16:DA:2F:DB:96:1F:BC:CE:58:2E:28:F2:B8:80:98:62:70:12:19"
    echo "5. Download new google-services.json from Firebase"
    echo "6. Replace app/google-services.json"
    echo ""
else
    echo "✓ Configuration looks good!"
    echo ""
    echo "If sign-in still fails, check:"
    echo "- SHA-1 fingerprint matches in Google Cloud Console"
    echo "- Google Play Services is up to date on device"
    echo "- Internet connection is available"
fi

echo ""
