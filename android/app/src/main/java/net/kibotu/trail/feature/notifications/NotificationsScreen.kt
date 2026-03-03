package net.kibotu.trail.feature.notifications

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SwipeToDismissBox
import androidx.compose.material3.SwipeToDismissBoxValue
import androidx.compose.material3.Text
import androidx.compose.material3.rememberSwipeToDismissBoxState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.paging.LoadState
import androidx.paging.compose.collectAsLazyPagingItems
import coil3.compose.AsyncImage
import dev.chrisbanes.haze.HazeState
import dev.chrisbanes.haze.hazeEffect

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen(
    hazeState: HazeState? = null,
    onNavigateBack: (() -> Unit)? = null,
    onNavigateToEntry: (String) -> Unit,
    onNavigateToUser: (String) -> Unit,
    viewModel: NotificationsViewModel = viewModel(factory = NotificationsViewModel.Factory(LocalContext.current))
) {
    val notifications = viewModel.notifications.collectAsLazyPagingItems()
    val unreadCount by viewModel.unreadCount.collectAsState()
    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    Box(Modifier.fillMaxSize()) {
        when {
            notifications.loadState.refresh is LoadState.Loading -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            notifications.itemCount == 0 -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("No notifications", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            else -> {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 56.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(
                        count = notifications.itemCount,
                        key = { index -> "notification_${notifications[index]?.id ?: index}_$index" }
                    ) { index ->
                        val notification = notifications[index] ?: return@items
                        val dismissState = rememberSwipeToDismissBoxState(
                            confirmValueChange = { value ->
                                if (value == SwipeToDismissBoxValue.EndToStart) {
                                    viewModel.deleteNotification(notification.id)
                                    true
                                } else {
                                    false
                                }
                            }
                        )

                        SwipeToDismissBox(
                            state = dismissState,
                            backgroundContent = {
                                Box(
                                    modifier = Modifier
                                        .fillMaxSize()
                                        .clip(RoundedCornerShape(12.dp))
                                        .background(MaterialTheme.colorScheme.error)
                                        .padding(horizontal = 20.dp),
                                    contentAlignment = Alignment.CenterEnd
                                ) {
                                    Icon(
                                        Icons.Default.Delete,
                                        contentDescription = "Delete",
                                        tint = MaterialTheme.colorScheme.onError
                                    )
                                }
                            },
                            enableDismissFromStartToEnd = false
                        ) {
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable {
                                        viewModel.markRead(notification.id)
                                        when {
                                            notification.entryHashId != null -> onNavigateToEntry(notification.entryHashId)
                                            notification.actorNickname != null -> onNavigateToUser(notification.actorNickname)
                                        }
                                    },
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(
                                    containerColor = if (notification.isRead)
                                        MaterialTheme.colorScheme.surface
                                    else
                                        MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.3f)
                                )
                            ) {
                                Row(
                                    modifier = Modifier.padding(16.dp),
                                    verticalAlignment = Alignment.Top
                                ) {
                                    notification.actorAvatarUrl?.let {
                                        AsyncImage(
                                            model = it,
                                            contentDescription = null,
                                            modifier = Modifier.size(40.dp).clip(CircleShape)
                                        )
                                        Spacer(modifier = Modifier.width(12.dp))
                                    }
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            text = buildNotificationText(notification),
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = if (!notification.isRead) FontWeight.SemiBold else FontWeight.Normal
                                        )
                                        notification.entryText?.let {
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Text(
                                                text = it,
                                                style = MaterialTheme.typography.bodySmall,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                                maxLines = 2
                                            )
                                        }
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = notification.createdAt.take(10),
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                                        )
                                    }
                                }
                            }
                        }
                    }

                    if (notifications.loadState.append is LoadState.Loading) {
                        item(key = "loading_indicator") {
                            Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator()
                            }
                        }
                    }
                }
            }
        }

        if (onNavigateBack != null) {
            val hazeBackgroundColor = MaterialTheme.colorScheme.surfaceContainerHigh.copy(alpha = 0.6f)
            val backButtonModifier = Modifier
                .statusBarsPadding()
                .padding(start = 12.dp, top = 8.dp)
                .size(40.dp)
                .align(Alignment.TopStart)
                .clip(CircleShape)
                .let { mod ->
                    if (hazeState != null) {
                        mod.hazeEffect(state = hazeState) {
                            backgroundColor = hazeBackgroundColor
                        }
                    } else {
                        mod
                    }
                }
                .clickable(onClick = onNavigateBack)

            Box(
                modifier = backButtonModifier,
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

        if (unreadCount > 0) {
            Button(
                onClick = { viewModel.markAllRead() },
                modifier = Modifier
                    .statusBarsPadding()
                    .padding(end = 12.dp, top = 8.dp)
                    .align(Alignment.TopEnd),
                shape = RoundedCornerShape(20.dp)
            ) {
                Text("Mark all read")
            }
        }
    }
}

private fun buildNotificationText(notification: net.kibotu.trail.shared.notification.Notification): String {
    val actorName = notification.actorName ?: "Someone"
    return when (notification.type) {
        "clap" -> {
            val count = notification.clapCount?.let { " ($it)" } ?: ""
            "$actorName clapped on your entry$count"
        }
        "comment" -> "$actorName commented on your entry"
        "mention" -> "$actorName mentioned you"
        "follow" -> "$actorName followed you"
        else -> notification.message ?: "$actorName interacted with your content"
    }
}
