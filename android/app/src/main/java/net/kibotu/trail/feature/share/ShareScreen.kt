package net.kibotu.trail.feature.share

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import kotlinx.coroutines.launch
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.feature.auth.LoginScreen
import net.kibotu.trail.shared.entry.CreateEntryRequest
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.image.ImageUploadManager
import net.kibotu.trail.shared.image.ImageUploadRepository
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.review.LocalInAppReviewManager
import net.kibotu.trail.shared.theme.LocalWindowSizeClass
import net.kibotu.trail.shared.theme.isCompactWidth

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ShareScreen(
    initialText: String,
    onShareSuccess: () -> Unit,
    onBack: () -> Unit
) {
    val authViewModel = LocalAuthViewModel.current
    val authState by authViewModel.state.collectAsState()

    if (!authState.isLoggedIn) {
        LoginScreen(onLoginSuccess = { authViewModel.handleGoogleSignIn(it) })
        return
    }

    val context = LocalContext.current
    val inAppReviewManager = LocalInAppReviewManager.current
    val entryRepository = remember { EntryRepository(ApiClient.client) }
    val imageUploadManager = remember { ImageUploadManager(ImageUploadRepository(ApiClient.client)) }
    var text by remember { mutableStateOf(initialText) }
    var isPosting by remember { mutableStateOf(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    val maxCharacters = 280
    val maxImages = 3
    val scope = rememberCoroutineScope()

    val selectedUris = remember { mutableStateListOf<Uri>() }
    var uploadProgress by remember { mutableStateOf(0f) }
    val haptic = LocalHapticFeedback.current

    val photoPickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.PickMultipleVisualMedia(maxImages)
    ) { uris ->
        val remaining = maxImages - selectedUris.size
        uris.take(remaining).forEach { uri -> selectedUris.add(uri) }
    }

    val isCompact = LocalWindowSizeClass.current.isCompactWidth

    val onSubmit: () -> Unit = {
        haptic.performHapticFeedback(HapticFeedbackType.LongPress)
        scope.launch {
            isPosting = true
            errorMessage = null
            uploadProgress = 0f

            val imageIds = mutableListOf<Int>()
            val uris = selectedUris.toList()
            var uploadFailed = false
            for ((index, uri) in uris.withIndex()) {
                imageUploadManager.uploadImage(context, uri) { progress ->
                    val base = index.toFloat() / uris.size
                    val portion = 1f / uris.size
                    uploadProgress = base + progress * portion
                }.fold(
                    onSuccess = { imageId -> imageIds.add(imageId) },
                    onFailure = { e ->
                        errorMessage = e.message ?: "Image upload failed"
                        uploadFailed = true
                    }
                )
                if (uploadFailed) break
            }

            uploadProgress = 0f
            if (uploadFailed) {
                isPosting = false
                return@launch
            }

            entryRepository.createEntry(
                CreateEntryRequest(text, imageIds.ifEmpty { null })
            ).fold(
                onSuccess = {
                    timber.log.Timber.d("──── ShareScreen: entry created successfully ────")
                    inAppReviewManager.markHasPosted()
                    onShareSuccess()
                },
                onFailure = { e ->
                    errorMessage = e.message ?: "Failed to share"
                    isPosting = false
                }
            )
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Share") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back"
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
                )
            )
        },
        contentWindowInsets = WindowInsets.statusBars
    ) { padding ->
        if (isCompact) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(horizontal = 16.dp, vertical = 8.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                ShareCardContent(
                    text = text,
                    onTextChange = { if (it.length <= maxCharacters) text = it },
                    maxCharacters = maxCharacters,
                    selectedUris = selectedUris,
                    maxImages = maxImages,
                    isPosting = isPosting,
                    uploadProgress = uploadProgress,
                    errorMessage = errorMessage,
                    onPickImages = {
                        photoPickerLauncher.launch(
                            PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageAndVideo)
                        )
                    },
                    onRemoveImage = { selectedUris.removeAt(it) },
                    onSubmit = onSubmit
                )
            }
        } else {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(horizontal = 24.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Card(
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        OutlinedTextField(
                            value = text,
                            onValueChange = { if (it.length <= maxCharacters) text = it },
                            modifier = Modifier.fillMaxWidth().weight(1f),
                            placeholder = { Text("What are you working on?") },
                            shape = RoundedCornerShape(12.dp),
                            enabled = !isPosting
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = "${text.length}/$maxCharacters",
                            style = MaterialTheme.typography.labelSmall,
                            color = if (text.length > maxCharacters) MaterialTheme.colorScheme.error
                            else MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.align(Alignment.End)
                        )
                    }
                }

                Column(
                    modifier = Modifier.weight(1f).verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    if (selectedUris.isNotEmpty()) {
                        Card(
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                        ) {
                            Row(
                                modifier = Modifier.padding(16.dp),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                selectedUris.forEachIndexed { index, uri ->
                                    Box {
                                        AsyncImage(
                                            model = uri,
                                            contentDescription = "Selected image",
                                            modifier = Modifier
                                                .size(64.dp)
                                                .clip(RoundedCornerShape(8.dp)),
                                            contentScale = ContentScale.Crop
                                        )
                                        IconButton(
                                            onClick = { selectedUris.removeAt(index) },
                                            modifier = Modifier
                                                .size(20.dp)
                                                .align(Alignment.TopEnd)
                                                .clip(CircleShape)
                                        ) {
                                            Icon(
                                                Icons.Default.Close,
                                                contentDescription = "Remove",
                                                modifier = Modifier.size(14.dp),
                                                tint = MaterialTheme.colorScheme.onError
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (isPosting && uploadProgress > 0f) {
                        LinearProgressIndicator(
                            progress = { uploadProgress },
                            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(4.dp)),
                        )
                    }

                    if (errorMessage != null) {
                        Text(
                            text = errorMessage!!,
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodySmall
                        )
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (selectedUris.size < maxImages) {
                            OutlinedButton(
                                onClick = {
                                    photoPickerLauncher.launch(
                                        PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageAndVideo)
                                    )
                                },
                                enabled = !isPosting,
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                FaIcon(FaIcons.Image, size = 16.dp, tint = MaterialTheme.colorScheme.primary)
                                Spacer(Modifier.width(6.dp))
                                Text("Add Image")
                            }
                        }

                        Spacer(Modifier.weight(1f))

                        if (isPosting) {
                            CircularProgressIndicator(
                                modifier = Modifier.padding(8.dp).size(24.dp),
                                strokeWidth = 2.dp
                            )
                        } else {
                            Button(
                                onClick = onSubmit,
                                enabled = (text.isNotBlank() || selectedUris.isNotEmpty()) && text.length <= maxCharacters && !isPosting,
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Text("Share")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ShareCardContent(
    text: String,
    onTextChange: (String) -> Unit,
    maxCharacters: Int,
    selectedUris: List<Uri>,
    maxImages: Int,
    isPosting: Boolean,
    uploadProgress: Float,
    errorMessage: String?,
    onPickImages: () -> Unit,
    onRemoveImage: (Int) -> Unit,
    onSubmit: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            OutlinedTextField(
                value = text,
                onValueChange = onTextChange,
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("What are you working on?") },
                minLines = 3,
                maxLines = 6,
                shape = RoundedCornerShape(12.dp),
                enabled = !isPosting
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "${text.length}/$maxCharacters",
                style = MaterialTheme.typography.labelSmall,
                color = if (text.length > maxCharacters) MaterialTheme.colorScheme.error
                else MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.align(Alignment.End)
            )

            if (selectedUris.isNotEmpty()) {
                Spacer(modifier = Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    selectedUris.forEachIndexed { index, uri ->
                        Box {
                            AsyncImage(
                                model = uri,
                                contentDescription = "Selected image",
                                modifier = Modifier
                                    .size(64.dp)
                                    .clip(RoundedCornerShape(8.dp)),
                                contentScale = ContentScale.Crop
                            )
                            IconButton(
                                onClick = { onRemoveImage(index) },
                                modifier = Modifier
                                    .size(20.dp)
                                    .align(Alignment.TopEnd)
                                    .clip(CircleShape)
                            ) {
                                Icon(
                                    Icons.Default.Close,
                                    contentDescription = "Remove",
                                    modifier = Modifier.size(14.dp),
                                    tint = MaterialTheme.colorScheme.onError
                                )
                            }
                        }
                    }
                }
            }

            if (isPosting && uploadProgress > 0f) {
                Spacer(modifier = Modifier.height(8.dp))
                LinearProgressIndicator(
                    progress = { uploadProgress },
                    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(4.dp)),
                )
            }

            if (errorMessage != null) {
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = errorMessage,
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodySmall
                )
            }

            Spacer(modifier = Modifier.height(12.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (selectedUris.size < maxImages) {
                    OutlinedButton(
                        onClick = onPickImages,
                        enabled = !isPosting,
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        FaIcon(FaIcons.Image, size = 16.dp, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(6.dp))
                        Text("Add Image")
                    }
                } else {
                    Spacer(Modifier.width(1.dp))
                }

                if (isPosting) {
                    CircularProgressIndicator(
                        modifier = Modifier.padding(8.dp).size(24.dp),
                        strokeWidth = 2.dp
                    )
                } else {
                    Button(
                        onClick = onSubmit,
                        enabled = (text.isNotBlank() || selectedUris.isNotEmpty()) && text.length <= maxCharacters && !isPosting,
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Text("Share")
                    }
                }
            }
        }
    }
}
