import com.amms.terminal.core.FakeFingerprintAdapter;
import com.amms.terminal.core.FingerprintOutcome;
import com.amms.terminal.core.TerminalSessionState;
import java.util.List;

public final class FakeFingerprintAdapterContract {
    public static void main(String[] args) {
        FakeFingerprintAdapter adapter = new FakeFingerprintAdapter();
        var candidates = List.of(new FakeFingerprintAdapter.Candidate("EMP-12", "TPL-12-RI", "SYNTHETIC_TEMPLATE_EMP_12", "CHECKSUM-12"));
        require(adapter.captureAndIdentify(FakeFingerprintAdapter.CaptureVector.MATCH_FIRST, candidates).status() == FingerprintOutcome.Status.MATCH, "match");
        require("TPL-12-RI".equals(adapter.captureAndIdentify(FakeFingerprintAdapter.CaptureVector.MATCH_FIRST, candidates).templateUuid()), "matched candidate");
        require(adapter.captureAndIdentify(FakeFingerprintAdapter.CaptureVector.NO_MATCH, candidates).status() == FingerprintOutcome.Status.NO_MATCH, "no-match");
        require(adapter.captureAndIdentify(FakeFingerprintAdapter.CaptureVector.LOW_SCORE, candidates).status() == FingerprintOutcome.Status.LOW_SCORE, "low-score");
        require(adapter.captureAndIdentify(FakeFingerprintAdapter.CaptureVector.UNAVAILABLE, candidates).status() == FingerprintOutcome.Status.UNAVAILABLE, "unavailable");
        require(adapter.captureAndIdentify(FakeFingerprintAdapter.CaptureVector.CANCELLED, candidates).status() == FingerprintOutcome.Status.CANCELLED, "cancelled");

        TerminalSessionState state = new TerminalSessionState();
        state.startMeeting("SESSION-1", "CHECKSUM-SET-1", 1);
        require(state.mode() == TerminalSessionState.Mode.MEETING, "meeting mode");
        require(state.candidateBytesInMemory() == 1, "candidate memory");
        state.closeAndClear();
        require(state.mode() == TerminalSessionState.Mode.CLOSED, "closed mode");
        require(state.candidateBytesInMemory() == 0 && state.sessionUuid() == null, "memory cleared");
        state.startEnrollment("ENROLL-1");
        require(state.mode() == TerminalSessionState.Mode.ENROLLMENT, "enrollment mode");
        state.closeAndClear();
        System.out.println("FLOW10A_ANDROID_CORE_PASS vectors=8");
    }

    private static void require(boolean condition, String name) {
        if (!condition) throw new AssertionError(name);
    }
}
