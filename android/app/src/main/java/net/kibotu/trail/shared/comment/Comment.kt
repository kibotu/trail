package net.kibotu.trail.shared.comment

import androidx.compose.runtime.Stable
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import net.kibotu.trail.shared.entry.MediaItemData

@Stable
@Serializable
data class Comment(
    val id: Int,
    @SerialName("entry_id") val entryId: Int,
    @SerialName("user_id") val userId: Int,
    val text: String,
    @SerialName("user_name") val userName: String,
    @SerialName("user_nickname") val userNickname: String? = null,
    @SerialName("gravatar_hash") val gravatarHash: String? = null,
    @SerialName("avatar_url") val avatarUrl: String,
    @SerialName("clap_count") val clapCount: Int = 0,
    @SerialName("user_clap_count") val userClapCount: Int = 0,
    @SerialName("created_at") val createdAt: String,
    @SerialName("updated_at") val updatedAt: String? = null,
    val images: List<CommentImage>? = null
) {
    val displayName: String
        get() = userNickname ?: userName
}

@Serializable
data class CommentImage(
    val id: Int,
    val url: String,
    val width: Int? = null,
    val height: Int? = null,
    @SerialName("file_size") val fileSize: Int? = null,
    @SerialName("mime_type") val mimeType: String? = null
) {
    val isVideo: Boolean get() = mimeType?.startsWith("video/") == true
    val isGif: Boolean get() = mimeType == "image/gif"
    val isImage: Boolean get() = !isVideo
}

private data class CommentMediaItem(private val image: CommentImage) : MediaItemData {
    override val id: Int get() = image.id
    override val url: String get() = image.url
    override val width: Int? get() = image.width
    override val height: Int? get() = image.height
    override val isVideo: Boolean get() = image.isVideo
    override val isGif: Boolean get() = image.isGif
    override val isImage: Boolean get() = image.isImage
}

fun CommentImage.toMediaItemData(): MediaItemData = CommentMediaItem(this)

@JvmName("commentImagesToMediaItemDataList")
fun List<CommentImage>.toMediaItemDataList(): List<MediaItemData> = map { it.toMediaItemData() }

@Serializable
data class CommentsResponse(
    val comments: List<Comment>,
    @SerialName("has_more") val hasMore: Boolean,
    @SerialName("next_cursor") val nextCursor: String? = null,
    val limit: Int
)

@Serializable
data class CreateCommentRequest(
    val text: String,
    @SerialName("image_ids") val imageIds: List<Int>? = null
)

@Serializable
data class CreateCommentResponse(
    val id: Int,
    @SerialName("created_at") val createdAt: String
)

@Serializable
data class UpdateCommentRequest(
    val text: String,
    @SerialName("image_ids") val imageIds: List<Int>? = null
)

@Serializable
data class UpdateCommentResponse(
    val success: Boolean,
    @SerialName("updated_at") val updatedAt: String
)

@Serializable
data class CommentClapRequest(val count: Int)

@Serializable
data class CommentClapResponse(
    val success: Boolean? = null,
    @SerialName("total_claps") val totalClaps: Int? = null,
    @SerialName("user_claps") val userClaps: Int? = null,
    val total: Int? = null
)

@Serializable
data class RecordViewRequest(
    val fingerprint: String? = null
)
