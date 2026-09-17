package net.kibotu.trail.shared.theme.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import dev.chrisbanes.haze.HazeState
import dev.chrisbanes.haze.hazeEffect
import net.kibotu.trail.shared.navigation.LocalAnimatedVisibilityScope
import net.kibotu.trail.shared.navigation.TrailMotion

/**
 * The floating back affordance on detail screens.
 *
 * It animates itself through [androidx.compose.animation.AnimatedVisibilityScope.animateEnterExit]
 * rather than riding the screen transition, because the screens it sits on top of are reached from
 * lists that have no back arrow: sliding it out sideways reads as the control leaving, while
 * letting it inherit the screen's motion just drags it across the content behind.
 */
@Composable
fun BackButton(
    onClick: () -> Unit,
    hazeState: HazeState,
    modifier: Modifier = Modifier,
) {
    val animatedVisibilityScope = LocalAnimatedVisibilityScope.current
    val hazeBackgroundColor = MaterialTheme.colorScheme.surfaceContainerHigh.copy(alpha = 0.6f)

    Box(
        modifier = modifier
            .then(
                animatedVisibilityScope?.run {
                    Modifier.animateEnterExit(
                        enter = TrailMotion.navIconEnter,
                        exit = TrailMotion.navIconExit,
                    )
                } ?: Modifier
            )
            .statusBarsPadding()
            .padding(start = 12.dp, top = 8.dp)
            .size(40.dp)
            .clip(CircleShape)
            .hazeEffect(state = hazeState) { backgroundColor = hazeBackgroundColor }
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            Icons.AutoMirrored.Filled.ArrowBack,
            contentDescription = "Back",
            modifier = Modifier.size(20.dp),
            tint = MaterialTheme.colorScheme.onSurface
        )
    }
}
