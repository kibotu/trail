package net.kibotu.trail.ui.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.ui.components.EntryList
import net.kibotu.trail.ui.components.SearchOverlay
import net.kibotu.trail.ui.viewmodel.SearchType
import net.kibotu.trail.ui.viewmodel.TrailViewModel
import net.kibotu.trail.ui.viewmodel.UiState

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MyFeedScreen(
    viewModel: TrailViewModel,
    scrollConnection: NestedScrollConnection
) {
    val uiState by viewModel.uiState.collectAsState()
    val myFeedEntries by viewModel.myFeedEntries.collectAsState()
    val myFeedLoading by viewModel.myFeedLoading.collectAsState()
    val commentsState by viewModel.commentsState.collectAsState()
    val celebrationEvent by viewModel.celebrationEvent.collectAsState()
    val profileState by viewModel.profileState.collectAsState()
    val searchOverlayState by viewModel.searchOverlayState.collectAsState()
    val currentlyPlayingVideoId by viewModel.currentlyPlayingVideoId.collectAsState()

    var entryText by remember { mutableStateOf("") }
    val maxCharacters = 140
    val snackbarHostState = remember { SnackbarHostState() }

    val isAuthenticated = uiState is UiState.Entries
    val currentUserId =
        if (uiState is UiState.Entries) (uiState as UiState.Entries).userId else null
    val userName = if (uiState is UiState.Entries) (uiState as UiState.Entries).userName else null
    val isAdmin = if (uiState is UiState.Entries) (uiState as UiState.Entries).isAdmin else false

    // Always load My Feed when the screen first appears (composition)
    LaunchedEffect(Unit) {
        // If user is authenticated, ensure we load the feed
        if (uiState is UiState.Entries) {
            // If we don't have the profile yet, load it (which will cascade to feed load)
            if (profileState == null) {
                viewModel.loadProfile()
            } else {
                // Profile already loaded, refresh the feed directly
                viewModel.refreshMyFeed()
            }
        }
    }

    // Also trigger when profile nickname becomes available (for cases where profile loads after screen)
    LaunchedEffect(profileState?.nickname) {
        val nickname = profileState?.nickname
        if (nickname != null && myFeedEntries.isEmpty() && !myFeedLoading) {
            viewModel.refreshMyFeed()
        }
    }

    // Show celebration when post is successful
    LaunchedEffect(celebrationEvent) {
        if (celebrationEvent) {
            snackbarHostState.showSnackbar(
                message = "🎉 Post created! Nice work! ✨",
                duration = SnackbarDuration.Short
            )
            viewModel.resetCelebration()
        }
    }

    val navigationBarBottom = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    // Show login prompt for unauthenticated users
    if (!isAuthenticated) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .systemBarsPadding(),
            contentAlignment = Alignment.Center
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Text(
                    text = "Sign in to view your feed",
                    style = MaterialTheme.typography.headlineSmall
                )
                Text(
                    text = "Create posts and see your personalized feed",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Button(onClick = { viewModel.navigateToLogin() }) {
                    Text("Sign In")
                }
            }
        }
        return
    }

    Box(modifier = Modifier.fillMaxSize()) {
        Scaffold(
            snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
            contentWindowInsets = WindowInsets(0, 0, 0, 0)
        ) { paddingValues ->
            PullToRefreshBox(
                isRefreshing = myFeedLoading,
                onRefresh = { viewModel.refreshMyFeed() },
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .nestedScroll(scrollConnection)
            ) {
                // Post creation card at the top (in a Column that doesn't contain the list)
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .statusBarsPadding()
                ) {
                    if (userName != null) {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 16.dp, vertical = 12.dp),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = MaterialTheme.colorScheme.surface
                            ),
                            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(16.dp),
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                Text(
                                    text = "What's on your mind, $userName?",
                                    style = MaterialTheme.typography.titleSmall,
                                    fontWeight = FontWeight.Medium,
                                    color = MaterialTheme.colorScheme.onSurface
                                )

                                OutlinedTextField(
                                    value = entryText,
                                    onValueChange = {
                                        if (it.length <= maxCharacters) {
                                            entryText = it
                                        }
                                    },
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(12.dp)),
                                    placeholder = {
                                        Text(
                                            "Share something...",
                                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                        )
                                    },
                                    minLines = 3,
                                    maxLines = 5,
                                    shape = RoundedCornerShape(12.dp),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = MaterialTheme.colorScheme.primary,
                                        unfocusedBorderColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f)
                                    ),
                                    isError = entryText.length > maxCharacters
                                )

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = "${entryText.length}/$maxCharacters",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = if (entryText.length > maxCharacters)
                                            MaterialTheme.colorScheme.error
                                        else
                                            MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                    )

                                    Button(
                                        onClick = {
                                            if (entryText.isNotBlank() && entryText.length <= maxCharacters) {
                                                viewModel.submitEntry(entryText)
                                                entryText = ""
                                            }
                                        },
                                        enabled = entryText.isNotBlank() && entryText.length <= maxCharacters,
                                        shape = RoundedCornerShape(10.dp),
                                        contentPadding = PaddingValues(horizontal = 24.dp, vertical = 10.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = MaterialTheme.colorScheme.primary,
                                            disabledContainerColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.12f)
                                        )
                                    ) {
                                        Text(
                                            "Post",
                                            style = MaterialTheme.typography.labelLarge,
                                            fontWeight = FontWeight.SemiBold
                                        )
                                    }
                                }
                            }
                        }
                    }
                }

                // Entry list - placed outside Column to allow proper scrolling
                EntryList(
                    entries = myFeedEntries,
                    isLoading = myFeedLoading && myFeedEntries.isEmpty(),
                    currentUserId = currentUserId,
                    isAdmin = isAdmin,
                    baseUrl = BuildConfig.API_BASE_URL,
                    currentlyPlayingVideoId = currentlyPlayingVideoId,
                    onVideoPlay = { viewModel.playVideo(it) },
                    onUpdateEntry = { entryId, text ->
                        viewModel.updateEntry(entryId, text)
                    },
                    onDeleteEntry = { entryId ->
                        viewModel.deleteEntry(entryId)
                    },
                    commentsState = commentsState,
                    onToggleComments = { entryId, hashId -> viewModel.toggleComments(entryId, hashId) },
                    onLoadComments = { entryId, hashId -> viewModel.loadComments(entryId, hashId) },
                    onCreateComment = { entryId, hashId, text -> viewModel.createComment(entryId, hashId, text) },
                    onUpdateComment = { commentId, text, entryId ->
                        viewModel.updateComment(commentId, text, entryId)
                    },
                    onDeleteComment = { commentId, entryId ->
                        viewModel.deleteComment(commentId, entryId)
                    },
                    onClapComment = { commentId, count, entryId ->
                        viewModel.clapComment(commentId, count, entryId)
                    },
                    onReportComment = { commentId, entryId ->
                        viewModel.reportComment(commentId, entryId)
                    },
                    contentPadding = PaddingValues(
                        start = 16.dp,
                        end = 16.dp,
                        // Add enough top padding to clear the post card when authenticated
                        // Post card: status bar + 12dp vertical padding + ~210dp card content
                        top = if (userName != null) statusBarTop + 236.dp else statusBarTop + 16.dp,
                        bottom = navigationBarBottom + 80.dp // Extra space for floating tab bar
                    ),
                    emptyMessage = "You haven't posted anything yet. Share your first thought!"
                )
            }
        }

        // Search Overlay
        SearchOverlay(
            isVisible = searchOverlayState.isVisible && searchOverlayState.searchType == SearchType.MY_FEED,
            searchQuery = searchOverlayState.query,
            searchResults = searchOverlayState.results,
            isLoading = searchOverlayState.isLoading,
            hasMore = searchOverlayState.hasMore,
            onQueryChange = { viewModel.updateSearchQuery(it) },
            onSearch = { viewModel.executeSearch(it) },
            onLoadMore = { viewModel.loadMoreSearchResults() },
            onDismiss = { viewModel.closeSearch() },
            currentUserId = currentUserId,
            isAdmin = isAdmin,
            commentsState = commentsState,
            onToggleComments = { entryId, hashId -> viewModel.toggleComments(entryId, hashId) },
            onLoadComments = { entryId, hashId -> viewModel.loadComments(entryId, hashId) },
            onCreateComment = { entryId, hashId, text -> viewModel.createComment(entryId, hashId, text) },
            onUpdateComment = { commentId, text, entryId ->
                viewModel.updateComment(commentId, text, entryId)
            },
            onDeleteComment = { commentId, entryId ->
                viewModel.deleteComment(commentId, entryId)
            },
            onClapComment = { commentId, count, entryId ->
                viewModel.clapComment(commentId, count, entryId)
            },
            onReportComment = { commentId, entryId ->
                viewModel.reportComment(commentId, entryId)
            },
            onUpdateEntry = { entryId, text ->
                viewModel.updateEntry(entryId, text)
            },
            onDeleteEntry = { entryId ->
                viewModel.deleteEntry(entryId)
            }
        )
    }
}
