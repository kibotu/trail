package net.kibotu.trail.shared.image

import android.content.Context
import android.net.Uri
import android.util.Base64

class ImageUploadManager(private val repository: ImageUploadRepository) {

    suspend fun uploadImage(
        context: Context,
        uri: Uri,
        onProgress: (Float) -> Unit = {}
    ): Result<Int> = runCatching {
        val contentResolver = context.contentResolver
        val mimeType = contentResolver.getType(uri) ?: "image/jpeg"
        val filename = uri.lastPathSegment ?: "image.jpg"
        val inputStream = contentResolver.openInputStream(uri)
            ?: throw IllegalStateException("Cannot open file")
        val bytes = inputStream.readBytes()
        inputStream.close()

        val chunkSize = 512 * 1024
        val totalChunks = (bytes.size + chunkSize - 1) / chunkSize

        val initResponse = repository.initUpload(
            imageType = mimeType,
            filename = filename,
            fileSize = bytes.size.toLong(),
            totalChunks = totalChunks
        ).getOrThrow()

        for (i in 0 until totalChunks) {
            val start = i * chunkSize
            val end = minOf(start + chunkSize, bytes.size)
            val chunk = bytes.copyOfRange(start, end)
            val base64 = Base64.encodeToString(chunk, Base64.NO_WRAP)

            repository.uploadChunk(initResponse.uploadId, i, base64).getOrThrow()
            onProgress((i + 1).toFloat() / totalChunks)
        }

        val completeResponse = repository.completeUpload(initResponse.uploadId).getOrThrow()
        completeResponse.imageId
    }
}
