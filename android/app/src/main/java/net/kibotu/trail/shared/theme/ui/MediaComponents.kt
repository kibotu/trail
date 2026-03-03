package net.kibotu.trail.shared.theme.ui

import android.app.Activity
import android.content.pm.ActivityInfo
import android.os.Build
import android.view.View
import android.view.ViewGroup
import android.view.WindowInsetsController
import androidx.annotation.OptIn
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.detectTransformGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.VolumeOff
import androidx.compose.material.icons.automirrored.filled.VolumeUp
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Fullscreen
import androidx.compose.material.icons.filled.FullscreenExit
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.Slider
import androidx.compose.material3.SliderDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.media3.common.MediaItem
import androidx.media3.common.Player
import androidx.media3.common.util.UnstableApi
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.ui.AspectRatioFrameLayout
import androidx.media3.ui.PlayerView
import coil3.compose.AsyncImage
import coil3.request.ImageRequest
import coil3.request.crossfade
import net.kibotu.trail.shared.entry.MediaItemData
import kotlin.math.abs
import kotlin.math.roundToInt

/**
 * Displays a gallery of media items (images, GIFs, videos)
 * Uses horizontal scroll for multiple items, single item shows without scroll
 */
@Composable
fun MediaGallery(
    media: List<MediaItemData>,
    baseUrl: String,
    currentlyPlayingId: String?,
    onVideoPlay: (String?) -> Unit,
    modifier: Modifier = Modifier
) {
    if (media.isEmpty()) return

    val aspectRatio = media.firstOrNull()?.let { item ->
        if (item.width != null && item.height != null && item.height!! > 0) {
            item.width!!.toFloat() / item.height!!.toFloat()
        } else {
            16f / 9f
        }
    } ?: (16f / 9f)

    if (media.size == 1) {
        // Single item - show full width
        val item = media.first()
        Box(
            modifier = modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
        ) {
            MediaItemView(
                item = item,
                baseUrl = baseUrl,
                currentlyPlayingId = currentlyPlayingId,
                onVideoPlay = onVideoPlay,
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(aspectRatio.coerceIn(0.5f, 2f))
            )
        }
    } else {
        // Multiple items - horizontal scroll
        LazyRow(
            modifier = modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            items(media.size, key = { index -> "media_${media[index].id}_$index" }) { index ->
                val item = media[index]
                val itemAspectRatio = if (item.width != null && item.height != null && item.height!! > 0) {
                    item.width!!.toFloat() / item.height!!.toFloat()
                } else {
                    16f / 9f
                }
                MediaItemView(
                    item = item,
                    baseUrl = baseUrl,
                    currentlyPlayingId = currentlyPlayingId,
                    onVideoPlay = onVideoPlay,
                    modifier = Modifier
                        .height(200.dp)
                        .aspectRatio(itemAspectRatio.coerceIn(0.5f, 2f))
                        .clip(RoundedCornerShape(12.dp))
                )
            }
        }
    }
}

/**
 * Renders a single media item based on its type
 */
@Composable
fun MediaItemView(
    item: MediaItemData,
    baseUrl: String,
    currentlyPlayingId: String?,
    onVideoPlay: (String?) -> Unit,
    modifier: Modifier = Modifier
) {
    val fullUrl = buildFullUrl(baseUrl, item.url)
    val mediaId = "media_${item.id}"
    var showFullscreenViewer by remember { mutableStateOf(false) }

    when {
        item.isVideo -> {
            VideoPlayer(
                url = fullUrl,
                mediaId = mediaId,
                isPlaying = currentlyPlayingId == mediaId,
                onPlayStateChange = { playing ->
                    onVideoPlay(if (playing) mediaId else null)
                },
                modifier = modifier
            )
        }
        item.isGif -> {
            GifImage(
                url = fullUrl,
                contentDescription = "Animated GIF",
                onClick = { showFullscreenViewer = true },
                modifier = modifier
            )
        }
        else -> {
            StaticImage(
                url = fullUrl,
                contentDescription = "Image",
                onClick = { showFullscreenViewer = true },
                modifier = modifier
            )
        }
    }

    if (showFullscreenViewer) {
        FullscreenImageViewer(
            url = fullUrl,
            onDismiss = { showFullscreenViewer = false }
        )
    }
}

