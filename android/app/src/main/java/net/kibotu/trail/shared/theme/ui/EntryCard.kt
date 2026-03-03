package net.kibotu.trail.shared.theme.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.MenuDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import net.kibotu.trail.shared.comment.Comment
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.toMediaItemDataList
import net.kibotu.trail.shared.util.openInCustomTab
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun EntryCard(
    entry: Entry,
    currentUserId: Int?,
    isAdmin: Boolean,
    baseUrl: String = "",
    showTags: Boolean = true,
    currentlyPlayingVideoId: String? = null,
    onVideoPlay: (String?) -> Unit = {},
    onCardClick: () -> Unit = {},
    onAvatarClick: () -> Unit = {},
    onUsernameClick: () -> Unit = {},
    onTagClick: (String) -> Unit = {},
    onClap: (Int) -> Unit = {},
    onShare: () -> Unit = {},
    onReport: () -> Unit = {},
    onMuteUser: () -> Unit = {},
    onEditEntry: (String) -> Unit = {},
    onDeleteEntry: () -> Unit = {},
    onToggleComments: () -> Unit = {},
    comments: List<Comment> = emptyList(),
    commentsLoading: Boolean = false,
    commentsExpanded: Boolean = false,
    onLoadComments: () -> Unit = {},
    onCreateComment: (String) -> Unit = {},
    onUpdateComment: (Int, String) -> Unit = { _, _ -> },
    onDeleteComment: (Int) -> Unit = {},
    onClapComment: (Int, Int) -> Unit = { _, _ -> },
    onReportComment: (Int) -> Unit = {},
    onMentionClick: (String) -> Unit = {},
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    var showMenu by remember { mutableStateOf(false) }
    var showEditDialog by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf(false) }
    val canModify = isAdmin || entry.userId == currentUserId
    val isOwnContent = entry.userId == currentUserId

    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable { onCardClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            // Header: avatar, name, date, menu
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 12.dp),
                verticalAlignment = Alignment.Top
            ) {
                AsyncImage(
                    model = entry.avatarUrl,
                    contentDescription = "Avatar",
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .clickable { onAvatarClick() }
                )

                Spacer(modifier = Modifier.width(12.dp))

                Column(modifier = Modifier.weight(1f)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.Top
                    ) {
                        Column {
                            Text(
                                text = entry.displayName,
                                fontWeight = FontWeight.SemiBold,
                                fontSize = 15.sp,
                                color = MaterialTheme.colorScheme.onSurface,
                                modifier = Modifier.clickable { onUsernameClick() }
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Text(
                                    text = formatRelativeTime(entry.createdAt),
                                    style = MaterialTheme.typography.labelSmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                                )
                                if (entry.updatedAt != null && entry.updatedAt != entry.createdAt) {
                                    Text(
                                        text = "• edited",
                                        style = MaterialTheme.typography.labelSmall,
                                        fontStyle = FontStyle.Italic,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                                    )
                                }
                            }
                        }

                        // Menu
                        Box {
                            IconButton(
                                onClick = { showMenu = true },
                                modifier = Modifier.size(32.dp)
                            ) {
                                FaIcon(
                                    faIcon = FaIcons.EllipsisV,
                                    size = 16.dp,
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                                )
                            }

                            DropdownMenu(
                                expanded = showMenu,
                                onDismissRequest = { showMenu = false }
                            ) {
                                if (canModify) {
                                    DropdownMenuItem(
                                        text = { Text("Edit") },
                                        onClick = {
                                            showMenu = false
                                            showEditDialog = true
                                        },
                                        leadingIcon = {
                                            FaIcon(faIcon = FaIcons.Edit, size = 16.dp, tint = MaterialTheme.colorScheme.onSurface)
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Delete") },
                                        onClick = {
                                            showMenu = false
                                            showDeleteDialog = true
                                        },
                                        leadingIcon = {
                                            FaIcon(faIcon = FaIcons.TrashAlt, size = 16.dp, tint = MaterialTheme.colorScheme.error)
                                        },
                                        colors = MenuDefaults.itemColors(textColor = MaterialTheme.colorScheme.error)
                                    )
                                }
                                if (!isOwnContent) {
                                    DropdownMenuItem(
                                        text = { Text("Report") },
                                        onClick = {
                                            showMenu = false
                                            onReport()
                                        },
                                        leadingIcon = {
                                            FaIcon(faIcon = FaIcons.Flag, size = 16.dp, tint = MaterialTheme.colorScheme.error)
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Mute User") },
                                        onClick = {
                                            showMenu = false
                                            onMuteUser()
                                        },
                                        leadingIcon = {
                                            FaIcon(faIcon = FaIcons.VolumeMute, size = 16.dp, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                        }
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // Text with @mention linking
            MentionText(
                text = entry.text,
                style = MaterialTheme.typography.bodyMedium,
                lineHeight = 22.sp,
                modifier = Modifier.padding(horizontal = 16.dp),
                onMentionClick = onMentionClick,
                onClick = onCardClick
            )

            // Tags
            if (showTags && entry.tags.isNotEmpty()) {
                Spacer(modifier = Modifier.height(8.dp))
                FlowRow(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    entry.tags.forEach { tag ->
                        Text(
                            text = "#${tag.name}",
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.primary,
                            modifier = Modifier.clickable { onTagClick(tag.name) }
                        )
                    }
                }
            }

            // Media
            if (entry.images.isNotEmpty()) {
                Spacer(modifier = Modifier.height(12.dp))
                Box(modifier = Modifier.padding(horizontal = 16.dp)) {
                    MediaGallery(
                        media = entry.images.toMediaItemDataList(),
                        baseUrl = baseUrl,
                        currentlyPlayingId = currentlyPlayingVideoId,
                        onVideoPlay = onVideoPlay
                    )
                }
            }

            // Link Preview
            if (entry.previewUrl != null && hasValidPreviewData(entry)) {
                Spacer(modifier = Modifier.height(12.dp))
                Box(modifier = Modifier.padding(horizontal = 16.dp)) {
                    LinkPreviewCard(
                        entry = entry,
                        onClick = { entry.previewUrl?.let { context.openInCustomTab(it) } }
                    )
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Action bar: claps, comments, views, share
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 8.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Clap button
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .clickable(enabled = !isOwnContent) { onClap(1) }
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    FaIcon(
                        faIcon = if (entry.userClapCount > 0) FaIcons.Heart else FaIcons.HeartRegular,
                        size = 18.dp,
                        tint = if (entry.userClapCount > 0)
                            MaterialTheme.colorScheme.error
                        else
                            MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                    )
                    if (entry.clapCount > 0) {
                        Text(
                            text = formatCount(entry.clapCount),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                        )
                    }
                }

                // Comments
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .clickable { onToggleComments() }
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    FaIcon(
                        faIcon = if (commentsExpanded) FaIcons.Comment else FaIcons.CommentRegular,
                        size = 18.dp,
                        tint = if (commentsExpanded)
                            MaterialTheme.colorScheme.primary
                        else
                            MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                    )
                    if (entry.commentCount > 0) {
                        Text(
                            text = formatCount(entry.commentCount),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                        )
                    }
                }

                // View count
                Row(
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    FaIcon(
                        faIcon = FaIcons.Eye,
                        size = 16.dp,
                        tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                    )
                    Text(
                        text = formatCount(entry.viewCount),
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                    )
                }

                // Share
                IconButton(
                    onClick = onShare,
                    modifier = Modifier.size(32.dp)
                ) {
                    FaIcon(
                        faIcon = FaIcons.ShareAlt,
                        size = 16.dp,
                        tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                    )
                }
            }

            // Comments section
            if (commentsExpanded) {
                CommentsSection(
                    entryId = entry.id,
                    commentCount = entry.commentCount,
                    comments = comments,
                    isLoading = commentsLoading,
                    currentUserId = currentUserId,
                    isAdmin = isAdmin,
                    baseUrl = baseUrl,
                    currentlyPlayingVideoId = currentlyPlayingVideoId,
                    onVideoPlay = onVideoPlay,
                    onLoadComments = onLoadComments,
                    onCreateComment = onCreateComment,
                    onUpdateComment = onUpdateComment,
                    onDeleteComment = onDeleteComment,
                    onClapComment = onClapComment,
                    onReportComment = onReportComment
                )
            }
        }
    }

    if (showEditDialog) {
        EditEntryDialog(
            entry = entry,
            onDismiss = { showEditDialog = false },
            onConfirm = { updatedText ->
                onEditEntry(updatedText)
                showEditDialog = false
            }
        )
    }

    if (showDeleteDialog) {
        DeleteConfirmationDialog(
            entry = entry,
            onDismiss = { showDeleteDialog = false },
            onConfirm = {
                onDeleteEntry()
                showDeleteDialog = false
            }
        )
    }
}

@Composable
fun LinkPreviewCard(
    entry: Entry,
    onClick: () -> Unit = {}
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            entry.previewImage?.let { imageUrl ->
                AsyncImage(
                    model = imageUrl,
                    contentDescription = "Link preview image",
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(160.dp)
                        .clip(RoundedCornerShape(topStart = 12.dp, topEnd = 12.dp))
                )
            }

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                entry.previewTitle?.let { title ->
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Medium,
                        maxLines = 2,
                        lineHeight = 18.sp,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }
                entry.previewDescription?.let { description ->
                    Text(
                        text = description,
                        style = MaterialTheme.typography.bodySmall,
                        maxLines = 2,
                        lineHeight = 16.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.8f)
                    )
                }
                Spacer(modifier = Modifier.height(2.dp))
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    FaIcon(
                        faIcon = FaIcons.Link,
                        size = 10.dp,
                        tint = MaterialTheme.colorScheme.primary.copy(alpha = 0.8f)
                    )
                    Text(
                        text = entry.previewSiteName ?: extractDomain(entry.previewUrl ?: ""),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.primary.copy(alpha = 0.8f),
                        maxLines = 1
                    )
                }
            }
        }
    }
}

