package net.kibotu.trail.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.MenuDefaults
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import net.kibotu.trail.data.model.Comment
import net.kibotu.trail.data.model.toMediaItemDataList
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit

@Composable
fun CommentsSection(
    entryId: Int,
    commentCount: Int,
    comments: List<Comment>,
    isLoading: Boolean,
    currentUserId: Int?,
    isAdmin: Boolean,
    baseUrl: String = "",
    currentlyPlayingVideoId: String? = null,
    onVideoPlay: (String?) -> Unit = {},
    onLoadComments: () -> Unit,
    onCreateComment: (String) -> Unit,
    onUpdateComment: (Int, String) -> Unit,
    onDeleteComment: (Int) -> Unit,
    onClapComment: (Int, Int) -> Unit,
    onReportComment: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    var commentText by remember { mutableStateOf("") }
    val maxCharacters = 140

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.15f))
    ) {
        // Subtle divider at top
        HorizontalDivider(
            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.1f),
            thickness = 1.dp
        )

        // Comment input (only if logged in)
        if (currentUserId != null) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                OutlinedTextField(
                    value = commentText,
                    onValueChange = {
                        if (it.length <= maxCharacters) {
                            commentText = it
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp)),
                    placeholder = {
                        Text(
                            "Add a comment...",
                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                        )
                    },
                    minLines = 2,
                    maxLines = 4,
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = MaterialTheme.colorScheme.primary,
                        unfocusedBorderColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f),
                        focusedContainerColor = MaterialTheme.colorScheme.surface,
                        unfocusedContainerColor = MaterialTheme.colorScheme.surface
                    ),
                    isError = commentText.length > maxCharacters
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "${commentText.length}/$maxCharacters",
                        style = MaterialTheme.typography.labelSmall,
                        color = if (commentText.length > maxCharacters)
                            MaterialTheme.colorScheme.error
                        else
                            MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                    )

                    Button(
                        onClick = {
                            if (commentText.isNotBlank() && commentText.length <= maxCharacters) {
                                onCreateComment(commentText)
                                commentText = ""
                            }
                        },
                        enabled = commentText.isNotBlank() && commentText.length <= maxCharacters,
                        shape = RoundedCornerShape(10.dp),
                        contentPadding = PaddingValues(horizontal = 20.dp, vertical = 8.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = MaterialTheme.colorScheme.primary,
                            disabledContainerColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.12f)
                        )
                    ) {
                        Text(
                            "Comment",
                            style = MaterialTheme.typography.labelMedium,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }
        }

        // Loading indicator
        if (isLoading) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(24.dp),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator(
                    modifier = Modifier.size(24.dp),
                    strokeWidth = 2.dp
                )
            }
        }

        // Comments list
        if (comments.isEmpty() && !isLoading) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(24.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "No comments yet. Be the first to comment!",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                )
            }
        } else {
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(0.dp)
            ) {
                comments.forEachIndexed { index, comment ->
                    CommentItem(
                        comment = comment,
                        canModify = currentUserId != null &&
                                (comment.userId == currentUserId || isAdmin),
                        baseUrl = baseUrl,
                        currentlyPlayingVideoId = currentlyPlayingVideoId,
                        onVideoPlay = onVideoPlay,
                        onEdit = { onUpdateComment(comment.id, it) },
                        onDelete = { onDeleteComment(comment.id) },
                        onClap = { count -> onClapComment(comment.id, count) },
                        onReport = { onReportComment(comment.id) }
                    )
                    if (index < comments.lastIndex) {
                        HorizontalDivider(
                            modifier = Modifier.padding(horizontal = 16.dp),
                            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.1f)
                        )
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(8.dp))
    }
}

