package net.kibotu.trail.shared.util

import android.content.Context
import android.net.Uri
import androidx.browser.customtabs.CustomTabsIntent

fun Context.openInCustomTab(url: String) {
    val intent = CustomTabsIntent.Builder()
        .setShowTitle(true)
        .build()
    intent.launchUrl(this, Uri.parse(url))
}
