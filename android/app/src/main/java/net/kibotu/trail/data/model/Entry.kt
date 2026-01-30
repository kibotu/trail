package net.kibotu.trail.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Entry(
    val id: Int,
    val text: String,
    @SerialName("user_id") val userId: Int,
    @SerialName("user_name") val userName: String,
    @SerialName("user_nickname") val userNickname: String? = null,
    @SerialName("gravatar_hash") val gravatarHash: String,
    @SerialName("avatar_url") val avatarUrl: String,
    @SerialName("created_at") val createdAt: String,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("comment_count") val commentCount: Int = 0,
    // URL Preview fields
    @SerialName("preview_url") val previewUrl: String? = null,
    @SerialName("preview_title") val previewTitle: String? = null,
    @SerialName("preview_description") val previewDescription: String? = null,
    @SerialName("preview_image") val previewImage: String? = null,
    @SerialName("preview_site_name") val previewSiteName: String? = null
) {
    // Helper property to get display name (nickname or fallback to userName)
    val displayName: String
        get() = userNickname ?: userName
}

@Serializable
data class EntriesResponse(
    val entries: List<Entry>,
    @SerialName("has_more") val hasMore: Boolean,
    @SerialName("next_cursor") val nextCursor: String? = null,
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
