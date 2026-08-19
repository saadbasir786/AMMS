package com.amms.terminal

import com.amms.terminal.core.FakeFingerprintAdapter
import com.amms.terminal.core.FingerprintOutcome
import com.amms.terminal.core.TerminalSessionState

/**
 * Android-facing orchestration boundary. Network implementations are injected so
 * the state machine remains JVM-testable and vendor-independent.
 */
class TerminalWorkflow(private val api: TerminalApi, private val adapter: FakeFingerprintAdapter) {
    companion object {
        const val ME = "/api/terminal/v1/me"
        const val CURRENT_ASSIGNMENTS = "/api/terminal/v1/assignments/current"
        const val ACTIVATE = "/api/terminal/v1/assignments/{assignment}/activate"
        const val CANDIDATES = "/api/terminal/v1/sessions/{session}/biometric-candidates"
        const val ATTENDANCE = "/api/terminal/v1/sessions/{session}/attendance/fingerprint"
        const val CLOSE = "/api/terminal/v1/sessions/{session}/close"
        const val ENROLLMENT_TEMPLATE = "/api/terminal/v1/enrollment/{enrollment}/template"
    }

    val state = TerminalSessionState()

    fun login(rawTerminalCredential: String): TerminalIdentity = api.me(rawTerminalCredential)

    fun loadCurrentAssignments(rawTerminalCredential: String): List<TerminalAssignment> = api.currentAssignments(rawTerminalCredential)

    fun activateMeeting(rawTerminalCredential: String, assignmentUuid: String): CandidateDownload {
        val session = api.activate(rawTerminalCredential, assignmentUuid)
        val candidates = api.candidates(session.sessionToken, session.sessionUuid)
        state.startMeeting(session.sessionUuid, candidates.candidateSetChecksum, candidates.templates.size)
        return candidates
    }

    fun identify(vector: FakeFingerprintAdapter.CaptureVector, candidates: List<FakeFingerprintAdapter.Candidate>): FingerprintOutcome =
        adapter.captureAndIdentify(vector, candidates)

    fun submitAttendance(sessionToken: String, sessionUuid: String, outcome: FingerprintOutcome, checksum: String, eventUuid: String): AttendanceResult {
        check(state.mode() == TerminalSessionState.Mode.MEETING)
        check(state.sessionUuid() == sessionUuid)
        check(outcome.status() == FingerprintOutcome.Status.MATCH)
        return api.attendance(sessionToken, sessionUuid, outcome.templateUuid(), checksum, eventUuid, outcome.score())
    }

    fun startEnrollment(rawTerminalCredential: String, enrollmentUuid: String) {
        api.enrollmentTask(rawTerminalCredential, enrollmentUuid)
        state.startEnrollment(enrollmentUuid)
    }

    fun submitEnrollment(rawTerminalCredential: String, enrollmentUuid: String, fingerPosition: String, templateData: String, qualityAccepted: Boolean): EnrollmentResult =
        api.submitTemplate(rawTerminalCredential, enrollmentUuid, fingerPosition, templateData, qualityAccepted)

    fun close(sessionToken: String, sessionUuid: String) {
        api.close(sessionToken, sessionUuid)
        state.closeAndClear()
    }

    interface TerminalApi {
        fun me(rawCredential: String): TerminalIdentity
        fun currentAssignments(rawCredential: String): List<TerminalAssignment>
        fun activate(rawCredential: String, assignmentUuid: String): ActivatedSession
        fun candidates(sessionToken: String, sessionUuid: String): CandidateDownload
        fun attendance(sessionToken: String, sessionUuid: String, templateUuid: String, checksum: String, eventUuid: String, score: Int): AttendanceResult
        fun enrollmentTask(rawCredential: String, enrollmentUuid: String): EnrollmentTask
        fun submitTemplate(rawCredential: String, enrollmentUuid: String, fingerPosition: String, templateData: String, qualityAccepted: Boolean): EnrollmentResult
        fun close(sessionToken: String, sessionUuid: String)
    }

    data class TerminalIdentity(val terminalUuid: String, val status: String)
    data class TerminalAssignment(val assignmentUuid: String, val meetingId: Long, val status: String)
    data class ActivatedSession(val sessionUuid: String, val sessionToken: String)
    data class CandidateDownload(val sessionUuid: String, val candidateSetChecksum: String, val templates: List<FakeFingerprintAdapter.Candidate>)
    data class AttendanceResult(val attendanceUuid: String, val status: String)
    data class EnrollmentTask(val enrollmentUuid: String, val employeeId: Long, val fingerPositions: List<String>)
    data class EnrollmentResult(val templateUuid: String, val status: String)
}
