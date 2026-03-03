package net.kibotu.trail.feature.userprofile

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
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBars
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.*
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
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
import net.kibotu.trail.shared.theme.ui.StatRow
import net.kibotu.trail.shared.util.openInCustomTab

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun UserProfileScreen(
    nickname: String,
    hazeState: HazeState,
    onNavigateBack: () -> Unit,
    onNavigateToEntry: (String) -> Unit,
    onNavigateToUser: (String) -> Unit = {},
    viewModel: UserProfileViewModel = viewModel(
        key = nickname,
        factory = UserProfileViewModel.Factory(nickname)
    )
) {
    val profileState by viewModel.state.collectAsState()
    val entries = viewModel.entries.collectAsLazyPagingItems()
    val authState by LocalAuthViewModel.current.state.collectAsState()
    val showTags by LocalThemePreferences.current.showEntryTags.collectAsState()
    val context = LocalContext.current
    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    Box(Modifier.fillMaxSize()) {
        when {
            profileState.isLoading -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            else -> {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 56.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    profileState.profile?.let { profile ->
                        item(key = "profile_header") {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                            ) {
                                Column(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalAlignment = Alignment.CenterHorizontally
                                ) {
                                    if (profile.headerImageUrl != null) {
                                        Box(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .height(140.dp)
                                                .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
                                        ) {
                                            AsyncImage(
                                                model = profile.headerImageUrl,
                                                contentDescription = "Header",
                                                modifier = Modifier.fillMaxSize(),
                                                contentScale = ContentScale.Crop
                                            )
                                        }
                                        AsyncImage(
                                            model = profile.avatarUrl,
                                            contentDescription = "Avatar",
                                            modifier = Modifier
                                                .size(80.dp)
                                                .offset(y = (-40).dp)
                                                .clip(CircleShape)
                                        )
                                        Spacer(modifier = Modifier.height((-28).dp))
                                    } else {
                                        Spacer(modifier = Modifier.height(20.dp))
                                        AsyncImage(
                                            model = profile.avatarUrl,
                                            contentDescription = "Avatar",
                                            modifier = Modifier.size(72.dp).clip(CircleShape)
                                        )
                                        Spacer(modifier = Modifier.height(12.dp))
                                    }

                                    Text(profile.name, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                                    profile.nickname?.let {
                                        Text(
                                            "@$it",
                                            style = MaterialTheme.typography.bodyMedium,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                    profile.bio?.let {
                                        Spacer(modifier = Modifier.height(8.dp))
                                        Text(
                                            it,
                                            style = MaterialTheme.typography.bodyMedium,
                                            modifier = Modifier.padding(horizontal = 20.dp)
                                        )
                                    }

                                    Spacer(modifier = Modifier.height(12.dp))

                                    FlowRow(
                                        modifier = Modifier.padding(horizontal = 20.dp),
                                        horizontalArrangement = Arrangement.spacedBy(16.dp),
                                        verticalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            FaIcon(FaIcons.CalendarAlt, size = 12.dp, tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f))
                                            Spacer(Modifier.width(4.dp))
                                            Text(
                                                "Joined ${profile.createdAt.take(10)}",
                                                style = MaterialTheme.typography.labelSmall,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                            )
                                        }
                                        profile.stats.previousLoginAt?.let { lastSeen ->
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                FaIcon(FaIcons.Clock, size = 12.dp, tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f))
                                                Spacer(Modifier.width(4.dp))
                                                Text(
                                                    "Last seen ${formatRelativeDate(lastSeen)}",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                                )
                                            }
                                        }
                                        profile.stats.lastEntryAt?.let { lastEntry ->
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                FaIcon(FaIcons.PenFancy, size = 12.dp, tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f))
                                                Spacer(Modifier.width(4.dp))
                                                Text(
                                                    "Last entry ${formatRelativeDate(lastEntry)}",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                                )
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(12.dp))

                                    Row(
                                        modifier = Modifier.padding(horizontal = 20.dp),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        if (authState.user?.id != profile.id) {
                                            OutlinedButton(
                                                onClick = {
                                                    if (profileState.isMuted) viewModel.unmuteUser()
                                                    else viewModel.muteUser()
                                                }
                                            ) {
                                                Text(if (profileState.isMuted) "Unmute" else "Mute")
                                            }
                                        }
                                        profile.nickname?.let { nick ->
                                            OutlinedButton(
                                                onClick = {
                                                    context.openInCustomTab("${BuildConfig.API_BASE_URL}api/users/$nick/rss")
                                                }
                                            ) {
                                                FaIcon(FaIcons.Rss, size = 14.dp, tint = MaterialTheme.colorScheme.primary)
                                                Spacer(Modifier.width(6.dp))
                                                Text("RSS")
                                            }
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(20.dp))
                                }
                            }
                        }

                        item(key = "user_stats") {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                            ) {
                                Column(modifier = Modifier.padding(20.dp)) {
                                    Text("Stats", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                    Spacer(modifier = Modifier.height(12.dp))
                                    StatRow("Entries", profile.stats.entryCount.toString())
                                    StatRow("Comments", profile.stats.commentCount.toString())
                                    StatRow("Links", profile.stats.linkCount.toString())
                                    StatRow("Entry Views", profile.stats.totalEntryViews.toString())
                                    StatRow("Entry Claps", profile.stats.totalEntryClaps.toString())
                                    StatRow("Profile Views", profile.stats.totalProfileViews.toString())
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
                            currentUserId = authState.user?.id,
                            isAdmin = authState.user?.isAdmin ?: false,
                            baseUrl = BuildConfig.API_BASE_URL,
                            showTags = showTags,
                            onCardClick = { entry.hashId?.let { onNavigateToEntry(it) } },
                            onClap = { count -> entry.hashId?.let { viewModel.addClaps(it, count) } },
                            onShare = { viewModel.shareEntry(context, entry) },
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

private fun formatRelativeDate(dateString: String): String {
    return try {
        val inputFormat = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.getDefault())
        val date = inputFormat.parse(dateString) ?: return dateString.take(10)
        val now = java.util.Date()
        val diffInDays = (now.time - date.time) / (1000 * 60 * 60 * 24)
        when {
            diffInDays < 1 -> "today"
            diffInDays < 2 -> "yesterday"
            diffInDays < 7 -> "${diffInDays}d ago"
            diffInDays < 30 -> "${diffInDays / 7}w ago"
            diffInDays < 365 -> "${diffInDays / 30}mo ago"
            else -> "${diffInDays / 365}y ago"
        }
    } catch (e: Exception) {
        dateString.take(10)
    }
}
