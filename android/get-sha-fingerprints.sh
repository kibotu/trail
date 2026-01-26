#!/bin/bash

# Script to get SHA-1 and SHA-256 fingerprints for Android keystores
# These are needed for Google Sign-In configuration

echo "================================"
echo "Android Keystore Fingerprints"
echo "================================"
echo ""

# Check if local.properties exists
if [ ! -f "../local.properties" ]; then
    echo "⚠️  Warning: local.properties not found"
    echo "   Using default keystore paths"
    echo ""
fi

# Try to get debug keystore info
echo "📱 DEBUG Keystore:"
echo "---"

DEBUG_KEYSTORE="app/certificates/debug.jks"
if [ -f "$DEBUG_KEYSTORE" ]; then
    echo "Keystore: $DEBUG_KEYSTORE"
    echo ""
    echo "SHA-1 and SHA-256 fingerprints:"
    keytool -list -v -keystore "$DEBUG_KEYSTORE" -alias debug -storepass android -keypass android 2>/dev/null | grep -E "SHA1:|SHA256:"
    echo ""
else
    echo "❌ Debug keystore not found at: $DEBUG_KEYSTORE"
    echo ""
fi

# Try to get release keystore info
echo "🚀 RELEASE Keystore:"
echo "---"

RELEASE_KEYSTORE="app/certificates/release.jks"
if [ -f "$RELEASE_KEYSTORE" ]; then
    echo "Keystore: $RELEASE_KEYSTORE"
    echo ""
    echo "⚠️  Release keystore found but password needed"
    echo "   Run manually with:"
    echo "   keytool -list -v -keystore $RELEASE_KEYSTORE -alias release"
    echo ""
else
    echo "❌ Release keystore not found at: $RELEASE_KEYSTORE"
    echo ""
fi

# Check for default Android debug keystore
echo "🔍 Default Android Debug Keystore:"
echo "---"

DEFAULT_DEBUG="$HOME/.android/debug.keystore"
if [ -f "$DEFAULT_DEBUG" ]; then
    echo "Keystore: $DEFAULT_DEBUG"
    echo ""
    echo "SHA-1 and SHA-256 fingerprints:"
    keytool -list -v -keystore "$DEFAULT_DEBUG" -alias androiddebugkey -storepass android -keypass android 2>/dev/null | grep -E "SHA1:|SHA256:"
    echo ""
else
    echo "❌ Default debug keystore not found at: $DEFAULT_DEBUG"
    echo ""
fi

echo "================================"
echo "Next Steps:"
echo "================================"
echo ""
echo "1. Copy the SHA-1 fingerprint(s) above"
echo "2. Go to Google Cloud Console:"
echo "   https://console.cloud.google.com/apis/credentials?project=kibotu-trail"
echo ""
echo "3. For each OAuth client (Android), add the SHA-1 fingerprint"
echo ""
echo "4. After updating, download new google-services.json from Firebase:"
echo "   https://console.firebase.google.com/project/kibotu-trail/settings/general"
echo ""
echo "5. Replace android/app/google-services.json with the new file"
echo ""
