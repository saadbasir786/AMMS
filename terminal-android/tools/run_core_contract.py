#!/usr/bin/env python3
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
required = [
    ROOT / 'app/src/main/java/com/amms/terminal/TerminalWorkflow.kt',
    ROOT / 'app/src/main/java/com/amms/terminal/MainActivity.kt',
    ROOT / 'app/src/main/java/com/amms/terminal/core/FakeFingerprintAdapter.java',
    ROOT / 'app/src/main/java/com/amms/terminal/core/TerminalSessionState.java',
    ROOT / 'core-test/FakeFingerprintAdapterContract.java',
]
missing = [str(p.relative_to(ROOT)) for p in required if not p.exists()]
if missing:
    raise SystemExit('MISSING:' + ','.join(missing))

workflow = (ROOT / 'app/src/main/java/com/amms/terminal/TerminalWorkflow.kt').read_text()
core = (ROOT / 'app/src/main/java/com/amms/terminal/core/FakeFingerprintAdapter.java').read_text()
state = (ROOT / 'app/src/main/java/com/amms/terminal/core/TerminalSessionState.java').read_text()
for marker in ('/api/terminal/v1/assignments/current', '/api/terminal/v1/sessions/{session}/biometric-candidates', 'attendance', 'close'):
    if marker not in workflow and marker.replace('{session}', 'session') not in workflow:
        raise SystemExit('WORKFLOW_MARKER_MISSING:' + marker)
for marker in ('MATCH_FIRST', 'NO_MATCH', 'LOW_SCORE', 'UNAVAILABLE', 'CANCELLED'):
    if marker not in core:
        raise SystemExit('ADAPTER_VECTOR_MISSING:' + marker)
for marker in ('startEnrollment', 'startMeeting', 'closeAndClear', 'candidateBytesInMemory'):
    if marker not in state:
        raise SystemExit('STATE_MARKER_MISSING:' + marker)

build = ROOT / 'build'
classes = build / 'contract-classes'
classes.mkdir(parents=True, exist_ok=True)
java_sources = [str(p) for p in (ROOT / 'app/src/main/java/com/amms/terminal/core').glob('*.java')]
java_sources.append(str(ROOT / 'core-test/FakeFingerprintAdapterContract.java'))
subprocess.run(['javac', '-d', str(classes), *java_sources], check=True)
result = subprocess.run(['java', '-cp', str(classes), 'FakeFingerprintAdapterContract'], check=True, capture_output=True, text=True)
print(result.stdout.strip())
print('FLOW10A_ANDROID_FOUNDATION_PASS files=%d' % len(required))
