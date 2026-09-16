package net.kibotu.trail.feature.collection

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.paging.LoadState
import androidx.paging.compose.collectAsLazyPagingItems
import coil3.compose.AsyncImage
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import dev.chrisbanes.haze.HazeState
import dev.chrisbanes.haze.hazeEffect
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.ui.EntryCard
import net.kibotu.trail.shared.theme.ui.ShimmerFeed
import net.kibotu.trail.shared.theme.ui.staggeredFadeIn
import net.kibotu.trail.shared.util.openInCustomTab
import net.kibotu.trail.shared.util.shareEntry

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun CollectionScreen(
    slug: String,
    hazeState: HazeState,
    onNavigateBack: () -> Unit,
    onNavigateToEntry: (String) -> Unit,
    onNavigateToUser: (String) -> Unit = {},
    viewModel: CollectionViewModel = viewModel(
        key = slug,
        factory = CollectionViewModel.Factory(slug)
    )
) {
    val collectionState by viewModel.state.collectAsState()
    val entries = viewModel.entries.collectAsLazyPagingItems()
    val authState by LocalAuthViewModel.current.state.collectAsState()
    val showTags by LocalThemePreferences.current.showEntryTags.collectAsState()
    val context = LocalContext.current
    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
    val baseUrl = BuildConfig.API_BASE_URL

    fun resolveUrl(url: String?): String? = url?.let { if (it.startsWith("http")) it else "$baseUrl$it" }

    Box(Modifier.fillMaxSize()) {
        Crossfade(
            targetState = collectionState.isLoading,
            animationSpec = tween(300),
            label = "collectionState"
        ) { isLoading ->
            if (isLoading) {
                Box(Modifier.fillMaxSize()) {
                    ShimmerFeed(modifier = Modifier.padding(top = statusBarTop + 56.dp))
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 56.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    collectionState.collection?.let { collection ->
                        item(key = "collection_header") {
                            val screenHeightDp = LocalConfiguration.current.screenHeightDp.dp
                            val headerContainerHeight = (screenHeightDp * 0.25f).coerceIn(140.dp, 200.dp)
                            val headerImageHeight = (headerContainerHeight - 40.dp).coerceAtLeast(100.dp)
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                            ) {
                                Column(modifier = Modifier.fillMaxWidth()) {
                                    val headerUrl = resolveUrl(collection.headerImageUrl)
                                    val avatarUrl = resolveUrl(collection.avatarUrl)
                                    if (headerUrl != null) {
                                        Box(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .height(headerContainerHeight)
                                        ) {
                                            AsyncImage(
                                                model = headerUrl,
                                                contentDescription = "Header",
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .height(headerImageHeight)
                                                    .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp)),
                                                contentScale = ContentScale.Crop
                                            )
                                            AsyncImage(
                                                model = avatarUrl,
                                                contentDescription = "Avatar",
                                                modifier = Modifier
                                                    .padding(start = 20.dp)
                                                    .size(80.dp)
                                                    .align(Alignment.BottomStart)
                                                    .clip(CircleShape),
                                                contentScale = ContentScale.Crop
                                            )
                                        }
                                    } else {
                                        Spacer(modifier = Modifier.height(20.dp))
                                        AsyncImage(
                                            model = avatarUrl,
                                            contentDescription = "Avatar",
                                            modifier = Modifier
                                                .padding(start = 20.dp)
                                                .size(72.dp)
                                                .clip(CircleShape),
                                            contentScale = ContentScale.Crop
                                        )
                                        Spacer(modifier = Modifier.height(12.dp))
                                    }

                                    Column(modifier = Modifier.padding(horizontal = 20.dp)) {
                                        Text(
                                            collection.name,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 22.sp
                                        )

                                        collection.bio?.let {
                                            Spacer(modifier = Modifier.height(10.dp))
                                            Text(
                                                it,
                                                style = MaterialTheme.typography.bodyMedium,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                                lineHeight = 22.sp
                                            )
                                        }

                                        if (collection.tags.isNotEmpty()) {
                                            var tagsExpanded by remember { mutableStateOf(false) }
                                            Spacer(modifier = Modifier.height(10.dp))
                                            Row(
                                                modifier = Modifier.clickable { tagsExpanded = !tagsExpanded },
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                                            ) {
                                                FaIcon(
                                                    faIcon = if (tagsExpanded) FaIcons.ChevronDown else FaIcons.ChevronRight,
                                                    size = 12.dp,
                                                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                                                )
                                                Text(
                                                    "Tags (${collection.tags.size})",
                                                    style = MaterialTheme.typography.labelMedium,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                                )
                                            }
                                            AnimatedVisibility(visible = tagsExpanded) {
                                                FlowRow(
                                                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                                                    verticalArrangement = Arrangement.spacedBy(4.dp)
                                                ) {
                                                    collection.tags.forEach { tag ->
                                                        Text(
                                                            text = "#${tag.name}",
                                                            style = MaterialTheme.typography.labelMedium,
                                                            color = MaterialTheme.colorScheme.primary
                                                        )
                                                    }
                                                }
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(12.dp))

                                        val statItems = buildList {
                                            if (collection.entryCount > 0)
                                                add(formatCompactNumber(collection.entryCount) to "Entries")
                                            if (collection.viewCount > 0)
                                                add(formatCompactNumber(collection.viewCount) to "Views")
                                        }

                                        if (statItems.isNotEmpty()) {
                                            FlowRow(
                                                horizontalArrangement = Arrangement.spacedBy(14.dp),
                                                verticalArrangement = Arrangement.spacedBy(2.dp)
                                            ) {
                                                statItems.forEach { (value, label) ->
                                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                                        Text(
                                                            value,
                                                            fontWeight = FontWeight.Bold,
                                                            fontSize = 14.sp,
                                                            color = MaterialTheme.colorScheme.primary
                                                        )
                                                        Spacer(Modifier.width(4.dp))
                                                        Text(
                                                            label,
                                                            fontSize = 13.sp,
                                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                                        )
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(14.dp))

                                    Row(
                                        modifier = Modifier.padding(horizontal = 20.dp),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        OutlinedButton(
                                            onClick = {
                                                context.openInCustomTab("${BuildConfig.API_BASE_URL}api/collections/$slug/rss")
                                            }
                                        ) {
                                            FaIcon(FaIcons.Rss, size = 14.dp, tint = MaterialTheme.colorScheme.primary)
                                            Spacer(Modifier.width(6.dp))
                                            Text("RSS")
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(20.dp))
                                }
                            }
                        }
                    }

                    items(
                        count = entries.itemCount,
                        key = { index -> "entry_$index" }
                    ) { index ->
                        val entry = entries[index] ?: return@items
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
                            onClap = { count -> entry.hashId?.let { viewModel.addClaps(it, count) } },
                            onShare = { shareEntry(context, entry) },
                            onMentionClick = { nick -> onNavigateToUser(nick) }
                        )
                    }

                    if (entries.loadState.append is LoadState.Loading) {
                        item(key = "loading_indicator") {
                            Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator()
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

private fun formatCompactNumber(value: Int): String {
    return when {
        value >= 1_000_000 -> {
            val formatted = value / 100_000 / 10.0
            if (formatted == formatted.toLong().toDouble()) "${formatted.toLong()}M"
            else "${formatted}M"
        }
        value >= 10_000 -> {
            val formatted = value / 100 / 10.0
            if (formatted == formatted.toLong().toDouble()) "${formatted.toLong()}K"
            else "${formatted}K"
        }
        value >= 1_000 -> String.format("%,d", value)
        else -> value.toString()
    }
}
