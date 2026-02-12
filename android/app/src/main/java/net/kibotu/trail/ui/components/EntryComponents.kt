package net.kibotu.trail.ui.components

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.MenuDefaults
import androidx.compose.material3.OutlinedTextField
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import net.kibotu.trail.data.model.Comment
import net.kibotu.trail.data.model.Entry
import net.kibotu.trail.ui.viewmodel.CommentState
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun EntryList(
    entries: List<Entry>,
    isLoading: Boolean,
    currentUserId: Int?,
    isAdmin: Boolean,
    onUpdateEntry: (Int, String) -> Unit,
    onDeleteEntry: (Int) -> Unit,
    commentsState: Map<Int, CommentState> = emptyMap(),
    onToggleComments: (Int, String?) -> Unit = { _, _ -> },
    onLoadComments: (Int, String?) -> Unit = { _, _ -> },
    onCreateComment: (Int, String?, String) -> Unit = { _, _, _ -> },
    onUpdateComment: (Int, String, Int) -> Unit = { _, _, _ -> },
    onDeleteComment: (Int, Int) -> Unit = { _, _ -> },
    onClapComment: (Int, Int, Int) -> Unit = { _, _, _ -> },
    onReportComment: (Int, Int) -> Unit = { _, _ -> },
    contentPadding: PaddingValues = PaddingValues(16.dp),
    emptyMessage: String = "No entries yet. Be the first to post!"
) {
    if (isLoading && entries.isEmpty()) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            CircularProgressIndicator()
        }
    } else if (entries.isEmpty()) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = emptyMessage,
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    } else {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = contentPadding,
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(entries) { entry ->
                val commentState = commentsState[entry.id] ?: CommentState()

                EntryCard(
                    entry = entry,
                    canModify = isAdmin || entry.userId == currentUserId,
                    onEdit = { /* handled by parent */ },
                    onDelete = { /* handled by parent */ },
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
                    onDeleteComment = { commentId -> onDeleteComment(commentId, entry.id) },
                    onClapComment = { commentId, count ->
                        onClapComment(commentId, count, entry.id)
                    },
                    onReportComment = { commentId -> onReportComment(commentId, entry.id) }
                )
            }
        }
    }
}

