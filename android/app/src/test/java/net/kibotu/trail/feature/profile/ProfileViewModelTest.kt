package net.kibotu.trail.feature.profile

import android.content.Context
import android.content.ContextWrapper
import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.Json
import net.kibotu.trail.shared.profile.ProfileCache
import net.kibotu.trail.shared.profile.ProfileResponse
import net.kibotu.trail.shared.profile.ProfileRepository
import net.kibotu.trail.shared.profile.ProfileStats
import net.kibotu.trail.shared.user.UserRepository
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.io.File
import java.io.IOException

/**
 * Verifies the stale-while-revalidate behaviour of [ProfileViewModel]: a cached profile is shown
 * immediately, then refreshed in the background.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ProfileViewModelTest {

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

    /** DataStore is a process-wide singleton, so one shared temp dir for the whole class; cleared between tests. */
    private val tempDir: File = File(System.getProperty("java.io.tmpdir"), "profile_vm_${System.nanoTime()}").apply {
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

    private fun testClient(mockEngine: MockEngine) = HttpClient(mockEngine) {
        install(ContentNegotiation) { json(json) }
    }

    @Before
    fun setUpDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        runBlocking { cache.clear() }
    }

    @After
    fun tearDownDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun shows_cached_profile_immediately_when_offline() {
        runBlocking { cache.save(profile) }
        val repository = ProfileRepository(offlineClient(), cache)
        val viewModel = ProfileViewModel(repository, UserRepository(offlineClient()), cache)

        // Poll until the SWR has shown the cached profile (the ViewModel loads in a coroutine).
        val deadline = System.currentTimeMillis() + 5000
        var state = viewModel.state.value
        while (state.profile == null && System.currentTimeMillis() < deadline) {
            Thread.sleep(50)
            state = viewModel.state.value
        }
        assertEquals(profile, state.profile)
        assertTrue(!state.isLoading)
    }

    @Test
    fun clears_loading_when_online() {
        val body = json.encodeToString(ProfileResponse.serializer(), profile)
        val repository = ProfileRepository(
            testClient(MockEngine { _ -> respond(body, headers = headersOf("Content-Type", "application/json")) }),
            cache
        )
        val viewModel = ProfileViewModel(repository, UserRepository(offlineClient()), cache)

        val deadline = System.currentTimeMillis() + 5000
        var state = viewModel.state.value
        while (state.profile == null && System.currentTimeMillis() < deadline) {
            Thread.sleep(50)
            state = viewModel.state.value
        }
        assertEquals(profile, state.profile)
        assertTrue(!state.isLoading)
    }
}
