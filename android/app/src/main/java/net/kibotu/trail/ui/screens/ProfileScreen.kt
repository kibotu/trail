package net.kibotu.trail.ui.screens

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
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import net.kibotu.trail.data.model.ProfileEntry
import net.kibotu.trail.data.storage.ThemePreferences
import net.kibotu.trail.ui.viewmodel.TrailViewModel
import java.text.SimpleDateFormat
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(
    viewModel: TrailViewModel,
    themePreferences: ThemePreferences,
    onLogout: () -> Unit,
    scrollConnection: NestedScrollConnection
) {
    val uiState by viewModel.uiState.collectAsState()
    val profileState by viewModel.profileState.collectAsState()
    val profileLoading by viewModel.profileLoading.collectAsState()

    var nicknameText by remember { mutableStateOf("") }
    var bioText by remember { mutableStateOf("") }
    var isEditingNickname by remember { mutableStateOf(false) }
    var isEditingBio by remember { mutableStateOf(false) }

    val isDarkTheme =
        MaterialTheme.colorScheme.background == androidx.compose.ui.graphics.Color(0xFF0F172A)
    val isAuthenticated = uiState is net.kibotu.trail.ui.viewmodel.UiState.Entries

    // Initialize edit fields when profile loads
    LaunchedEffect(profileState) {
        profileState?.let { profile ->
            nicknameText = profile.nickname ?: ""
            bioText = profile.bio ?: ""
        }
    }

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
    val navigationBarBottom = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    Scaffold(
        contentWindowInsets = WindowInsets(0, 0, 0, 0)
    ) { _ ->
        if (!isAuthenticated) {
            // Show login prompt for unauthenticated users
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .systemBarsPadding(),
                contentAlignment = Alignment.Center
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Text(
                        text = "Sign in to view your profile",
                        style = MaterialTheme.typography.headlineSmall
                    )
                    Button(onClick = { viewModel.navigateToLogin() }) {
                        Text("Sign In")
                    }
                }
            }
        } else if (profileLoading && profileState == null) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .systemBarsPadding(),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator()
            }
        } else {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 16.dp)
                    .nestedScroll(scrollConnection),
                contentPadding = PaddingValues(
                    top = statusBarTop + 16.dp,
                    bottom = navigationBarBottom + 16.dp
                ),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                item {
                    // Profile header
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(24.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            // Avatar
                            AsyncImage(
                                model = profileState?.avatarUrl,
                                contentDescription = "Profile avatar",
                                modifier = Modifier
                                    .size(96.dp)
                                    .clip(CircleShape)
                            )

                            Spacer(modifier = Modifier.height(16.dp))

                            // Name
                            Text(
                                text = profileState?.name ?: "",
                                style = MaterialTheme.typography.headlineSmall,
                                fontWeight = FontWeight.Bold
                            )

                            Spacer(modifier = Modifier.height(4.dp))

                            // Nickname with edit
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                if (isEditingNickname) {
                                    OutlinedTextField(
                                        value = nicknameText,
                                        onValueChange = { nicknameText = it },
                                        modifier = Modifier.width(200.dp),
                                        placeholder = { Text("@nickname") },
                                        singleLine = true,
                                        prefix = { Text("@") }
                                    )
                                    IconButton(
                                        onClick = {
                                            viewModel.updateProfile(
                                                nickname = nicknameText,
                                                bio = null
                                            )
                                            isEditingNickname = false
                                        },
                                        enabled = nicknameText.isNotBlank()
                                    ) {
                                        Text("Save", style = MaterialTheme.typography.bodySmall)
                                    }
                                } else {
                                    Text(
                                        text = "@${profileState?.nickname ?: "set_nickname"}",
                                        style = MaterialTheme.typography.bodyLarge,
                                        color = MaterialTheme.colorScheme.primary
                                    )
                                    IconButton(
                                        onClick = { isEditingNickname = true },
                                        modifier = Modifier.size(24.dp)
                                    ) {
                                        FaIcon(
                                            faIcon = FaIcons.Edit,
                                            size = 14.dp,
                                            tint = MaterialTheme.colorScheme.onSurface
                                        )
                                    }
                                }
                            }

                            Spacer(modifier = Modifier.height(8.dp))

                            // Email
                            Text(
                                text = profileState?.email ?: "",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )

                            Spacer(modifier = Modifier.height(8.dp))

                            // Member since
                            Text(
                                text = "Member since ${formatDate(profileState?.createdAt ?: "")}",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }

                item {
                    // Bio section
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp)
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "Bio",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.SemiBold
                                )
                                if (!isEditingBio) {
                                    IconButton(
                                        onClick = { isEditingBio = true },
                                        modifier = Modifier.size(32.dp)
                                    ) {
                                        FaIcon(
                                            faIcon = FaIcons.Edit,
                                            size = 18.dp,
                                            tint = MaterialTheme.colorScheme.onSurface
                                        )
                                    }
                                }
                            }

                            Spacer(modifier = Modifier.height(8.dp))

                            if (isEditingBio) {
                                OutlinedTextField(
                                    value = bioText,
                                    onValueChange = { if (it.length <= 160) bioText = it },
                                    modifier = Modifier.fillMaxWidth(),
                                    placeholder = { Text("Tell us about yourself...") },
                                    minLines = 3,
                                    maxLines = 5,
                                    supportingText = {
                                        Text("${bioText.length}/160")
                                    }
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(
                                        8.dp,
                                        Alignment.End
                                    )
                                ) {
                                    OutlinedButton(onClick = {
                                        bioText = profileState?.bio ?: ""
                                        isEditingBio = false
                                    }) {
                                        Text("Cancel")
                                    }
                                    Button(
                                        onClick = {
                                            viewModel.updateProfile(nickname = null, bio = bioText)
                                            isEditingBio = false
                                        }
                                    ) {
                                        Text("Save")
                                    }
                                }
                            } else {
                                Text(
                                    text = profileState?.bio?.ifBlank { "No bio yet. Click edit to add one." }
                                        ?: "No bio yet. Click edit to add one.",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = if (profileState?.bio.isNullOrBlank())
                                        MaterialTheme.colorScheme.onSurfaceVariant
                                    else
                                        MaterialTheme.colorScheme.onSurface
                                )
                            }
                        }
                    }
                }

                item {
                    // Stats section
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp)
                        ) {
                            Text(
                                text = "Statistics",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.SemiBold
                            )

                            Spacer(modifier = Modifier.height(16.dp))

                            // Stats grid
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceEvenly
                            ) {
                                StatItem(
                                    label = "Entries",
                                    value = profileState?.stats?.entryCount?.toString() ?: "0"
                                )
                                StatItem(
                                    label = "Comments",
                                    value = profileState?.stats?.commentCount?.toString() ?: "0"
                                )
                                StatItem(
                                    label = "Links",
                                    value = profileState?.stats?.linkCount?.toString() ?: "0"
                                )
                            }

                            Spacer(modifier = Modifier.height(16.dp))
                            HorizontalDivider()
                            Spacer(modifier = Modifier.height(16.dp))

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceEvenly
                            ) {
                                StatItem(
                                    label = "Entry Views",
                                    value = profileState?.stats?.totalEntryViews?.toString() ?: "0"
                                )
                                StatItem(
                                    label = "Entry Claps",
                                    value = profileState?.stats?.totalEntryClaps?.toString() ?: "0"
                                )
                            }
                        }
                    }
                }

                // Top entries by claps
                profileState?.stats?.topEntriesByClaps?.takeIf { it.isNotEmpty() }
                    ?.let { topEntries ->
                        item {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                            ) {
                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(16.dp)
                                ) {
                                    Text(
                                        text = "Top Entries by Claps",
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.SemiBold
                                    )
                                    Spacer(modifier = Modifier.height(8.dp))
                                    topEntries.take(3).forEach { entry ->
                                        TopEntryItem(entry = entry)
                                        Spacer(modifier = Modifier.height(8.dp))
                                    }
                                }
                            }
                        }
                    }

                // Top entries by views
                profileState?.stats?.topEntriesByViews?.takeIf { it.isNotEmpty() }
                    ?.let { topEntries ->
                        item {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                            ) {
                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(16.dp)
                                ) {
                                    Text(
                                        text = "Top Entries by Views",
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.SemiBold
                                    )
                                    Spacer(modifier = Modifier.height(8.dp))
                                    topEntries.take(3).forEach { entry ->
                                        TopEntryItem(entry = entry)
                                        Spacer(modifier = Modifier.height(8.dp))
                                    }
                                }
                            }
                        }
                    }

                item {
                    // Theme toggle card
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(
                                    text = "Theme",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.SemiBold
                                )
                                Text(
                                    text = if (isDarkTheme) "Dark mode" else "Light mode",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                            IconButton(onClick = { themePreferences.toggleTheme() }) {
                                FaIcon(
                                    faIcon = if (isDarkTheme) FaIcons.Sun else FaIcons.Moon,
                                    size = 24.dp,
                                    tint = MaterialTheme.colorScheme.primary
                                )
                            }
                        }
                    }
                }

                item {
                    // Logout button
                    Button(
                        onClick = onLogout,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Logout")
                    }
                }

                // Bottom spacing
                item {
                    Spacer(modifier = Modifier.height(32.dp))
                }
            }
        }
    }
}

@Composable
fun StatItem(label: String, value: String) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = value,
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.primary
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
fun TopEntryItem(entry: ProfileEntry) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp)
        ) {
            Text(
                text = entry.text,
                style = MaterialTheme.typography.bodyMedium,
                maxLines = 2
            )
            Spacer(modifier = Modifier.height(4.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = "👏 ${entry.clapCount ?: "0"}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = "👁 ${entry.viewCount ?: "0"}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
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
