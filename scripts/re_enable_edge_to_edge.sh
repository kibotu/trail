#!/bin/bash

# Script to re-enable edge-to-edge mode once Google Play Services is fixed
# Run this script when Google releases an update that fixes the Android 16 compatibility issue

echo "🔧 Re-enabling edge-to-edge mode in MainActivity..."

MAIN_ACTIVITY="android/app/src/main/java/net/kibotu/trail/MainActivity.kt"

if [ ! -f "$MAIN_ACTIVITY" ]; then
    echo "❌ Error: MainActivity.kt not found at $MAIN_ACTIVITY"
    exit 1
fi

# Create a backup
cp "$MAIN_ACTIVITY" "$MAIN_ACTIVITY.backup"
echo "✅ Created backup: $MAIN_ACTIVITY.backup"

# Replace the temporary workaround with proper edge-to-edge code
cat > "$MAIN_ACTIVITY" << 'EOF'
package net.kibotu.trail

import android.content.Intent
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.view.WindowCompat
import net.kibotu.trail.ui.TrailApp
import net.kibotu.trail.ui.theme.TrailTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Edge-to-edge mode re-enabled after Google Play Services fix
        enableEdgeToEdge()
        
        // Ensure window insets are properly dispatched to views
        WindowCompat.setDecorFitsSystemWindows(window, false)
        
        setContent {
            TrailTheme {
                TrailApp(
                    sharedUrl = extractSharedUrl(intent)
                )
            }
        }
    }
    
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
    }
    
    private fun extractSharedUrl(intent: Intent?): String? {
        if (intent?.action == Intent.ACTION_SEND && intent.type == "text/plain") {
            return intent.getStringExtra(Intent.EXTRA_TEXT)
        }
        return null
    }
}
EOF

echo "✅ Edge-to-edge mode has been re-enabled in MainActivity.kt"
echo ""
echo "📝 Next steps:"
echo "   1. Test Google OAuth on Android 16 device"
echo "   2. If it works, delete the backup: rm $MAIN_ACTIVITY.backup"
echo "   3. If it still crashes, restore backup: mv $MAIN_ACTIVITY.backup $MAIN_ACTIVITY"
echo ""
echo "🎉 Done!"
