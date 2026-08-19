package com.amms.terminal.core;

public final class FingerprintOutcome {
    public enum Status { MATCH, NO_MATCH, LOW_SCORE, UNAVAILABLE, CANCELLED }
    private final Status status;
    private final String templateUuid;
    private final int score;
    private final String reason;

    private FingerprintOutcome(Status status, String templateUuid, int score, String reason) {
        this.status = status;
        this.templateUuid = templateUuid;
        this.score = score;
        this.reason = reason;
    }

    public static FingerprintOutcome match(String templateUuid, int score) { return new FingerprintOutcome(Status.MATCH, templateUuid, score, null); }
    public static FingerprintOutcome noMatch() { return new FingerprintOutcome(Status.NO_MATCH, null, 0, "NO_MATCH"); }
    public static FingerprintOutcome lowScore(int score) { return new FingerprintOutcome(Status.LOW_SCORE, null, score, "QUALITY_TOO_LOW"); }
    public static FingerprintOutcome unavailable() { return new FingerprintOutcome(Status.UNAVAILABLE, null, 0, "DEVICE_UNAVAILABLE"); }
    public static FingerprintOutcome cancelled() { return new FingerprintOutcome(Status.CANCELLED, null, 0, "CAPTURE_CANCELLED"); }

    public Status status() { return status; }
    public String templateUuid() { return templateUuid; }
    public int score() { return score; }
    public String reason() { return reason; }
}
