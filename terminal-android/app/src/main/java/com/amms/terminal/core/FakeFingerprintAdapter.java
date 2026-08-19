package com.amms.terminal.core;

import java.util.List;
import java.util.Objects;

/** Synthetic FLOW-10A adapter. It must never be used as a production hardware driver. */
public final class FakeFingerprintAdapter {
    public enum CaptureVector { MATCH_FIRST, NO_MATCH, LOW_SCORE, UNAVAILABLE, CANCELLED }

    public FingerprintOutcome captureAndIdentify(CaptureVector vector, List<Candidate> candidates) {
        Objects.requireNonNull(vector, "vector");
        if (vector == CaptureVector.UNAVAILABLE) return FingerprintOutcome.unavailable();
        if (vector == CaptureVector.CANCELLED) return FingerprintOutcome.cancelled();
        if (vector == CaptureVector.NO_MATCH || candidates == null || candidates.isEmpty()) return FingerprintOutcome.noMatch();
        if (vector == CaptureVector.LOW_SCORE) return FingerprintOutcome.lowScore(41);
        Candidate first = candidates.get(0);
        return FingerprintOutcome.match(first.templateUuid(), 97);
    }

    public record Candidate(String employeeId, String templateUuid, String templateData, String checksum) {
        public Candidate {
            if (employeeId == null || templateUuid == null || templateData == null || checksum == null) throw new IllegalArgumentException("candidate fields required");
        }
    }
}
