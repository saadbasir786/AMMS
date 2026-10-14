package af.gov.aop.amms.terminal.biometrics

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class BihValidatorTest {
    @Test
    fun `accepts a valid grayscale bih`() {
        val data = ByteArray(40 + 256 * 4 + 320 * 480)
        writeInt(data, 0, 40)
        writeInt(data, 4, 320)
        writeInt(data, 8, -480)
        writeShort(data, 12, 1)
        writeShort(data, 14, 8)

        assertTrue(BihValidator.validate(data, 320, 480, detected = true).valid)
    }

    @Test
    fun `rejects a capture where no finger was detected`() {
        val data = ByteArray(40 + 256 * 4 + 320 * 480)

        assertFalse(BihValidator.validate(data, 320, 480, detected = false).valid)
    }

    private fun writeShort(data: ByteArray, offset: Int, value: Int) {
        data[offset] = value.toByte()
        data[offset + 1] = (value ushr 8).toByte()
    }

    private fun writeInt(data: ByteArray, offset: Int, value: Int) {
        repeat(4) { index -> data[offset + index] = (value ushr (index * 8)).toByte() }
    }
}
