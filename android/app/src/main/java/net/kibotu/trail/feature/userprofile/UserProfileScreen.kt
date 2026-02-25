package net.kibotu.trail.feature.userprofile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
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
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.storage.LocalThemePreferences
import net.kibotu.trail.shared.theme.ui.EntryCard

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UserProfileScreen(
    nickname: String,
    onNavigateBack: () -> Unit,
    onNavigateToEntry: (String) -> Unit,
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

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("@$nickname") },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        if (profileState.isLoading) {
            Box(Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
            return@Scaffold
        }

        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Profile header
            profileState.profile?.let { profile ->
                item {
                    Card(
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(20.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            AsyncImage(
                                model = profile.avatarUrl,
                                contentDescription = "Avatar",
                                modifier = Modifier.size(72.dp).clip(CircleShape)
                            )
                            Spacer(modifier = Modifier.height(12.dp))
                            Text(profile.name, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                            profile.nickname?.let {
                                Text("@$it", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            profile.bio?.let {
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(it, style = MaterialTheme.typography.bodyMedium)
                            }
                            Spacer(modifier = Modifier.height(12.dp))
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
                        }
                    }
                }
            }

            // Entries
            items(
                count = entries.itemCount,
                key = { index -> entries[index]?.id ?: index }
            ) { index ->
                val entry = entries[index] ?: return@items
                EntryCard(
                    entry = entry,
                    currentUserId = authState.user?.id,
                    isAdmin = authState.user?.isAdmin ?: false,
                    showTags = showTags,
                    onCardClick = { entry.hashId?.let { onNavigateToEntry(it) } },
                    onClap = { count -> entry.hashId?.let { viewModel.addClaps(it, count) } },
                    onShare = { viewModel.shareEntry(context, entry) }
                )
            }

            if (entries.loadState.append is LoadState.Loading) {
                item {
                    Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                }
            }
        }
    }
}
