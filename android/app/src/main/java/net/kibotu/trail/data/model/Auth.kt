package net.kibotu.trail.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class GoogleAuthRequest(
    @SerialName("id_token") val idToken: String
)

@Serializable
data class AuthResponse(
    val token: String,
    val user: User
)

@Serializable
data class User(
    val id: Int,
    val email: String,
    val name: String,
    @SerialName("is_admin") val isAdmin: Boolean,
    @SerialName("gravatar_hash") val gravatarHash: String,
    @SerialName("gravatar_url") val gravatarUrl: String
)
