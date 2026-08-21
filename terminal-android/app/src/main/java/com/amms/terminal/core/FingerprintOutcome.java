package com.amms.terminal.core;

public final class FingerprintOutcome {
    public enum Status { MATCH, NO_MATCH, LOW_SCORE, UNAVAILABLE, CANCELLED }
    private final Status status;
    private final Long employeeId;
    private final String templateUuid;
    private final int score;
    private final String reason;

    private FingerprintOutcome(Status status, Long employeeId, String templateUuid, int score, String reason) {
        this.status = status;
        this.employeeId = employeeId;
        this.templateUuid = templateUuid;
        this.score = score;
        this.reason = reason;
    }

    public static FingerprintOutcome match(long employeeId, String templateUuid, int score) { return new FingerprintOutcome(Status.MATCH, employeeId, templateUuid, score, null); }
    public static FingerprintOutcome noMatch() { return new FingerprintOutcome(Status.NO_MATCH, null, null, 0, "NO_MATCH"); }
    public static FingerprintOutcome lowScore(int score) { return new FingerprintOutcome(Status.LOW_SCORE, null, null, score, "QUALITY_TOO_LOW"); }
    public static FingerprintOutcome unavailable() { return new FingerprintOutcome(Status.UNAVAILABLE, null, null, 0, "DEVICE_UNAVAILABLE"); }
    public static FingerprintOutcome cancelled() { return new FingerprintOutcome(Status.CANCELLED, null, null, 0, "CAPTURE_CANCELLED"); }

    public Status status() { return status; }
    public Long employeeId() { return employeeId; }
    public String templateUuid() { return templateUuid; }
    public int score() { return score; }
    public String reason() { return reason; }
}
