package net.kibotu.trail.shared.image

import android.content.Context
import android.net.Uri
import android.util.Base64
import android.util.Log

class ImageUploadManager(private val repository: ImageUploadRepository) {

    companion object {
        private const val TAG = "ImageUploadManager"
    }

    suspend fun uploadImage(
        context: Context,
        uri: Uri,
        imageType: String = "post",
        onProgress: (Float) -> Unit = {}
    ): Result<Int> = runCatching {
        val contentResolver = context.contentResolver
        val mimeType = contentResolver.getType(uri)
        Log.d(TAG, "uploadImage: uri=$uri mimeType=$mimeType")

        val (bytes, filename) = if (shouldCompress(mimeType)) {
            Log.d(TAG, "Compressing image...")
            val compressed = ImageCompressor.compress(context, uri)
            Log.d(TAG, "Compressed: ${compressed.width}x${compressed.height} ${compressed.mimeType} ${compressed.bytes.size} bytes")
            compressed.bytes to buildFilename(uri, compressed.mimeType)
        } else {
            Log.d(TAG, "Skipping compression for mimeType=$mimeType")
            val stream = contentResolver.openInputStream(uri)
                ?: throw IllegalStateException("Cannot open file")
            val raw = stream.use { it.readBytes() }
            raw to (uri.lastPathSegment ?: "file")
        }

        val chunkSize = 512 * 1024
        val totalChunks = (bytes.size + chunkSize - 1) / chunkSize

        val initResponse = repository.initUpload(
            imageType = imageType,
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
        Log.d(TAG, "Upload complete: imageId=${completeResponse.imageId}")
        completeResponse.imageId
    }.also { result ->
        result.onFailure { e -> Log.e(TAG, "uploadImage failed", e) }
    }

    private fun shouldCompress(mimeType: String?): Boolean {
        if (mimeType == null) return true
        if (mimeType == "image/gif") return false
        if (mimeType.startsWith("video/")) return false
        return mimeType.startsWith("image/")
    }

    private fun buildFilename(uri: Uri, mimeType: String): String {
        val baseName = uri.lastPathSegment
            ?.substringBeforeLast('.')
            ?: "image"
        val extension = when (mimeType) {
            "image/webp" -> "webp"
            "image/png" -> "png"
            else -> "jpg"
        }
        return "$baseName.$extension"
    }
}
