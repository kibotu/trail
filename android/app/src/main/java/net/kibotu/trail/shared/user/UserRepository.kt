package net.kibotu.trail.shared.user

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import net.kibotu.trail.shared.entry.ViewRequest
import net.kibotu.trail.shared.entry.ViewResponse
import net.kibotu.trail.shared.profile.ProfileResponse

class UserRepository(private val client: HttpClient) {

    suspend fun getPublicProfile(nickname: String): Result<ProfileResponse> = runCatching {
        client.get("api/users/$nickname").body()
    }

    suspend fun muteUser(userId: Int): Result<MuteResponse> = runCatching {
        client.post("api/users/$userId/mute").body()
    }

    suspend fun unmuteUser(userId: Int): Result<MuteResponse> = runCatching {
        client.delete("api/users/$userId/mute").body()
    }

    suspend fun getMuteStatus(userId: Int): Result<MuteStatusResponse> = runCatching {
        client.get("api/users/$userId/mute-status").body()
    }

    suspend fun getFilters(): Result<FiltersResponse> = runCatching {
        client.get("api/filters").body()
    }

    suspend fun recordProfileView(nickname: String, fingerprint: String? = null): Result<ViewResponse> = runCatching {
        client.post("api/users/$nickname/views") {
            contentType(ContentType.Application.Json)
            setBody(ViewRequest(fingerprint))
        }.body()
    }
}
