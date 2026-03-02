package net.kibotu.trail.shared.splash

import android.graphics.BitmapFactory
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.keyframes
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import net.kibotu.trail.R
import net.kibotu.trail.shared.theme.LightTertiary
import kotlin.math.sqrt
import kotlin.random.Random
import kotlin.time.Duration
import kotlin.time.DurationUnit

@Stable
private class Sparkle(
    val offsetX: Float,
    val offsetY: Float,
    val driftX: Float,
    val driftY: Float,
    val size: Float,
    val color: Color,
    val delayMs: Int,
    val rotationDeg: Float,
    val rotationSpeed: Float,
    val peakAt: Float,
    val fadeAt: Float,
    val durationFactor: Float
) {
    val progress = Animatable(0f)
}

private val sparkleColors = listOf(
    Color(0xFFFBBF24), // gold/sun
    Color(0xFF60A5FA), // sky blue
    Color(0xFF34D399), // leaf green
    Color(0xFFF472B6), // pink
    Color(0xFFA78BFA), // purple
    Color(0xFFFFFFFF), // white
)

@Composable
fun HeartBeatAnimation(
    modifier: Modifier = Modifier,
    isVisible: Boolean = true,
    exitAnimationDuration: Duration = Duration.ZERO,
    onStartExitAnimation: () -> Unit = {}
) {
    val iconSize = 160.dp
    val circleSize = 144.dp

    val backgroundColor = LightTertiary
    val context = LocalContext.current
    val density = LocalDensity.current

    val iconBitmap = remember {
        BitmapFactory.decodeResource(context.resources, R.drawable.icon)
    }

    var isExitAnimationStarted by remember { mutableStateOf(false) }
    val isInitialComposition = remember { mutableStateOf(true) }

    LaunchedEffect(Unit) {
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
    val snappyEasing = CubicBezierEasing(0.2f, 0.0f, 0.2f, 1.0f)

    val iconPx = with(density) { iconSize.toPx() }
    val trailDurationMs = (exitDurationMs * 0.55f).toInt()
    val trailEasing = CubicBezierEasing(0.4f, 0.0f, 0.0f, 1.0f)

    val iconProgress = remember { Animatable(0f) }
    val iconAlpha = remember { Animatable(1f) }
    val curveDirection = remember { if (Random.nextBoolean()) 1f else -1f }

    val sparkleCount = 20
    val sparkles = remember {
        val rng = Random(System.nanoTime())
        List(sparkleCount) { _ ->
            Sparkle(
                offsetX = (rng.nextFloat() - 0.5f) * iconPx * 3.5f,
                offsetY = (rng.nextFloat() - 0.5f) * iconPx * 3.5f,
                driftX = (rng.nextFloat() - 0.5f) * iconPx * 1.2f,
                driftY = (rng.nextFloat() - 0.5f) * iconPx * 1.2f,
                size = 4f + rng.nextFloat() * 20f,
                color = sparkleColors[rng.nextInt(sparkleColors.size)],
                delayMs = (rng.nextFloat() * 180f).toInt(),
                rotationDeg = rng.nextFloat() * 360f,
                rotationSpeed = (rng.nextFloat() - 0.5f) * 240f,
                peakAt = 0.15f + rng.nextFloat() * 0.2f,
                fadeAt = 0.5f + rng.nextFloat() * 0.3f,
                durationFactor = 0.6f + rng.nextFloat() * 0.5f
            )
        }
    }

    LaunchedEffect(isExitAnimationStarted) {
        if (!isExitAnimationStarted) return@LaunchedEffect

        launch {
            iconProgress.animateTo(
                targetValue = 1f,
                animationSpec = tween(
                    durationMillis = trailDurationMs,
                    easing = trailEasing
                )
            )
        }

        launch {
            delay((trailDurationMs * 0.5f).toLong())
            iconAlpha.animateTo(
                targetValue = 0f,
                animationSpec = tween(
                    durationMillis = (trailDurationMs * 0.35f).toInt(),
                    easing = CubicBezierEasing(0.4f, 0.0f, 1.0f, 1.0f)
                )
            )
        }

        sparkles.forEach { sparkle ->
            launch {
                delay(sparkle.delayMs.toLong())
                val dur = (trailDurationMs * sparkle.durationFactor).toInt()
                sparkle.progress.animateTo(
                    targetValue = 1f,
                    animationSpec = keyframes {
                        durationMillis = dur
                        1f at (dur * sparkle.peakAt).toInt()
                        1f at (dur * sparkle.fadeAt).toInt()
                        0f at dur
                    }
                )
            }
        }
    }

    val circleAlpha by animateFloatAsState(
        targetValue = if (isExitAnimationStarted) 1f else 0f,
        animationSpec = tween(
            durationMillis = 100,
            easing = snappyEasing
        ),
        label = "circleAlpha"
    )

    val circleScale by animateFloatAsState(
        targetValue = if (isExitAnimationStarted) screenDiagonal / circleSize.value else 0f,
        animationSpec = tween(
            durationMillis = exitDurationMs,
            easing = snappyEasing
        ),
        label = "circleScale"
    )

    val p = iconProgress.value
    val curveX = curveDirection * iconPx * 0.6f * p * (1f - p) * 4f
    val curveY = -iconPx * 2.8f * p
    val tilt = curveDirection * -8f * p * (1f - p) * 4f
    val iconScale = 1f + 0.1f * p * (1f - p) * 4f - 0.3f * p * p

    Box(
        modifier = modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Box(
            modifier = Modifier
                .size(circleSize)
                .graphicsLayer {
                    translationX = curveX
                    translationY = curveY
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
            val imageBitmap = remember { bitmap.asImageBitmap() }

            Canvas(modifier = Modifier.fillMaxSize()) {
                val cx = size.width / 2f
                val cy = size.height / 2f

                sparkles.forEach { sparkle ->
                    val sp = sparkle.progress.value
                    if (sp > 0f) {
                        val sparkleX = cx + curveX * 0.6f +
                            sparkle.offsetX * (0.5f + p * 0.5f) +
                            sparkle.driftX * sp
                        val sparkleY = cy + curveY * 0.6f +
                            sparkle.offsetY * (0.5f + p * 0.5f) +
                            sparkle.driftY * sp
                        val sparkleSize = sparkle.size * sp
                        val sparkleAlpha = sp.coerceIn(0f, 1f) * 0.9f

                        rotate(
                            degrees = sparkle.rotationDeg + sparkle.rotationSpeed * sp,
                            pivot = Offset(sparkleX, sparkleY)
                        ) {
                            drawFourPointStar(
                                center = Offset(sparkleX, sparkleY),
                                size = sparkleSize,
                                color = sparkle.color,
                                alpha = sparkleAlpha
                            )
                        }
                    }
                }
            }

            Image(
                bitmap = imageBitmap,
                contentDescription = null,
                modifier = Modifier
                    .size(iconSize)
                    .graphicsLayer {
                        translationX = curveX
                        translationY = curveY
                        scaleX = iconScale.coerceAtLeast(0f)
                        scaleY = iconScale.coerceAtLeast(0f)
                        rotationZ = tilt
                        alpha = iconAlpha.value
                    }
            )
        }
    }
}

private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawFourPointStar(
    center: Offset,
    size: Float,
    color: Color,
    alpha: Float
) {
    val inner = size * 0.3f
    val path = Path().apply {
        moveTo(center.x, center.y - size)
        lineTo(center.x + inner, center.y - inner)
        lineTo(center.x + size, center.y)
        lineTo(center.x + inner, center.y + inner)
        lineTo(center.x, center.y + size)
        lineTo(center.x - inner, center.y + inner)
        lineTo(center.x - size, center.y)
        lineTo(center.x - inner, center.y - inner)
        close()
    }
    drawPath(path = path, color = color, alpha = alpha)
}
