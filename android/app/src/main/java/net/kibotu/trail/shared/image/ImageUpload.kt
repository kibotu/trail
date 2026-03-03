package net.kibotu.trail.shared.image

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class InitUploadRequest(
    @SerialName("image_type") val imageType: String,
    val filename: String,
    @SerialName("file_size") val fileSize: Long,
    @SerialName("total_chunks") val totalChunks: Int
)

@Serializable
data class InitUploadResponse(
    @SerialName("upload_id") val uploadId: String,
    @SerialName("chunk_size") val chunkSize: Int
)

@Serializable
data class ChunkUploadRequest(
    @SerialName("upload_id") val uploadId: String,
    @SerialName("chunk_index") val chunkIndex: Int,
    @SerialName("chunk_data") val chunkData: String
)

@Serializable
data class ChunkUploadResponse(
    @SerialName("uploaded_chunks") val uploadedChunks: Int,
    @SerialName("total_chunks") val totalChunks: Int,
    val progress: Float
)

@Serializable
data class CompleteUploadRequest(
    @SerialName("upload_id") val uploadId: String
)

@Serializable
data class CompleteUploadResponse(
    @SerialName("image_id") val imageId: Int,
    val url: String,
    val width: Int? = null,
    val height: Int? = null,
    @SerialName("file_size") val fileSize: Long? = null
)