/**
 * Displays a static image using Coil
 */
@Composable
fun StaticImage(
    url: String,
    contentDescription: String,
    onClick: (() -> Unit)? = null,
    modifier: Modifier = Modifier
) {
    AsyncImage(
        model = ImageRequest.Builder(LocalContext.current)
            .data(url)
            .crossfade(true)
            .build(),
        contentDescription = contentDescription,
        contentScale = ContentScale.Crop,
        modifier = modifier.then(
            if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier
        )
    )
}

/**
 * Displays an animated GIF using Coil with GIF decoder
 * GIFs auto-play, muted, and loop infinitely
 */
@Composable
fun GifImage(
    url: String,
    contentDescription: String,
    onClick: (() -> Unit)? = null,
    modifier: Modifier = Modifier
) {
    AsyncImage(
        model = ImageRequest.Builder(LocalContext.current)
            .data(url)
            .crossfade(true)
            .build(),
        contentDescription = contentDescription,
        contentScale = ContentScale.Crop,
        modifier = modifier.then(
            if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier
        )
    )
}

/**
 * Inline video player using Media3 ExoPlayer
 * - Starts muted
 * - Shows play button overlay when paused
 * - On tap: starts playback (pauses other videos via onPlayStateChange)
 * - Shows controls when playing (play/pause, seek bar, time, mute, fullscreen)
 * - Fullscreen rotates to landscape (Activity handles configChanges)
 */
