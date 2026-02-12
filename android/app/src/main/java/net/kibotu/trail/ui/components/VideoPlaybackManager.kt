package net.kibotu.trail.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Manages video playback state to ensure only one video plays at a time.
 * 
 * Usage:
 * - Call `playVideo(id)` when a video should start playing
 * - Call `stopVideo()` when all videos should stop
 * - Observe `currentlyPlayingId` to know which video is playing
 * - When a new video starts, the previous one automatically stops
 */
class VideoPlaybackManager {
    private val _currentlyPlayingId = MutableStateFlow<String?>(null)
    
    /**
     * The ID of the currently playing video, or null if no video is playing
     */
    val currentlyPlayingId: StateFlow<String?> = _currentlyPlayingId.asStateFlow()
    
    /**
     * Start playing a video. Stops any other currently playing video.
     * 
     * @param id The unique identifier of the video to play (e.g., "media_123")
     */
    fun playVideo(id: String) {
        _currentlyPlayingId.value = id
    }
    
    /**
     * Stop all video playback
     */
    fun stopVideo() {
        _currentlyPlayingId.value = null
    }
    
    /**
     * Toggle playback for a specific video
     * 
     * @param id The unique identifier of the video
     * @return true if the video is now playing, false if stopped
     */
    fun toggleVideo(id: String): Boolean {
        return if (_currentlyPlayingId.value == id) {
            stopVideo()
            false
        } else {
            playVideo(id)
            true
        }
    }
    
    /**
     * Check if a specific video is currently playing
     * 
     * @param id The unique identifier of the video
     * @return true if this video is playing
     */
    fun isPlaying(id: String): Boolean {
        return _currentlyPlayingId.value == id
    }
}

/**
 * CompositionLocal for accessing the VideoPlaybackManager throughout the compose tree.
 * This allows video components to coordinate playback without prop drilling.
 */
val LocalVideoPlaybackManager = compositionLocalOf<VideoPlaybackManager?> { null }

/**
 * Creates and remembers a VideoPlaybackManager instance.
 * Use this at the top level of your composition to provide the manager.
 */
@Composable
fun rememberVideoPlaybackManager(): VideoPlaybackManager {
    return remember { VideoPlaybackManager() }
}

/**
 * Simple state holder for video playback that can be used without a full manager.
 * Useful for simpler use cases where you don't need the full manager.
 */
@Composable
fun rememberVideoPlaybackState(): VideoPlaybackState {
    var currentlyPlayingId by remember { mutableStateOf<String?>(null) }
    
    return remember(currentlyPlayingId) {
        VideoPlaybackState(
            currentlyPlayingId = currentlyPlayingId,
            onPlayVideo = { id -> currentlyPlayingId = id },
            onStopVideo = { currentlyPlayingId = null }
        )
    }
}

/**
 * Simple state class for video playback
 */
data class VideoPlaybackState(
    val currentlyPlayingId: String?,
    val onPlayVideo: (String?) -> Unit,
    val onStopVideo: () -> Unit
)
