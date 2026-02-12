package net.kibotu.trail.data.model

/**
 * A common interface for media items to allow unified handling
 * of both EntryImage and CommentImage in the UI layer.
 */
interface MediaItemData {
    val id: Int
    val url: String
    val width: Int?
    val height: Int?
    val isVideo: Boolean
    val isGif: Boolean
    val isImage: Boolean
}

/**
 * Wrapper to make EntryImage conform to MediaItemData
 */
private data class EntryMediaItem(private val image: EntryImage) : MediaItemData {
    override val id: Int get() = image.id
    override val url: String get() = image.url
    override val width: Int? get() = image.width
    override val height: Int? get() = image.height
    override val isVideo: Boolean get() = image.isVideo
    override val isGif: Boolean get() = image.isGif
    override val isImage: Boolean get() = image.isImage
}

/**
 * Wrapper to make CommentImage conform to MediaItemData
 */
private data class CommentMediaItem(private val image: CommentImage) : MediaItemData {
    override val id: Int get() = image.id
    override val url: String get() = image.url
    override val width: Int? get() = image.width
    override val height: Int? get() = image.height
    override val isVideo: Boolean get() = image.isVideo
    override val isGif: Boolean get() = image.isGif
    override val isImage: Boolean get() = image.isImage
}

/**
 * Extension function to convert EntryImage to MediaItemData
 */
fun EntryImage.toMediaItemData(): MediaItemData = EntryMediaItem(this)

/**
 * Extension function to convert CommentImage to MediaItemData
 */
fun CommentImage.toMediaItemData(): MediaItemData = CommentMediaItem(this)

/**
 * Extension function to convert a list of EntryImage to MediaItemData list
 */
fun List<EntryImage>.toMediaItemDataList(): List<MediaItemData> = map { it.toMediaItemData() }

/**
 * Extension function to convert a list of CommentImage to MediaItemData list
 */
@JvmName("commentImagesToMediaItemDataList")
fun List<CommentImage>.toMediaItemDataList(): List<MediaItemData> = map { it.toMediaItemData() }
