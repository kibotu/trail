package net.kibotu.trail.feature.entrydetail

import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.tween
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import dev.chrisbanes.haze.HazeState
import dev.chrisbanes.haze.hazeEffect
import androidx.lifecycle.viewmodel.compose.viewModel
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.LocalWindowSizeClass
import net.kibotu.trail.shared.theme.isCompactWidth
import net.kibotu.trail.shared.theme.ui.EntryCard
import net.kibotu.trail.shared.theme.ui.ShimmerFeed
import net.kibotu.trail.shared.theme.ui.staggeredFadeIn

@Composable
fun EntryDetailScreen(
    hashId: String,
    hazeState: HazeState,
    onNavigateBack: () -> Unit,
    onNavigateToUser: (String) -> Unit,
    viewModel: EntryDetailViewModel = viewModel(
        key = hashId,
        factory = EntryDetailViewModel.Factory(hashId)
    )
) {
    val detailState by viewModel.state.collectAsState()
    val authState by LocalAuthViewModel.current.state.collectAsState()
    val showTags by LocalThemePreferences.current.showEntryTags.collectAsState()
    val currentlyPlayingVideoId by viewModel.currentlyPlayingVideoId.collectAsState()
    val context = LocalContext.current
    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
    val isCompact = LocalWindowSizeClass.current.isCompactWidth

    LaunchedEffect(Unit) {
        viewModel.entryDeleted.collect { onNavigateBack() }
    }

    Box(Modifier.fillMaxSize()) {
        val detailViewState = when {
            detailState.isLoading -> "loading"
            detailState.error != null -> "error"
            detailState.entry != null -> "content"
            else -> "loading"
        }

        Crossfade(
            targetState = detailViewState,
            animationSpec = tween(300),
            label = "detailState"
        ) { state ->
            when (state) {
                "loading" -> {
                    Box(Modifier.fillMaxSize()) {
                        ShimmerFeed(modifier = Modifier.padding(top = statusBarTop + 56.dp))
                    }
                }

                "error" -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text("Error: ${detailState.error}", color = MaterialTheme.colorScheme.error)
                    }
                }

                else -> {
                val entry = detailState.entry ?: return@Crossfade
                val horizontalPadding = if (isCompact) 16.dp else 24.dp
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.TopCenter
                ) {
                LazyColumn(
                    modifier = Modifier
                        .then(if (isCompact) Modifier.fillMaxWidth() else Modifier.widthIn(max = 600.dp))
                        .fillMaxSize(),
                    contentPadding = PaddingValues(start = horizontalPadding, end = horizontalPadding, top = statusBarTop + 56.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    item {
                        EntryCard(
                            entry = entry,
                            modifier = Modifier
                                .animateItem(fadeInSpec = tween(300), fadeOutSpec = tween(200))
                                .staggeredFadeIn(0),
                            currentUserId = authState.user?.id,
                            isAdmin = authState.user?.isAdmin ?: false,
                            baseUrl = BuildConfig.API_BASE_URL,
                            showTags = showTags,
                            currentlyPlayingVideoId = currentlyPlayingVideoId,
                            onVideoPlay = viewModel::onVideoPlay,
                            onAvatarClick = { entry.userNickname?.let { onNavigateToUser(it) } },
                            onUsernameClick = { entry.userNickname?.let { onNavigateToUser(it) } },
                            onClap = { count -> viewModel.addClaps(count) },
                            onShare = { viewModel.shareEntry(context) },
                            onReport = { viewModel.reportEntry() },
                            onMuteUser = { viewModel.muteUser(entry.userId) },
                            onEditEntry = { text -> viewModel.updateEntry(entry.id, text) },
                            onDeleteEntry = { viewModel.deleteEntry(entry.id) },
                            onToggleComments = { viewModel.loadComments() },
                            comments = detailState.comments,
                            commentsLoading = detailState.isCommentsLoading,
                            commentsExpanded = true,
                            onLoadComments = { viewModel.loadComments() },
                            onCreateComment = { text -> viewModel.createComment(text) },
                            onUpdateComment = { commentId, text -> viewModel.updateComment(commentId, text) },
                            onDeleteComment = { commentId -> viewModel.deleteComment(commentId) },
                            onClapComment = { commentId, count -> viewModel.clapComment(commentId, count) },
                            onReportComment = { commentId -> viewModel.reportComment(commentId) },
                            onMentionClick = { nick -> onNavigateToUser(nick) }
                        )
                    }
                }
                }
                }
            }
        }

        val hazeBackgroundColor = MaterialTheme.colorScheme.surfaceContainerHigh.copy(alpha = 0.6f)
        Box(
            modifier = Modifier
                .statusBarsPadding()
                .padding(start = 12.dp, top = 8.dp)
                .size(40.dp)
                .align(Alignment.TopStart)
                .clip(CircleShape)
                .hazeEffect(state = hazeState) {
                    backgroundColor = hazeBackgroundColor
                }
                .clickable(onClick = onNavigateBack),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                Icons.AutoMirrored.Filled.ArrowBack,
                contentDescription = "Back",
                modifier = Modifier.size(20.dp),
                tint = MaterialTheme.colorScheme.onSurface
            )
        }
    }
}
