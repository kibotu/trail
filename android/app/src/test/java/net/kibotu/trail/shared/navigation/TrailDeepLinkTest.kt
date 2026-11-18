package net.kibotu.trail.shared.navigation

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * Pins down which App Links are opened in place and which stay on the share flow. Tapping a link is
 * the only way into a screen the user was not already looking at, so the mapping is worth a test.
 */
class TrailDeepLinkTest {

    private fun deepLink(url: String?) = TrailDeepLink.routeFor(url)

    @Test
    fun status_link_opens_the_entry() {
        assertEquals(
            Routes.entryDetail("000NX2YY"),
            deepLink("https://trail.services.kibotu.net/status/000NX2YY")
        )
    }

    @Test
    fun profile_link_opens_the_profile_without_the_at_sign() {
        assertEquals(
            Routes.userProfile("jan_rabe"),
            deepLink("https://trail.services.kibotu.net/@jan_rabe")
        )
    }

    @Test
    fun collection_link_opens_the_collection() {
        assertEquals(
            Routes.collection("trail-links"),
            deepLink("https://trail.services.kibotu.net/collection/trail-links")
        )
    }

    @Test
    fun trailing_slash_and_query_are_ignored() {
        assertEquals(
            Routes.entryDetail("000NX2YY"),
            deepLink("https://trail.services.kibotu.net/status/000NX2YY/?utm_source=share")
        )
    }

    @Test
    fun host_case_does_not_matter() {
        assertEquals(
            Routes.entryDetail("000NX2YY"),
            deepLink("https://TRAIL.SERVICES.KIBOTU.NET/status/000NX2YY")
        )
    }

    @Test
    fun link_for_another_host_stays_on_the_share_flow() {
        assertNull(deepLink("https://kibotu.net/status/000NX2YY"))
    }

    @Test
    fun path_that_is_not_an_entity_page_stays_on_the_share_flow() {
        assertNull(deepLink("https://trail.services.kibotu.net/"))
        assertNull(deepLink("https://trail.services.kibotu.net/data-privacy/"))
        assertNull(deepLink("https://trail.services.kibotu.net/status/"))
        assertNull(deepLink("https://trail.services.kibotu.net/status/000NX2YY/comments"))
        assertNull(deepLink("https://trail.services.kibotu.net/collections"))
    }

    @Test
    fun id_outside_the_backend_charset_stays_on_the_share_flow() {
        assertNull(deepLink("https://trail.services.kibotu.net/status/000NX-YY"))
        assertNull(deepLink("https://trail.services.kibotu.net/@a"))
        assertNull(deepLink("https://trail.services.kibotu.net/collection/Trail"))
    }

    @Test
    fun missing_or_unparseable_url_stays_on_the_share_flow() {
        assertNull(deepLink(null))
        assertNull(deepLink(""))
        assertNull(deepLink("not a url at all"))
    }
}
