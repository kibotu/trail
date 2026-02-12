package net.kibotu.trail.ui.components

import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import net.kibotu.trail.data.model.Entry
import net.kibotu.trail.ui.viewmodel.CommentState

/**
 * Configuration constants for search behavior.
 */
object SearchConfig {
    const val DEBOUNCE_MS = 300L
    const val PAGE_SIZE = 20
    const val LOAD_MORE_THRESHOLD = 3
}

/**
 * A full-screen search overlay with debounced input and paginated results.
 *
 * @param isVisible Whether the overlay is visible
 * @param searchQuery Current search query text
 * @param searchResults List of search results
 * @param isLoading Whether a search is in progress
 * @param hasMore Whether more results are available
 * @param onQueryChange Callback when search query changes
 * @param onSearch Callback to execute search (after debounce)
 * @param onLoadMore Callback to load more results
 * @param onDismiss Callback when overlay is dismissed
 * @param currentUserId Current user's ID for entry modification checks
 * @param isAdmin Whether current user is admin
 * @param commentsState Comments state map for entries
 * @param onToggleComments Callback to toggle comments expansion
 * @param onLoadComments Callback to load comments for an entry
 * @param onCreateComment Callback to create a comment
 * @param onUpdateComment Callback to update a comment
 * @param onDeleteComment Callback to delete a comment
 * @param onClapComment Callback to clap a comment
 * @param onReportComment Callback to report a comment
 * @param onUpdateEntry Callback to update an entry
 * @param onDeleteEntry Callback to delete an entry
 */
