package net.kibotu.trail.feature.home

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.zIndex
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.paging.LoadState
import androidx.paging.compose.collectAsLazyPagingItems
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.ui.EntryCard

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onNavigateToEntry: (String) -> Unit,
    onNavigateToUser: (String) -> Unit,
    onNavigateToSearch: (String) -> Unit = {},
    onNavigateToNotifications: () -> Unit = {},
    scrollConnection: NestedScrollConnection? = null,
    viewModel: HomeViewModel = viewModel(factory = HomeViewModel.Factory(LocalContext.current))
) {
    val authState by LocalAuthViewModel.current.state.collectAsState()
    val showTags by LocalThemePreferences.current.showEntryTags.collectAsState()
    val entries = viewModel.entries.collectAsLazyPagingItems()
    val commentsState by viewModel.commentsState.collectAsState()
    val currentlyPlayingVideoId by viewModel.currentlyPlayingVideoId.collectAsState()
    val unreadCount by viewModel.unreadNotificationCount.collectAsState()
    val context = LocalContext.current

    LaunchedEffect(authState.isLoggedIn) {
        if (authState.isLoggedIn) {
            viewModel.refreshUnreadNotifications()
        }
    }

    val isRefreshing = entries.loadState.refresh is LoadState.Loading

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    Box(modifier = Modifier.fillMaxSize()) {
        PullToRefreshBox(
            isRefreshing = isRefreshing,
            onRefresh = {
                entries.refresh()
                viewModel.refreshUnreadNotifications()
            },
            modifier = Modifier.fillMaxSize()
        ) {
            when {
                entries.loadState.refresh is LoadState.Error -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            "Something went wrong. Pull to refresh.",
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
                entries.itemCount == 0 && !isRefreshing -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            "No entries yet. Be the first to post!",
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
                else -> {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize()
                            .let { mod -> scrollConnection?.let { mod.nestedScroll(it) } ?: mod },
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 16.dp, bottom = 100.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        items(
                            count = entries.itemCount,
                            key = { index -> "entry_$index" }
                        ) { index ->
                            val entry = entries[index] ?: return@items
                            val commentState = commentsState[entry.id] ?: CommentState()

                            EntryCard(
                                entry = entry,
                                currentUserId = authState.user?.id,
                                isAdmin = authState.user?.isAdmin ?: false,
                                baseUrl = BuildConfig.API_BASE_URL,
                                showTags = showTags,
                                currentlyPlayingVideoId = currentlyPlayingVideoId,
                                onVideoPlay = viewModel::onVideoPlay,
                                onCardClick = { entry.hashId?.let { onNavigateToEntry(it) } },
                                onAvatarClick = { entry.userNickname?.let { onNavigateToUser(it) } },
                                onUsernameClick = { entry.userNickname?.let { onNavigateToUser(it) } },
                                onTagClick = { tag -> onNavigateToSearch("#$tag") },
                                onClap = { count -> entry.hashId?.let { viewModel.addClaps(it, count) } },
                                onShare = { viewModel.shareEntry(context, entry) },
                                onReport = { entry.hashId?.let { viewModel.reportEntry(it) } },
                                onMuteUser = { viewModel.muteUser(entry.userId) },
                                onEditEntry = { text -> viewModel.updateEntry(entry.id, text) },
                                onDeleteEntry = { viewModel.deleteEntry(entry.id) },
                                onToggleComments = { viewModel.toggleComments(entry.id, entry.hashId) },
                                comments = commentState.comments,
                                commentsLoading = commentState.isLoading,
                                commentsExpanded = commentState.isExpanded,
                                onLoadComments = { viewModel.loadComments(entry.id, entry.hashId) },
                                onCreateComment = { text -> viewModel.createComment(entry.id, entry.hashId, text) },
                                onUpdateComment = { commentId, text -> viewModel.updateComment(commentId, text, entry.id) },
                                onDeleteComment = { commentId -> viewModel.deleteComment(commentId, entry.id) },
                                onClapComment = { commentId, count -> viewModel.clapComment(commentId, count, entry.id) },
                                onReportComment = { commentId -> viewModel.reportComment(commentId, entry.id) }
                            )
                        }

                        if (entries.loadState.append is LoadState.Loading) {
                            item(key = "loading_indicator") {
                                Box(Modifier.fillMaxSize().padding(16.dp), contentAlignment = Alignment.Center) {
                                    CircularProgressIndicator(modifier = Modifier.padding(16.dp))
                                }
                            }
                        }
                    }
                }
            }
        }

        // Notification bell — top right, respects status bar
        if (authState.isLoggedIn) {
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(top = statusBarTop + 8.dp, end = 12.dp)
                    .zIndex(1f)
            ) {
                IconButton(
                    onClick = onNavigateToNotifications,
                    modifier = Modifier.size(40.dp),
                    colors = IconButtonDefaults.iconButtonColors(
                        containerColor = MaterialTheme.colorScheme.surfaceContainerHigh
                    )
                ) {
                    FaIcon(
                        faIcon = if (unreadCount > 0) FaIcons.Bell else FaIcons.BellRegular,
                        size = 20.dp,
                        tint = if (unreadCount > 0)
                            MaterialTheme.colorScheme.primary
                        else
                            MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                if (unreadCount > 0) {
                    Box(
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .offset(x = 0.dp, y = 6.dp)
                            .size(10.dp)
                            .background(MaterialTheme.colorScheme.error, CircleShape)
                    )
                }
            }
        }
    }
}