@Composable
fun EditEntryDialog(
    entry: Entry,
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit
) {
    var editedText by remember { mutableStateOf(entry.text) }
    val maxCharacters = 280

    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Edit Entry") },
        text = {
            Column {
                androidx.compose.material3.OutlinedTextField(
                    value = editedText,
                    onValueChange = { if (it.length <= maxCharacters) editedText = it },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3,
                    maxLines = 8,
                    supportingText = {
                        Text("${editedText.length}/$maxCharacters", style = MaterialTheme.typography.bodySmall)
                    },
                    isError = editedText.length > maxCharacters
                )
            }
        },
        confirmButton = {
            androidx.compose.material3.TextButton(
                onClick = { onConfirm(editedText) },
                enabled = editedText.isNotBlank() && editedText.length <= maxCharacters && editedText != entry.text
            ) { Text("Save") }
        },
        dismissButton = {
            androidx.compose.material3.TextButton(onClick = onDismiss) { Text("Cancel") }
        }
    )
}

@Composable
fun DeleteConfirmationDialog(
    entry: Entry,
    onDismiss: () -> Unit,
    onConfirm: () -> Unit
) {
    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDismiss,
        icon = {
            FaIcon(faIcon = FaIcons.TrashAlt, size = 24.dp, tint = MaterialTheme.colorScheme.error)
        },
        title = { Text("Delete Entry?") },
        text = {
            Column {
                Text("Are you sure you want to delete this entry?")
                Spacer(modifier = Modifier.height(8.dp))
                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
                ) {
                    Text(
                        text = entry.text,
                        modifier = Modifier.padding(12.dp),
                        style = MaterialTheme.typography.bodyMedium,
                        maxLines = 3
                    )
                }
            }
        },
        confirmButton = {
            androidx.compose.material3.TextButton(onClick = onConfirm) {
                Text("Delete", color = MaterialTheme.colorScheme.error)
            }
        },
        dismissButton = {
            androidx.compose.material3.TextButton(onClick = onDismiss) { Text("Cancel") }
        }
    )
}

