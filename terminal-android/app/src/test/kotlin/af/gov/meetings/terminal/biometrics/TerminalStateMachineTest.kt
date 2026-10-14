package af.gov.aop.amms.terminal.biometrics

import org.junit.Assert.assertEquals
import org.junit.Test

class TerminalStateMachineTest {
    @Test
    fun `supports the normal scanner lifecycle`() {
        val machine = TerminalStateMachine()

        listOf(
            TerminalState.INITIALIZING,
            TerminalState.PERMISSION_PENDING,
            TerminalState.READY,
            TerminalState.DEVICE_SELECTED,
            TerminalState.SCANNER_OPEN,
            TerminalState.CAPTURING,
            TerminalState.SCANNER_OPEN,
            TerminalState.CLOSED,
        ).forEach(machine::transitionTo)

        assertEquals(TerminalState.CLOSED, machine.state)
    }

    @Test(expected = IllegalStateException::class)
    fun `rejects capture before scanner is open`() {
        TerminalStateMachine().transitionTo(TerminalState.CAPTURING)
    }
}
