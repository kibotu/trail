package net.kibotu.trail.shared.theme.ui

import androidx.compose.animation.core.FastOutLinearInEasing
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay

object TrailAnimationDefaults {
    const val TRANSITION_DURATION_ENTER = 300
    const val TRANSITION_DURATION_EXIT = 200
    const val INTERACTION_DURATION = 400
    const val STAGGER_DELAY_MS = 30L
    const val STAGGER_MAX_INDEX = 6

    val enterEasing = FastOutSlowInEasing
    val exitEasing = FastOutLinearInEasing
}

/**
 * Animates an item's first appearance with a staggered fade + slide.
 * Uses rememberSaveable so re-scrolling to the same card skips the animation.
 */
fun Modifier.staggeredFadeIn(index: Int): Modifier = composed {
    val alreadyVisible = rememberSaveable { mutableStateOf(false) }

    if (alreadyVisible.value) return@composed this

    LaunchedEffect(Unit) {
        delay(
            (index.coerceAtMost(TrailAnimationDefaults.STAGGER_MAX_INDEX) *
                    TrailAnimationDefaults.STAGGER_DELAY_MS)
        )
        alreadyVisible.value = true
    }
    val alpha by animateFloatAsState(
        targetValue = if (alreadyVisible.value) 1f else 0f,
        animationSpec = tween(220, easing = TrailAnimationDefaults.enterEasing),
        label = "staggerAlpha"
    )
    val offsetY by animateDpAsState(
        targetValue = if (alreadyVisible.value) 0.dp else 12.dp,
        animationSpec = tween(220, easing = TrailAnimationDefaults.enterEasing),
        label = "staggerOffset"
    )
    this.graphicsLayer {
        this.alpha = alpha
        translationY = offsetY.toPx()
    }
}

fun Modifier.pressScale(interactionSource: MutableInteractionSource): Modifier = composed {
    val isPressed by interactionSource.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.98f else 1f,
        animationSpec = spring(
            dampingRatio = Spring.DampingRatioMediumBouncy,
            stiffness = Spring.StiffnessMediumLow
        ),
        label = "pressScale"
    )
    this.graphicsLayer {
        scaleX = scale
        scaleY = scale
    }
}

@Composable
fun rememberHaptic(): () -> Unit {
    val haptic = LocalHapticFeedback.current
    return remember(haptic) {
        { haptic.performHapticFeedback(HapticFeedbackType.LongPress) }
    }
}
