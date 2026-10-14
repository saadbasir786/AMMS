package af.gov.aop.amms.terminal.biometrics

import af.gov.aop.amms.terminal.net.IdentificationSessionStart
import af.gov.aop.amms.terminal.net.IdentificationTemplate

/**
 * Result of 1:N identification matching.
 */
data class IdentificationMatchResult(
    /** Whether an employee was identified */
    val identified: Boolean,
    /** The matched candidate token (null if not identified) */
    val candidateToken: String?,
    /** The best verification score (null if not identified) */
    val bestScore: Double?,
    /** The margin between best and second-best scores (null if not identified) */
    val margin: Double?,
    /** Reason for rejection (null if identified) */
    val reason: String?,
    /** All scores per candidate for debugging */
    val candidateScores: Map<String, Double>
)

/**
 * 1:N Biometric Identification Matcher.
 *
 * Performs identification by comparing a probe template against multiple
 * candidate templates. Groups scores by candidate (employee) and finds
 * the best match.
 *
 * This matcher runs on the terminal (SVT3000) using the DERMALOG SDK.
 * The server controls the candidate set via IdentificationSession.
 */
class IdentificationMatcher(
    private val scanner: BiometricScanner,
    private val identificationThreshold: Double = 30.0,
    private val minimumMargin: Double = 10.0
) {

    /**
     * Perform 1:N identification matching.
     *
     * @param probe The captured probe template (BiometricTemplate from extractTemplate)
     * @param session The identification session with candidates and templates
     * @return IdentificationMatchResult with the best match or rejection reason
     */
    suspend fun identify(
        probe: BiometricTemplate,
        session: IdentificationSessionStart
    ): IdentificationMatchResult {
        if (session.templates.isEmpty()) {
            return IdentificationMatchResult(
                identified = false,
                candidateToken = null,
                bestScore = null,
                margin = null,
                reason = "NO_TEMPLATES",
                candidateScores = emptyMap()
            )
        }

        // Group templates by candidate_token
        val templatesByCandidate = session.templates.groupBy { it.candidateToken }

        // Compare probe against each template
        val scoresByCandidate = mutableMapOf<String, MutableList<Double>>()

        for ((candidateToken, templates) in templatesByCandidate) {
            val scores = mutableListOf<Double>()

            for (template in templates) {
                try {
                    // Create a BiometricTemplate from the candidate template bytes
                    val candidateTemplate = BiometricTemplate(
                        bytes = template.templateBytes,
                        format = probe.format, // Use same format as probe
                        sdkVersion = probe.sdkVersion
                    )

                    val result = scanner.verify(
                        reference = candidateTemplate,
                        probe = probe,
                        threshold = identificationThreshold
                    )

                    if (result.isSuccess) {
                        val verificationResult = result.getOrNull()
                        if (verificationResult != null &&
                            verificationResult.outcome != VerificationOutcome.VERIFY_ERROR
                        ) {
                            verificationResult.score?.let { scores.add(it) }
                        }
                    }
                } catch (e: Exception) {
                    // Skip failed comparisons
                    // Don't log the template bytes
                }
            }

            if (scores.isNotEmpty()) {
                scoresByCandidate[candidateToken] = scores
            }
        }

        if (scoresByCandidate.isEmpty()) {
            return IdentificationMatchResult(
                identified = false,
                candidateToken = null,
                bestScore = null,
                margin = null,
                reason = "NO_VALID_COMPARISONS",
                candidateScores = emptyMap()
            )
        }

        // Find best score per candidate
        val bestScores = scoresByCandidate.mapValues { (_, scores) -> scores.max() }

        // Sort candidates by best score descending
        val sortedCandidates = bestScores.entries.sortedByDescending { it.value }

        val bestCandidate = sortedCandidates[0]
        val secondBestScore = if (sortedCandidates.size > 1) sortedCandidates[1].value else 0.0
        val margin = bestCandidate.value - secondBestScore

        // Build candidate scores map for debugging
        val candidateScores = bestScores.toMap()

        // Check threshold
        if (bestCandidate.value < identificationThreshold) {
            return IdentificationMatchResult(
                identified = false,
                candidateToken = null,
                bestScore = bestCandidate.value,
                margin = margin,
                reason = "BELOW_THRESHOLD",
                candidateScores = candidateScores
            )
        }

        // Check margin
        if (margin < minimumMargin) {
            return IdentificationMatchResult(
                identified = false,
                candidateToken = null,
                bestScore = bestCandidate.value,
                margin = margin,
                reason = "AMBIGUOUS_MATCH",
                candidateScores = candidateScores
            )
        }

        // Identified!
        return IdentificationMatchResult(
            identified = true,
            candidateToken = bestCandidate.key,
            bestScore = bestCandidate.value,
            margin = margin,
            reason = null,
            candidateScores = candidateScores
        )
    }

    /**
     * Wipe all candidate templates from memory.
     * Call this when the identification session ends or expires.
     */
    fun wipeTemplates(session: IdentificationSessionStart) {
        // Templates are in the IdentificationTemplate objects
        // The ByteArray data will be garbage collected when the session object is dereferenced
        // For extra security, we can zero out the bytes
        for (template in session.templates) {
            template.templateBytes.fill(0)
        }
    }
}
