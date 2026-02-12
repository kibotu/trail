package net.kibotu.trail.ui.components

import android.view.ViewGroup
import androidx.annotation.OptIn
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import android.app.Activity
import android.content.pm.ActivityInfo
import android.view.View
import android.view.WindowInsetsController
import android.os.Build
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Fullscreen
import androidx.compose.material.icons.filled.FullscreenExit
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.VolumeOff
import androidx.compose.material.icons.filled.VolumeUp
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
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
import net.kibotu.trail.data.model.MediaItemData

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
            items(media, key = { it.id }) { item ->
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
                modifier = modifier
            )
        }
        else -> {
            StaticImage(
                url = fullUrl,
                contentDescription = "Image",
                modifier = modifier
            )
        }
    }
}

/**
 * Displays a static image using Coil
 */
@Composable
fun StaticImage(
    url: String,
    contentDescription: String,
    modifier: Modifier = Modifier
) {
    AsyncImage(
        model = ImageRequest.Builder(LocalContext.current)
            .data(url)
            .crossfade(true)
            .build(),
        contentDescription = contentDescription,
        contentScale = ContentScale.Crop,
        modifier = modifier
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
    modifier: Modifier = Modifier
) {
    AsyncImage(
        model = ImageRequest.Builder(LocalContext.current)
            .data(url)
            .crossfade(true)
            .build(),
        contentDescription = contentDescription,
        contentScale = ContentScale.Crop,
        modifier = modifier
    )
}

/**
 * Inline video player using Media3 ExoPlayer
 * - Starts muted
 * - Shows play button overlay when paused
 * - On tap: starts playback (pauses other videos via onPlayStateChange)
 * - Shows controls when playing (mute, fullscreen)
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

    // Create ExoPlayer instance
    val exoPlayer = remember {
        ExoPlayer.Builder(context).build().apply {
            volume = 0f // Start muted
            repeatMode = Player.REPEAT_MODE_OFF
        }
    }

    // Set media item
    LaunchedEffect(url) {
        exoPlayer.setMediaItem(MediaItem.fromUri(url))
        exoPlayer.prepare()
    }

    // Handle play/pause based on isPlaying state
    LaunchedEffect(isPlaying) {
        if (isPlaying) {
            exoPlayer.play()
            showControlOverlay = true
        } else {
            exoPlayer.pause()
            showControlOverlay = false
        }
    }

    // Auto-hide control overlay after delay
    LaunchedEffect(showControlOverlay, isPlaying) {
        if (showControlOverlay && isPlaying) {
            kotlinx.coroutines.delay(2500)
            showControlOverlay = false
        }
    }

    // Handle fullscreen mode - orientation and system bars
    DisposableEffect(isFullscreen) {
        if (isFullscreen) {
            // Enter fullscreen: landscape + hide system bars
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
            // Exit fullscreen: restore orientation + show system bars
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
            // Always restore on dispose
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

    // Clean up player when composable leaves composition
    DisposableEffect(Unit) {
        onDispose {
            exoPlayer.release()
        }
    }

    // Fullscreen overlay using Dialog (no config change, just UI overlay)
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
                    onPlayStateChange = onPlayStateChange,
                    onMuteToggle = {
                        isMuted = !isMuted
                        exoPlayer.volume = if (isMuted) 0f else 1f
                    },
                    onFullscreenToggle = { isFullscreen = false },
                    onControlOverlayToggle = { showControlOverlay = !showControlOverlay },
                    modifier = Modifier.fillMaxSize()
                )
            }
        }
    }

    // Inline player (non-fullscreen)
    VideoPlayerContent(
        exoPlayer = exoPlayer,
        isPlaying = isPlaying,
        isMuted = isMuted,
        showControlOverlay = showControlOverlay && !isFullscreen,
        isFullscreen = false,
        onPlayStateChange = onPlayStateChange,
        onMuteToggle = {
            isMuted = !isMuted
            exoPlayer.volume = if (isMuted) 0f else 1f
        },
        onFullscreenToggle = { isFullscreen = true },
        onControlOverlayToggle = { showControlOverlay = !showControlOverlay },
        modifier = modifier
    )
}

/**
 * Shared video player content used both inline and in fullscreen dialog
 */
@OptIn(UnstableApi::class)
@Composable
private fun VideoPlayerContent(
    exoPlayer: ExoPlayer,
    isPlaying: Boolean,
    isMuted: Boolean,
    showControlOverlay: Boolean,
    isFullscreen: Boolean,
    onPlayStateChange: (Boolean) -> Unit,
    onMuteToggle: () -> Unit,
    onFullscreenToggle: () -> Unit,
    onControlOverlayToggle: () -> Unit,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .background(Color.Black)
            .then(if (!isFullscreen) Modifier.clip(RoundedCornerShape(12.dp)) else Modifier)
    ) {
        // Video surface
        AndroidView(
            factory = { ctx ->
                PlayerView(ctx).apply {
                    player = exoPlayer
                    useController = false // We use custom controls
                    resizeMode = AspectRatioFrameLayout.RESIZE_MODE_FIT
                    layoutParams = ViewGroup.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.MATCH_PARENT
                    )
                    setShowBuffering(PlayerView.SHOW_BUFFERING_WHEN_PLAYING)
                }
            },
            update = { playerView ->
                playerView.player = exoPlayer
            },
            modifier = Modifier
                .fillMaxSize()
                .clickable {
                    if (isPlaying) {
                        onControlOverlayToggle()
                    }
                }
        )

        // Play button overlay when not playing
        if (!isPlaying) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .clickable {
                        onPlayStateChange(true)
                    },
                contentAlignment = Alignment.Center
            ) {
                // Modern frosted glass style play button
                Surface(
                    modifier = Modifier.size(56.dp),
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
        }

        // Custom control overlay when playing
        if (isPlaying && showControlOverlay) {
            // Semi-transparent tap area to pause
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .clickable { onPlayStateChange(false) },
                contentAlignment = Alignment.Center
            ) {
                // Pause icon in center
                Surface(
                    modifier = Modifier.size(48.dp),
                    shape = CircleShape,
                    color = Color.Black.copy(alpha = 0.5f)
                ) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Filled.PlayArrow,
                            contentDescription = "Pause video",
                            tint = Color.White,
                            modifier = Modifier.size(28.dp)
                        )
                    }
                }
            }

            // Bottom control bar with mute and fullscreen
            Row(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .padding(8.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                // Mute/Unmute button
                IconButton(
                    onClick = onMuteToggle,
                    modifier = Modifier.size(36.dp),
                    colors = IconButtonDefaults.iconButtonColors(
                        containerColor = Color.Black.copy(alpha = 0.6f)
                    )
                ) {
                    Icon(
                        imageVector = if (isMuted) Icons.Filled.VolumeOff else Icons.Filled.VolumeUp,
                        contentDescription = if (isMuted) "Unmute" else "Mute",
                        tint = Color.White,
                        modifier = Modifier.size(20.dp)
                    )
                }

                // Fullscreen button
                IconButton(
                    onClick = onFullscreenToggle,
                    modifier = Modifier.size(36.dp),
                    colors = IconButtonDefaults.iconButtonColors(
                        containerColor = Color.Black.copy(alpha = 0.6f)
                    )
                ) {
                    Icon(
                        imageVector = if (isFullscreen) Icons.Filled.FullscreenExit else Icons.Filled.Fullscreen,
                        contentDescription = if (isFullscreen) "Exit fullscreen" else "Fullscreen",
                        tint = Color.White,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }
        }

        // Always visible mute indicator when muted and playing (small icon in corner)
        if (isPlaying && !showControlOverlay && isMuted) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .padding(8.dp)
                    .size(28.dp)
                    .background(Color.Black.copy(alpha = 0.5f), CircleShape)
                    .clickable { onMuteToggle() },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.VolumeOff,
                    contentDescription = "Tap to unmute",
                    tint = Color.White,
                    modifier = Modifier.size(16.dp)
                )
            }
        }
    }
}

/**
 * Builds the full URL from base URL and relative path
 */
private fun buildFullUrl(baseUrl: String, relativePath: String): String {
    val cleanBase = baseUrl.trimEnd('/')
    val cleanPath = relativePath.trimStart('/')
    return "$cleanBase/$cleanPath"
}
