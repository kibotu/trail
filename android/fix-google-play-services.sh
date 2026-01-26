#!/bin/bash

echo "================================"
echo "Google Play Services Fix Helper"
echo "================================"
echo ""

# Check if device is connected
if ! command -v adb &> /dev/null; then
    echo "❌ adb not found in PATH"
    exit 1
fi

DEVICE=$(adb devices | grep -v "List" | grep "device" | wc -l)
if [ $DEVICE -eq 0 ]; then
    echo "❌ No device connected"
    echo "   Connect your device and enable USB debugging"
    exit 1
fi

echo "✓ Device connected"
echo ""

# Get device info
echo "📱 Device Information:"
echo "---"
echo "Model: $(adb shell getprop ro.product.model)"
echo "Android: $(adb shell getprop ro.build.version.release)"
echo "SDK: $(adb shell getprop ro.build.version.sdk)"
echo ""

# Get Google Play Services version
echo "🔍 Google Play Services:"
echo "---"
GMS_VERSION=$(adb shell dumpsys package com.google.android.gms | grep versionName | head -1 | awk '{print $1}')
echo "$GMS_VERSION"
echo ""

# Get screen info
echo "📺 Display Information:"
echo "---"
adb shell wm size
adb shell wm density
echo ""

echo "================================"
echo "Recommended Actions:"
echo "================================"
echo ""

echo "1. Clear Google Play Services Cache:"
echo "   adb shell pm clear com.google.android.gms"
echo ""

echo "2. Restart Device:"
echo "   adb reboot"
echo ""

echo "3. Update Google Play Services:"
echo "   Open Play Store → Search 'Google Play services' → Update"
echo ""

echo "4. Check for system updates:"
echo "   Settings → System → System update"
echo ""

echo "================================"
echo "Quick Fix (Clear Cache):"
echo "================================"
echo ""

read -p "Clear Google Play Services cache now? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Clearing cache..."
    adb shell pm clear com.google.android.gms
    echo "✓ Cache cleared"
    echo ""
    echo "Now restart your device:"
    echo "  adb reboot"
    echo ""
    echo "After restart, try sign-in again"
else
    echo "Skipped cache clear"
fi

echo ""
echo "================================"
echo "Testing Sign-In:"
echo "================================"
echo ""
echo "After applying fixes, rebuild and test:"
echo "  cd android"
echo "  ./gradlew clean assembleDebug installDebug"
echo ""
echo "Watch logs during sign-in:"
echo "  adb logcat | grep -E 'AuthScreen|Credential|GoogleId|gms'"
echo ""
