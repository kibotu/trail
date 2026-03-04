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
import net.kibotu.splashscreen.splash
import net.kibotu.trail.feature.auth.AuthViewModel
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.navigation.TrailNavigation
import net.kibotu.trail.shared.splash.HeartBeatAnimation
import androidx.activity.result.contract.ActivityResultContracts
import net.kibotu.trail.shared.review.InAppReviewManager
import net.kibotu.trail.shared.review.LocalInAppReviewManager
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.theme.TrailTheme
import net.kibotu.trail.shared.theme.LocalWindowSizeClass
import net.kibotu.trail.shared.update.InAppUpdateManager
import net.kibotu.trail.shared.update.LocalInAppUpdateManager
import androidx.compose.material3.windowsizeclass.ExperimentalMaterial3WindowSizeClassApi
import androidx.compose.material3.windowsizeclass.calculateWindowSizeClass
import kotlin.time.Duration.Companion.milliseconds

class MainActivity : ComponentActivity() {
    private lateinit var themePreferences: ThemePreferences
    private lateinit var inAppReviewManager: InAppReviewManager
    private lateinit var inAppUpdateManager: InAppUpdateManager
    private var splashScreenDecorator: net.kibotu.splashscreen.SplashScreenDecorator? = null
    private val pendingSharedText = MutableStateFlow<String?>(null)

    private val updateResultLauncher = registerForActivityResult(
        ActivityResultContracts.StartIntentSenderForResult()
    ) { /* result handling is optional for flexible updates */ }

    @OptIn(ExperimentalMaterial3WindowSizeClassApi::class)
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
        timber.log.Timber.d("──── MainActivity.onCreate: creating InAppReviewManager ────")
        inAppReviewManager = InAppReviewManager(applicationContext)
        inAppUpdateManager = InAppUpdateManager(applicationContext)

        setContent {
            val isDarkTheme by themePreferences.isDarkTheme.collectAsState()
            val authViewModel: AuthViewModel =
                viewModel(factory = AuthViewModel.Factory(applicationContext))
            val authState by authViewModel.state.collectAsState()

            LaunchedEffect(authState.isLoading) {
                if (!authState.isLoading) {
                    splashScreenDecorator?.dismiss()
                    inAppUpdateManager.checkAndPromptUpdate(
                        this@MainActivity,
                        updateResultLauncher,
                    )
                    timber.log.Timber.d("──── MainActivity: auth loaded, checking review on app start ────")
                    timber.log.Timber.d("MainActivity: isLoggedIn=%s, user=%s", authState.isLoggedIn, authState.user?.nickname)
                    inAppReviewManager.dumpState()
                    inAppReviewManager.promptIfEligible(this@MainActivity)
                }
            }

            val windowSizeClass = calculateWindowSizeClass(this@MainActivity)

            CompositionLocalProvider(
                LocalWindowSizeClass provides windowSizeClass,
                LocalAuthViewModel provides authViewModel,
                LocalThemePreferences provides themePreferences,
                LocalInAppReviewManager provides inAppReviewManager,
                LocalInAppUpdateManager provides inAppUpdateManager,
            ) {
                TrailTheme(darkTheme = isDarkTheme) {
                    Surface(
                        modifier = Modifier.fillMaxSize(),
                        color = MaterialTheme.colorScheme.background,
                    ) {
                        TrailNavigation(
                            themePreferences = themePreferences,
                            pendingSharedText = pendingSharedText,
                            onConsumeSharedText = { pendingSharedText.value = null }
                        )
                    }
                }
            }
        }

        handleSharedContent(intent)
    }

    override fun onDestroy() {
        inAppUpdateManager.unregisterListener()
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
                pendingSharedText.value = text
            }
            intent.action = null
        }
    }
}
