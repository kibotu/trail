package net.kibotu.trail.shared.util

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.browser.customtabs.CustomTabsClient
import androidx.browser.customtabs.CustomTabsIntent

fun Context.openInCustomTab(url: String) {
    val uri = Uri.parse(url)

    try {
        val customTabsIntent = CustomTabsIntent.Builder()
            .setShowTitle(true)
            .setUrlBarHidingEnabled(false)
            .setShareState(CustomTabsIntent.SHARE_STATE_ON)
            .build()

        val intent = customTabsIntent.intent
        intent.data = uri
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

        val packageName = CustomTabsClient.getPackageName(this, null)
        if (packageName != null) {
            intent.setPackage(packageName)
        }

        startActivity(intent)
    } catch (e: ActivityNotFoundException) {
        val intent = Intent(Intent.ACTION_VIEW, uri)
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        startActivity(intent)
    }
}
