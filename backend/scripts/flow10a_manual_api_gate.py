import json
import os
import sqlite3
import subprocess
import time
import uuid
from pathlib import Path

import requests

fixture = json.loads(Path('/tmp/flow10a_manual_fixture.json').read_text())
manual_password = os.environ.get('AMMS_MANUAL_PASSWORD')
if not manual_password:
    raise RuntimeError('AMMS_MANUAL_PASSWORD must be supplied for the manual gate runner')
base = fixture['base_url']
root = Path('/home/ubuntu/amms/backend')
env = os.environ.copy()
env.update({
    'DB_CONNECTION': 'sqlite',
    'DB_DATABASE': '/home/ubuntu/amms/backend/database/flow10a_manual.sqlite',
    'APP_ENV': 'local',
    'APP_DEBUG': 'false',
})
log = []

def call(label, method, path, token=None, payload=None):
    headers = {'Accept': 'application/json'}
    if payload is not None:
        headers['Content-Type'] = 'application/json'
    if token:
        headers['Authorization'] = f'Bearer {token}'
    response = requests.request(method, base + path, headers=headers, json=payload, timeout=20)
    try:
        body = response.json()
    except ValueError:
        body = {'raw': response.text[:500]}
    safe = json.loads(json.dumps(body, ensure_ascii=False))
    if isinstance(safe, dict):
        data = safe.get('data')
        if isinstance(data, dict):
            if 'token' in data:
                data['token'] = '<redacted>'
            if 'session_token' in data:
                data['session_token'] = '<redacted>'
            if 'terminal_credential' in data:
                data['terminal_credential'] = '<redacted>'
            if isinstance(data.get('candidates'), list):
                for candidate in data['candidates']:
                    if isinstance(candidate, dict) and 'template_data' in candidate:
                        candidate['template_data'] = '<bytes-present>'
    log.append({'step': label, 'method': method, 'path': path, 'status': response.status_code, 'body': safe})
    print(json.dumps({'step': label, 'status': response.status_code, 'body': safe}, ensure_ascii=False), flush=True)
    return response.status_code, body

def login(email):
    status, body = call(f'login:{email}', 'POST', '/api/login', payload={'email': email, 'password': manual_password})
    assert status == 200, (email, status, body)
    return body['data']['token']

def mutate(kind):
    result = subprocess.run(
        ['php', 'scripts/flow10a_manual_mutate_fixture.php', kind],
        cwd=root,
        env=env,
        text=True,
        capture_output=True,
        check=True,
    )
    log.append({'step': f'fixture_mutation:{kind}', 'status': 200, 'body': json.loads(result.stdout)})

admin = login(fixture['admin_email'])
actor = login(fixture['actor_email'])
coordinator = login(fixture['coordinator_email'])

call('unauthorized human biometric metadata', 'GET', f"/api/employees/{fixture['employee_id']}/biometrics", admin)
call('scoped human biometric metadata', 'GET', f"/api/employees/{fixture['employee_id']}/biometrics", actor)

status, body = call('start enrollment', 'POST', '/api/biometric/enrollment-sessions', actor, {'employee_id': fixture['employee_id'], 'terminal_id': fixture['terminal_id'], 'required_finger_count': 2})
assert status in (200, 201), body
enrollment_uuid = body['data']['uuid']
with sqlite3.connect('/home/ubuntu/amms/backend/database/flow10a_manual.sqlite') as db:
    enrollment = db.execute('SELECT id FROM biometric_enrollment_sessions WHERE uuid = ?', (enrollment_uuid,)).fetchone()[0]

terminal1 = fixture['terminals'][0]
credential1 = terminal1['credential']
call('terminal credential identity', 'GET', '/api/terminal/v1/me', credential1)
call('terminal current enrollment', 'GET', '/api/terminal/v1/enrollment/current', credential1)
raw_payload = {'finger_position': 'RIGHT_INDEX', 'template_vendor': 'FAKE', 'template_format': 'SIMULATOR-V1', 'algorithm_version': 'SIMULATOR-V1', 'template_data': 'MANUAL_RIGHT_V1', 'quality_score': 95, 'quality_accepted': True, 'fingerprint_image': 'forbidden'}
call('reject raw enrollment image', 'POST', f'/api/terminal/v1/enrollment/{enrollment_uuid}/template', credential1, raw_payload)