@OptIn(UnstableApi::class)
@Composable
fun VideoPlayer(
    url: String,
    mediaId: String,
    isPlaying: Boolean,
    onPlayStateChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val activity = context as? Activity
    var isMuted by remember { mutableStateOf(true) }
    var isFullscreen by remember { mutableStateOf(false) }
    var showControlOverlay by remember { mutableStateOf(false) }
    var controlOverlayTrigger by remember { mutableLongStateOf(0L) }

    val exoPlayer = remember {
        ExoPlayer.Builder(context).build().apply {
            volume = 0f
            repeatMode = Player.REPEAT_MODE_OFF
        }
    }

    var currentPositionMs by remember { mutableLongStateOf(0L) }
    var durationMs by remember { mutableLongStateOf(0L) }
    var isSeeking by remember { mutableStateOf(false) }

    LaunchedEffect(url) {
        exoPlayer.setMediaItem(MediaItem.fromUri(url))
        exoPlayer.prepare()
    }

    LaunchedEffect(isPlaying) {
        if (isPlaying) {
            exoPlayer.play()
            showControlOverlay = true
            controlOverlayTrigger = System.nanoTime()
        } else {
            exoPlayer.pause()
        }
    }

    // Poll position while playing
    LaunchedEffect(isPlaying) {
        while (isPlaying) {
            if (!isSeeking) {
                currentPositionMs = exoPlayer.currentPosition
                durationMs = exoPlayer.duration.coerceAtLeast(0L)
            }
            kotlinx.coroutines.delay(250)
        }
        // Update once more after pause
        currentPositionMs = exoPlayer.currentPosition
        durationMs = exoPlayer.duration.coerceAtLeast(0L)
    }

    // Auto-hide controls after 1.5s
    LaunchedEffect(controlOverlayTrigger) {
        if (showControlOverlay && isPlaying) {
            kotlinx.coroutines.delay(1500)
            showControlOverlay = false
        }
    }

    DisposableEffect(isFullscreen) {
        if (isFullscreen) {
            activity?.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE
            activity?.window?.let { window ->
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    window.insetsController?.let { controller ->
                        controller.hide(android.view.WindowInsets.Type.systemBars())
                        controller.systemBarsBehavior = WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                    }
                } else {
                    @Suppress("DEPRECATION")
                    window.decorView.systemUiVisibility = (
                        View.SYSTEM_UI_FLAG_FULLSCREEN or
                        View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                        View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                    )
                }
            }
        } else {
            activity?.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
            activity?.window?.let { window ->
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    window.insetsController?.show(android.view.WindowInsets.Type.systemBars())
                } else {
                    @Suppress("DEPRECATION")
                    window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_VISIBLE
                }
            }
        }
        onDispose {
            activity?.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
            activity?.window?.let { window ->
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    window.insetsController?.show(android.view.WindowInsets.Type.systemBars())
                } else {
                    @Suppress("DEPRECATION")
                    window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_VISIBLE
                }
            }
        }
    }

    DisposableEffect(Unit) {
        onDispose { exoPlayer.release() }
    }

    val onShowControls: () -> Unit = {
        showControlOverlay = !showControlOverlay
        controlOverlayTrigger = System.nanoTime()
    }

    val onMuteToggle: () -> Unit = {
        isMuted = !isMuted
        exoPlayer.volume = if (isMuted) 0f else 1f
        controlOverlayTrigger = System.nanoTime()
    }

    val onSeek: (Long) -> Unit = { posMs ->
        exoPlayer.seekTo(posMs)
        currentPositionMs = posMs
        controlOverlayTrigger = System.nanoTime()
    }

    if (isFullscreen) {
        Dialog(
            onDismissRequest = { isFullscreen = false },
            properties = DialogProperties(
                usePlatformDefaultWidth = false,
                dismissOnBackPress = true,
                dismissOnClickOutside = false,
                decorFitsSystemWindows = false
            )
        ) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black)
            ) {
                VideoPlayerContent(
                    exoPlayer = exoPlayer,
                    isPlaying = isPlaying,
                    isMuted = isMuted,
                    showControlOverlay = showControlOverlay,
                    isFullscreen = true,
                    currentPositionMs = currentPositionMs,
                    durationMs = durationMs,
                    onPlayStateChange = onPlayStateChange,
                    onMuteToggle = onMuteToggle,
                    onFullscreenToggle = { isFullscreen = false },
                    onControlOverlayToggle = onShowControls,
                    onSeek = onSeek,
                    onSeekStart = { isSeeking = true },
                    onSeekEnd = { isSeeking = false },
                    modifier = Modifier.fillMaxSize()
                )
            }
        }
    }

    VideoPlayerContent(
        exoPlayer = exoPlayer,
        isPlaying = isPlaying,
        isMuted = isMuted,
        showControlOverlay = showControlOverlay && !isFullscreen,
        isFullscreen = false,
        currentPositionMs = currentPositionMs,
        durationMs = durationMs,
        onPlayStateChange = onPlayStateChange,
        onMuteToggle = onMuteToggle,
        onFullscreenToggle = { isFullscreen = true },
        onControlOverlayToggle = onShowControls,
        onSeek = onSeek,
        onSeekStart = { isSeeking = true },
        onSeekEnd = { isSeeking = false },
        modifier = modifier
    )
}

private fun formatDuration(ms: Long): String {
    val totalSec = (ms / 1000).coerceAtLeast(0)
    val min = totalSec / 60
    val sec = totalSec % 60
    return "%d:%02d".format(min, sec)
}

/**
 * Shared video player content used both inline and in fullscreen dialog.
 * Controls: play/pause, seek bar with timestamps, mute, fullscreen toggle.
 */
