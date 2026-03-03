package net.kibotu.trail.shared.image

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.net.Uri
import android.os.Build
import androidx.exifinterface.media.ExifInterface
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream

data class CompressedImage(
    val bytes: ByteArray,
    val mimeType: String,
    val width: Int,
    val height: Int
) {
    override fun equals(other: Any?): Boolean {
        if (this === other) return true
        if (other !is CompressedImage) return false
        return bytes.contentEquals(other.bytes) &&
            mimeType == other.mimeType &&
            width == other.width &&
            height == other.height
    }

    override fun hashCode(): Int {
        var result = bytes.contentHashCode()
        result = 31 * result + mimeType.hashCode()
        result = 31 * result + width
        result = 31 * result + height
        return result
    }
}

object ImageCompressor {

    const val MAX_DIMENSION = 2048
    const val LOSSY_QUALITY = 80

    suspend fun compress(
        context: Context,
        uri: Uri,
        maxDimension: Int = MAX_DIMENSION
    ): CompressedImage {
        val rawBytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw IllegalStateException("Cannot read image from $uri")

        return withContext(Dispatchers.IO) {
            compressBytes(rawBytes, maxDimension)
        }
    }

    private fun compressBytes(rawBytes: ByteArray, maxDimension: Int): CompressedImage {
        val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeByteArray(rawBytes, 0, rawBytes.size, options)

        val origW = options.outWidth
        val origH = options.outHeight
        require(origW > 0 && origH > 0) { "Invalid image dimensions: ${origW}x$origH" }

        var sampleSize = 1
        while (origW / (sampleSize * 2) >= maxDimension &&
            origH / (sampleSize * 2) >= maxDimension
        ) {
            sampleSize *= 2
        }

        val decodeOptions = BitmapFactory.Options().apply { inSampleSize = sampleSize }
        val sampled = BitmapFactory.decodeByteArray(rawBytes, 0, rawBytes.size, decodeOptions)
            ?: throw IllegalStateException("Cannot decode image")

        val rotationDegrees = try {
            ByteArrayInputStream(rawBytes).use { readExifRotation(ExifInterface(it)) }
        } catch (_: Exception) {
            0
        }

        val rotated = applyRotation(sampled, rotationDegrees)

        val scale = minOf(
            maxDimension.toFloat() / rotated.width,
            maxDimension.toFloat() / rotated.height,
            1f
        )

        val finalBitmap = if (scale < 1f) {
            val newW = (rotated.width * scale).toInt().coerceAtLeast(1)
            val newH = (rotated.height * scale).toInt().coerceAtLeast(1)
            Bitmap.createScaledBitmap(rotated, newW, newH, true).also {
                if (it !== rotated) rotated.recycle()
            }
        } else {
            rotated
        }

        if (finalBitmap !== sampled && sampled !== rotated) {
            sampled.recycle()
        }

        val hasAlpha = finalBitmap.hasAlpha()

        val (format, mimeType) = @Suppress("DEPRECATION") when {
            hasAlpha && Build.VERSION.SDK_INT >= Build.VERSION_CODES.R ->
                Bitmap.CompressFormat.WEBP_LOSSLESS to "image/webp"
            hasAlpha ->
                Bitmap.CompressFormat.PNG to "image/png"
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.R ->
                Bitmap.CompressFormat.WEBP_LOSSY to "image/webp"
            else ->
                Bitmap.CompressFormat.JPEG to "image/jpeg"
        }

        val quality = if (format == Bitmap.CompressFormat.PNG) 100 else LOSSY_QUALITY

        val output = ByteArrayOutputStream()
        finalBitmap.compress(format, quality, output)
        val compressedBytes = output.toByteArray()

        val result = CompressedImage(
            bytes = compressedBytes,
            mimeType = mimeType,
            width = finalBitmap.width,
            height = finalBitmap.height
        )

        finalBitmap.recycle()
        return result
    }

    private fun readExifRotation(exif: ExifInterface): Int {
        return when (exif.getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL)) {
            ExifInterface.ORIENTATION_ROTATE_90 -> 90
            ExifInterface.ORIENTATION_ROTATE_180 -> 180
            ExifInterface.ORIENTATION_ROTATE_270 -> 270
            else -> 0
        }
    }

    private fun applyRotation(bitmap: Bitmap, degrees: Int): Bitmap {
        if (degrees == 0) return bitmap
        val matrix = Matrix().apply { postRotate(degrees.toFloat()) }
        return Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, matrix, true)
    }
}
