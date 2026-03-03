package net.kibotu.trail.shared.image

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.plugins.timeout
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType

class ImageUploadRepository(private val client: HttpClient) {

    suspend fun initUpload(
        imageType: String,
        filename: String,
        fileSize: Long,
        totalChunks: Int
    ): Result<InitUploadResponse> = runCatching {
        client.post("api/images/upload/init") {
            contentType(ContentType.Application.Json)
            setBody(InitUploadRequest(imageType, filename, fileSize, totalChunks))
        }.body()
    }

    suspend fun uploadChunk(
        uploadId: String,
        chunkIndex: Int,
        chunkData: String
    ): Result<ChunkUploadResponse> = runCatching {
        client.post("api/images/upload/chunk") {
            contentType(ContentType.Application.Json)
            timeout {
                requestTimeoutMillis = 60_000
                socketTimeoutMillis = 60_000
            }
            setBody(ChunkUploadRequest(uploadId, chunkIndex, chunkData))
        }.body()
    }

    suspend fun completeUpload(uploadId: String): Result<CompleteUploadResponse> = runCatching {
        client.post("api/images/upload/complete") {
            contentType(ContentType.Application.Json)
            timeout {
                requestTimeoutMillis = 60_000
                socketTimeoutMillis = 60_000
            }
            setBody(CompleteUploadRequest(uploadId))
        }.body()
    }
}
