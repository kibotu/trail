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
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
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
import androidx.compose.material.icons.filled.Replay
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
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
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
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
import androidx.compose.ui.platform.LocalConfiguration
import coil3.compose.AsyncImage
import coil3.compose.AsyncImagePainter
import coil3.compose.rememberAsyncImagePainter
import coil3.request.ImageRequest
import coil3.request.crossfade
import net.kibotu.trail.shared.entry.MediaItemData
import timber.log.Timber
import kotlin.math.abs
import kotlin.math.roundToInt

/**
 * Displays a gallery of media items (images, GIFs, videos).
 * Each item renders full-width stacked vertically, matching the web layout.
 * Images use horizontal scroll only when there are multiple images and no videos.
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

    val hasVideos = media.any { it.isVideo }

    if (media.size == 1 || hasVideos) {
        Column(
            modifier = modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            media.forEach { item ->
                val hasDimensions = item.width != null && item.height != null && item.height!! > 0
                val itemModifier = if (hasDimensions) {
                    val ratio = (item.width!!.toFloat() / item.height!!.toFloat()).coerceIn(0.5f, 2f)
                    Modifier.fillMaxWidth().aspectRatio(ratio)
                } else if (item.isSvg) {
                    Modifier.fillMaxWidth().height(200.dp)
                } else {
                    Modifier.fillMaxWidth().aspectRatio(16f / 9f)
                }
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                ) {
                    MediaItemView(
                        item = item,
                        baseUrl = baseUrl,
                        currentlyPlayingId = currentlyPlayingId,
                        onVideoPlay = onVideoPlay,
                        modifier = itemModifier
                    )
                }
            }
        }
    } else {
        val screenHeightDp = LocalConfiguration.current.screenHeightDp.dp
        val galleryHeight = (screenHeightDp * 0.3f).coerceIn(140.dp, 280.dp)
        LazyRow(
            modifier = modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            items(media.size, key = { index -> "media_${media[index].id}_$index" }) { index ->
                val item = media[index]
                val hasDimensions = item.width != null && item.height != null && item.height!! > 0
                val itemModifier = if (hasDimensions) {
                    val ratio = (item.width!!.toFloat() / item.height!!.toFloat()).coerceIn(0.5f, 2f)
                    Modifier.height(galleryHeight).aspectRatio(ratio).clip(RoundedCornerShape(12.dp))
                } else if (item.isSvg) {
                    Modifier.height(galleryHeight).clip(RoundedCornerShape(12.dp))
                } else {
                    Modifier.height(galleryHeight).aspectRatio(16f / 9f).clip(RoundedCornerShape(12.dp))
                }
                MediaItemView(
                    item = item,
                    baseUrl = baseUrl,
                    currentlyPlayingId = currentlyPlayingId,
                    onVideoPlay = onVideoPlay,
                    modifier = itemModifier
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
    var svgUsesWebView by remember { mutableStateOf(false) }

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
        item.isSvg -> {
            SvgImage(
                url = fullUrl,
                contentDescription = "SVG image",
                onClick = { showFullscreenViewer = true },
                onWebViewFallback = { svgUsesWebView = it },
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
        if (item.isSvg && svgUsesWebView) {
            FullscreenSvgViewer(
                url = fullUrl,
                onDismiss = { showFullscreenViewer = false }
            )
        } else {
            FullscreenImageViewer(
                url = fullUrl,
                onDismiss = { showFullscreenViewer = false }
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
 * Displays an SVG image. First attempts native rendering via Coil's SvgDecoder.
 * If the decoded bitmap is blank (e.g. the SVG uses <foreignObject> with embedded HTML,
 * which AndroidSVG doesn't support), falls back to a WebView for full rendering.
 */