@OptIn(UnstableApi::class)
@Composable
private fun VideoPlayerContent(
    exoPlayer: ExoPlayer,
    isPlaying: Boolean,
    isMuted: Boolean,
    showControlOverlay: Boolean,
    isFullscreen: Boolean,
    currentPositionMs: Long,
    durationMs: Long,
    onPlayStateChange: (Boolean) -> Unit,
    onMuteToggle: () -> Unit,
    onFullscreenToggle: () -> Unit,
    onControlOverlayToggle: () -> Unit,
    onSeek: (Long) -> Unit,
    onSeekStart: () -> Unit,
    onSeekEnd: () -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .background(Color.Black)
            .then(if (!isFullscreen) Modifier.clip(RoundedCornerShape(12.dp)) else Modifier)
    ) {
        AndroidView(
            factory = { ctx ->
                PlayerView(ctx).apply {
                    player = exoPlayer
                    useController = false
                    resizeMode = AspectRatioFrameLayout.RESIZE_MODE_FIT
                    layoutParams = ViewGroup.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.MATCH_PARENT
                    )
                    setShowBuffering(PlayerView.SHOW_BUFFERING_WHEN_PLAYING)
                }
            },
            update = { playerView -> playerView.player = exoPlayer },
            modifier = Modifier
                .fillMaxSize()
                .clickable {
                    if (!isPlaying) onPlayStateChange(true)
                    else onControlOverlayToggle()
                }
        )

        // Play button when paused -- instant appear/disappear via AnimatedVisibility
        AnimatedVisibility(
            visible = !isPlaying,
            enter = fadeIn(tween(150)),
            exit = fadeOut(tween(150)),
            modifier = Modifier.align(Alignment.Center)
        ) {
            Surface(
                modifier = Modifier
                    .size(56.dp)
                    .clickable { onPlayStateChange(true) },
                shape = CircleShape,
                color = Color.White.copy(alpha = 0.95f),
                shadowElevation = 8.dp
            ) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.PlayArrow,
                        contentDescription = "Play video",
                        tint = Color.Black.copy(alpha = 0.85f),
                        modifier = Modifier.size(32.dp)
                    )
                }
            }
        }

        // Control overlay (scrim + controls)
        AnimatedVisibility(
            visible = showControlOverlay || !isPlaying,
            enter = fadeIn(tween(200)),
            exit = fadeOut(tween(200)),
            modifier = Modifier.matchParentSize()
        ) {
            Box(modifier = Modifier.fillMaxSize()) {
                // Bottom controls bar
                Column(
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .fillMaxWidth()
                        .background(Color.Black.copy(alpha = 0.45f))
                        .padding(horizontal = if (isFullscreen) 16.dp else 4.dp)
                ) {
                    // Seek bar
                    if (durationMs > 0) {
                        Slider(
                            value = currentPositionMs.toFloat(),
                            onValueChange = { onSeekStart(); onSeek(it.toLong()) },
                            onValueChangeFinished = { onSeekEnd() },
                            valueRange = 0f..durationMs.toFloat(),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(if (isFullscreen) 28.dp else 20.dp),
                            colors = SliderDefaults.colors(
                                thumbColor = Color.White,
                                activeTrackColor = Color.White,
                                inactiveTrackColor = Color.White.copy(alpha = 0.3f)
                            )
                        )
                    }

                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = if (isFullscreen) 8.dp else 2.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Play/Pause
                        IconButton(
                            onClick = { onPlayStateChange(!isPlaying) },
                            modifier = Modifier.size(if (isFullscreen) 40.dp else 32.dp)
                        ) {
                            Icon(
                                imageVector = if (isPlaying) Icons.Filled.Pause else Icons.Filled.PlayArrow,
                                contentDescription = if (isPlaying) "Pause" else "Play",
                                tint = Color.White,
                                modifier = Modifier.size(if (isFullscreen) 24.dp else 20.dp)
                            )
                        }

                        // Timestamp
                        if (durationMs > 0) {
                            Text(
                                text = "${formatDuration(currentPositionMs)} / ${formatDuration(durationMs)}",
                                color = Color.White,
                                fontSize = if (isFullscreen) 13.sp else 11.sp,
                                fontWeight = FontWeight.Medium,
                                modifier = Modifier.padding(start = 4.dp)
                            )
                        }

                        Spacer(modifier = Modifier.weight(1f))

                        // Mute
                        IconButton(
                            onClick = onMuteToggle,
                            modifier = Modifier.size(if (isFullscreen) 40.dp else 32.dp)
                        ) {
                            Icon(
                                imageVector = if (isMuted) Icons.AutoMirrored.Filled.VolumeOff else Icons.AutoMirrored.Filled.VolumeUp,
                                contentDescription = if (isMuted) "Unmute" else "Mute",
                                tint = Color.White,
                                modifier = Modifier.size(if (isFullscreen) 24.dp else 18.dp)
                            )
                        }

                        // Fullscreen
                        IconButton(
                            onClick = onFullscreenToggle,
                            modifier = Modifier.size(if (isFullscreen) 40.dp else 32.dp)
                        ) {
                            Icon(
                                imageVector = if (isFullscreen) Icons.Filled.FullscreenExit else Icons.Filled.Fullscreen,
                                contentDescription = if (isFullscreen) "Exit fullscreen" else "Fullscreen",
                                tint = Color.White,
                                modifier = Modifier.size(if (isFullscreen) 24.dp else 18.dp)
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * Fullscreen image viewer with pinch-to-zoom, double-tap zoom, pan, and dismiss gestures.
 */
@Composable
private fun FullscreenImageViewer(
    url: String,
    onDismiss: () -> Unit
) {
    var scale by remember { mutableFloatStateOf(1f) }
    var offsetX by remember { mutableFloatStateOf(0f) }
    var offsetY by remember { mutableFloatStateOf(0f) }
    var dismissOffsetY by remember { mutableFloatStateOf(0f) }

    var targetScale by remember { mutableFloatStateOf(1f) }
    val animatedScale by animateFloatAsState(
        targetValue = targetScale,
        animationSpec = tween(durationMillis = 300),
        label = "zoomAnimation"
    )

    LaunchedEffect(animatedScale) {
        scale = animatedScale
        if (animatedScale == 1f) {
            offsetX = 0f
            offsetY = 0f
        }
    }

    val scrimAlpha = if (scale <= 1f) {
        (1f - (abs(dismissOffsetY) / 800f)).coerceIn(0.4f, 1f)
    } else {
        1f
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(
            usePlatformDefaultWidth = false,
            dismissOnBackPress = true,
            dismissOnClickOutside = false,
            decorFitsSystemWindows = false
        )
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color.Black.copy(alpha = scrimAlpha)),
            contentAlignment = Alignment.Center
        ) {
            AsyncImage(
                model = ImageRequest.Builder(LocalContext.current)
                    .data(url)
                    .crossfade(true)
                    .build(),
                contentDescription = "Fullscreen image",
                contentScale = ContentScale.Fit,
                modifier = Modifier
                    .fillMaxSize()
                    .offset { IntOffset(0, dismissOffsetY.roundToInt()) }
                    .graphicsLayer(
                        scaleX = scale,
                        scaleY = scale,
                        translationX = offsetX,
                        translationY = offsetY
                    )
                    .pointerInput(Unit) {
                        detectTransformGestures { _, pan, zoom, _ ->
                            targetScale = (targetScale * zoom).coerceIn(0.5f, 5f)
                            scale = (scale * zoom).coerceIn(0.5f, 5f)

                            if (scale > 1f) {
                                val maxX = (size.width * (scale - 1f)) / 2f
                                val maxY = (size.height * (scale - 1f)) / 2f
                                offsetX = (offsetX + pan.x * scale).coerceIn(-maxX, maxX)
                                offsetY = (offsetY + pan.y * scale).coerceIn(-maxY, maxY)
                            } else {
                                dismissOffsetY += pan.y
                            }
                        }
                    }
                    .pointerInput(Unit) {
                        detectTapGestures(
                            onDoubleTap = {
                                if (targetScale > 1f) {
                                    targetScale = 1f
                                    offsetX = 0f
                                    offsetY = 0f
                                    dismissOffsetY = 0f
                                } else {
                                    targetScale = 2.5f
                                }
                            },
                            onTap = {
                                if (scale <= 1.01f) {
                                    onDismiss()
                                }
                            }
                        )
                    }
            )

            IconButton(
                onClick = onDismiss,
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(16.dp)
                    .size(40.dp),
                colors = IconButtonDefaults.iconButtonColors(
                    containerColor = Color.Black.copy(alpha = 0.5f)
                )
            ) {
                Icon(
                    imageVector = Icons.Filled.Close,
                    contentDescription = "Close",
                    tint = Color.White,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }

    LaunchedEffect(dismissOffsetY) {
        if (abs(dismissOffsetY) > 300f && scale <= 1f) {
            onDismiss()
        }
    }
}

/**
 * Builds the full URL from base URL and relative path
 */
private fun buildFullUrl(baseUrl: String, path: String): String {
    if (path.startsWith("http://") || path.startsWith("https://")) return path
    if (baseUrl.isBlank()) return path
    return "${baseUrl.trimEnd('/')}/${path.trimStart('/')}"
}