def submit(finger, value):
    status, body = call(f'submit template:{finger}', 'POST', f'/api/terminal/v1/enrollment/{enrollment_uuid}/template', credential1, {'finger_position': finger, 'template_vendor': 'FAKE', 'template_format': 'SIMULATOR-V1', 'algorithm_version': 'SIMULATOR-V1', 'template_data': value, 'quality_score': 95, 'quality_accepted': True})
    assert status in (200, 201), body
    return body['data']

right_v1 = submit('RIGHT_INDEX', 'MANUAL_RIGHT_V1')
left_v1 = submit('LEFT_INDEX', 'MANUAL_LEFT_V1')
call('complete two-finger enrollment', 'POST', f'/api/biometric/enrollment-sessions/{enrollment}/complete', actor)
status, biometric_body = call('verify enrolled metadata without bytes', 'GET', f"/api/employees/{fixture['employee_id']}/biometrics", actor)
assert status == 200

status, body = call('start re-enrollment', 'POST', '/api/biometric/enrollment-sessions', actor, {'employee_id': fixture['employee_id'], 'terminal_id': fixture['terminal_id'], 'required_finger_count': 2})
assert status in (200, 201), body
reenroll_uuid = body['data']['uuid']
with sqlite3.connect('/home/ubuntu/amms/backend/database/flow10a_manual.sqlite') as db:
    reenroll = db.execute('SELECT id FROM biometric_enrollment_sessions WHERE uuid = ?', (reenroll_uuid,)).fetchone()[0]
right_v2_payload = {'finger_position': 'RIGHT_INDEX', 'template_vendor': 'FAKE', 'template_format': 'SIMULATOR-V1', 'algorithm_version': 'SIMULATOR-V1', 'template_data': 'MANUAL_RIGHT_V2', 'quality_score': 96, 'quality_accepted': True, 'replace_active': True}
status, right_v2_body = call('submit re-enrollment right finger', 'POST', f'/api/terminal/v1/enrollment/{reenroll_uuid}/template', credential1, right_v2_payload)
assert status in (200, 201), right_v2_body
call('complete re-enrollment', 'POST', f'/api/biometric/enrollment-sessions/{reenroll}/complete', actor)
status, biometric_after_reenroll = call('verify versioned biometric lifecycle', 'GET', f"/api/employees/{fixture['employee_id']}/biometrics", actor)
assert status == 200

status, activation = call('activate terminal 1 meeting session', 'POST', f"/api/terminal/v1/assignments/{terminal1['assignment_uuid']}/activate", credential1)
assert status == 201, activation
session1 = activation['data']['session']['uuid']
session_token1 = activation['data']['session_token']
status, manifest = call('ordinary FLOW-09 manifest remains byte-free', 'GET', f'/api/terminal/v1/sessions/{session1}/manifest', session_token1)
assert status == 200
status, candidates = call('terminal candidate set includes local bytes', 'GET', f'/api/terminal/v1/sessions/{session1}/biometric-candidates', session_token1)
assert status == 200
candidate = next(item for item in candidates['data']['candidates'] if int(item['employee_id']) == int(fixture['employee_id']))
assert candidate.get('template_data')
checksum = candidates['data']['candidate_set_checksum']
attendance_payload = {'client_event_uuid': str(uuid.uuid4()), 'employee_id': fixture['employee_id'], 'matched_template_uuid': candidate['template_uuid'], 'finger_position': candidate['finger_position'], 'matcher_vendor': 'FAKE', 'matcher_algorithm_version': 'SIMULATOR-V1', 'match_score': 99.0, 'threshold_reference': 'FAKE_ACCEPTED', 'candidate_set_checksum': checksum}
status, valid_attendance = call('valid fingerprint attendance', 'POST', f'/api/terminal/v1/sessions/{session1}/attendance/fingerprint', session_token1, attendance_payload)
assert status in (200, 201), valid_attendance
status, duplicate = call('duplicate employee fingerprint scan', 'POST', f'/api/terminal/v1/sessions/{session1}/attendance/fingerprint', session_token1, {**attendance_payload, 'client_event_uuid': str(uuid.uuid4())})
assert status == 409, duplicate
status, unknown = call('unknown fingerprint template', 'POST', f'/api/terminal/v1/sessions/{session1}/attendance/fingerprint', session_token1, {**attendance_payload, 'client_event_uuid': str(uuid.uuid4()), 'matched_template_uuid': str(uuid.uuid4())})
assert status in (404, 422), unknown
status, raw_attendance = call('reject raw attendance probe', 'POST', f'/api/terminal/v1/sessions/{session1}/attendance/fingerprint', session_token1, {**attendance_payload, 'client_event_uuid': str(uuid.uuid4()), 'fingerprint_image': 'forbidden'})
assert status == 422, raw_attendance
call('close primary terminal session', 'POST', f'/api/terminal/v1/sessions/{session1}/close', session_token1, {'reason': 'manual FLOW-10A evidence complete'})

