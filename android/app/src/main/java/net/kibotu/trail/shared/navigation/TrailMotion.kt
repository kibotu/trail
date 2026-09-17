package net.kibotu.trail.shared.navigation

import androidx.compose.animation.EnterTransition
import androidx.compose.animation.ExitTransition
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.FiniteAnimationSpec
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.slideOutVertically
import androidx.compose.ui.geometry.Rect
import androidx.navigation.NavBackStackEntry

/**
 * Every navigation animation in the app, in one place.
 *
 * Screens fall into three families and the family of the *arriving* screen decides the motion
 * (when popping, the family of the screen being *removed* decides it, so a push and its pop are
 * exact mirrors):
 *
 * - [Kind.Tab] fades through. Tabs are siblings, so any lateral movement would wrongly imply depth.
 * - [Kind.Entry] only fades. The entry card's shared bounds already carry the eye from the list to
 *   the detail, and a second spatial animation on the screen underneath would fight it.
 * - [Kind.Push] slides in from the right over a parallaxing parent, the usual "one level deeper" idiom.
 *
 * Pop and predictive-pop use the same values, so a flung back gesture and a tapped back button are
 * indistinguishable, and a half-completed gesture just seeks the same timeline.
 */
object TrailMotion {

    private const val PushMs = 300
    private const val FadeMs = 220
    private const val FadeThroughOutMs = 70
    private const val FadeThroughInMs = 140

    /** How far the screen left behind by a push drifts, as a fraction of its width. */
    private const val ParallaxDivisor = 4

    private val Emphasized = CubicBezierEasing(0.2f, 0f, 0f, 1f)
    private val EmphasizedDecelerate = CubicBezierEasing(0.05f, 0.7f, 0.1f, 1f)
    private val EmphasizedAccelerate = CubicBezierEasing(0.3f, 0f, 0.8f, 0.15f)

    private enum class Kind { Tab, Entry, Push }

    private fun kindOf(entry: NavBackStackEntry): Kind {
        val route = entry.destination.route ?: return Kind.Tab
        return when {
            route == Routes.ENTRY_DETAIL -> Kind.Entry
            route in Routes.TABS -> Kind.Tab
            else -> Kind.Push
        }
    }

    private val fadeThroughIn =
        fadeIn(tween(FadeThroughInMs, delayMillis = FadeThroughOutMs, easing = LinearEasing))
    private val fadeThroughOut = fadeOut(tween(FadeThroughOutMs, easing = LinearEasing))

    private val containerIn = fadeIn(tween(FadeMs, easing = EmphasizedDecelerate))
    private val containerOut = fadeOut(tween(FadeMs, easing = EmphasizedAccelerate))

    private val pushIn = slideInHorizontally(tween(PushMs, easing = Emphasized)) { it }
    private val pushOut =
        slideOutHorizontally(tween(PushMs, easing = Emphasized)) { -it / ParallaxDivisor }
    private val popIn =
        slideInHorizontally(tween(PushMs, easing = Emphasized)) { -it / ParallaxDivisor }
    private val popOut = slideOutHorizontally(tween(PushMs, easing = Emphasized)) { it }

    fun enter(target: NavBackStackEntry): EnterTransition = when (kindOf(target)) {
        Kind.Tab -> fadeThroughIn
        Kind.Entry -> containerIn
        Kind.Push -> pushIn
    }

    fun exit(target: NavBackStackEntry): ExitTransition = when (kindOf(target)) {
        Kind.Tab -> fadeThroughOut
        Kind.Entry -> containerOut
        Kind.Push -> pushOut
    }

    fun popEnter(popped: NavBackStackEntry): EnterTransition = when (kindOf(popped)) {
        Kind.Tab -> fadeThroughIn
        Kind.Entry -> containerIn
        Kind.Push -> popIn
    }

    fun popExit(popped: NavBackStackEntry): ExitTransition = when (kindOf(popped)) {
        Kind.Tab -> fadeThroughOut
        Kind.Entry -> containerOut
        Kind.Push -> popOut
    }

    /** Timing for [androidx.compose.animation.SharedTransitionScope.sharedBounds] on entry cards. */
    val sharedBounds: FiniteAnimationSpec<Rect> = tween(PushMs, easing = Emphasized)

    /** Cross-fade between the list and detail versions of a shared entry card. */
    val sharedContentIn = fadeIn(tween(FadeMs, easing = LinearEasing))
    val sharedContentOut = fadeOut(tween(FadeMs, easing = LinearEasing))

    /**
     * The back arrow leaves sideways instead of riding the screen's own transition, so it reads as
     * "this control is going away" rather than drifting across the content behind it.
     */
    val navIconEnter: EnterTransition =
        slideInHorizontally(tween(PushMs, delayMillis = 60, easing = EmphasizedDecelerate)) { -it } +
            fadeIn(tween(FadeMs, delayMillis = 60))
    val navIconExit: ExitTransition =
        slideOutHorizontally(tween(PushMs, easing = EmphasizedAccelerate)) { -it } +
            fadeOut(tween(FadeMs / 2))

    val tabBarEnter: EnterTransition =
        slideInVertically(tween(PushMs, delayMillis = 60, easing = EmphasizedDecelerate)) { it } +
            fadeIn(tween(FadeMs, delayMillis = 60))
    val tabBarExit: ExitTransition =
        slideOutVertically(tween(PushMs, easing = EmphasizedAccelerate)) { it } +
            fadeOut(tween(FadeMs / 2))
}
