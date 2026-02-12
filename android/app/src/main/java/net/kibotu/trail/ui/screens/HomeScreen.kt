package net.kibotu.trail.ui.screens

import android.os.Build
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.ui.components.EntryList
import net.kibotu.trail.ui.components.SearchOverlay
import net.kibotu.trail.ui.viewmodel.SearchType
import net.kibotu.trail.ui.viewmodel.TrailViewModel
import net.kibotu.trail.ui.viewmodel.UiState

@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    viewModel: TrailViewModel,
    scrollConnection: NestedScrollConnection
) {
    val uiState by viewModel.uiState.collectAsState()
    val homeEntries by viewModel.homeEntries.collectAsState()
    val homeLoading by viewModel.homeLoading.collectAsState()
    val commentsState by viewModel.commentsState.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    val searchOverlayState by viewModel.searchOverlayState.collectAsState()
    val currentlyPlayingVideoId by viewModel.currentlyPlayingVideoId.collectAsState()

    var searchText by remember { mutableStateOf(searchQuery) }
    val keyboardController = LocalSoftwareKeyboardController.current

    val isAuthenticated = uiState is UiState.Entries
    val currentUserId =
        if (uiState is UiState.Entries) (uiState as UiState.Entries).userId else null
    val isAdmin = if (uiState is UiState.Entries) (uiState as UiState.Entries).isAdmin else false

    // Load entries when screen appears (handles both authenticated and unauthenticated users)
    LaunchedEffect(Unit) {
        if (homeEntries.isEmpty()) {
            if (isAuthenticated) {
                viewModel.loadHomeEntries()
            } else {
                viewModel.loadPublicEntries()
            }
        }
    }

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
    val navigationBarBottom = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    Box(modifier = Modifier.fillMaxSize()) {
        Scaffold(
            contentWindowInsets = WindowInsets(0, 0, 0, 0)
        ) { paddingValues ->
            PullToRefreshBox(
                isRefreshing = homeLoading,
                onRefresh = {
                    if (isAuthenticated) {
                        viewModel.loadHomeEntries(searchQuery.ifBlank { null })
                    } else {
                        viewModel.loadPublicEntries()
                    }
                },
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .nestedScroll(scrollConnection)
            ) {
                // Calculate top padding based on header content:
                // - Status bar + Search field (~56dp) + padding (16dp) = ~72dp
                val headerTopPadding = statusBarTop + 72.dp // Just search field

                // Entry list (placed first so search field renders on top)
                EntryList(
                    entries = homeEntries,
                    isLoading = homeLoading && homeEntries.isEmpty(),
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
                        top = headerTopPadding,
                        bottom = navigationBarBottom + 80.dp // Extra space for floating tab bar
                    ),
                    emptyMessage = if (searchQuery.isNotBlank()) {
                        "No entries found for \"$searchQuery\""
                    } else {
                        "No entries yet. Be the first to post!"
                    }
                )

                // Search field at the top (rendered after EntryList so it's on top and clickable)
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                ) {
                    TextField(
                        value = searchText,
                        onValueChange = {
                            searchText = it
                            if (it.isBlank()) {
                                viewModel.clearSearch()
                            }
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 8.dp),
                        placeholder = { Text("Search entries...") },
                        leadingIcon = {
                            FaIcon(
                                faIcon = FaIcons.Search,
                                size = 20.dp,
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        },
                        trailingIcon = {
                            if (searchText.isNotEmpty()) {
                                IconButton(onClick = {
                                    searchText = ""
                                    viewModel.clearSearch()
                                }) {
                                    FaIcon(
                                        faIcon = FaIcons.Times,
                                        size = 20.dp,
                                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        },
                        singleLine = true,
                        colors = TextFieldDefaults.colors(
                            focusedContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                            unfocusedContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                            disabledContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                            focusedIndicatorColor = Color.Transparent,
                            unfocusedIndicatorColor = Color.Transparent,
                            disabledIndicatorColor = Color.Transparent
                        ),
                        shape = MaterialTheme.shapes.medium,
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                        keyboardActions = KeyboardActions(
                            onSearch = {
                                if (searchText.isNotBlank()) {
                                    viewModel.searchEntries(searchText)
                                    keyboardController?.hide()
                                }
                            }
                        )
                    )
                }
            }
        }

        // Search Overlay
        SearchOverlay(
            isVisible = searchOverlayState.isVisible && searchOverlayState.searchType == SearchType.HOME,
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

