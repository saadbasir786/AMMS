package af.gov.aop.amms.terminal.net

import af.gov.aop.amms.terminal.BuildConfig
import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import org.json.JSONArray
import org.json.JSONObject
import java.security.KeyStore
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import java.util.Locale
import java.util.UUID
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

data class MeetingSummary(
    val id: String,
    val title: String,
    val meetingDate: String,
    val startTime: String,
    val location: String?,
    val status: String,
    val assignmentUuid: String? = null,
    val assignmentStatus: String? = null,
    val isStale: Boolean = false,
) {
    val canRecordAttendance: Boolean
        get() = !isStale && (status == "SCHEDULED" || status == "IN_PROGRESS")
}

data class ParticipantItem(
    val id: String,
    val employeeId: String,
    val employeeNumber: String,
    val firstName: String,
    val lastName: String,
    val jobTitle: String?,
    val attendanceStatus: String,
) {
    val attended: Boolean
        get() = attendanceStatus == "PRESENT" || attendanceStatus == "present" || attendanceStatus == "LATE"
}

data class ParticipantPage(
    val participants: List<ParticipantItem>,
    val currentPage: Int,
    val lastPage: Int,
    val total: Int,
)

data class EnrollmentProgress(
    val contextUuid: String,
    val employeeId: String,
    val activeFingerCount: Int,
    val requiredFingerCount: Int,
    val activeFingerPositions: Set<String>,
)

data class AttendanceResult(
    val accepted: Boolean,
    val duplicate: Boolean,
    val attendanceStatus: String?,
    val error: String?,
)

data class TerminalPairingResult(
    val terminalId: String,
    val departmentId: String,
    val departmentName: String,
    val status: String,
)

data class IdentificationCandidate(
    val candidateToken: String,
    val fingerPositions: List<String>,
)

data class IdentificationTemplate(
    val candidateToken: String,
    val fingerPosition: String,
    val templateBytes: ByteArray,
    val employeeId: String,
    val templateUuid: String,
    val templateChecksum: String,
    val templateVendor: String,
    val algorithmVersion: String,
)

data class IdentificationSessionStart(
    val sessionId: String,
    val expiresAt: String,
    val candidates: List<IdentificationCandidate>,
    val templates: List<IdentificationTemplate>,
    val candidateSetChecksum: String,
)

data class IdentificationResult(
    val identified: Boolean,
    val employeeId: String?,
    val employeeName: String?,
    val attendanceStatus: String?,
    val reason: String?,
    val matchedTemplateUuid: String? = null,
    val fingerPosition: String? = null,
    val matcherVendor: String? = null,
    val matcherAlgorithmVersion: String? = null,
    val candidateSetChecksum: String? = null,
)

/**
 * Laravel AMMS terminal client.
 *
 * The biometric layer is intentionally independent from this class. This
 * client only implements the authenticated terminal/session contract exposed
 * by backend/routes/api/attendance-biometrics.php.
 */
