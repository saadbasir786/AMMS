package com.amms.terminal.core;

/** FLOW-10A terminal lifecycle; no biometric bytes are retained after CLOSED. */
public final class TerminalSessionState {
    public enum Mode { CLOSED, ENROLLMENT, MEETING }
    private Mode mode = Mode.CLOSED;
    private String sessionUuid;
    private String candidateSetChecksum;
    private int candidateBytesInMemory;

    public void startEnrollment(String enrollmentUuid) {
        requireClosed();
        mode = Mode.ENROLLMENT;
        sessionUuid = enrollmentUuid;
    }

    public void startMeeting(String sessionUuid, String candidateSetChecksum, int candidateCount) {
        requireClosed();
        if (sessionUuid == null || sessionUuid.isBlank()) throw new IllegalArgumentException("sessionUuid required");
        if (candidateSetChecksum == null || candidateSetChecksum.isBlank()) throw new IllegalArgumentException("candidate checksum required");
        mode = Mode.MEETING;
        this.sessionUuid = sessionUuid;
        this.candidateSetChecksum = candidateSetChecksum;
        this.candidateBytesInMemory = Math.max(0, candidateCount);
    }

    public void closeAndClear() {
        mode = Mode.CLOSED;
        sessionUuid = null;
        candidateSetChecksum = null;
        candidateBytesInMemory = 0;
    }

    public Mode mode() { return mode; }
    public String sessionUuid() { return sessionUuid; }
    public String candidateSetChecksum() { return candidateSetChecksum; }
    public int candidateBytesInMemory() { return candidateBytesInMemory; }

    private void requireClosed() {
        if (mode != Mode.CLOSED) throw new IllegalStateException("terminal mode already active");
    }
}
