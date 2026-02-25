package net.kibotu.trail

import android.content.Intent
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.viewmodel.compose.viewModel
import net.kibotu.trail.feature.auth.AuthViewModel
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.navigation.TrailNavigation
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.theme.TrailTheme

class MainActivity : ComponentActivity() {
    private lateinit var themePreferences: ThemePreferences

    @RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)

        themePreferences = ThemePreferences(applicationContext)

        setContent {
            val isDarkTheme by themePreferences.isDarkTheme.collectAsState()
            val authViewModel: AuthViewModel = viewModel(factory = AuthViewModel.Factory(applicationContext))

            CompositionLocalProvider(
                LocalAuthViewModel provides authViewModel,
                LocalThemePreferences provides themePreferences
            ) {
                TrailTheme(darkTheme = isDarkTheme) {
                    Surface(
                        modifier = Modifier.fillMaxSize(),
                        color = MaterialTheme.colorScheme.background,
                    ) {
                        TrailNavigation(themePreferences = themePreferences)
                    }
                }
            }
        }

        handleSharedContent(intent)
    }

    @RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleSharedContent(intent)
    }

    private fun handleSharedContent(intent: Intent?) {
        when (intent?.action) {
            Intent.ACTION_SEND -> {
                if (intent.type == "text/plain") {
                    intent.getStringExtra(Intent.EXTRA_TEXT)
                }
            }
        }
    }
}
