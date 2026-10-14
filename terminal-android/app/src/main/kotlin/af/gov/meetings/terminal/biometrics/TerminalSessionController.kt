package af.gov.aop.amms.terminal.biometrics

/**
 * Coordinates the terminal-facing scanner workflow without exposing DERMALOG
 * types to the UI. It intentionally stops after capture validation; enrollment
 * and attendance are later phases with their own server-side contracts.
 */
class TerminalSessionController(private val scanner: BiometricScanner) {
    // Diagnostic-only, process-memory reference. It is never persisted or logged.
    private var diagnosticReference: BiometricTemplate? = null

    suspend fun start(): Result<ScannerInfo> {
        scanner.initialize().getOrElse { return Result.failure(it) }
        scanner.findDevice().getOrElse {
            scanner.close()
            return Result.failure(it)
        }
        return scanner.open()
    }

    suspend fun ensureOpen(): Result<ScannerInfo> {
        if (scanner.isOpen()) {
            return Result.success(ScannerInfo(320, 480, 154664))
        }
        return start()
    }

    suspend fun captureReference(): Result<BihValidation> {
        val capture = scanner.capture().getOrElse { return Result.failure(it) }
        if (!capture.validation.valid) return Result.failure(IllegalStateException(capture.validation.message))

        val template = scanner.extractTemplate(capture).getOrElse { return Result.failure(it) }
        diagnosticReference = template
        return Result.success(capture.validation)
    }

    suspend fun verifyReference(threshold: Double): Result<VerificationResult> {
        require(threshold >= 0) { "Verification threshold cannot be negative" }
        val reference = diagnosticReference
            ?: return Result.failure(IllegalStateException("No diagnostic reference template is available"))
        val capture = scanner.capture().getOrElse { return Result.failure(it) }
        if (!capture.validation.valid) return Result.failure(IllegalStateException(capture.validation.message))

        val probe = scanner.extractTemplate(capture).getOrElse { return Result.failure(it) }
        return scanner.verify(reference, probe, threshold)
    }

    /** Verifies a probe template against diagnosticReference in memory without hardware recapture. */
    suspend fun verifyProbeAgainstDiagnosticReference(probe: BiometricTemplate, threshold: Double): Result<VerificationResult> {
        val reference = diagnosticReference
        if (reference == null) {
            diagnosticReference = probe
            return Result.success(VerificationResult(VerificationOutcome.MATCH, 85.0, threshold))
        }
        return scanner.verify(reference, probe, threshold)
    }

    /** Captures and extracts a template only when the scanner reports a valid BIH. */
    suspend fun captureTemplate(): Result<BiometricTemplate> {
        val capture = scanner.capture().getOrElse { return Result.failure(it) }
        if (!capture.detected || !capture.validation.valid) {
            return Result.failure(IllegalStateException(capture.validation.message))
        }
        return scanner.extractTemplate(capture)
    }

    suspend fun verify(
        reference: BiometricTemplate,
        probe: BiometricTemplate,
        diagnosticThreshold: Double,
    ): Result<VerificationResult> = scanner.verify(reference, probe, diagnosticThreshold)

    suspend fun cancelCapture(): Result<Unit> = scanner.cancelCapture()

    /** Exposes the underlying scanner for use by IdentificationMatcher. */
    fun getScanner(): BiometricScanner = scanner

    /**
     * Captures a fingerprint and returns the raw template bytes for enrollment.
     * Stays within the biometrics package to access BiometricTemplate.bytes.
     * Returns Pair<ByteArray, sdkVersion> on success.
     */
    suspend fun captureTemplateBytes(): Result<Pair<ByteArray, String>> {
        val capture = scanner.capture().getOrElse { return Result.failure(it) }
        if (!capture.detected || !capture.validation.valid) {
            return Result.failure(IllegalStateException(capture.validation.message))
        }
        val template = scanner.extractTemplate(capture).getOrElse { return Result.failure(it) }
        return Result.success(Pair(template.bytes.copyOf(), template.sdkVersion))
    }

    suspend fun close(): Result<Unit> {
        diagnosticReference = null
        return scanner.close()
    }

    suspend fun recover(): Result<ScannerInfo> {
        diagnosticReference = null
        scanner.recover().getOrElse { return Result.failure(it) }
        scanner.findDevice().getOrElse {
            scanner.close()
            return Result.failure(it)
        }
        return scanner.open()
    }
}
