package net.kibotu.trail.shared.profile

import android.content.Context
import android.content.ContextWrapper
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.io.File
import java.io.IOException

/**
 * Exercises the network-first / cache-fallback logic in [ProfileRepository] that keeps the
 * Profile tab rendering without a network connection.
 */
class ProfileRepositoryTest {

    private val json = Json { ignoreUnknownKeys = true }

    private val profile = ProfileResponse(
        id = 1,
        nickname = "tester",
        name = "Test User",
        bio = "a bio",
        photoUrl = null,
        gravatarHash = "hash",
        createdAt = "2024-01-01T00:00:00Z",
        stats = ProfileStats()
    )

    /**
     * DataStore is a process-wide singleton, so every [ProfileCache] in this JVM shares one backing
     * file (the delegate resolves the file from the first context's `filesDir`). One shared temp dir
     * for the whole class; the cache is cleared between tests for isolation.
     */
    private val tempDir: File = File(System.getProperty("java.io.tmpdir"), "profile_repo_${System.nanoTime()}").apply {
        deleteRecursively()
        mkdirs()
    }

    private val cache = ProfileCache(contextFor(tempDir))

    /** DataStore resolves its backing file from `context.applicationContext.filesDir`, so point it at a temp dir. */
    private fun contextFor(dir: File): Context = object : ContextWrapper(null) {
        override fun getFilesDir(): File = dir
        override fun getApplicationContext(): Context = this
    }

    /** HttpClient that always fails (simulates being offline). */
    private fun offlineClient() = testClient(MockEngine { _ -> throw IOException("offline") })

    /** HttpClient that returns [body] as JSON (simulates being online). */
    private fun onlineClient(body: String) = testClient(
        MockEngine { _ -> respond(body, headers = headersOf("Content-Type", "application/json")) }
    )

    private fun testClient(mockEngine: MockEngine) = HttpClient(mockEngine) {
        install(ContentNegotiation) { json(json) }
    }

    @Before
    fun clearCache() {
        runBlocking { cache.clear() }
    }

    @Test
    fun profile_cache_round_trip() {
        runBlocking {
            assertEquals(null, cache.load())
            cache.save(profile)
            assertEquals(profile, cache.load())
            cache.clear()
            assertEquals(null, cache.load())
        }
    }

    @Test
    fun get_profile_returns_cache_when_offline() {
        runBlocking { cache.save(profile) }
        val repository = ProfileRepository(offlineClient(), cache)

        val result = runBlocking { repository.getProfile() }
        assertTrue(result.isSuccess)
        assertEquals(profile, result.getOrThrow())
    }

    @Test
    fun get_profile_returns_failure_when_offline_and_no_cache() {
        val repository = ProfileRepository(offlineClient(), cache)

        val result = runBlocking { repository.getProfile() }
        assertTrue(result.isFailure)
    }

    @Test
    fun second_cache_instance_shares_datastore() {
        // Two [ProfileCache] instances must share one DataStore (the delegate is a companion singleton);
        // a per-instance DataStore would crash with "multiple DataStores active for the same file".
        val second = ProfileCache(contextFor(tempDir))
        runBlocking {
            cache.save(profile)
            assertEquals(profile, second.load())
            second.clear()
            assertEquals(null, cache.load())
        }
    }

    @Test
    fun get_profile_refreshes_cache_when_online() {
        runBlocking { cache.save(profile) }

        val fresh = profile.copy(nickname = "fresh")
        val body = json.encodeToString(ProfileResponse.serializer(), fresh)
        val repository = ProfileRepository(onlineClient(body), cache)

        val result = runBlocking { repository.getProfile() }
        assertTrue(result.isSuccess)
        assertEquals(fresh, result.getOrThrow())
        assertEquals(fresh, runBlocking { cache.load() })
    }
}
