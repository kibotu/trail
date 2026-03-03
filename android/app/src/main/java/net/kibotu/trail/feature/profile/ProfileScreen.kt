package net.kibotu.trail.feature.profile

import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.KeyboardArrowUp
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.viewmodel.compose.viewModel
import coil3.compose.AsyncImage
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import net.kibotu.trail.R
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.feature.auth.LoginScreen
import net.kibotu.trail.shared.profile.ProfileEntry
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.util.openInCustomTab

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun ProfileScreen(
    themePreferences: ThemePreferences,
    onNavigateToEntry: (String) -> Unit,
    scrollConnection: NestedScrollConnection? = null,
) {
    val authViewModel = LocalAuthViewModel.current
    val authState by authViewModel.state.collectAsState()

    if (!authState.isLoggedIn) {
        LoginScreen(onLoginSuccess = { authViewModel.handleGoogleSignIn(it) })
        return
    }

    val viewModel: ProfileViewModel = viewModel(factory = ProfileViewModel.Factory(LocalContext.current))
    val profileState by viewModel.state.collectAsState()
    val isDarkTheme by themePreferences.isDarkTheme.collectAsState()
    val showEntryTags by themePreferences.showEntryTags.collectAsState()
    val clipboardManager = LocalClipboardManager.current
    val context = LocalContext.current

    if (profileState.isLoading) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        return
    }

    val profile = profileState.profile ?: return

    val statusBarTop = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .let { mod -> scrollConnection?.let { mod.nestedScroll(it) } ?: mod },
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = statusBarTop + 16.dp, bottom = 100.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {

        // ── Profile Header ──────────────────────────────────────────────
        item(key = "profile_header") {
            Card(
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    AsyncImage(
                        model = profile.avatarUrl,
                        contentDescription = "Profile picture",
                        modifier = Modifier
                            .size(80.dp)
                            .clip(CircleShape)
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    Text(profile.name, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    profile.nickname?.let {
                        Text(
                            "@$it",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                    profile.bio?.let {
                        Spacer(modifier = Modifier.height(6.dp))
                        Text(
                            it,
                            style = MaterialTheme.typography.bodyMedium,
                            textAlign = TextAlign.Center,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    Text(
                        "Member since ${profile.createdAt.take(10)}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                    )
                    profile.email?.let { email ->
                        Text(
                            email,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                        )
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Stats grid — only show non-zero values
                    val stats = profile.stats
                    val statItems = buildList {
                        if (stats.entryCount > 0) add(Triple(FaIcons.PenFancy, stats.entryCount.toString(), "Entries"))
                        if (stats.commentCount > 0) add(Triple(FaIcons.Comment, stats.commentCount.toString(), "Comments"))
                        if (stats.linkCount > 0) add(Triple(FaIcons.Link, stats.linkCount.toString(), "Links"))
                        if (stats.totalEntryViews > 0) add(Triple(FaIcons.Eye, stats.totalEntryViews.toString(), "Views"))
                        if (stats.totalEntryClaps > 0) add(Triple(FaIcons.Heart, stats.totalEntryClaps.toString(), "Claps"))
                        if (stats.totalProfileViews > 0) add(Triple(FaIcons.User, stats.totalProfileViews.toString(), "Profile Views"))
                    }

                    if (statItems.isNotEmpty()) {
                        FlowRow(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.Center,
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            statItems.forEach { (icon, value, label) ->
                                Column(
                                    modifier = Modifier.padding(horizontal = 12.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        FaIcon(
                                            faIcon = icon,
                                            size = 12.dp,
                                            tint = MaterialTheme.colorScheme.primary.copy(alpha = 0.8f)
                                        )
                                        Text(
                                            value,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 16.sp
                                        )
                                    }
                                    Text(
                                        label,
                                        style = MaterialTheme.typography.labelSmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }

        // ── Top Entries by Claps ─────────────────────────────────────────
        if (profile.stats.topEntriesByClaps.isNotEmpty()) {
            item(key = "top_by_claps") {
                SectionCard(
                    title = "Top by Claps",
                    icon = FaIcons.Heart,
                    iconTint = Color(0xFFE91E63)
                ) {
                    profile.stats.topEntriesByClaps.forEachIndexed { index, entry ->
                        TopEntryRow(
                            index = index + 1,
                            entry = entry,
                            statValue = entry.clapCount ?: "0",
                            statIcon = FaIcons.Heart,
                            statColor = Color(0xFFE91E63),
                            onClick = { entry.hashId?.let { onNavigateToEntry(it) } }
                        )
                        if (index < profile.stats.topEntriesByClaps.lastIndex) {
                            HorizontalDivider(
                                modifier = Modifier.padding(vertical = 4.dp),
                                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.1f)
                            )
                        }
                    }
                }
            }
        }

        // ── Top Entries by Views ─────────────────────────────────────────
        if (profile.stats.topEntriesByViews.isNotEmpty()) {
            item(key = "top_by_views") {
                SectionCard(
                    title = "Top by Views",
                    icon = FaIcons.Eye,
                    iconTint = Color(0xFF42A5F5)
                ) {
                    profile.stats.topEntriesByViews.forEachIndexed { index, entry ->
                        TopEntryRow(
                            index = index + 1,
                            entry = entry,
                            statValue = entry.viewCount ?: "0",
                            statIcon = FaIcons.Eye,
                            statColor = Color(0xFF42A5F5),
                            onClick = { entry.hashId?.let { onNavigateToEntry(it) } }
                        )
                        if (index < profile.stats.topEntriesByViews.lastIndex) {
                            HorizontalDivider(
                                modifier = Modifier.padding(vertical = 4.dp),
                                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.1f)
                            )
                        }
                    }
                }
            }
        }

        // ── Account Settings (nickname + bio editing) ────────────────────
        item(key = "account_settings") {
            var editNickname by remember { mutableStateOf(profile.nickname ?: "") }
            var editBio by remember { mutableStateOf(profile.bio ?: "") }
            var hasChanges by remember { mutableStateOf(false) }

            SectionCard(
                title = "Account Settings",
                icon = FaIcons.UserCog,
                iconTint = Color(0xFF66BB6A)
            ) {
                OutlinedTextField(
                    value = editNickname,
                    onValueChange = {
                        editNickname = it
                        hasChanges = true
                    },
                    label = { Text("Nickname") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp)
                )
                Spacer(modifier = Modifier.height(12.dp))
                OutlinedTextField(
                    value = editBio,
                    onValueChange = {
                        editBio = it
                        hasChanges = true
                    },
                    label = { Text("Bio") },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 2,
                    maxLines = 4,
                    shape = RoundedCornerShape(12.dp)
                )
                if (hasChanges) {
                    Spacer(modifier = Modifier.height(12.dp))
                    Button(
                        onClick = {
                            viewModel.updateProfile(editNickname, editBio.ifBlank { null })
                            hasChanges = false
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        enabled = editNickname.isNotBlank() && !profileState.isUpdating
                    ) {
                        if (profileState.isUpdating) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(16.dp),
                                strokeWidth = 2.dp,
                                color = MaterialTheme.colorScheme.onPrimary
                            )
                        } else {
                            Text("Save Changes")
                        }
                    }
                }
            }
        }

        // ── Privacy (Muted Users) ────────────────────────────────────────
        item(key = "muted_users") {
            val mutedUsers = profileState.filters?.mutedUsers.orEmpty()
            val mutedCount = mutedUsers.size

            Card(
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            FaIcon(FaIcons.ShieldAlt, size = 16.dp, tint = Color(0xFF42A5F5))
                            Text("Privacy", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                        }
                        Box(
                            modifier = Modifier
                                .background(
                                    MaterialTheme.colorScheme.primary.copy(alpha = 0.15f),
                                    RoundedCornerShape(8.dp)
                                )
                                .padding(horizontal = 8.dp, vertical = 2.dp)
                        ) {
                            Text(
                                mutedCount.toString(),
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.primary
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    if (mutedUsers.isEmpty()) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 16.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            FaIcon(
                                FaIcons.VolumeMute,
                                size = 36.dp,
                                tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.3f)
                            )
                            Spacer(modifier = Modifier.height(12.dp))
                            Text(
                                "No muted users",
                                fontWeight = FontWeight.SemiBold,
                                style = MaterialTheme.typography.bodyMedium
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(
                                "You haven't muted anyone yet.",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f)
                            )
                        }
                    } else {
                        mutedUsers.forEachIndexed { index, user ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 8.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier.weight(1f)
                                ) {
                                    user.avatarUrl?.let {
                                        AsyncImage(
                                            model = it,
                                            contentDescription = null,
                                            modifier = Modifier
                                                .size(36.dp)
                                                .clip(CircleShape)
                                        )
                                        Spacer(modifier = Modifier.width(10.dp))
                                    }
                                    Column {
                                        Text(
                                            user.nickname ?: user.name,
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.Medium
                                        )
                                        user.mutedAt?.let {
                                            Text(
                                                "Muted ${it.take(10)}",
                                                style = MaterialTheme.typography.labelSmall,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                                            )
                                        }
                                    }
                                }
                                OutlinedButton(
                                    onClick = { viewModel.unmuteUser(user.id) },
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                                ) {
                                    Text("Unmute", style = MaterialTheme.typography.labelSmall)
                                }
                            }
                            if (index < mutedUsers.lastIndex) {
                                HorizontalDivider(
                                    color = MaterialTheme.colorScheme.outline.copy(alpha = 0.1f)
                                )
                            }
                        }
                    }
                }
            }
        }

        // ── Embed Preview ────────────────────────────────────────────────
        profile.nickname?.let { nick ->
            item(key = "embed_preview") {
                val embedUrl = "https://trail.kibotu.net/@$nick/embed"
                val htmlSnippet = "<iframe src=\"$embedUrl\" width=\"100%\" height=\"400\" frameborder=\"0\"></iframe>"
                var isExpanded by remember { mutableStateOf(false) }

                Card(
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                ) {
                    Column(modifier = Modifier.padding(20.dp)) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { isExpanded = !isExpanded },
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                FaIcon(FaIcons.Code, size = 16.dp, tint = Color(0xFFAB47BC))
                                Text("Embed Preview", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                            }
                            Icon(
                                imageVector = if (isExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                                contentDescription = if (isExpanded) "Collapse" else "Expand"
                            )
                        }

                        AnimatedVisibility(
                            visible = isExpanded,
                            enter = expandVertically(),
                            exit = shrinkVertically()
                        ) {
                            Column {
                                Spacer(modifier = Modifier.height(8.dp))

                                AndroidView(
                                    factory = { ctx ->
                                        WebView(ctx).apply {
                                            webViewClient = WebViewClient()
                                            settings.javaScriptEnabled = true
                                            loadUrl(embedUrl)
                                        }
                                    },
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(300.dp)
                                        .clip(RoundedCornerShape(12.dp))
                                )

                                Spacer(modifier = Modifier.height(12.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    OutlinedButton(
                                        onClick = {
                                            clipboardManager.setText(AnnotatedString(embedUrl))
                                            Toast.makeText(context, "Embed URL copied", Toast.LENGTH_SHORT).show()
                                        },
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(10.dp)
                                    ) {
                                        Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text("Copy URL", style = MaterialTheme.typography.labelMedium)
                                    }
                                    OutlinedButton(
                                        onClick = {
                                            clipboardManager.setText(AnnotatedString(htmlSnippet))
                                            Toast.makeText(context, "HTML snippet copied", Toast.LENGTH_SHORT).show()
                                        },
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(10.dp)
                                    ) {
                                        Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text("Copy HTML", style = MaterialTheme.typography.labelMedium)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // ── Settings ─────────────────────────────────────────────────────
        item(key = "settings") {
            SectionCard(
                title = "Settings",
                icon = FaIcons.Cog,
                iconTint = MaterialTheme.colorScheme.onSurfaceVariant
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("Theme", fontWeight = FontWeight.Medium)
                    IconButton(onClick = { themePreferences.toggleTheme() }) {
                        Icon(
                            imageVector = if (isDarkTheme) Icons.Default.LightMode else Icons.Default.DarkMode,
                            contentDescription = "Toggle theme"
                        )
                    }
                }
                HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.15f))
                Spacer(modifier = Modifier.height(4.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("Show Entry Tags", fontWeight = FontWeight.Medium)
                    Switch(
                        checked = showEntryTags,
                        onCheckedChange = { themePreferences.setShowEntryTags(it) }
                    )
                }
            }
        }

        // ── Your Data ────────────────────────────────────────────────────
        item(key = "your_data") {
            SectionCard(
                title = "Your Data",
                icon = FaIcons.Download,
                iconTint = Color(0xFF42A5F5)
            ) {
                Text(
                    "Download a complete export of your data, including your profile, entries, comments, media, and more.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Spacer(modifier = Modifier.height(12.dp))
                OutlinedButton(
                    onClick = { viewModel.downloadExport(context) },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    enabled = !profileState.isExporting
                ) {
                    if (profileState.isExporting) {
                        CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                    } else {
                        FaIcon(FaIcons.Download, size = 14.dp, tint = MaterialTheme.colorScheme.primary)
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Download My Data")
                }
            }
        }

        // ── Legal ────────────────────────────────────────────────────────
        item(key = "legal_links") {
            SectionCard(
                title = "Legal",
                icon = FaIcons.ShieldAlt,
                iconTint = Color(0xFFFFA726)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .clickable { context.openInCustomTab("https://trail.services.kibotu.net/data-privacy/") }
                        .padding(vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    FaIcon(FaIcons.ShieldAlt, size = 16.dp, tint = Color(0xFFFFA726))
                    Spacer(Modifier.width(12.dp))
                    Text("Data Privacy", style = MaterialTheme.typography.bodyMedium)
                }
                HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.15f))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .clickable { context.openInCustomTab("https://trail.services.kibotu.net/terms-and-conditions/") }
                        .padding(vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    FaIcon(FaIcons.FileContract, size = 16.dp, tint = Color(0xFFFFA726))
                    Spacer(Modifier.width(12.dp))
                    Text("Terms & Conditions", style = MaterialTheme.typography.bodyMedium)
                }
            }
        }

        // ── Danger Zone ──────────────────────────────────────────────────
        item(key = "danger_zone") {
            var showDeleteConfirmation by remember { mutableStateOf(false) }

            Card(
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.errorContainer
                ),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        FaIcon(FaIcons.ExclamationTriangle, size = 16.dp, tint = MaterialTheme.colorScheme.error)
                        Text(
                            "Danger Zone",
                            fontWeight = FontWeight.Bold,
                            fontSize = 16.sp,
                            color = MaterialTheme.colorScheme.error
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        "Requesting deletion will hide your content immediately. Your account will be permanently deleted after 14 days.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onErrorContainer
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Button(
                        onClick = { showDeleteConfirmation = true },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = MaterialTheme.colorScheme.error
                        )
                    ) {
                        FaIcon(FaIcons.TrashAlt, size = 14.dp, tint = MaterialTheme.colorScheme.onError)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Delete My Account", color = MaterialTheme.colorScheme.onError)
                    }
                }
            }

            if (showDeleteConfirmation) {
                val nickname = profile.nickname ?: ""
                var confirmInput by remember { mutableStateOf("") }
                val isConfirmed = confirmInput.trim() == nickname

                Dialog(
                    onDismissRequest = { showDeleteConfirmation = false },
                    properties = DialogProperties(usePlatformDefaultWidth = false)
                ) {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(24.dp),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        ),
                        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
                    ) {
                        Column(
                            modifier = Modifier.verticalScroll(rememberScrollState())
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .background(MaterialTheme.colorScheme.surfaceContainerHighest)
                                    .padding(top = 16.dp, bottom = 4.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Image(
                                    painter = painterResource(R.drawable.delete_whale),
                                    contentDescription = "Are you sure you want to say goodbye?",
                                    modifier = Modifier.fillMaxWidth(0.7f),
                                    contentScale = ContentScale.FillWidth
                                )
                            }

                            Column(
                                modifier = Modifier.padding(20.dp)
                            ) {
                                Text(
                                    "Before you go, here\u2019s what will happen:",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurface
                                )

                                Spacer(modifier = Modifier.height(12.dp))

                                DeleteConsequenceItem(
                                    icon = FaIcons.EyeSlash,
                                    text = "Your profile, entries, and comments will be hidden immediately from public view."
                                )
                                DeleteConsequenceItem(
                                    icon = FaIcons.Clock,
                                    text = "Your account and all data will be permanently deleted after 14 days."
                                )
                                DeleteConsequenceItem(
                                    icon = FaIcons.Undo,
                                    text = "You can reverse this decision within the 14-day window by logging back in."
                                )

                                Spacer(modifier = Modifier.height(16.dp))

                                Text(
                                    "Type your nickname $nickname to confirm:",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                OutlinedTextField(
                                    value = confirmInput,
                                    onValueChange = { confirmInput = it },
                                    modifier = Modifier.fillMaxWidth(),
                                    placeholder = { Text("Enter your nickname") },
                                    singleLine = true,
                                    shape = RoundedCornerShape(8.dp)
                                )

                                Spacer(modifier = Modifier.height(20.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    OutlinedButton(
                                        onClick = { showDeleteConfirmation = false },
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(8.dp)
                                    ) {
                                        Text("Cancel")
                                    }
                                    Button(
                                        onClick = {
                                            showDeleteConfirmation = false
                                            viewModel.requestDeletion { authViewModel.logout() }
                                        },
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(8.dp),
                                        enabled = isConfirmed,
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = Color(0xFFD32F2F),
                                            disabledContainerColor = Color(0xFFD32F2F).copy(alpha = 0.4f)
                                        )
                                    ) {
                                        FaIcon(FaIcons.TrashAlt, size = 12.dp, tint = Color.White)
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text("Delete", color = Color.White)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // ── Logout ───────────────────────────────────────────────────────
        item(key = "logout") {
            Button(
                onClick = { authViewModel.logout() },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Logout")
            }
        }
    }
}

// ── Reusable section card with icon + title ──────────────────────────────
@Composable
private fun SectionCard(
    title: String,
    icon: com.guru.fontawesomecomposelib.FaIconType,
    iconTint: Color,
    content: @Composable () -> Unit
) {
    Card(
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                FaIcon(faIcon = icon, size = 16.dp, tint = iconTint)
                Text(title, fontWeight = FontWeight.Bold, fontSize = 16.sp)
            }
            Spacer(modifier = Modifier.height(12.dp))
            content()
        }
    }
}

// ── Top entry row with rank number ───────────────────────────────────────
@Composable
private fun TopEntryRow(
    index: Int,
    entry: ProfileEntry,
    statValue: String,
    statIcon: com.guru.fontawesomecomposelib.FaIconType,
    statColor: Color,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .clickable { onClick() }
            .padding(vertical = 8.dp, horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = "#$index",
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.Bold,
            color = statColor.copy(alpha = 0.8f),
            modifier = Modifier.width(28.dp)
        )
        Text(
            text = entry.text,
            style = MaterialTheme.typography.bodySmall,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
            color = MaterialTheme.colorScheme.onSurface
        )
        Spacer(modifier = Modifier.width(8.dp))
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier
                .background(
                    statColor.copy(alpha = 0.1f),
                    RoundedCornerShape(12.dp)
                )
                .padding(horizontal = 8.dp, vertical = 4.dp)
        ) {
            FaIcon(faIcon = statIcon, size = 10.dp, tint = statColor)
            Text(
                text = statValue,
                style = MaterialTheme.typography.labelSmall,
                fontWeight = FontWeight.SemiBold,
                color = statColor
            )
        }
    }
}

@Composable
private fun DeleteConsequenceItem(
    icon: com.guru.fontawesomecomposelib.FaIconType,
    text: String
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp),
        verticalAlignment = Alignment.Top
    ) {
        FaIcon(
            icon,
            size = 14.dp,
            tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f),
            modifier = Modifier.padding(top = 2.dp)
        )
        Spacer(modifier = Modifier.width(12.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            lineHeight = 20.sp
        )
    }
}
