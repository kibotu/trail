package net.kibotu.trail.ui.screens

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
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
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.unit.dp
import net.kibotu.trail.ui.components.EntryList
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

    var entryText by remember { mutableStateOf("") }
    val maxCharacters = 140
    val snackbarHostState = remember { SnackbarHostState() }

    val currentUserId = if (uiState is UiState.Entries) (uiState as UiState.Entries).userId else null
    val userName = if (uiState is UiState.Entries) (uiState as UiState.Entries).userName else null
    val isAdmin = if (uiState is UiState.Entries) (uiState as UiState.Entries).isAdmin else false

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
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .statusBarsPadding()
            ) {
                // Post creation card
                if (userName != null) {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp)
                        ) {
                            Text(
                                text = "What's on your mind, $userName?",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                            Spacer(modifier = Modifier.height(8.dp))

                            OutlinedTextField(
                                value = entryText,
                                onValueChange = {
                                    if (it.length <= maxCharacters) {
                                        entryText = it
                                    }
                                },
                                modifier = Modifier.fillMaxWidth(),
                                placeholder = { Text("Share something...") },
                                minLines = 3,
                                maxLines = 5,
                                supportingText = {
                                    Text(
                                        text = "${entryText.length}/$maxCharacters",
                                        style = MaterialTheme.typography.bodySmall
                                    )
                                },
                                isError = entryText.length > maxCharacters
                            )

                            Spacer(modifier = Modifier.height(8.dp))

                            Button(
                                onClick = {
                                    if (entryText.isNotBlank() && entryText.length <= maxCharacters) {
                                        viewModel.submitEntry(entryText)
                                        entryText = ""
                                    }
                                },
                                modifier = Modifier.align(Alignment.End),
                                enabled = entryText.isNotBlank() && entryText.length <= maxCharacters
                            ) {
                                Text("Post")
                            }
                        }
                    }
                }

                // Entry list
                EntryList(
                    entries = myFeedEntries,
                    isLoading = myFeedLoading && myFeedEntries.isEmpty(),
                    currentUserId = currentUserId,
                    isAdmin = isAdmin,
                    onUpdateEntry = { entryId, text ->
                        viewModel.updateEntry(entryId, text)
                    },
                    onDeleteEntry = { entryId ->
                        viewModel.deleteEntry(entryId)
                    },
                    commentsState = commentsState,
                    onToggleComments = { entryId -> viewModel.toggleComments(entryId) },
                    onLoadComments = { entryId -> viewModel.loadComments(entryId) },
                    onCreateComment = { entryId, text -> viewModel.createComment(entryId, text) },
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
                        top = 8.dp,
                        bottom = navigationBarBottom + 8.dp
                    ),
                    emptyMessage = "You haven't posted anything yet. Share your first thought!"
                )
            }
        }
    }
}
