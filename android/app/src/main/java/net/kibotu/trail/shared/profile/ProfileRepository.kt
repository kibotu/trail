package net.kibotu.trail.shared.profile

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.get
import io.ktor.client.request.post
import io.ktor.client.request.put
import io.ktor.client.request.setBody
import io.ktor.client.statement.readRawBytes
import io.ktor.http.ContentType
import io.ktor.http.contentType

class ProfileRepository(private val client: HttpClient, private val profileCache: ProfileCache? = null) {

    /**
     * Network-first. When offline, returns the last cached profile (if any) so the screen can render.
     */
    suspend fun getProfile(): Result<ProfileResponse> {
        val cache = profileCache ?: return runCatching { client.get("api/profile").body() }
        val network: Result<ProfileResponse> = runCatching { client.get("api/profile").body() }
        return when {
            network.isSuccess -> {
                val profile = network.getOrThrow()
                cache.save(profile)
                Result.success(profile)
            }
            else -> {
                val error = network.exceptionOrNull() ?: IllegalStateException("Failed to load profile")
                cache.load()?.let { Result.success(it) } ?: Result.failure(error)
            }
        }
    }

    suspend fun updateProfile(request: UpdateProfileRequest): Result<UpdateProfileResponse> = runCatching {
        client.put("api/profile") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.body()
    }

    suspend fun exportData(): Result<ByteArray> = runCatching {
        client.get("api/profile/export").readRawBytes()
    }

    suspend fun requestDeletion(): Result<DeletionResponse> = runCatching {
        client.post("api/profile/delete").body()
    }

    suspend fun revertDeletion(): Result<RevertDeletionResponse> = runCatching {
        client.post("api/profile/revert-deletion").body()
    }

    suspend fun sendFeedback(text: String): Result<FeedbackResponse> = runCatching {
        client.post("api/feedback") {
            contentType(ContentType.Application.Json)
            setBody(mapOf("text" to text))
        }.body()
    }
}
