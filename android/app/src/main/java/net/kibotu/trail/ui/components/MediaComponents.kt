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
 * - Shows controls when playing
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
    var showControls by remember { mutableStateOf(false) }

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
            showControls = true
        } else {
            exoPlayer.pause()
            showControls = false
        }
    }

    // Clean up player when composable leaves composition
    DisposableEffect(Unit) {
        onDispose {
            exoPlayer.release()
        }
    }

    Box(
        modifier = modifier
            .background(Color.Black)
            .clip(RoundedCornerShape(12.dp))
    ) {
        // Video surface
        AndroidView(
            factory = { ctx ->
                PlayerView(ctx).apply {
                    player = exoPlayer
                    useController = true
                    controllerShowTimeoutMs = 750
                    controllerHideOnTouch = true
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
                // Show/hide controller based on play state
                if (isPlaying) {
                    playerView.showController()
                } else {
                    playerView.hideController()
                }
            },
            modifier = Modifier.fillMaxSize()
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
