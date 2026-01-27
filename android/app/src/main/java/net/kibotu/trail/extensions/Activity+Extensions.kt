package net.kibotu.trail.extensions


import android.app.Activity
import android.app.Dialog
import android.view.View
import android.view.Window
import androidx.annotation.ColorInt
import androidx.annotation.Px
import androidx.core.view.ViewCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.core.view.updatePaddingRelative


/**
 * true for dark tinted icons
 */
var Activity.isLightStatusBar
    get() = window.isLightStatusBar
    set(enabled) {
        window.isLightStatusBar = enabled
    }

var Window.isLightStatusBar
    get() = WindowCompat.getInsetsController(this, decorView).isAppearanceLightStatusBars
    set(enabled) {
        WindowCompat.getInsetsController(this, decorView).isAppearanceLightStatusBars = enabled
    }

/**
 * true for dark tinted icons
 */
var Activity.isLightNavigationBar
    get() = window.isLightNavigationBar
    set(enabled) {
        window.isLightNavigationBar = enabled
    }

/**
 * true for dark tinted icons
 */

var Window.isLightNavigationBar
    get() = WindowCompat.getInsetsController(this, decorView).isAppearanceLightNavigationBars
    set(enabled) {
        WindowCompat.getInsetsController(this, decorView).isAppearanceLightNavigationBars = enabled
    }

/**
 * sets status bar color [ColorInt]
 */
var Activity.statusBarColor: Int
    get() = window.statusBarColor
    set(value) {
        window.statusBarColor = value
    }

/**
 * sets status bar color [ColorInt]
 */
var Dialog.statusBarColor: Int?
    get() = window?.statusBarColor
    set(value) {
        window?.statusBarColor = value ?: return
    }

/**
 * Sets navigation bar color [ColorInt]
 */
var Activity.navigationBarColor: Int
    get() = window.navigationBarColor
    set(value) {
        window.navigationBarColor = value
    }

/**
 * Sets navigation bar color [ColorInt]
 */
var Dialog.navigationBarColor: Int?
    get() = window?.navigationBarColor
    set(value) {
        window?.navigationBarColor = value ?: return
    }


fun Window.toggleAdjustResize(view: View?, windowSoftInputMode: Int) {
    setSoftInputMode(windowSoftInputMode)
    view?.applyImePadding()
}

var Window.showSystemStatusBar: Boolean
    get() {
        throw NotImplementedError()
    }
    set(value) {
        with(WindowCompat.getInsetsController(this, this.decorView)) {
            if (value) {
                show(WindowInsetsCompat.Type.statusBars())
            } else {
                systemBarsBehavior = WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                hide(WindowInsetsCompat.Type.statusBars())
            }
        }
    }

var Window.showSystemNavigationBar: Boolean
    get() {
        throw NotImplementedError()
    }
    set(value) {
        with(WindowCompat.getInsetsController(this, this.decorView)) {
            if (value) {
                show(WindowInsetsCompat.Type.navigationBars())
            } else {
                systemBarsBehavior = WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                hide(WindowInsetsCompat.Type.navigationBars())
            }
        }
    }


/**
 * Applies padding to account for IME (keyboard) height in a safe and idiomatic way.
 * Preserves existing padding on other sides.
 *
 * @param additionalPadding Additional padding to add to the IME height in pixels
 */
fun View.applyImePadding(@Px additionalPadding: Int = 0) {
    ViewCompat.setOnApplyWindowInsetsListener(this) { view, insets ->
        val imeHeight = insets.getInsets(WindowInsetsCompat.Type.ime()).bottom
        view.updatePaddingRelative(
            bottom = imeHeight + additionalPadding
        )
        insets
    }.also { requestApplyInsetsWhenAttached() }
}


/**
 * Helper function to request insets when view is attached
 */
private fun View.requestApplyInsetsWhenAttached() {
    if (isAttachedToWindow) {
        ViewCompat.requestApplyInsets(this)
    } else {
        addOnAttachStateChangeListener(object : View.OnAttachStateChangeListener {
            override fun onViewAttachedToWindow(v: View) {
                ViewCompat.requestApplyInsets(v)
                removeOnAttachStateChangeListener(this)
            }

            override fun onViewDetachedFromWindow(v: View) = Unit
        })
    }
}