@Composable
fun SvgImage(
    url: String,
    contentDescription: String,
    onClick: (() -> Unit)? = null,
    onWebViewFallback: ((Boolean) -> Unit)? = null,
    modifier: Modifier = Modifier
) {
    var useWebView by remember { mutableStateOf(false) }

    if (useWebView) {
        SvgWebView(
            url = url,
            onClick = onClick,
            modifier = modifier
        )
    } else {
        val context = LocalContext.current
        val imageLoader = remember { coil3.SingletonImageLoader.get(context) }
        val painter = rememberAsyncImagePainter(
            model = ImageRequest.Builder(context)
                .data(url)
                .crossfade(true)
                .build(),
            imageLoader = imageLoader,
            onSuccess = { state ->
                val bitmap = (state.result.image as? coil3.BitmapImage)?.bitmap
                if (bitmap != null && isBitmapBlank(bitmap)) {
                    Timber.d("SVG rendered blank via Coil, falling back to WebView: %s", url)
                    useWebView = true
                    onWebViewFallback?.invoke(true)
                }
            },
            onError = { state ->
                Timber.w(state.result.throwable, "SVG failed to load via Coil, falling back to WebView: %s", url)
                useWebView = true
                onWebViewFallback?.invoke(true)
            }
        )

        val intrinsicSize = painter.intrinsicSize
        val aspectModifier = if (
            painter.state is AsyncImagePainter.State.Success &&
            intrinsicSize.width > 0 && intrinsicSize.height > 0 &&
            intrinsicSize.width.isFinite() && intrinsicSize.height.isFinite()
        ) {
            modifier.aspectRatio(intrinsicSize.width / intrinsicSize.height)
        } else {
            modifier
        }

        androidx.compose.foundation.Image(
            painter = painter,
            contentDescription = contentDescription,
            contentScale = ContentScale.Fit,
            modifier = aspectModifier
                .background(Color.White, RoundedCornerShape(8.dp))
                .clip(RoundedCornerShape(8.dp))
                .then(
                    if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier
                )
        )
    }
}

/**
 * Checks if a bitmap is effectively blank (all pixels are transparent or white).
 * Samples a grid of pixels for performance.
 */
private fun isBitmapBlank(bitmap: android.graphics.Bitmap): Boolean {
    val w = bitmap.width
    val h = bitmap.height
    if (w == 0 || h == 0) return true

    val step = maxOf(1, minOf(w, h) / 10)
    for (y in 0 until h step step) {
        for (x in 0 until w step step) {
            val pixel = bitmap.getPixel(x, y)
            val alpha = (pixel ushr 24) and 0xFF
            if (alpha == 0) continue
            val r = (pixel ushr 16) and 0xFF
            val g = (pixel ushr 8) and 0xFF
            val b = pixel and 0xFF
            if (r < 250 || g < 250 || b < 250) return false
        }
    }
    return true
}

/**
 * Renders an SVG via WebView — handles complex SVGs with foreignObject,
 * embedded HTML/CSS, and fonts that AndroidSVG cannot render.
 * Loads the SVG URL directly so the browser engine renders it as a document.
 * A transparent overlay captures taps for fullscreen; the inline WebView
 * itself has touch disabled so it doesn't steal scroll/click events.
 */
