package net.kibotu.trail.feature.entrydetail

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilledIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.ui.EntryCard

@Composable
fun EntryDetailScreen(
    hashId: String,
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

    Box(Modifier.fillMaxSize()) {
        when {
            detailState.isLoading -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }

            detailState.error != null -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("Error: ${detailState.error}", color = MaterialTheme.colorScheme.error)
                }
            }

            detailState.entry != null -> {
                val entry = detailState.entry!!
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 56.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    item {
                        EntryCard(
                            entry = entry,
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
                            onReportComment = { commentId -> viewModel.reportComment(commentId) }
                        )
                    }
                }
            }
        }

        FilledIconButton(
            onClick = onNavigateBack,
            modifier = Modifier
                .statusBarsPadding()
                .padding(start = 12.dp, top = 8.dp)
                .size(40.dp)
                .align(Alignment.TopStart),
            shape = CircleShape,
            colors = IconButtonDefaults.filledIconButtonColors(
                containerColor = MaterialTheme.colorScheme.surfaceContainerHigh,
                contentColor = MaterialTheme.colorScheme.onSurface
            )
        ) {
            Icon(
                Icons.AutoMirrored.Filled.ArrowBack,
                contentDescription = "Back",
                modifier = Modifier.size(20.dp)
            )
        }
    }
}
