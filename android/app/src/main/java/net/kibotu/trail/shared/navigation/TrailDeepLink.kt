package net.kibotu.trail.shared.navigation

import java.net.URI

/**
 * Maps a trail App Link onto the route of the screen that shows the same thing.
 *
 * The manifest claims every path on the app-link host, so this is where the decision lives: a link
 * that names an entry, a profile or a collection opens that screen, everything else falls through
 * to the share flow it used to open.
 */
object TrailDeepLink {
    private const val HOST = "trail.services.kibotu.net"

    // Whole-path matches, so a link that does not name one page exactly is not half-deep-linked.
    // Charsets mirror the backend's validation: hash ids are base62, nicknames are
    // [a-zA-Z0-9_-]{3,50}, collection slugs are [a-z0-9-]{2,64}.
    private val ENTRY = Regex("/status/([A-Za-z0-9]+)/?")
    private val USER = Regex("/@([A-Za-z0-9_-]{3,50})/?")
    private val COLLECTION = Regex("/collection/([a-z0-9-]{2,64})/?")

    fun routeFor(url: String?): String? {
        val uri = url?.let { runCatching { URI(it) }.getOrNull() } ?: return null
        if (uri.host?.equals(HOST, ignoreCase = true) != true) return null
        val path = uri.path ?: return null
        ENTRY.matchEntire(path)?.let { return Routes.entryDetail(it.groupValues[1]) }
        USER.matchEntire(path)?.let { return Routes.userProfile(it.groupValues[1]) }
        COLLECTION.matchEntire(path)?.let { return Routes.collection(it.groupValues[1]) }
        return null
    }
}
