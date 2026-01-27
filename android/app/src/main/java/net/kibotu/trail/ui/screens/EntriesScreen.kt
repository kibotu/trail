package net.kibotu.trail.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import net.kibotu.trail.data.model.Entry
import androidx.compose.material3.HorizontalDivider
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EntriesScreen(
    entries: List<Entry>,
    isLoading: Boolean,
    userName: String,
    currentUserId: Int,
    isAdmin: Boolean,
    onSubmitEntry: (String) -> Unit,
    onUpdateEntry: (Int, String) -> Unit,
    onDeleteEntry: (Int) -> Unit,
    onRefresh: () -> Unit,
    onLogout: () -> Unit
) {
    var entryText by remember { mutableStateOf("") }
    val maxCharacters = 280
    var editingEntry by remember { mutableStateOf<Entry?>(null) }
    var showDeleteDialog by remember { mutableStateOf<Entry?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Trail") },
                actions = {
                    TextButton(onClick = onLogout) {
                        Text("Logout")
                    }
                }
            )
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Submit form at the top (like old Twitter)
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
                                onSubmitEntry(entryText)
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

            HorizontalDivider()

            // Entries list
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
                        text = "No entries yet. Be the first to post!",
                        style = MaterialTheme.typography.bodyLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(entries) { entry ->
                        EntryItem(
                            entry = entry,
                            canModify = isAdmin || entry.userId == currentUserId,
                            onEdit = { editingEntry = entry },
                            onDelete = { showDeleteDialog = entry }
                        )
                    }
                }
            }
        }
        
        // Edit Dialog
        editingEntry?.let { entry ->
            EditEntryDialog(
                entry = entry,
                onDismiss = { editingEntry = null },
                onConfirm = { updatedText ->
                    onUpdateEntry(entry.id, updatedText)
                    editingEntry = null
                }
            )
        }
        
        // Delete Confirmation Dialog
        showDeleteDialog?.let { entry ->
            DeleteConfirmationDialog(
                entry = entry,
                onDismiss = { showDeleteDialog = null },
                onConfirm = {
                    onDeleteEntry(entry.id)
                    showDeleteDialog = null
                }
            )
        }
    }
}

@Composable
fun EntryItem(
    entry: Entry,
    canModify: Boolean,
    onEdit: () -> Unit,
    onDelete: () -> Unit
) {
    var showMenu by remember { mutableStateOf(false) }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            // Avatar
            AsyncImage(
                model = entry.gravatarUrl,
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
                        text = entry.userName,
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
                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
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
                                    Icon(
                                        imageVector = Icons.Default.MoreVert,
                                        contentDescription = "More options",
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
                                            onEdit()
                                        },
                                        leadingIcon = {
                                            Icon(
                                                imageVector = Icons.Default.Edit,
                                                contentDescription = null
                                            )
                                        }
                                    )
                                    DropdownMenuItem(
                                        text = { Text("Delete") },
                                        onClick = {
                                            showMenu = false
                                            onDelete()
                                        },
                                        leadingIcon = {
                                            Icon(
                                                imageVector = Icons.Default.Delete,
                                                contentDescription = null,
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
            Icon(
                imageVector = Icons.Default.Delete,
                contentDescription = null,
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
