package af.gov.aop.amms.terminal.biometrics

enum class TerminalState {
    POWER_OFF,
    INITIALIZING,
    PERMISSION_PENDING,
    READY,
    DEVICE_SELECTED,
    SCANNER_OPEN,
    CAPTURING,
    CLOSED,
    ERROR,
    RECOVERING,
}

/** Enforces the lifecycle required by the SVT3000 SE integration contract. */
class TerminalStateMachine(initialState: TerminalState = TerminalState.POWER_OFF) {
    @Volatile
    var state: TerminalState = initialState
        private set

    @Synchronized
    fun transitionTo(next: TerminalState) {
        check(next in allowedTransitions.getValue(state)) {
            "Invalid terminal transition: $state -> $next"
        }
        state = next
    }

    @Synchronized
    fun fail() {
        state = TerminalState.ERROR
    }

    private companion object {
        val allowedTransitions = mapOf(
            TerminalState.POWER_OFF to setOf(TerminalState.INITIALIZING, TerminalState.CLOSED),
            TerminalState.INITIALIZING to setOf(TerminalState.PERMISSION_PENDING, TerminalState.ERROR, TerminalState.CLOSED),
            TerminalState.PERMISSION_PENDING to setOf(TerminalState.READY, TerminalState.ERROR, TerminalState.CLOSED),
            TerminalState.READY to setOf(TerminalState.DEVICE_SELECTED, TerminalState.CLOSED, TerminalState.ERROR),
            TerminalState.DEVICE_SELECTED to setOf(TerminalState.SCANNER_OPEN, TerminalState.CLOSED, TerminalState.ERROR),
            TerminalState.SCANNER_OPEN to setOf(TerminalState.CAPTURING, TerminalState.CLOSED, TerminalState.ERROR),
            TerminalState.CAPTURING to setOf(TerminalState.SCANNER_OPEN, TerminalState.ERROR),
            TerminalState.CLOSED to setOf(TerminalState.INITIALIZING, TerminalState.POWER_OFF),
            TerminalState.ERROR to setOf(TerminalState.RECOVERING, TerminalState.CLOSED),
            TerminalState.RECOVERING to setOf(TerminalState.INITIALIZING, TerminalState.ERROR, TerminalState.CLOSED),
        )
    }
}
