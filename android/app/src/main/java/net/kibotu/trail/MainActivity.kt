package net.kibotu.trail

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.viewmodel.compose.viewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import net.kibotu.splashscreen.splash
import net.kibotu.trail.feature.auth.AuthViewModel
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.navigation.TrailNavigation
import net.kibotu.trail.shared.splash.HeartBeatAnimation
import net.kibotu.trail.shared.review.InAppReviewManager
import net.kibotu.trail.shared.review.LocalInAppReviewManager
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.theme.TrailTheme
import kotlin.time.Duration.Companion.milliseconds

class MainActivity : ComponentActivity() {
    private lateinit var themePreferences: ThemePreferences
    private lateinit var inAppReviewManager: InAppReviewManager
    private var splashScreenDecorator: net.kibotu.splashscreen.SplashScreenDecorator? = null
    private val _pendingSharedText = MutableStateFlow<String?>(null)
    private val pendingSharedText = _pendingSharedText.asStateFlow()

    override fun onCreate(savedInstanceState: Bundle?) {
        splashScreenDecorator = splash {
            exitAnimationDuration = 1250
            content {
                TrailTheme {
                    HeartBeatAnimation(
                        isVisible = isVisible.value,
                        exitAnimationDuration = exitAnimationDuration.milliseconds,
                        onStartExitAnimation = { startExitAnimation() }
                    )
                }
            }
        }

        splashScreenDecorator?.shouldKeepOnScreen = false
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)

        themePreferences = ThemePreferences(applicationContext)
        inAppReviewManager = InAppReviewManager(applicationContext)

        setContent {
            val isDarkTheme by themePreferences.isDarkTheme.collectAsState()
            val authViewModel: AuthViewModel =
                viewModel(factory = AuthViewModel.Factory(applicationContext))
            val authState by authViewModel.state.collectAsState()

            LaunchedEffect(authState.isLoading) {
                if (!authState.isLoading) {
                    splashScreenDecorator?.dismiss()
                }
            }

            CompositionLocalProvider(
                LocalAuthViewModel provides authViewModel,
                LocalThemePreferences provides themePreferences,
                LocalInAppReviewManager provides inAppReviewManager
            ) {
                TrailTheme(darkTheme = isDarkTheme) {
                    Surface(
                        modifier = Modifier.fillMaxSize(),
                        color = MaterialTheme.colorScheme.background,
                    ) {
                        TrailNavigation(
                            themePreferences = themePreferences,
                            pendingSharedText = pendingSharedText,
                            onConsumeSharedText = { _pendingSharedText.value = null }
                        )
                    }
                }
            }
        }

        handleSharedContent(intent)
    }

    override fun onDestroy() {
        splashScreenDecorator?.dismiss()
        splashScreenDecorator = null
        super.onDestroy()
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleSharedContent(intent)
    }

    private fun handleSharedContent(intent: Intent?) {
        if (intent?.action == Intent.ACTION_SEND && intent.type == "text/plain") {
            intent.getStringExtra(Intent.EXTRA_TEXT)?.let { text ->
                _pendingSharedText.value = text
            }
            intent.action = null
        }
    }
}