@OptIn(FlowPreview::class)
@Composable
fun SearchOverlay(
    isVisible: Boolean,
    searchQuery: String,
    searchResults: List<Entry>,
    isLoading: Boolean,
    hasMore: Boolean,
    onQueryChange: (String) -> Unit,
    onSearch: (String) -> Unit,
    onLoadMore: () -> Unit,
    onDismiss: () -> Unit,
    currentUserId: Int?,
    isAdmin: Boolean,
    commentsState: Map<Int, CommentState>,
    onToggleComments: (Int, String?) -> Unit,
    onLoadComments: (Int, String?) -> Unit,
    onCreateComment: (Int, String?, String) -> Unit,
    onUpdateComment: (Int, String, Int) -> Unit,
    onDeleteComment: (Int, Int) -> Unit,
    onClapComment: (Int, Int, Int) -> Unit,
    onReportComment: (Int, Int) -> Unit,
    onUpdateEntry: (Int, String) -> Unit,
    onDeleteEntry: (Int) -> Unit
) {
    val focusRequester = remember { FocusRequester() }
    val keyboardController = LocalSoftwareKeyboardController.current
    val listState = rememberLazyListState()

    // Handle back button to dismiss overlay
    BackHandler(enabled = isVisible) {
        onDismiss()
    }

    // Auto-focus search field when overlay opens
    LaunchedEffect(isVisible) {
        if (isVisible) {
            focusRequester.requestFocus()
        }
    }

    // Debounced search
    LaunchedEffect(Unit) {
        snapshotFlow { searchQuery }
            .debounce(SearchConfig.DEBOUNCE_MS)
            .distinctUntilChanged()
            .collectLatest { query ->
                if (query.isNotBlank()) {
                    onSearch(query)
                }
            }
    }

    // Infinite scroll detection
    val shouldLoadMore by remember {
        derivedStateOf {
            val layoutInfo = listState.layoutInfo
            val totalItems = layoutInfo.totalItemsCount
            val lastVisibleItem = layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0

            hasMore && !isLoading && totalItems > 0 &&
                    lastVisibleItem >= totalItems - SearchConfig.LOAD_MORE_THRESHOLD
        }
    }

    LaunchedEffect(shouldLoadMore) {
        if (shouldLoadMore) {
            onLoadMore()
        }
    }

    val navigationBarBottom = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    AnimatedVisibility(
        visible = isVisible,
        enter = fadeIn() + slideInVertically { it / 4 },
        exit = fadeOut() + slideOutVertically { it / 4 }
    ) {
        Surface(
            modifier = Modifier.fillMaxSize(),
            color = MaterialTheme.colorScheme.background
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .statusBarsPadding()
            ) {
                // Search header
                SearchHeader(
                    query = searchQuery,
                    onQueryChange = onQueryChange,
                    onClear = {
                        onQueryChange("")
                    },
                    onDismiss = {
                        keyboardController?.hide()
                        onDismiss()
                    },
                    onSearch = {
                        if (searchQuery.isNotBlank()) {
                            keyboardController?.hide()
                            onSearch(searchQuery)
                        }
                    },
                    focusRequester = focusRequester
                )

                // Content area
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .weight(1f)
                ) {
                    when {
                        isLoading && searchResults.isEmpty() -> {
                            // Initial loading state
                            Box(
                                modifier = Modifier.fillMaxSize(),
                                contentAlignment = Alignment.Center
                            ) {
                                CircularProgressIndicator()
                            }
                        }

                        searchQuery.isBlank() -> {
                            // Empty query prompt
                            Box(
                                modifier = Modifier.fillMaxSize(),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.Center
                                ) {
                                    FaIcon(
                                        faIcon = FaIcons.Search,
                                        size = 64.dp,
                                        tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                                    )
                                    Spacer(modifier = Modifier.height(16.dp))
                                    Text(
                                        text = "Start typing to search",
                                        style = MaterialTheme.typography.bodyLarge,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        }

                        searchResults.isEmpty() && !isLoading -> {
                            // No results state
                            Box(
                                modifier = Modifier.fillMaxSize(),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = "No results for \"$searchQuery\"",
                                    style = MaterialTheme.typography.bodyLarge,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }

                        else -> {
                            // Results list
                            LazyColumn(
                                state = listState,
                                modifier = Modifier.fillMaxSize(),
                                contentPadding = PaddingValues(
                                    start = 16.dp,
                                    end = 16.dp,
                                    top = 8.dp,
                                    bottom = navigationBarBottom + 16.dp
                                ),
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                items(
                                    items = searchResults,
                                    key = { it.id }
                                ) { entry ->
                                    val commentState = commentsState[entry.id] ?: CommentState()

                                    EntryCard(
                                        entry = entry,
                                        canModify = isAdmin || entry.userId == currentUserId,
                                        onEdit = { },
                                        onDelete = { },
                                        onUpdateEntry = onUpdateEntry,
                                        onDeleteEntry = onDeleteEntry,
                                        currentUserId = currentUserId,
                                        isAdmin = isAdmin,
                                        comments = commentState.comments,
                                        commentsLoading = commentState.isLoading,
                                        commentsExpanded = commentState.isExpanded,
                                        onToggleComments = { onToggleComments(entry.id, entry.hashId) },
                                        onLoadComments = { onLoadComments(entry.id, entry.hashId) },
                                        onCreateComment = { text -> onCreateComment(entry.id, entry.hashId, text) },
                                        onUpdateComment = { commentId, text ->
                                            onUpdateComment(commentId, text, entry.id)
                                        },
                                        onDeleteComment = { commentId ->
                                            onDeleteComment(commentId, entry.id)
                                        },
                                        onClapComment = { commentId, count ->
                                            onClapComment(commentId, count, entry.id)
                                        },
                                        onReportComment = { commentId ->
                                            onReportComment(commentId, entry.id)
                                        }
                                    )
                                }

                                // Load more indicator
                                if (isLoading && searchResults.isNotEmpty()) {
                                    item {
                                        Box(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .padding(16.dp),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            CircularProgressIndicator(
                                                modifier = Modifier.size(24.dp),
                                                strokeWidth = 2.dp
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SearchHeader(
    query: String,
    onQueryChange: (String) -> Unit,
    onClear: () -> Unit,
    onDismiss: () -> Unit,
    onSearch: () -> Unit,
    focusRequester: FocusRequester
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surface)
            .padding(horizontal = 4.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Back button
        IconButton(onClick = onDismiss) {
            FaIcon(
                faIcon = FaIcons.ArrowLeft,
                size = 20.dp,
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }

        // Search field
        TextField(
            value = query,
            onValueChange = onQueryChange,
            modifier = Modifier
                .weight(1f)
                .focusRequester(focusRequester),
            placeholder = { Text("Search entries...") },
            leadingIcon = {
                FaIcon(
                    faIcon = FaIcons.Search,
                    size = 20.dp,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                )
            },
            trailingIcon = {
                if (query.isNotEmpty()) {
                    IconButton(onClick = onClear) {
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
            keyboardActions = KeyboardActions(onSearch = { onSearch() })
        )

        Spacer(modifier = Modifier.padding(end = 8.dp))
    }
}
