package net.kibotu.trail.shared.entry

import androidx.compose.runtime.Stable
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Stable
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
    @SerialName("hash_id") val hashId: String? = null,
    @SerialName("comment_count") val commentCount: Int = 0,
    @SerialName("clap_count") val clapCount: Int = 0,
    @SerialName("user_clap_count") val userClapCount: Int = 0,
    @SerialName("view_count") val viewCount: Int = 0,
    @SerialName("preview_url") val previewUrl: String? = null,
    @SerialName("preview_title") val previewTitle: String? = null,
    @SerialName("preview_description") val previewDescription: String? = null,
    @SerialName("preview_image") val previewImage: String? = null,
    @SerialName("preview_site_name") val previewSiteName: String? = null,
    val tags: List<Tag> = emptyList(),
    val images: List<EntryImage> = emptyList()
) {
    val displayName: String
        get() = userNickname ?: userName
}

@Serializable
data class Tag(
    val id: Int,
    val name: String,
    val slug: String
)

@Serializable
data class EntryImage(
    val id: Int,
    val url: String,
    val width: Int? = null,
    val height: Int? = null,
    @SerialName("mime_type") val mimeType: String? = null
) {
    val isVideo: Boolean get() = mimeType?.startsWith("video/") == true
    val isGif: Boolean get() = mimeType == "image/gif"
    val isImage: Boolean get() = !isVideo
}

@Serializable
data class EntriesResponse(
    val entries: List<Entry>,
    @SerialName("has_more") val hasMore: Boolean,
    @SerialName("next_cursor") val nextCursor: String? = null,
    val limit: Int
)

@Serializable
data class CreateEntryRequest(val text: String)

@Serializable
data class CreateEntryResponse(
    val id: Int,
    @SerialName("created_at") val createdAt: String
)

@Serializable
data class UpdateEntryRequest(val text: String)

@Serializable
data class UpdateEntryResponse(
    val success: Boolean,
    @SerialName("updated_at") val updatedAt: String
)

@Serializable
data class ClapRequest(val count: Int)

@Serializable
data class ClapResponse(
    val success: Boolean? = null,
    @SerialName("total_claps") val totalClaps: Int? = null,
    @SerialName("user_claps") val userClaps: Int? = null
)

@Serializable
data class ViewRequest(val fingerprint: String? = null)

@Serializable
data class ViewResponse(
    val recorded: Boolean? = null,
    @SerialName("view_count") val viewCount: Int? = null
)

@Serializable
data class ReportRequest(val reason: String? = null)

@Serializable
data class ReportResponse(
    val success: Boolean? = null,
    @SerialName("report_count") val reportCount: Int? = null,
    val message: String? = null
)

interface MediaItemData {
    val id: Int
    val url: String
    val width: Int?
    val height: Int?
    val isVideo: Boolean
    val isGif: Boolean
    val isImage: Boolean
}

private data class EntryMediaItem(private val image: EntryImage) : MediaItemData {
    override val id: Int get() = image.id
    override val url: String get() = image.url
    override val width: Int? get() = image.width
    override val height: Int? get() = image.height
    override val isVideo: Boolean get() = image.isVideo
    override val isGif: Boolean get() = image.isGif
    override val isImage: Boolean get() = image.isImage
}

fun EntryImage.toMediaItemData(): MediaItemData = EntryMediaItem(this)
fun List<EntryImage>.toMediaItemDataList(): List<MediaItemData> = map { it.toMediaItemData() }
