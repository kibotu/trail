package net.kibotu.trail.shared.auth

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class GoogleAuthRequest(
    @SerialName("id_token") val idToken: String
)

@Serializable
data class AuthResponse(
    val token: String,
    val user: AuthUser
)

@Serializable
data class AuthUser(
    val id: Int,
    val email: String,
    val name: String,
    val nickname: String? = null,
    @SerialName("is_admin") val isAdmin: Boolean,
    @SerialName("gravatar_hash") val gravatarHash: String,
    @SerialName("gravatar_url") val gravatarUrl: String
)
