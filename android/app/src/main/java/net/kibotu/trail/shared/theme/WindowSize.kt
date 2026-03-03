package net.kibotu.trail.shared.theme

import androidx.compose.material3.windowsizeclass.ExperimentalMaterial3WindowSizeClassApi
import androidx.compose.material3.windowsizeclass.WindowSizeClass
import androidx.compose.material3.windowsizeclass.WindowWidthSizeClass
import androidx.compose.runtime.staticCompositionLocalOf

@OptIn(ExperimentalMaterial3WindowSizeClassApi::class)
val LocalWindowSizeClass = staticCompositionLocalOf<WindowSizeClass> {
    error("No WindowSizeClass provided")
}

@OptIn(ExperimentalMaterial3WindowSizeClassApi::class)
val WindowSizeClass.isCompactWidth: Boolean
    get() = widthSizeClass == WindowWidthSizeClass.Compact