class LaravelTerminalClient(
    context: Context,
    private val identityPreferencesName: String = "amms_terminal_identity",
) {
    private val preferences = context.getSharedPreferences(identityPreferencesName, Context.MODE_PRIVATE)
    private val assignments = mutableMapOf<String, MeetingSummary>()
    private val sessions = mutableMapOf<String, ActiveSession>()
    private val identificationContexts = mutableMapOf<String, IdentificationContext>()

    /**
     * The server identity is authoritative after pairing. Before pairing we
     * expose the locally generated code only as a temporary support identifier.
     */
    val deviceCode: String
        get() = preferences.getString(KEY_TERMINAL_UUID, null)
            ?: preferences.getString(KEY_DEVICE_CODE, null)
            ?: "TERM-${UUID.randomUUID().toString().uppercase(Locale.US)}".also {
                preferences.edit().putString(KEY_DEVICE_CODE, it).apply()
            }

    val terminalId: String?
        get() = preferences.getString(KEY_TERMINAL_UUID, null)

    private val baseUrl: String?
        get() = BuildConfig.AMMS_API_BASE_URL.trim().trimEnd('/').takeIf { it.isNotBlank() }
            ?: preferences.getString(KEY_BASE_URL, null)?.trim()?.trimEnd('/')

    private val credential: String?
        get() = readCredential()

    val isPaired: Boolean
        get() = !baseUrl.isNullOrBlank() && !credential.isNullOrBlank()

    val pairedDepartmentName: String?
        get() = preferences.getString(KEY_TERMINAL_NAME, null)

    fun clearPairing() {
        sessions.clear()
        assignments.clear()
        identificationContexts.clear()
        preferences.edit()
            .remove(KEY_BASE_URL)
            .remove(KEY_CREDENTIAL)
            .remove(KEY_CREDENTIAL_CIPHERTEXT)
            .remove(KEY_CREDENTIAL_IV)
            .remove(KEY_TERMINAL_UUID)
            .remove(KEY_TERMINAL_NAME)
            .apply()
    }

    suspend fun pairWithCode(apiBaseUrl: String, pairingCode: String): Result<TerminalPairingResult> = withContext(Dispatchers.IO) {
        runCatching {
            require(pairingCode.matches(Regex("^\\d{4}$"))) { "INVALID_TERMINAL_PAIRING_CODE" }
            require(apiBaseUrl.startsWith("http://") || apiBaseUrl.startsWith("https://")) { "INVALID_API_BASE_URL" }

            val response = requestWithCredentials(
                root = apiBaseUrl.trimEnd('/'),
                authToken = null,
                method = "POST",
                path = "/api/terminal/v1/pair",
                body = JSONObject().put("pairing_code", pairingCode),
            )
            val data = response.optJSONObject("data") ?: error("TERMINAL_PAIRING_RESPONSE_EMPTY")
            val terminal = data.optJSONObject("terminal") ?: error("TERMINAL_IDENTITY_EMPTY")
            val terminalUuid = terminal.optString("uuid").takeIf { it.isNotBlank() }
                ?: error("TERMINAL_IDENTITY_EMPTY")
            val terminalName = terminal.optString("name", "ترمینال جلسات")
            val credential = data.optString("credential").takeIf { it.isNotBlank() }
                ?: error("TERMINAL_CREDENTIAL_EMPTY")

            preferences.edit()
                .putString(KEY_BASE_URL, apiBaseUrl.trimEnd('/'))
                .putString(KEY_TERMINAL_UUID, terminalUuid)
                .putString(KEY_TERMINAL_NAME, terminalName)
                .apply()
            writeCredential(credential)

            TerminalPairingResult(
                terminalId = terminalUuid,
                departmentId = "",
                departmentName = terminalName,
                status = terminal.optString("status", "ACTIVE"),
            )
        }
    }

    private fun readCredential(): String? {
        val ciphertext = preferences.getString(KEY_CREDENTIAL_CIPHERTEXT, null)
        val iv = preferences.getString(KEY_CREDENTIAL_IV, null)
        if (!ciphertext.isNullOrBlank() && !iv.isNullOrBlank()) {
            return runCatching {
                val cipher = Cipher.getInstance("AES/GCM/NoPadding")
                cipher.init(
                    Cipher.DECRYPT_MODE,
                    keystoreKey(),
                    GCMParameterSpec(128, Base64.decode(iv, Base64.DEFAULT)),
                )
                String(cipher.doFinal(Base64.decode(ciphertext, Base64.DEFAULT)), Charsets.UTF_8)
            }.getOrNull()
        }

        // Migrate an older development build that stored the token in plain preferences.
        return preferences.getString(KEY_CREDENTIAL, null)?.also { legacy ->
            writeCredential(legacy)
            preferences.edit().remove(KEY_CREDENTIAL).apply()
        }
    }

    private fun writeCredential(value: String) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, keystoreKey())
        preferences.edit()
            .putString(KEY_CREDENTIAL_CIPHERTEXT, Base64.encodeToString(cipher.doFinal(value.toByteArray(Charsets.UTF_8)), Base64.NO_WRAP))
            .putString(KEY_CREDENTIAL_IV, Base64.encodeToString(cipher.iv, Base64.NO_WRAP))
            .remove(KEY_CREDENTIAL)
            .apply()
    }

    private fun keystoreKey(): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (keyStore.getKey(KEY_ALIAS, null) as? SecretKey)?.let { return it }
        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore")
        generator.init(KeyGenParameterSpec.Builder(
            KEY_ALIAS,
            KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
        ).setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .build())
        return generator.generateKey()
    }

    suspend fun getActiveMeetings(): Result<List<MeetingSummary>> = withContext(Dispatchers.IO) {
        runCatching {
            val response = request("GET", "/api/terminal/v1/assignments/current")
            val rows = response.optJSONArray("data") ?: JSONArray()
            assignments.clear()
            buildList {
                for (index in 0 until rows.length()) {
                    val row = rows.getJSONObject(index)
                    val stale = row.optBoolean("is_stale", false) || row.optString("status") == "STALE"
                    val validFrom = row.optString("valid_from", "")
                    val meeting = MeetingSummary(
                        id = row.getString("meeting_id"),
                        title = row.optString("subject", "جلسه داخلی"),
                        meetingDate = row.optString("meeting_date", validFrom.take(10)),
                        startTime = row.optString("start_time", validFrom.drop(11).take(5)),
                        location = row.optString("location", "").takeIf { it.isNotBlank() },
                        status = row.optString("meeting_status", "UNKNOWN"),
                        assignmentUuid = row.optString("assignment_uuid", "").takeIf { it.isNotBlank() },
                        assignmentStatus = row.optString("status", "").takeIf { it.isNotBlank() },
                        isStale = stale,
                    )
                    assignments[meeting.id] = meeting
                    add(meeting)
                }
            }
        }
    }

    suspend fun heartbeat(appVersion: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            request(
                method = "POST",
                path = "/api/terminal/v1/heartbeat",
                body = JSONObject().put("app_version", appVersion),
            )
            Unit
        }
    }

    suspend fun getMeetingParticipants(
        assignmentUuid: String,
        page: Int = 1,
        perPage: Int = 50,
        search: String = "",
    ): Result<ParticipantPage> = withContext(Dispatchers.IO) {
        runCatching {
            require(assignmentUuid.isNotBlank()) { "TERMINAL_ASSIGNMENT_NOT_FOUND" }
            require(page >= 1) { "INVALID_PAGE" }
            require(perPage in 1..50) { "INVALID_PER_PAGE" }
            val searchQuery = search.trim().takeIf { it.isNotEmpty() }
                ?.let { "&search=${URLEncoder.encode(it, "UTF-8")}" }
                .orEmpty()
            val response = request(
                "GET",
                "/api/terminal/v1/assignments/$assignmentUuid/participants?page=$page&per_page=$perPage$searchQuery",
            )
            val meta = response.optJSONObject("meta") ?: error("TERMINAL_PARTICIPANTS_PAGINATION_EMPTY")
            ParticipantPage(
                participants = parseParticipants(response.optJSONArray("data") ?: JSONArray()),
                currentPage = meta.optInt("current_page", page),
                lastPage = meta.optInt("last_page", page),
                total = meta.optInt("total", 0),
            )
        }
    }

    suspend fun submitAttendanceEvent(
        meetingId: String,
        employeeNumber: String,
        eventType: String,
        verificationResult: String,
        verificationScore: Double,
        participantId: String? = null,
        employeeId: String? = null,
        matchedTemplateUuid: String? = null,
        fingerPosition: String? = null,
        matcherVendor: String? = null,
        matcherAlgorithmVersion: String? = null,
        candidateSetChecksum: String? = null,
    ): Result<AttendanceResult> = withContext(Dispatchers.IO) {
        runCatching {
            require(verificationResult == "MATCH") { "BIOMETRIC_MATCH_REQUIRED" }
            val session = ensureSession(meetingId)
            val employee = employeeId?.toIntOrNull() ?: error("EMPLOYEE_ID_REQUIRED")
            val templateUuid = matchedTemplateUuid ?: error("MATCHED_TEMPLATE_REQUIRED")
            val checksum = candidateSetChecksum ?: session.candidateSetChecksum ?: error("CANDIDATE_SET_CHECKSUM_REQUIRED")
            val body = JSONObject().apply {
                put("client_event_uuid", UUID.randomUUID().toString())
                put("employee_id", employee)
                put("matched_template_uuid", templateUuid)
                put("finger_position", fingerPosition ?: "RIGHT_THUMB")
                put("matcher_vendor", matcherVendor ?: "DERMALOG")
                put("matcher_algorithm_version", matcherAlgorithmVersion ?: "DERMALOG")
                put("match_score", verificationScore)
                put("threshold_reference", "DERMALOG:30.0:margin10.0")
                put("candidate_set_checksum", checksum)
                put("device_captured_at", JSONObject.NULL)
            }
            request("POST", "/api/terminal/v1/sessions/${session.uuid}/attendance/fingerprint", body, session.token)
            AttendanceResult(true, false, "PRESENT", null)
        }
    }

    suspend fun startIdentificationSession(meetingId: String): Result<IdentificationSessionStart> = withContext(Dispatchers.IO) {
        runCatching {
            val session = ensureSession(meetingId)
            val response = request("GET", "/api/terminal/v1/sessions/${session.uuid}/biometric-candidates", token = session.token)
            val data = response.optJSONObject("data") ?: error("BIOMETRIC_CANDIDATES_EMPTY")
            val candidates = mutableListOf<IdentificationCandidate>()
            val templates = mutableListOf<IdentificationTemplate>()
            val rows = data.optJSONArray("candidates") ?: JSONArray()
            for (index in 0 until rows.length()) {
                val row = rows.getJSONObject(index)
                val employeeId = row.getString("employee_id")
                val templateUuid = row.getString("template_uuid")
                val token = "$employeeId:$templateUuid"
                val finger = row.optString("finger_position", "RIGHT_THUMB")
                candidates += IdentificationCandidate(token, listOf(finger))
                templates += IdentificationTemplate(
                    candidateToken = token,
                    fingerPosition = finger,
                    templateBytes = decodeTemplate(row.optString("template_data"), row.optString("template_encoding", "RAW_UTF8")),
                    employeeId = employeeId,
                    templateUuid = templateUuid,
                    templateChecksum = row.optString("template_checksum"),
                    templateVendor = row.optString("template_vendor", "DERMALOG"),
                    algorithmVersion = row.optString("algorithm_version", "DERMALOG"),
                )
            }
            val result = IdentificationSessionStart(
                sessionId = session.uuid,
                expiresAt = session.expiresAt,
                candidates = candidates.distinctBy { it.candidateToken },
                templates = templates,
                candidateSetChecksum = data.getString("candidate_set_checksum"),
            )
            session.candidateSetChecksum = result.candidateSetChecksum
            identificationContexts[session.uuid] = IdentificationContext(result, session.participants)
            result
        }
    }

    suspend fun completeIdentificationSession(
        sessionId: String,
        candidateToken: String,
        verificationScore: Double,
        margin: Double,
        clientEventId: String = UUID.randomUUID().toString(),
    ): Result<IdentificationResult> = withContext(Dispatchers.IO) {
        runCatching {
            val context = identificationContexts[sessionId] ?: error("IDENTIFICATION_SESSION_NOT_FOUND")
            val template = context.session.templates.firstOrNull { it.candidateToken == candidateToken }
                ?: error("MATCHED_TEMPLATE_NOT_FOUND")
            val participant = context.participants.firstOrNull { it.employeeId == template.employeeId }
            IdentificationResult(
                identified = true,
                employeeId = template.employeeId,
                employeeName = participant?.let { "${it.firstName} ${it.lastName}".trim() },
                attendanceStatus = null,
                reason = null,
                matchedTemplateUuid = template.templateUuid,
                fingerPosition = template.fingerPosition,
                matcherVendor = template.templateVendor,
                matcherAlgorithmVersion = template.algorithmVersion,
                candidateSetChecksum = context.session.candidateSetChecksum,
            )
        }
    }

    suspend fun enrollMeetingParticipantTemplate(
        meetingId: String,
        employeeId: String,
        fingerPosition: String,
        templateBytes: ByteArray,
        deviceModel: String = "DERMALOG SVT3000 SE",
        sdkVersion: String? = "2.8.1",
    ): Result<EnrollmentProgress> = withContext(Dispatchers.IO) {
        runCatching {
            val session = ensureSession(meetingId)
            val payload = JSONObject().apply {
                put("employee_id", employeeId)
                put("finger_position", fingerPosition)
                put("template_vendor", "DERMALOG")
                put("template_format", "DERMALOG-FORMAT-0")
                put("algorithm_version", sdkVersion ?: deviceModel)
                put("template_data", Base64.encodeToString(templateBytes, Base64.NO_WRAP))
                put("template_encoding", "BASE64")
                put("quality_accepted", true)
            }
            val response = request(
                "POST",
                "/api/terminal/v1/sessions/${session.uuid}/biometric-templates",
                payload,
                session.token,
            )
            val progress = response.optJSONObject("data")?.optJSONObject("progress")
                ?: error("BIOMETRIC_ENROLLMENT_PROGRESS_EMPTY")
            val positionsJson = progress.optJSONArray("active_finger_positions") ?: JSONArray()
            val positions = buildSet {
                for (index in 0 until positionsJson.length()) add(positionsJson.optString(index))
            }
            EnrollmentProgress(
                contextUuid = session.uuid,
                employeeId = progress.getString("employee_id"),
                activeFingerCount = progress.getInt("active_finger_count"),
                requiredFingerCount = progress.getInt("required_finger_count"),
                activeFingerPositions = positions,
            )
        }
    }

    suspend fun closeMeetingSession(meetingId: String, reason: String = "پایان کار ترمینال"): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            val session = sessions[meetingId] ?: return@runCatching Unit
            request("POST", "/api/terminal/v1/sessions/${session.uuid}/close", JSONObject().put("reason", reason), session.token)
            sessions.remove(meetingId)
            identificationContexts.remove(session.uuid)
            Unit
        }
    }

    private fun ensureSession(meetingId: String): ActiveSession {
        sessions[meetingId]?.let { return it }
        val assignment = assignments[meetingId] ?: error("TERMINAL_ASSIGNMENT_NOT_FOUND")
        if (assignment.isStale || assignment.assignmentUuid.isNullOrBlank()) error("TERMINAL_ASSIGNMENT_STALE")
        val activation = request("POST", "/api/terminal/v1/assignments/${assignment.assignmentUuid}/activate")
        val data = activation.optJSONObject("data") ?: error("TERMINAL_SESSION_EMPTY")
        val sessionRow = data.optJSONObject("session") ?: error("TERMINAL_SESSION_EMPTY")
        val session = ActiveSession(
            uuid = sessionRow.getString("uuid"),
            token = data.getString("session_token"),
            expiresAt = sessionRow.optString("expires_at", ""),
        )
        val manifestResponse = request("GET", "/api/terminal/v1/sessions/${session.uuid}/manifest", token = session.token)
        val manifest = manifestResponse.optJSONObject("data") ?: error("TERMINAL_MANIFEST_EMPTY")
        session.participants = parseParticipants(manifest.optJSONArray("participants") ?: JSONArray())
        sessions[meetingId] = session
        return session
    }

    private fun parseParticipants(rows: JSONArray): List<ParticipantItem> = buildList {
        for (index in 0 until rows.length()) {
            val row = rows.getJSONObject(index)
            val displayName = row.optString("display_name").trim().split(" ", limit = 2)
            add(ParticipantItem(
                id = row.optString("employee_id"),
                employeeId = row.optString("employee_id"),
                employeeNumber = row.optString("employee_number", row.optString("employee_id")),
                firstName = row.optString("first_name", displayName.firstOrNull().orEmpty()),
                lastName = row.optString("last_name", displayName.getOrNull(1).orEmpty()),
                jobTitle = row.optString("job_title", "").takeIf { it.isNotBlank() },
                attendanceStatus = row.optString("attendance_status", "NOT_CHECKED_IN"),
            ))
        }
    }

    private fun decodeTemplate(value: String, encoding: String): ByteArray {
        if (encoding == "BASE64") return Base64.decode(value, Base64.DEFAULT)
        return value.toByteArray(Charsets.ISO_8859_1)
    }

    private fun request(
        method: String,
        path: String,
        body: JSONObject? = null,
        token: String? = credential,
    ): JSONObject {
        val root = baseUrl ?: error("TERMINAL_NOT_CONFIGURED")
        return requestWithCredentials(root, token, method, path, body)
    }

    private fun requestWithCredentials(
        root: String,
        authToken: String?,
        method: String,
        path: String,
        body: JSONObject? = null,
    ): JSONObject {
        val connection = (URL(root.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Content-Type", "application/json")
            authToken?.takeIf { it.isNotBlank() }?.let { setRequestProperty("Authorization", "Bearer $it") }
            connectTimeout = 8000
            readTimeout = 8000
            doOutput = body != null
        }
        body?.let { connection.outputStream.use { output -> output.write(it.toString().toByteArray(Charsets.UTF_8)) } }
        val responseText = (if (connection.responseCode in 200..299) connection.inputStream else connection.errorStream)
            ?.bufferedReader()?.use { it.readText() }.orEmpty()
        if (connection.responseCode !in 200..299) {
            val errorPayload = runCatching { JSONObject(responseText) }.getOrNull()
            val code = errorPayload?.optString("code", "HTTP_${connection.responseCode}") ?: "HTTP_${connection.responseCode}"
            val message = errorPayload?.optString("message", responseText) ?: responseText
            error("$code: $message")
        }
        return if (responseText.isBlank()) JSONObject() else JSONObject(responseText)
    }

    private class ActiveSession(
        val uuid: String,
        val token: String,
        val expiresAt: String,
        var participants: List<ParticipantItem> = emptyList(),
        var candidateSetChecksum: String? = null,
    )

    private data class IdentificationContext(
        val session: IdentificationSessionStart,
        val participants: List<ParticipantItem>,
    )

    private companion object {
        const val KEY_DEVICE_CODE = "device_code"
        const val KEY_BASE_URL = "api_base_url"
        const val KEY_CREDENTIAL = "terminal_credential"
        const val KEY_CREDENTIAL_CIPHERTEXT = "terminal_credential_ciphertext"
        const val KEY_CREDENTIAL_IV = "terminal_credential_iv"
        const val KEY_TERMINAL_UUID = "terminal_uuid"
        const val KEY_TERMINAL_NAME = "terminal_name"
        const val KEY_ALIAS = "amms_terminal_credential_key"
    }
}
