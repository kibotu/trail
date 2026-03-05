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

class ProfileRepository(private val client: HttpClient) {

    suspend fun getProfile(): Result<ProfileResponse> = runCatching {
        client.get("api/profile").body()
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