@Composable
private fun SvgWebView(
    url: String,
    onClick: (() -> Unit)? = null,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier.clip(RoundedCornerShape(8.dp))
    ) {
        AndroidView(
            factory = { ctx ->
                android.webkit.WebView(ctx).apply {
                    setBackgroundColor(android.graphics.Color.TRANSPARENT)
                    settings.apply {
                        loadWithOverviewMode = true
                        useWideViewPort = true
                        builtInZoomControls = false
                        displayZoomControls = false
                        javaScriptEnabled = false
                        @Suppress("DEPRECATION")
                        allowFileAccess = false
                        setSupportZoom(false)
                    }
                    isVerticalScrollBarEnabled = false
                    isHorizontalScrollBarEnabled = false
                    isFocusable = false
                    isFocusableInTouchMode = false
                    isClickable = false
                    loadUrl(url)
                }
            },
            modifier = Modifier.fillMaxSize()
        )
        Box(
            modifier = Modifier
                .fillMaxSize()
                .pointerInput(onClick) {
                    if (onClick != null) {
                        detectTapGestures { onClick() }
                    } else {
                        awaitPointerEventScope {
                            while (true) {
                                val event = awaitPointerEvent()
                                event.changes.forEach { it.consume() }
                            }
                        }
                    }
                }
        )
    }
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
    var hasEnded by remember { mutableStateOf(false) }

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

    // Listen for playback ended
    DisposableEffect(exoPlayer) {
        val listener = object : Player.Listener {
            override fun onPlaybackStateChanged(state: Int) {
                if (state == Player.STATE_ENDED) {
                    hasEnded = true
                    onPlayStateChange(false)
                    showControlOverlay = false
                }
            }
        }
        exoPlayer.addListener(listener)
        onDispose { exoPlayer.removeListener(listener) }
    }

    LaunchedEffect(isPlaying) {
        if (isPlaying) {
            if (hasEnded) {
                exoPlayer.seekTo(0)
                hasEnded = false
            }
            exoPlayer.play()
            showControlOverlay = true
            controlOverlayTrigger = System.nanoTime()
        } else {
            exoPlayer.pause()
            // On pause: keep controls visible (matches web behavior)
            if (!hasEnded) {
                showControlOverlay = true
            }
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
        currentPositionMs = exoPlayer.currentPosition
        durationMs = exoPlayer.duration.coerceAtLeast(0L)
    }

    // Auto-hide controls only while playing (paused keeps them visible, matching web)
    LaunchedEffect(controlOverlayTrigger) {
        if (showControlOverlay && isPlaying) {
            kotlinx.coroutines.delay(2500)
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
                    hasEnded = hasEnded,
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
        hasEnded = hasEnded,
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
    val hrs = totalSec / 3600
    val min = (totalSec % 3600) / 60
    val sec = totalSec % 60
    return if (hrs > 0) "%d:%02d:%02d".format(hrs, min, sec)
    else "%d:%02d".format(min, sec)
}

/**
 * Thin seek bar matching web styling: 4dp track, 12dp draggable handle.
 */
@Composable
private fun VideoSeekBar(
    currentMs: Long,
    durationMs: Long,
    onSeekStart: () -> Unit,
    onSeek: (Long) -> Unit,
    onSeekEnd: () -> Unit,
    modifier: Modifier = Modifier
) {
    val fraction = if (durationMs > 0) (currentMs.toFloat() / durationMs).coerceIn(0f, 1f) else 0f
    val trackHeight = 4.dp
    val thumbRadius = 6.dp
    val touchTargetHeight = 24.dp

    Box(
        modifier = modifier.height(touchTargetHeight),
        contentAlignment = Alignment.Center
    ) {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(trackHeight)
                .pointerInput(durationMs) {
                    detectHorizontalDragGestures(
                        onDragStart = { offset ->
                            onSeekStart()
                            val pct = (offset.x / size.width).coerceIn(0f, 1f)
                            onSeek((pct * durationMs).toLong())
                        },
                        onHorizontalDrag = { change, _ ->
                            change.consume()
                            val pct = (change.position.x / size.width).coerceIn(0f, 1f)
                            onSeek((pct * durationMs).toLong())
                        },
                        onDragEnd = { onSeekEnd() },
                        onDragCancel = { onSeekEnd() }
                    )
                }
                .pointerInput(durationMs) {
                    detectTapGestures { offset ->
                        val pct = (offset.x / size.width).coerceIn(0f, 1f)
                        onSeekStart()
                        onSeek((pct * durationMs).toLong())
                        onSeekEnd()
                    }
                }
        ) {
            val trackHeightPx = trackHeight.toPx()
            val cornerPx = trackHeightPx / 2f
            val thumbRadiusPx = thumbRadius.toPx()

            // Inactive track
            drawRoundRect(
                color = Color.White.copy(alpha = 0.25f),
                size = Size(size.width, trackHeightPx),
                cornerRadius = CornerRadius(cornerPx, cornerPx)
            )
            // Active track
            val filledWidth = size.width * fraction
            if (filledWidth > 0f) {
                drawRoundRect(
                    color = Color.White,
                    size = Size(filledWidth, trackHeightPx),
                    cornerRadius = CornerRadius(cornerPx, cornerPx)
                )
            }
            // Thumb
            drawCircle(
                color = Color.White,
                radius = thumbRadiusPx,
                center = Offset(filledWidth.coerceIn(thumbRadiusPx, size.width - thumbRadiusPx), trackHeightPx / 2f)
            )
        }
    }
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
    hasEnded: Boolean,
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

        // Play/Replay button overlay when not playing
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
                        imageVector = if (hasEnded) Icons.Filled.Replay else Icons.Filled.PlayArrow,
                        contentDescription = if (hasEnded) "Replay video" else "Play video",
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
                // Bottom controls bar with gradient scrim (matches web)
                Column(
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .fillMaxWidth()
                        .background(
                            Brush.verticalGradient(
                                colors = listOf(Color.Transparent, Color.Black.copy(alpha = 0.8f))
                            )
                        )
                        .padding(horizontal = if (isFullscreen) 16.dp else 8.dp)
                ) {
                    // Thin seek bar (matches web: 4px track, 12px handle)
                    if (durationMs > 0) {
                        VideoSeekBar(
                            currentMs = currentPositionMs,
                            durationMs = durationMs,
                            onSeekStart = onSeekStart,
                            onSeek = onSeek,
                            onSeekEnd = onSeekEnd,
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = if (isFullscreen) 8.dp else 4.dp)
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
                            modifier = Modifier.size(if (isFullscreen) 40.dp else 28.dp)
                        ) {
                            Icon(
                                imageVector = if (isPlaying) Icons.Filled.Pause else Icons.Filled.PlayArrow,
                                contentDescription = if (isPlaying) "Pause" else "Play",
                                tint = Color.White,
                                modifier = Modifier.size(if (isFullscreen) 22.dp else 18.dp)
                            )
                        }

                        // Timestamp
                        if (durationMs > 0) {
                            Text(
                                text = "${formatDuration(currentPositionMs)} / ${formatDuration(durationMs)}",
                                color = Color.White.copy(alpha = 0.9f),
                                fontSize = if (isFullscreen) 13.sp else 11.sp,
                                fontWeight = FontWeight.Medium,
                                modifier = Modifier.padding(start = 2.dp)
                            )
                        }

                        Spacer(modifier = Modifier.weight(1f))

                        // Mute
                        IconButton(
                            onClick = onMuteToggle,
                            modifier = Modifier.size(if (isFullscreen) 40.dp else 28.dp)
                        ) {
                            Icon(
                                imageVector = if (isMuted) Icons.AutoMirrored.Filled.VolumeOff else Icons.AutoMirrored.Filled.VolumeUp,
                                contentDescription = if (isMuted) "Unmute" else "Mute",
                                tint = Color.White,
                                modifier = Modifier.size(if (isFullscreen) 22.dp else 16.dp)
                            )
                        }

                        // Fullscreen
                        IconButton(
                            onClick = onFullscreenToggle,
                            modifier = Modifier.size(if (isFullscreen) 40.dp else 28.dp)
                        ) {
                            Icon(
                                imageVector = if (isFullscreen) Icons.Filled.FullscreenExit else Icons.Filled.Fullscreen,
                                contentDescription = if (isFullscreen) "Exit fullscreen" else "Fullscreen",
                                tint = Color.White,
                                modifier = Modifier.size(if (isFullscreen) 22.dp else 16.dp)
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
 * Fullscreen SVG viewer using WebView with built-in zoom, text selection, and scroll.
 * The WebView owns all touch events so users can pinch-to-zoom, select text, and pan.
 * Dismiss via close button or system back.
 */
@Composable
private fun FullscreenSvgViewer(
    url: String,
    onDismiss: () -> Unit
) {
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
                .background(Color.Black)
        ) {
            AndroidView(
                factory = { ctx ->
                    android.webkit.WebView(ctx).apply {
                        setBackgroundColor(android.graphics.Color.BLACK)
                        settings.apply {
                            loadWithOverviewMode = true
                            useWideViewPort = true
                            builtInZoomControls = true
                            displayZoomControls = false
                            javaScriptEnabled = false
                            @Suppress("DEPRECATION")
                            allowFileAccess = false
                            setSupportZoom(true)
                        }
                        loadUrl(url)
                    }
                },
                modifier = Modifier.fillMaxSize()
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
}

/**
 * Builds the full URL from base URL and relative path
 */
private fun buildFullUrl(baseUrl: String, path: String): String {
    if (path.startsWith("http://") || path.startsWith("https://")) return path
    if (baseUrl.isBlank()) return path
    return "${baseUrl.trimEnd('/')}/${path.trimStart('/')}"
}
