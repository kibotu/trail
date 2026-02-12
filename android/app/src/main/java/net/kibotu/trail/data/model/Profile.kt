package net.kibotu.trail.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ProfileResponse(
    val id: Int,
    val nickname: String?,
    val name: String,
    val email: String? = null,
    val bio: String?,
    @SerialName("photo_url") val photoUrl: String?,
    @SerialName("gravatar_hash") val gravatarHash: String?,
    @SerialName("profile_image_url") val profileImageUrl: String? = null,
    @SerialName("header_image_url") val headerImageUrl: String? = null,
    @SerialName("is_admin") val isAdmin: Boolean? = null,
    @SerialName("created_at") val createdAt: String,
    @SerialName("updated_at") val updatedAt: String? = null,
    val stats: ProfileStats
) {
    // Helper to get the best avatar URL
    val avatarUrl: String
        get() = profileImageUrl ?: photoUrl ?: run {
            gravatarHash?.let { "https://www.gravatar.com/avatar/$it?s=200&d=identicon" }
        } ?: ""
}

@Serializable
data class ProfileStats(
    @SerialName("entry_count") val entryCount: Int = 0,
    @SerialName("link_count") val linkCount: Int = 0,
    @SerialName("comment_count") val commentCount: Int = 0,
    @SerialName("total_entry_views") val totalEntryViews: Int = 0,
    @SerialName("total_comment_views") val totalCommentViews: Int = 0,
    @SerialName("total_profile_views") val totalProfileViews: Int = 0,
    @SerialName("total_entry_claps") val totalEntryClaps: Int = 0,
    @SerialName("total_comment_claps") val totalCommentClaps: Int = 0,
    @SerialName("last_entry_at") val lastEntryAt: String? = null,
    @SerialName("top_entries_by_claps") val topEntriesByClaps: List<Entry> = emptyList(),
    @SerialName("top_entries_by_views") val topEntriesByViews: List<Entry> = emptyList()
)

@Serializable
data class UpdateProfileRequest(
    val nickname: String,
    val bio: String? = null
)

@Serializable
data class UpdateProfileResponse(
    val success: Boolean
)