private fun hasValidPreviewData(entry: Entry): Boolean {
    val hasValidTitle = entry.previewTitle?.let { title ->
        title.length > 3 &&
                !title.lowercase().contains("just a moment") &&
                !title.lowercase().contains("please wait")
    } ?: false
    val hasValidDescription = entry.previewDescription?.let { it.length > 10 } ?: false
    return hasValidTitle || hasValidDescription || entry.previewImage != null
}

private fun extractDomain(url: String): String {
    return try {
        url.removePrefix("https://").removePrefix("http://").removePrefix("www.").split("/")[0]
    } catch (e: Exception) {
        url
    }
}

private fun formatRelativeTime(dateString: String): String {
    return try {
        val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
        val date = inputFormat.parse(dateString) ?: return dateString
        val now = Date()
        val diffInMillis = now.time - date.time
        val diffInSeconds = diffInMillis / 1000
        val diffInMinutes = diffInSeconds / 60
        val diffInHours = diffInMinutes / 60
        val diffInDays = diffInHours / 24
        when {
            diffInSeconds < 60 -> "just now"
            diffInMinutes < 60 -> "${diffInMinutes}m"
            diffInHours < 24 -> "${diffInHours}h"
            diffInDays < 7 -> "${diffInDays}d"
            else -> {
                val outputFormat = SimpleDateFormat("MMM dd", Locale.getDefault())
                outputFormat.format(date)
            }
        }
    } catch (e: Exception) {
        dateString
    }
}

private fun formatCount(count: Int): String {
    return when {
        count >= 1_000_000 -> String.format("%.1fM", count / 1_000_000.0)
        count >= 1_000 -> String.format("%.1fK", count / 1_000.0)
        else -> count.toString()
    }
}
