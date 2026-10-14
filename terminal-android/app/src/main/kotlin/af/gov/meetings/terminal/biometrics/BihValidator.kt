package af.gov.aop.amms.terminal.biometrics

/** Structural validation for the F1 8-bit little-endian BITMAPINFOHEADER capture. */
internal object BihValidator {
    private const val headerSize = 40

    fun validate(data: ByteArray, expectedWidth: Int, expectedHeight: Int, detected: Boolean): BihValidation {
        return try {
            require(detected) { "Finger was not detected" }
            require(data.size >= headerSize) { "BIH is shorter than BITMAPINFOHEADER" }
            val declaredHeaderSize = intLE(data, 0)
            val width = intLE(data, 4)
            val signedHeight = intLE(data, 8)
            val height = kotlin.math.abs(signedHeight.toLong())
            val planes = shortLE(data, 12)
            val bitsPerPixel = shortLE(data, 14)
            val compression = intLE(data, 16)
            val colorsUsed = intLE(data, 32)
            require(declaredHeaderSize >= headerSize) { "Invalid BIH header size" }
            require(width == expectedWidth && height == expectedHeight.toLong()) { "Unexpected BIH dimensions" }
            require(planes == 1 && bitsPerPixel == 8 && compression == 0) { "Unsupported BIH format" }
            val paletteEntries = if (colorsUsed > 0) colorsUsed else 256
            require(paletteEntries in 1..256) { "Invalid BIH palette" }
            val rowStride = ((width.toLong() * bitsPerPixel + 31) / 32) * 4
            val expectedSize = declaredHeaderSize.toLong() + paletteEntries * 4L + rowStride * height
            require(expectedSize <= data.size) { "BIH pixel data is truncated" }
            BihValidation(true, "BIH validation passed: ${width}x$height")
        } catch (error: IllegalArgumentException) {
            BihValidation(false, error.message ?: "Invalid BIH")
        }
    }

    private fun shortLE(data: ByteArray, offset: Int): Int =
        (data[offset].toInt() and 0xff) or ((data[offset + 1].toInt() and 0xff) shl 8)

    private fun intLE(data: ByteArray, offset: Int): Int =
        (data[offset].toInt() and 0xff) or
            ((data[offset + 1].toInt() and 0xff) shl 8) or
            ((data[offset + 2].toInt() and 0xff) shl 16) or
            ((data[offset + 3].toInt() and 0xff) shl 24)
}
