package af.gov.aop.amms.terminal.biometrics

/**
 * Application-facing biometric boundary. No caller outside this package needs
 * to know about DERMALOG SDK classes, native buffers, or reflection.
 */
interface BiometricScanner {
    suspend fun initialize(): Result<BiometricDeviceInfo>
    suspend fun findDevice(): Result<BiometricDeviceInfo>
    suspend fun open(): Result<ScannerInfo>
    suspend fun capture(): Result<FingerprintCapture>
    suspend fun cancelCapture(): Result<Unit>
    suspend fun extractTemplate(capture: FingerprintCapture): Result<BiometricTemplate>
    suspend fun verify(
        reference: BiometricTemplate,
        probe: BiometricTemplate,
        threshold: Double,
    ): Result<VerificationResult>
    fun isOpen(): Boolean
    suspend fun close(): Result<Unit>
    suspend fun recover(): Result<BiometricDeviceInfo>
}

data class BiometricDeviceInfo(
    val id: Int? = null,
    val serial: String? = null,
    val sdkVersion: String,
)

data class ScannerInfo(
    val width: Int,
    val height: Int,
    val bihBufferSize: Int,
    val sdkVersion: String? = null,
)

data class BihValidation(
    val valid: Boolean,
    val message: String,
)

/** Raw BIH remains in terminal process memory and must never be logged or persisted by default. */
class FingerprintCapture internal constructor(
    internal val bih: ByteArray,
    val detected: Boolean,
    val validation: BihValidation,
)

/** Template bytes are restricted terminal-only data. */
class BiometricTemplate internal constructor(
    internal val bytes: ByteArray,
    val format: Int,
    val sdkVersion: String,
) {
    /** Remove an ephemeral diagnostic template once it is no longer needed. */
    internal fun wipe() = bytes.fill(0)
}

enum class VerificationOutcome { MATCH, NO_MATCH, VERIFY_ERROR }

data class VerificationResult(
    val outcome: VerificationOutcome,
    val score: Double? = null,
    val threshold: Double? = null,
    val errorCode: String? = null,
)
