package net.kibotu.trail

import android.content.Intent
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import net.kibotu.trail.data.storage.ThemePreferences
import net.kibotu.trail.ui.screens.EntriesScreen
import net.kibotu.trail.ui.screens.LoginScreen
import net.kibotu.trail.ui.theme.GoogleAuthTheme
import net.kibotu.trail.ui.viewmodel.TrailViewModel
import net.kibotu.trail.ui.viewmodel.UiState

class MainActivity : ComponentActivity() {
    private lateinit var viewModel: TrailViewModel
    private lateinit var themePreferences: ThemePreferences

    @RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        viewModel = TrailViewModel(applicationContext)
        themePreferences = ThemePreferences(applicationContext)

        setContent {
            val isDarkTheme by themePreferences.isDarkTheme.collectAsState()
            
            GoogleAuthTheme(darkTheme = isDarkTheme) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background,
                ) {
                    TrailApp(
                        viewModel = viewModel,
                        themePreferences = themePreferences
                    )
                }
            }
        }
        
        // Handle shared content from other apps after setContent
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
                    val sharedText = intent.getStringExtra(Intent.EXTRA_TEXT)
                    sharedText?.let {
                        // Set the pending shared text in the ViewModel
                        // If user is logged in, it will be submitted automatically
                        // If not, it will be submitted after login
                        viewModel.setPendingSharedText(it)
                    }
                }
            }
        }
    }
}

@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
@Composable
fun TrailApp(
    viewModel: TrailViewModel,
    themePreferences: ThemePreferences
) {
    val uiState by viewModel.uiState.collectAsState()
    val showCelebration by viewModel.celebrationEvent.collectAsState()

    when (val state = uiState) {
        is UiState.Loading -> {
            Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator()
            }
        }
        is UiState.PublicEntries -> {
            EntriesScreen(
                entries = state.entries,
                isLoading = state.isLoading,
                userName = null,
                currentUserId = null,
                isAdmin = false,
                onSubmitEntry = null,
                onUpdateEntry = { _, _ -> },
                onDeleteEntry = { },
                onRefresh = {
                    viewModel.loadPublicEntries()
                },
                onLogout = null,
                onLogin = {
                    viewModel.navigateToLogin()
                },
                onToggleTheme = {
                    themePreferences.toggleTheme()
                }
            )
        }
        is UiState.Login -> {
            LoginScreen(
                onLoginSuccess = { idToken ->
                    // Pass any pending shared text to the sign-in handler
                    viewModel.handleGoogleSignIn(idToken)
                }
            )
        }
        is UiState.Entries -> {
            val commentsState by viewModel.commentsState.collectAsState()
            
            EntriesScreen(
                entries = state.entries,
                isLoading = state.isLoading,
                userName = state.userName,
                currentUserId = state.userId,
                isAdmin = state.isAdmin,
                onSubmitEntry = { text ->
                    viewModel.submitEntry(text)
                },
                onUpdateEntry = { entryId, text ->
                    viewModel.updateEntry(entryId, text)
                },
                onDeleteEntry = { entryId ->
                    viewModel.deleteEntry(entryId)
                },
                onRefresh = {
                    viewModel.loadEntries()
                },
                onLogout = {
                    viewModel.logout()
                },
                onLogin = null,
                onToggleTheme = {
                    themePreferences.toggleTheme()
                },
                showCelebration = showCelebration,
                onCelebrationShown = {
                    viewModel.resetCelebration()
                },
                // Comment callbacks
                commentsState = commentsState,
                onToggleComments = { entryId -> viewModel.toggleComments(entryId) },
                onLoadComments = { entryId -> viewModel.loadComments(entryId) },
                onCreateComment = { entryId, text -> viewModel.createComment(entryId, text) },
                onUpdateComment = { commentId, text, entryId -> viewModel.updateComment(commentId, text, entryId) },
                onDeleteComment = { commentId, entryId -> viewModel.deleteComment(commentId, entryId) },
                onClapComment = { commentId, count, entryId -> viewModel.clapComment(commentId, count, entryId) },
                onReportComment = { commentId, entryId -> viewModel.reportComment(commentId, entryId) }
            )
        }
        is UiState.Error -> {
            // Show error state - for now just go back to login
            LoginScreen(
                onLoginSuccess = { idToken ->
                    viewModel.handleGoogleSignIn(idToken)
                }
            )
        }
    }
}