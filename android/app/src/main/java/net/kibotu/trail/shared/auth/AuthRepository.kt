package net.kibotu.trail.shared.auth

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.storage.TokenManager

class AuthRepository(
    private val client: HttpClient,
    private val tokenManager: TokenManager
) {
    suspend fun googleSignIn(idToken: String): Result<AuthResponse> = runCatching {
        val response: AuthResponse = client.post("api/auth/google") {
            contentType(ContentType.Application.Json)
            setBody(GoogleAuthRequest(idToken))
        }.body()

        storeAuth(response)
        response
    }

    /**
     * Re-issues the stored JWT (sent via the Authorization header).
     * Returns whether the user is still logged in:
     * - server rejects the token (401) → logged out locally
     * - network / server error → token kept, retried next start or resume
     */
    suspend fun refreshAuthTokenIfNeeded(): Boolean {
        val token = getAuthToken() ?: return false
        val response = runCatching { client.post("api/auth/refresh") }.getOrNull() ?: return true
        val refreshed = runCatching {
            val body: AuthResponse = response.body()
            storeAuth(body)
        }.isSuccess
        return when {
            refreshed -> true
            response.status.value == 401 -> { logout(); false }
            else -> true
        }
    }

    private suspend fun storeAuth(response: AuthResponse) {
        tokenManager.saveAuthToken(
            token = response.token,
            email = response.user.email,
            name = response.user.name,
            userId = response.user.id,
            nickname = response.user.nickname,
            photoUrl = response.user.gravatarUrl
        )
        ApiClient.setAuthToken(response.token)
    }

    suspend fun getAuthToken(): String? = tokenManager.getAuthToken()

    suspend fun isLoggedIn(): Boolean = tokenManager.isLoggedIn()

    suspend fun logout() {
        tokenManager.clearAuthToken()
        ApiClient.setAuthToken(null)
    }

    suspend fun saveNickname(nickname: String) {
        tokenManager.saveNickname(nickname)
    }

    val userName = tokenManager.userName
    val userId = tokenManager.userId
    val userNickname = tokenManager.userNickname
    val userPhotoUrl = tokenManager.userPhotoUrl
}
