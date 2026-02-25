package net.kibotu.trail.shared.extensions

import android.app.Activity
import android.app.Dialog
import android.view.View
import android.view.Window
import androidx.annotation.Px
import androidx.core.view.ViewCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.core.view.updatePaddingRelative

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

var Activity.isLightNavigationBar
    get() = window.isLightNavigationBar
    set(enabled) {
        window.isLightNavigationBar = enabled
    }

var Window.isLightNavigationBar
    get() = WindowCompat.getInsetsController(this, decorView).isAppearanceLightNavigationBars
    set(enabled) {
        WindowCompat.getInsetsController(this, decorView).isAppearanceLightNavigationBars = enabled
    }

var Activity.statusBarColor: Int
    get() = window.statusBarColor
    set(value) {
        window.statusBarColor = value
    }

var Dialog.statusBarColor: Int?
    get() = window?.statusBarColor
    set(value) {
        window?.statusBarColor = value ?: return
    }

var Activity.navigationBarColor: Int
    get() = window.navigationBarColor
    set(value) {
        window.navigationBarColor = value
    }

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
                systemBarsBehavior =
                    WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
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
                systemBarsBehavior =
                    WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                hide(WindowInsetsCompat.Type.navigationBars())
            }
        }
    }

fun View.applyImePadding(@Px additionalPadding: Int = 0) {
    ViewCompat.setOnApplyWindowInsetsListener(this) { view, insets ->
        val imeHeight = insets.getInsets(WindowInsetsCompat.Type.ime()).bottom
        view.updatePaddingRelative(
            bottom = imeHeight + additionalPadding
        )
        insets
    }.also { requestApplyInsetsWhenAttached() }
}

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
