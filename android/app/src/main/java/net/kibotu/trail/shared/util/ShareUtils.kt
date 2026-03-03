package net.kibotu.trail.shared.util

import android.content.Context
import android.content.Intent
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.shared.entry.Entry

fun shareEntry(context: Context, entry: Entry) {
    val url = "${BuildConfig.WEB_BASE_URL}/status/${entry.hashId}"
    val intent = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, "${entry.text}\n$url")
    }
    context.startActivity(Intent.createChooser(intent, "Share entry"))
}