@Composable
fun CommentItem(
    comment: Comment,
    canModify: Boolean,
    baseUrl: String = "",
    currentlyPlayingVideoId: String? = null,
    onVideoPlay: (String?) -> Unit = {},
    onEdit: (String) -> Unit,
    onDelete: () -> Unit,
    onClap: (Int) -> Unit,
    onReport: () -> Unit
) {
    var showMenu by remember { mutableStateOf(false) }
    var showEditDialog by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf(false) }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.Top
    ) {
        // Avatar
        AsyncImage(
            model = comment.avatarUrl,
            contentDescription = "Avatar",
            modifier = Modifier
                .size(36.dp)
                .clip(CircleShape)
        )

        Spacer(modifier = Modifier.width(12.dp))

        Column(modifier = Modifier.weight(1f)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Text(
                            text = comment.displayName,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = formatDate(comment.createdAt),
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                        )
                        // Show "edited" indicator if comment was modified
                        if (comment.updatedAt != null && comment.updatedAt != comment.createdAt) {
                            Text(
                                text = "• edited",
                                style = MaterialTheme.typography.labelSmall,
                                fontStyle = FontStyle.Italic,
                                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                            )
                        }
                    }
                }

                // Action menu
                Box {
                    IconButton(
                        onClick = { showMenu = true },
                        modifier = Modifier.size(28.dp)
                    ) {
                        FaIcon(
                            faIcon = FaIcons.EllipsisV,
                            size = 14.dp,
                            tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
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
                                    FaIcon(
                                        faIcon = FaIcons.Edit,
                                        size = 16.dp,
                                        tint = MaterialTheme.colorScheme.onSurface
                                    )
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("Delete") },
                                onClick = {
                                    showMenu = false
                                    showDeleteDialog = true
                                },
                                leadingIcon = {
                                    FaIcon(
                                        faIcon = FaIcons.TrashAlt,
                                        size = 16.dp,
                                        tint = MaterialTheme.colorScheme.error
                                    )
                                },
                                colors = MenuDefaults.itemColors(
                                    textColor = MaterialTheme.colorScheme.error
                                )
                            )
                        } else {
                            DropdownMenuItem(
                                text = { Text("Report") },
                                onClick = {
                                    showMenu = false
                                    onReport()
                                },
                                leadingIcon = {
                                    FaIcon(
                                        faIcon = FaIcons.Flag,
                                        size = 16.dp,
                                        tint = MaterialTheme.colorScheme.error
                                    )
                                }
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(4.dp))

            Text(
                text = comment.text,
                style = MaterialTheme.typography.bodySmall,
                lineHeight = 18.sp,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.9f)
            )

            // Comment media (images, GIFs, videos)
            comment.images?.let { images ->
                if (images.isNotEmpty()) {
                    Spacer(modifier = Modifier.height(8.dp))
                    MediaGallery(
                        media = images.toMediaItemDataList(),
                        baseUrl = baseUrl,
                        currentlyPlayingId = currentlyPlayingVideoId,
                        onVideoPlay = onVideoPlay
                    )
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            // Clap button - more compact
            Row(
                modifier = Modifier
                    .clip(RoundedCornerShape(16.dp))
                    .padding(vertical = 2.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                IconButton(
                    onClick = {
                        val newCount = if (comment.userClapCount > 0) 0 else 1
                        onClap(newCount)
                    },
                    modifier = Modifier.size(28.dp)
                ) {
                    FaIcon(
                        faIcon = if (comment.userClapCount > 0) FaIcons.Heart else FaIcons.HeartRegular,
                        size = 16.dp,
                        tint = if (comment.userClapCount > 0)
                            MaterialTheme.colorScheme.error
                        else
                            MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                    )
                }

                if (comment.clapCount > 0) {
                    Text(
                        text = comment.clapCount.toString(),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                    )
                }
            }
        }
    }

    // Edit dialog
    if (showEditDialog) {
        EditCommentDialog(
            comment = comment,
            onDismiss = { showEditDialog = false },
            onConfirm = { newText ->
                onEdit(newText)
                showEditDialog = false
            }
        )
    }

    // Delete dialog
    if (showDeleteDialog) {
        DeleteCommentDialog(
            comment = comment,
            onDismiss = { showDeleteDialog = false },
            onConfirm = {
                onDelete()
                showDeleteDialog = false
            }
        )
    }
}

@Composable
fun EditCommentDialog(
    comment: Comment,
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit
) {
    var editedText by remember { mutableStateOf(comment.text) }
    val maxCharacters = 140

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Edit Comment") },
        text = {
            Column {
                OutlinedTextField(
                    value = editedText,
                    onValueChange = {
                        if (it.length <= maxCharacters) {
                            editedText = it
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3,
                    maxLines = 8,
                    supportingText = {
                        Text(
                            text = "${editedText.length}/$maxCharacters",
                            style = MaterialTheme.typography.bodySmall
                        )
                    },
                    isError = editedText.length > maxCharacters
                )
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onConfirm(editedText) },
                enabled = editedText.isNotBlank() &&
                        editedText.length <= maxCharacters &&
                        editedText != comment.text
            ) {
                Text("Save")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Cancel")
            }
        }
    )
}

@Composable
fun DeleteCommentDialog(
    comment: Comment,
    onDismiss: () -> Unit,
    onConfirm: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = {
            FaIcon(
                faIcon = FaIcons.TrashAlt,
                size = 24.dp,
                tint = MaterialTheme.colorScheme.error
            )
        },
        title = { Text("Delete Comment?") },
        text = {
            Column {
                Text("Are you sure you want to delete this comment?")
                Spacer(modifier = Modifier.height(8.dp))
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                ) {
                    Text(
                        text = comment.text,
                        modifier = Modifier.padding(12.dp),
                        style = MaterialTheme.typography.bodyMedium,
                        maxLines = 3
                    )
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = onConfirm,
                colors = ButtonDefaults.textButtonColors(
                    contentColor = MaterialTheme.colorScheme.error
                )
            ) {
                Text("Delete")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Cancel")
            }
        }
    )
}

private fun formatDate(dateString: String): String {
    return try {
        val instant = Instant.parse(dateString)
        val now = Instant.now()
        val duration = ChronoUnit.SECONDS.between(instant, now)

        when {
            duration < 60 -> "just now"
            duration < 3600 -> "${duration / 60}m"
            duration < 86400 -> "${duration / 3600}h"
            duration < 604800 -> "${duration / 86400}d"
            else -> {
                val formatter = DateTimeFormatter.ofPattern("MMM d")
                    .withZone(ZoneId.systemDefault())
                formatter.format(instant)
            }
        }
    } catch (e: Exception) {
        dateString
    }
}
