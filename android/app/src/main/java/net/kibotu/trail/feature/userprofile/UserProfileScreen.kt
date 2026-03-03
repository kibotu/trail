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
import net.kibotu.trail.shared.util.openInCustomTab
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit
import java.util.Locale

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
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    profileState.profile?.let { profile ->
                        item(key = "profile_header") {
                            Card(
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                            ) {
                                Column(
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    if (profile.headerImageUrl != null) {
                                        Box(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .height(180.dp)
                                        ) {
                                            AsyncImage(
                                                model = profile.headerImageUrl,
                                                contentDescription = "Header",
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .height(140.dp)
                                                    .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp)),
                                                contentScale = ContentScale.Crop
                                            )
                                            AsyncImage(
                                                model = profile.avatarUrl,
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
                                            model = profile.avatarUrl,
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
                                        // Name & nickname - left aligned like web
                                        Text(
                                            profile.name,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 22.sp
                                        )
                                        profile.nickname?.let {
                                            Text(
                                                "@$it",
                                                style = MaterialTheme.typography.bodyMedium,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant
                                            )
                                        }

                                        // Bio
                                        profile.bio?.let {
                                            Spacer(modifier = Modifier.height(10.dp))
                                            Text(
                                                it,
                                                style = MaterialTheme.typography.bodyMedium,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                                lineHeight = 22.sp
                                            )
                                        }

                                        Spacer(modifier = Modifier.height(12.dp))

                                        // Joined + Last post row
                                        Row(
                                            horizontalArrangement = Arrangement.spacedBy(16.dp),
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                FaIcon(
                                                    FaIcons.CalendarAlt,
                                                    size = 12.dp,
                                                    tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                                )
                                                Spacer(Modifier.width(5.dp))
                                                Text(
                                                    "Joined ${formatMonthYear(profile.createdAt)}",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                                )
                                            }
                                            profile.stats.lastEntryAt?.let { lastEntry ->
                                                Row(verticalAlignment = Alignment.CenterVertically) {
                                                    FaIcon(
                                                        FaIcons.PenFancy,
                                                        size = 12.dp,
                                                        tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                                    )
                                                    Spacer(Modifier.width(5.dp))
                                                    Text(
                                                        "Last post ${formatRelativeDate(lastEntry)}",
                                                        style = MaterialTheme.typography.labelSmall,
                                                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                                    )
                                                }
                                            }
                                        }

                                        // Inline stats row (like web: "2,951 Entries  13.5K Views  2,609 Claps")
                                        val statItems = buildList {
                                            if (profile.stats.entryCount > 0)
                                                add(formatCompactNumber(profile.stats.entryCount) to "Entries")
                                            if (profile.stats.totalEntryViews > 0)
                                                add(formatCompactNumber(profile.stats.totalEntryViews) to "Views")
                                            if (profile.stats.totalEntryClaps > 0)
                                                add(formatCompactNumber(profile.stats.totalEntryClaps) to "Claps")
                                            if (profile.stats.commentCount > 0)
                                                add(formatCompactNumber(profile.stats.commentCount) to "Comments")
                                        }

                                        if (statItems.isNotEmpty()) {
                                            Spacer(modifier = Modifier.height(6.dp))
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

                                    // Action buttons
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

private val inputFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")

private fun formatMonthYear(dateString: String): String {
    return try {
        val dateTime = LocalDateTime.parse(dateString, inputFormatter)
        dateTime.format(DateTimeFormatter.ofPattern("MMMM yyyy", Locale.ENGLISH))
    } catch (e: Exception) {
        dateString.take(10)
    }
}

private fun formatRelativeDate(dateString: String): String {
    return try {
        val dateTime = LocalDateTime.parse(dateString, inputFormatter)
        val now = LocalDateTime.now()
        val diffMinutes = ChronoUnit.MINUTES.between(dateTime, now)
        val diffHours = ChronoUnit.HOURS.between(dateTime, now)
        val diffDays = ChronoUnit.DAYS.between(dateTime, now)
        when {
            diffMinutes < 1 -> "just now"
            diffMinutes < 60 -> "${diffMinutes}m ago"
            diffHours < 24 -> "${diffHours}h ago"
            diffDays < 2 -> "yesterday"
            diffDays < 7 -> "${diffDays}d ago"
            diffDays < 30 -> "${diffDays / 7}w ago"
            diffDays < 365 -> "${diffDays / 30}mo ago"
            else -> "${diffDays / 365}y ago"
        }
    } catch (e: Exception) {
        dateString.take(10)
    }
}

/**
 * Compact number formatting: 1234 → "1,234", 13500 → "13.5K", 1200000 → "1.2M"
 */
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
        value >= 1_000 -> {
            String.format(Locale.US, "%,d", value)
        }
        else -> value.toString()
    }
}
