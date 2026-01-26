package net.kibotu.trail.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class GoogleAuthRequest(
    @SerialName("google_token") val googleToken: String
)

@Serializable
data class AuthResponse(
    val jwt: String,
    val user: User
)

@Serializable
data class User(
    val id: Int,
    val email: String,
    val name: String,
    @SerialName("gravatar_url") val gravatarUrl: String,
    @SerialName("is_admin") val isAdmin: Boolean = false
)

@Serializable
data class CreateEntryRequest(
    val url: String,
    val message: String
)

@Serializable
data class CreateEntryResponse(
    val id: Int,
    @SerialName("created_at") val createdAt: String
)

@Serializable
data class Entry(
    val id: Int,
    @SerialName("user_id") val userId: Int,
    val url: String,
    val message: String,
    @SerialName("user_name") val userName: String,
    @SerialName("user_email") val userEmail: String,
    @SerialName("gravatar_url") val gravatarUrl: String,
    @SerialName("created_at") val createdAt: String,
    @SerialName("updated_at") val updatedAt: String
)

@Serializable
data class EntriesResponse(
    val entries: List<Entry>,
    val total: Int,
    val page: Int,
    val pages: Int,
    val limit: Int
)

@Serializable
data class ErrorResponse(
    val error: String
)
