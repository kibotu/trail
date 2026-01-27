package net.kibotu.trail.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Entry(
    val id: Int,
    val text: String,
    @SerialName("user_id") val userId: Int,
    @SerialName("user_name") val userName: String,
    @SerialName("gravatar_hash") val gravatarHash: String,
    @SerialName("avatar_url") val avatarUrl: String,
    @SerialName("created_at") val createdAt: String,
    @SerialName("updated_at") val updatedAt: String? = null
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
data class CreateEntryRequest(
    val text: String
)

@Serializable
data class CreateEntryResponse(
    val id: Int,
    @SerialName("created_at") val createdAt: String
)

@Serializable
data class UpdateEntryRequest(
    val text: String
)

@Serializable
data class UpdateEntryResponse(
    val success: Boolean,
    @SerialName("updated_at") val updatedAt: String
)
