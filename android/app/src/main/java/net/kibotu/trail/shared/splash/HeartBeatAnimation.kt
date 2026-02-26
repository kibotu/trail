package net.kibotu.trail.shared.splash

import android.graphics.BitmapFactory
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import net.kibotu.trail.R
import kotlin.math.sqrt
import kotlin.time.Duration
import kotlin.time.DurationUnit

@Composable
fun HeartBeatAnimation(
    modifier: Modifier = Modifier,
    isVisible: Boolean = true,
    exitAnimationDuration: Duration = Duration.ZERO,
    onStartExitAnimation: () -> Unit = {}
) {
    val iconSize = 108.dp
    val circleSize = 144.dp

    val backgroundColor = MaterialTheme.colorScheme.primary
    val context = LocalContext.current

    val iconBitmap = remember {
        BitmapFactory.decodeResource(context.resources, R.drawable.icon)
    }

    var isExitAnimationStarted by remember { mutableStateOf(false) }

    // Track if this is the first composition to avoid animating on initial render
    val isInitialComposition = remember { mutableStateOf(true) }

    LaunchedEffect(Unit) {
        // Mark that initial composition is complete
        isInitialComposition.value = false
    }

    LaunchedEffect(isVisible) {
        if (!isVisible && !isExitAnimationStarted) {
            isExitAnimationStarted = true
            onStartExitAnimation()
        }
    }

    val configuration = LocalConfiguration.current
    val screenWidth = configuration.screenWidthDp
    val screenHeight = configuration.screenHeightDp
    val screenDiagonal = sqrt((screenWidth * screenWidth + screenHeight * screenHeight).toFloat())

    val exitDurationMs = exitAnimationDuration.toInt(DurationUnit.MILLISECONDS)
    val fadeEasing = CubicBezierEasing(0.4f, 0.0f, 0.2f, 1.0f)
    val snappyEasing = CubicBezierEasing(0.2f, 0.0f, 0.2f, 1.0f)

    // Icon scale: starts at 1.0 immediately (no animation), shrinks to 0 during exit
    val iconScale by animateFloatAsState(
        targetValue = if (isExitAnimationStarted) 0f else 1f,
        animationSpec = if (isInitialComposition.value) {
            // No animation on first render - snap to 1.0 immediately
            tween(durationMillis = 0)
        } else {
            tween(durationMillis = exitDurationMs, easing = snappyEasing)
        },
        label = "iconScale"
    )

    // Circle alpha: invisible until exit starts, then quickly fade in
    val circleAlpha by animateFloatAsState(
        targetValue = if (isExitAnimationStarted) 1f else 0f,
        animationSpec = tween(
            durationMillis = 100,
            easing = snappyEasing
        ),
        label = "circleAlpha"
    )

    // Circle scale: grow from center to fill screen
    val circleScale by animateFloatAsState(
        targetValue = if (isExitAnimationStarted) screenDiagonal / circleSize.value else 0f,
        animationSpec = tween(
            durationMillis = exitDurationMs,
            easing = snappyEasing
        ),
        label = "circleScale"
    )

    Box(
        modifier = modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        // Circle - always rendered but invisible until exit
        Box(
            modifier = Modifier
                .size(circleSize)
                .graphicsLayer {
                    scaleX = circleScale
                    scaleY = circleScale
                    alpha = circleAlpha
                }
                .background(
                    color = backgroundColor,
                    shape = CircleShape
                )
        )

        iconBitmap?.let { bitmap ->
            Image(
                bitmap = bitmap.asImageBitmap(),
                contentDescription = null,
                modifier = Modifier
                    .size(iconSize)
                    .graphicsLayer {
                        scaleX = iconScale
                        scaleY = iconScale
                    }
            )
        }
    }
}

