package net.kibotu.trail.shared.profile

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.get
import io.ktor.client.request.put
import io.ktor.client.request.setBody
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
}
