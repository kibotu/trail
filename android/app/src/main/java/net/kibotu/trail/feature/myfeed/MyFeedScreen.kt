package net.kibotu.trail.feature.myfeed

import android.os.Build
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.paging.LoadState
import androidx.paging.compose.collectAsLazyPagingItems
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.feature.auth.LoginScreen
import net.kibotu.trail.feature.home.CommentState
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.ui.EntryCard

@OptIn(ExperimentalMaterial3Api::class)
@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
@Composable
fun MyFeedScreen(
    onNavigateToEntry: (String) -> Unit,
    onNavigateToUser: (String) -> Unit,
    onNavigateToSearch: (String) -> Unit = {},
    scrollConnection: NestedScrollConnection? = null
) {
    val authViewModel = LocalAuthViewModel.current
    val authState by authViewModel.state.collectAsState()

    if (!authState.isLoggedIn) {
        LoginScreen(onLoginSuccess = { authViewModel.handleGoogleSignIn(it) })
        return
    }

    val nickname = authState.user?.nickname
    if (nickname == null) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        return
    }
    val viewModel: MyFeedViewModel = viewModel(factory = MyFeedViewModel.Factory(LocalContext.current, nickname))
    val entries = viewModel.entries.collectAsLazyPagingItems()
    val commentsState by viewModel.commentsState.collectAsState()
    val currentlyPlayingVideoId by viewModel.currentlyPlayingVideoId.collectAsState()
    val showTags by LocalThemePreferences.current.showEntryTags.collectAsState()
    val isPosting by viewModel.isPosting.collectAsState()
    val context = LocalContext.current

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    PullToRefreshBox(
        isRefreshing = entries.loadState.refresh is LoadState.Loading,
        onRefresh = { entries.refresh() },
        modifier = Modifier.fillMaxSize()
    ) {
        LazyColumn(
            modifier = Modifier.fillMaxSize()
                .let { mod -> scrollConnection?.let { mod.nestedScroll(it) } ?: mod },
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 16.dp, bottom = 100.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Compose card
            item {
                ComposeCard(
                    isPosting = isPosting,
                    onPost = { viewModel.createEntry(it) }
                )
            }

            items(
                count = entries.itemCount,
                key = { index -> entries[index]?.id ?: index }
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
                item {
                    Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                }
            }
        }
    }
}

@Composable
private fun ComposeCard(
    isPosting: Boolean,
    onPost: (String) -> Unit
) {
    var text by remember { mutableStateOf("") }
    val maxCharacters = 140

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            OutlinedTextField(
                value = text,
                onValueChange = { if (it.length <= maxCharacters) text = it },
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("What are you working on?") },
                minLines = 2,
                maxLines = 5,
                shape = RoundedCornerShape(12.dp)
            )
            Spacer(modifier = Modifier.height(12.dp))
            Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterEnd) {
                if (isPosting) {
                    CircularProgressIndicator(modifier = Modifier.padding(8.dp))
                } else {
                    Button(
                        onClick = {
                            onPost(text)
                            text = ""
                        },
                        enabled = text.isNotBlank() && text.length <= maxCharacters,
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Text("Post")
                    }
                }
            }
        }
    }
}