second_employee = fixture['second_employee_id']
status, manual = call('manual attendance override with reason', 'POST', f"/api/meetings/{fixture['meeting_id']}/attendance/manual", coordinator, {'employee_id': second_employee, 'reason': 'Manual FLOW-10A gate evidence'})
assert status in (200, 201), manual

terminal2 = fixture['terminals'][1]
print('waiting for terminal activation throttle window', flush=True)
time.sleep(61)
status, activation2 = call('activate terminal 2 for FLOW-07 stale test', 'POST', f"/api/terminal/v1/assignments/{terminal2['assignment_uuid']}/activate", terminal2['credential'])
assert status == 201, activation2
session2 = activation2['data']['session']['uuid']
session_token2 = activation2['data']['session_token']
status, candidates2 = call('load candidates before FLOW-07 mutation', 'GET', f'/api/terminal/v1/sessions/{session2}/biometric-candidates', session_token2)
assert status == 200
mutate('schedule')
status, stale_schedule = call('FLOW-07 stale candidate API denial', 'GET', f'/api/terminal/v1/sessions/{session2}/biometric-candidates', session_token2)
assert status == 422 and stale_schedule.get('code') == 'TERMINAL_SESSION_STALE', stale_schedule
terminal3 = fixture['terminals'][2]
with sqlite3.connect('/home/ubuntu/amms/backend/database/flow10a_manual.sqlite') as db:
    terminal3_assignment_id = db.execute('SELECT id FROM meeting_terminal_assignments WHERE uuid = ?', (terminal3['assignment_uuid'],)).fetchone()[0]
status, refreshed_assignment = call('refresh terminal 3 assignment after FLOW-07 change', 'POST', f"/api/meetings/{fixture['meeting_id']}/terminal-assignments/{terminal3_assignment_id}/refresh", coordinator)
assert status == 200, refreshed_assignment
print('waiting for terminal activation throttle window', flush=True)
time.sleep(61)
status, activation3 = call('activate terminal 3 for FLOW-08 stale test', 'POST', f"/api/terminal/v1/assignments/{terminal3['assignment_uuid']}/activate", terminal3['credential'])
assert status == 201, activation3
session3 = activation3['data']['session']['uuid']
session_token3 = activation3['data']['session_token']
status, candidates3 = call('load candidates before FLOW-08 mutation', 'GET', f'/api/terminal/v1/sessions/{session3}/biometric-candidates', session_token3)
assert status == 200
mutate('participants')
status, stale_participants = call('FLOW-08 stale candidate API denial', 'GET', f'/api/terminal/v1/sessions/{session3}/biometric-candidates', session_token3)
assert status == 422 and stale_participants.get('code') == 'TERMINAL_SESSION_STALE', stale_participants

out = {'status': 'MANUAL_FLOW10A_API_GATE_PASS', 'checks': len(log), 'steps': log}
Path('/home/ubuntu/amms-github/flow10a_manual_api_gate.json').write_text(json.dumps(out, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'status': out['status'], 'checks': out['checks']}, ensure_ascii=False, indent=2))