@Composable
fun EntryCard(
    entry: Entry,
    canModify: Boolean,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
    onUpdateEntry: (Int, String) -> Unit,
    onDeleteEntry: (Int) -> Unit,
    currentUserId: Int? = null,
    isAdmin: Boolean = false,
    comments: List<Comment> = emptyList(),
    commentsLoading: Boolean = false,
    commentsExpanded: Boolean = false,
    onToggleComments: () -> Unit = {},
    onLoadComments: () -> Unit = {},
    onCreateComment: (String) -> Unit = {},
    onUpdateComment: (Int, String) -> Unit = { _, _ -> },
    onDeleteComment: (Int) -> Unit = {},
    onClapComment: (Int, Int) -> Unit = { _, _ -> },
    onReportComment: (Int) -> Unit = {}
) {
    var showMenu by remember { mutableStateOf(false) }
    var editingEntry by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf(false) }

    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                // Avatar
                AsyncImage(
                    model = entry.avatarUrl,
                    contentDescription = "Avatar",
                    modifier = Modifier
                        .size(48.dp)
                        .clip(CircleShape)
                )

                Spacer(modifier = Modifier.width(12.dp))

                Column(modifier = Modifier.weight(1f)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = entry.displayName,
                            fontWeight = FontWeight.Bold,
                            fontSize = 16.sp
                        )

                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Column(
                                horizontalAlignment = Alignment.End
                            ) {
                                Text(
                                    text = formatDate(entry.createdAt),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )

                                // Show "edited" indicator if entry was modified
                                if (entry.updatedAt != null && entry.updatedAt != entry.createdAt) {
                                    Text(
                                        text = "edited ${formatRelativeTime(entry.updatedAt)}",
                                        style = MaterialTheme.typography.labelSmall,
                                        fontStyle = FontStyle.Italic,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(
                                            alpha = 0.7f
                                        )
                                    )
                                }
                            }

                            // Action menu for entry creator or admin
                            if (canModify) {
                                Box {
                                    IconButton(
                                        onClick = { showMenu = true },
                                        modifier = Modifier.size(24.dp)
                                    ) {
                                        FaIcon(
                                            faIcon = FaIcons.EllipsisV,
                                            size = 16.dp,
                                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }

                                    DropdownMenu(
                                        expanded = showMenu,
                                        onDismissRequest = { showMenu = false }
                                    ) {
                                        DropdownMenuItem(
                                            text = { Text("Edit") },
                                            onClick = {
                                                showMenu = false
                                                editingEntry = true
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
                                    }
                                }
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(4.dp))

                    Text(
                        text = entry.text,
                        style = MaterialTheme.typography.bodyMedium
                    )

                    // URL Preview Card (only if we have valid preview data)
                    if (entry.previewUrl != null && hasValidPreviewData(entry)) {
                        Spacer(modifier = Modifier.height(12.dp))
                        LinkPreviewCard(entry = entry)
                    }
                }
            }

            // Action bar with chat icon
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.Start,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Chat/Comment icon button
                Row(
                    modifier = Modifier
                        .clickable { onToggleComments() }
                        .padding(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    FaIcon(
                        faIcon = if (commentsExpanded) FaIcons.Comment else FaIcons.CommentRegular,
                        size = 20.dp,
                        tint = if (commentsExpanded)
                            MaterialTheme.colorScheme.primary
                        else
                            MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    if (entry.commentCount > 0) {
                        Text(
                            text = entry.commentCount.toString(),
                            style = MaterialTheme.typography.bodySmall,
                            color = if (commentsExpanded)
                                MaterialTheme.colorScheme.primary
                            else
                                MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }

            // Comments Section (conditionally shown)
            if (commentsExpanded) {
                CommentsSection(
                    entryId = entry.id,
                    commentCount = entry.commentCount,
                    comments = comments,
                    isLoading = commentsLoading,
                    currentUserId = currentUserId,
                    isAdmin = isAdmin,
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

    // Edit Dialog
    if (editingEntry) {
        EditEntryDialog(
            entry = entry,
            onDismiss = { editingEntry = false },
            onConfirm = { updatedText ->
                onUpdateEntry(entry.id, updatedText)
                editingEntry = false
            }
        )
    }

    // Delete Confirmation Dialog
    if (showDeleteDialog) {
        DeleteConfirmationDialog(
            entry = entry,
            onDismiss = { showDeleteDialog = false },
            onConfirm = {
                onDeleteEntry(entry.id)
                showDeleteDialog = false
            }
        )
    }
}

@Composable
fun LinkPreviewCard(entry: Entry) {
    val context = LocalContext.current

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable {
                entry.previewUrl?.let { url ->
                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                    context.startActivity(intent)
                }
            },
        shape = RoundedCornerShape(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(
            modifier = Modifier.fillMaxWidth()
        ) {
            // Preview Image (if available)
            entry.previewImage?.let { imageUrl ->
                AsyncImage(
                    model = imageUrl,
                    contentDescription = "Link preview image",
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(180.dp)
                        .clip(RoundedCornerShape(topStart = 8.dp, topEnd = 8.dp))
                )
            }

            // Preview Content
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp)
            ) {
                // Title
                entry.previewTitle?.let { title ->
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 2,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                }

                // Description
                entry.previewDescription?.let { description ->
                    Text(
                        text = description,
                        style = MaterialTheme.typography.bodySmall,
                        maxLines = 3,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.8f)
                    )
                    Spacer(modifier = Modifier.height(6.dp))
                }

                // Site Name / URL
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = "🔗",
                        style = MaterialTheme.typography.bodySmall
                    )
                    Text(
                        text = entry.previewSiteName ?: extractDomain(entry.previewUrl ?: ""),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.primary,
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
    val maxCharacters = 140

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Edit Entry") },
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
                enabled = editedText.isNotBlank() && editedText.length <= maxCharacters && editedText != entry.text
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
fun DeleteConfirmationDialog(
    entry: Entry,
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
        title = { Text("Delete Entry?") },
        text = {
            Column {
                Text("Are you sure you want to delete this entry?")
                Spacer(modifier = Modifier.height(8.dp))
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                    )
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
            TextButton(
                onClick = onConfirm
            ) {
                Text("Delete", color = MaterialTheme.colorScheme.error)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Cancel")
            }
        }
    )
}

// Helper functions
private fun hasValidPreviewData(entry: Entry): Boolean {
    val hasValidTitle = entry.previewTitle?.let { title ->
        title.length > 3 &&
                !title.lowercase().contains("just a moment") &&
                !title.lowercase().contains("please wait")
    } ?: false

    val hasValidDescription = entry.previewDescription?.let { desc ->
        desc.length > 10
    } ?: false

    // Show card if we have at least title, description, OR image
    return hasValidTitle || hasValidDescription || entry.previewImage != null
}

private fun extractDomain(url: String): String {
    return try {
        val domain = url
            .removePrefix("https://")
            .removePrefix("http://")
            .removePrefix("www.")
            .split("/")[0]
        domain
    } catch (e: Exception) {
        url
    }
}

private fun formatDate(dateString: String): String {
    return try {
        val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
        val outputFormat = SimpleDateFormat("MMM dd, yyyy", Locale.getDefault())
        val date = inputFormat.parse(dateString)
        date?.let { outputFormat.format(it) } ?: dateString
    } catch (e: Exception) {
        dateString
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
            diffInMinutes < 60 -> "${diffInMinutes}m ago"
            diffInHours < 24 -> "${diffInHours}h ago"
            diffInDays < 7 -> "${diffInDays}d ago"
            else -> formatDate(dateString)
        }
    } catch (e: Exception) {
        dateString
    }
}
