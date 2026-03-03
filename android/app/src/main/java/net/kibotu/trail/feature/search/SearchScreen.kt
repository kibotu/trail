package net.kibotu.trail.feature.search

import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Clear
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
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
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.paging.LoadState
import androidx.paging.compose.collectAsLazyPagingItems
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.ui.EntryCard
import net.kibotu.trail.shared.theme.ui.ShimmerFeed
import net.kibotu.trail.shared.theme.ui.staggeredFadeIn

@Composable
fun SearchScreen(
    onNavigateToEntry: (String) -> Unit,
    onNavigateToUser: (String) -> Unit,
    initialQuery: String = "",
    scrollConnection: NestedScrollConnection? = null,
    viewModel: SearchViewModel = viewModel(factory = SearchViewModel.Factory())
) {
    LaunchedEffect(initialQuery) {
        if (initialQuery.isNotBlank()) {
            viewModel.updateQuery(initialQuery)
        }
    }

    val query by viewModel.query.collectAsState()
    val results = viewModel.searchResults.collectAsLazyPagingItems()
    val authState by LocalAuthViewModel.current.state.collectAsState()
    val showTags by LocalThemePreferences.current.showEntryTags.collectAsState()
    val context = LocalContext.current

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    Column(modifier = Modifier.fillMaxSize()) {
        OutlinedTextField(
            value = query,
            onValueChange = { viewModel.updateQuery(it) },
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 16.dp, end = 16.dp, top = statusBarTop + 12.dp, bottom = 12.dp),
            placeholder = { Text("Search entries or #hashtags...") },
            leadingIcon = { Icon(Icons.Default.Search, contentDescription = "Search") },
            trailingIcon = {
                if (query.isNotEmpty()) {
                    IconButton(onClick = { viewModel.updateQuery("") }) {
                        Icon(Icons.Default.Clear, contentDescription = "Clear")
                    }
                }
            },
            shape = RoundedCornerShape(24.dp),
            singleLine = true,
            colors = OutlinedTextFieldDefaults.colors(
                focusedContainerColor = MaterialTheme.colorScheme.surface,
                unfocusedContainerColor = MaterialTheme.colorScheme.surface
            )
        )

        val searchState = when {
            query.isBlank() -> "hint"
            results.loadState.refresh is LoadState.Loading && results.itemCount == 0 -> "loading"
            results.itemCount == 0 -> "empty"
            else -> "content"
        }

        Crossfade(
            targetState = searchState,
            animationSpec = tween(300),
            label = "searchState"
        ) { state ->
            when (state) {
                "hint" -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            "Search for entries or use #hashtags",
                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                        )
                    }
                }
                "loading" -> {
                    Box(Modifier.fillMaxSize()) {
                        ShimmerFeed(modifier = Modifier.padding(top = 8.dp))
                    }
                }
                "empty" -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            "No results found for \"$query\"",
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
                else -> {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize()
                            .let { mod -> scrollConnection?.let { mod.nestedScroll(it) } ?: mod },
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = 100.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        items(
                            count = results.itemCount,
                            key = { index -> results[index]?.id ?: index }
                        ) { index ->
                            val entry = results[index] ?: return@items
                            EntryCard(
                                entry = entry,
                                modifier = Modifier
                                    .animateItem(
                                        fadeInSpec = tween(300),
                                        fadeOutSpec = tween(200),
                                        placementSpec = spring(
                                            dampingRatio = Spring.DampingRatioLowBouncy,
                                            stiffness = Spring.StiffnessLow
                                        )
                                    )
                                    .staggeredFadeIn(index),
                                currentUserId = authState.user?.id,
                                isAdmin = authState.user?.isAdmin ?: false,
                                baseUrl = BuildConfig.API_BASE_URL,
                                showTags = showTags,
                                onCardClick = { entry.hashId?.let { onNavigateToEntry(it) } },
                                onAvatarClick = { entry.userNickname?.let { onNavigateToUser(it) } },
                                onUsernameClick = { entry.userNickname?.let { onNavigateToUser(it) } },
                                onTagClick = { tag -> viewModel.updateQuery("#$tag") },
                                onClap = { count -> entry.hashId?.let { viewModel.addClaps(it, count) } },
                                onShare = { viewModel.shareEntry(context, entry) },
                                onReport = { entry.hashId?.let { viewModel.reportEntry(it) } },
                                onMuteUser = { viewModel.muteUser(entry.userId) },
                                onMentionClick = { nick -> onNavigateToUser(nick) }
                            )
                        }

                        if (results.loadState.append is LoadState.Loading) {
                            item(key = "loading_indicator") {
                                Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                                    CircularProgressIndicator()
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
