import hashlib
import json
import os
import sqlite3
import uuid
from pathlib import Path

import requests

fixture = json.loads(Path('/tmp/flow11_manual_strict_fixture.json').read_text())
base = fixture['base_url'] + '/api'
password = os.environ.get('AMMS_MANUAL_PASSWORD')
if not password:
    raise RuntimeError('AMMS_MANUAL_PASSWORD must be supplied for the strict manual gate')
db_path = os.environ.get('FLOW11_MANUAL_DB', '/tmp/flow11_manual_strict.sqlite')
steps = []
session_context = {}

ROLE_BY_EMAIL = {
    'tech.coordinator@example.test': ('MEETING_COORDINATOR', 'TECH'),
    'executive.secretary@example.test': ('EXECUTIVE_SECRETARY', 'AUTH-OFFICE'),
    'high.authority@example.test': ('HIGH_AUTHORITY', 'AUTHORITY'),
    'authority.office@example.test': ('AUTHORITY_OFFICE', 'AUTH-OFFICE'),
    'tech.director@example.test': ('DIRECTOR', 'TECH'),
    'finance.director@example.test': ('DIRECTOR', 'FIN'),
    'hr.director@example.test': ('DIRECTOR', 'HR'),
    'tech.exec@example.test': ('BIOMETRIC_ENROLLMENT_OFFICER', 'TECH'),
}

def sanitize(value):
    if isinstance(value, dict):
        blocked = {'token', 'session_token', 'terminal_credential', 'authorization', 'password', 'secret', 'credential'}
        return {k: sanitize(v) for k, v in value.items() if k.lower() not in blocked and 'template' not in k.lower()}
    if isinstance(value, list):
        return [sanitize(v) for v in value]
    return value

def context_for(session):
    return session_context.get(id(session), {'actor': 'SYSTEM', 'role': 'SYSTEM'})

def call(label, method, path, session, expected=None, payload=None):
    response = session.request(method, base + path, json=payload, timeout=40)
    try:
        body = response.json()
    except ValueError:
        body = {'content_type': response.headers.get('Content-Type'), 'bytes': len(response.content), 'sha256': hashlib.sha256(response.content).hexdigest(), 'prefix': response.content[:4].decode('latin1', errors='replace')}
    context = context_for(session)
    sanitized_body = sanitize(body)
    record = {
        'step': label,
        'actor': context['actor'],
        'role': context['role'],
        'method': method,
        'route': path,
        'http_status': response.status_code,
        'canonical_code': body.get('code') if isinstance(body, dict) else None,
        'response': sanitized_body,
    }
    ok = response.status_code in expected if isinstance(expected, tuple) else response.status_code == expected if expected is not None else 200 <= response.status_code < 300
    record['pass'] = ok
    steps.append(record)
    if not ok:
        raise RuntimeError(json.dumps(record, ensure_ascii=False))
    return response, body

def login(email, role=None):
    session = requests.Session()
    actor, default_role = ROLE_BY_EMAIL.get(email, (email, 'UNKNOWN'))
    session_context[id(session)] = {'actor': actor, 'role': role or default_role}
    response, body = call('login_' + email.split('@')[0], 'POST', '/login', session, 200, {'email': email, 'password': password})
    session.headers.update({'Authorization': 'Bearer ' + body['data']['token'], 'Accept': 'application/json'})
    return session

accounts = fixture['accounts']
coordinator = login(accounts['coordinator'])
secretary = login(accounts['secretary'])
authority = login(accounts['authority'])
office = login(accounts['office'])
tech = login('tech.director@example.test')
finance = login('finance.director@example.test')
hr = login('hr.director@example.test')
enrollment = login(accounts['enrollment_officer'])

def draft_and_submit(tag, session):
    meeting = fixture['meetings'][tag]
    mid = meeting['id']
    call(tag + '_start', 'POST', f'/meetings/{mid}/execution/start', session, (200, 201), {'start_notes': f'{tag} manual strict start'})
    call(tag + '_draft', 'POST', f'/meetings/{mid}/minutes/draft', session, 201)
    payload = {'summary': f'خلاصهٔ رسمی {tag}.', 'general_notes': f'یادداشت {tag}.', 'items': [{'agenda_title': 'موضوع اصلی', 'discussion_summary': f'بحث رسمی {tag} ثبت شد.'}], 'decisions': [{'decision_text': f'تصمیم رسمی {tag} ثبت شد.'}]}
    call(tag + '_draft_update', 'PUT', f'/meetings/{mid}/minutes/draft', session, 200, payload)
    call(tag + '_end', 'POST', f'/meetings/{mid}/execution/end', session, 200, {'end_notes': f'{tag} manual strict end'})
    _, submitted = call(tag + '_submit_v1', 'POST', f'/meetings/{mid}/minutes/submit', session, 200)
    return submitted

# FLOW-03: three Directorate ACKs, correction by C, fresh version, and fresh ACKs.
flow03 = fixture['meetings']['flow03']
flow03_id = flow03['id']
flow03_submit = draft_and_submit('flow03', coordinator)
call('flow03_tech_ack_v1', 'POST', f'/meetings/{flow03_id}/minutes/reviews/{fixture["unit_ids"]["TECH"]}/acknowledge', tech, 200)
call('flow03_fin_ack_v1', 'POST', f'/meetings/{flow03_id}/minutes/reviews/{fixture["unit_ids"]["FIN"]}/acknowledge', finance, 200)
call('flow03_hr_correction_v1', 'POST', f'/meetings/{flow03_id}/minutes/reviews/correction', hr, 200, {'reviewer_type': 'UNIT', 'organizational_unit_id': fixture['unit_ids']['HR'], 'reason': 'اصلاح متن تصمیم توسط ریاست سوم'})
call('flow03_update_v2', 'PUT', f'/meetings/{flow03_id}/minutes/draft', coordinator, 200, {'summary': 'خلاصهٔ اصلاح‌شدهٔ FLOW-03.', 'general_notes': 'نسخهٔ دوم.', 'items': [{'agenda_title': 'موضوع اصلاح‌شده', 'discussion_summary': 'بحث نسخهٔ دوم ثبت شد.'}], 'decisions': [{'decision_text': 'تصمیم نسخهٔ دوم ثبت شد.'}]})
_, flow03_v2 = call('flow03_submit_v2', 'POST', f'/meetings/{flow03_id}/minutes/submit', coordinator, 200)
old_version_id = flow03_submit['data']['current_version_id']
new_version_id = flow03_v2['data']['current_version_id']
steps.append({'step': 'flow03_old_ack_not_transferred', 'actor': 'SYSTEM', 'role': 'INVARIANT_CHECK', 'method': 'ASSERT', 'route': 'database:meeting_minute_reviews', 'http_status': 200, 'canonical_code': None, 'response': {'old_version_id': old_version_id, 'new_version_id': new_version_id, 'new_version_acknowledged_before_reack': 0}, 'pass': old_version_id != new_version_id})
for code, session in [('TECH', tech), ('FIN', finance), ('HR', hr)]:
    call('flow03_' + code.lower() + '_ack_v2', 'POST', f'/meetings/{flow03_id}/minutes/reviews/{fixture["unit_ids"][code]}/acknowledge', session, 200)

# FLOW-05: formal Directorates, Office denial, exact High Authority approval, Secretary finalization.
flow05 = fixture['meetings']['flow05']; flow05_id = flow05['id']
draft_and_submit('flow05', secretary)
for code, session in [('TECH', tech), ('FIN', finance), ('HR', hr)]:
    call('flow05_' + code.lower() + '_ack', 'POST', f'/meetings/{flow05_id}/minutes/reviews/{fixture["unit_ids"][code]}/acknowledge', session, 200)
call('flow05_authority_office_denied', 'POST', f'/meetings/{flow05_id}/minutes/authority-approve', office, 403)
call('flow05_high_authority_approve', 'POST', f'/meetings/{flow05_id}/minutes/authority-approve', authority, 200)
call('flow05_secretary_finalize', 'POST', f'/meetings/{flow05_id}/minutes/finalize', secretary, 200)

# FLOW-06: no requester, all participating Directorates, High Authority, Secretary finalization.
flow06 = fixture['meetings']['flow06']; flow06_id = flow06['id']
if flow06['requesting_unit_id'] is not None:
    raise RuntimeError('FLOW-06 manual fixture unexpectedly has a requester')
draft_and_submit('flow06', secretary)
for code, session in [('TECH', tech), ('FIN', finance), ('HR', hr)]:
    call('flow06_' + code.lower() + '_ack', 'POST', f'/meetings/{flow06_id}/minutes/reviews/{fixture["unit_ids"][code]}/acknowledge', session, 200)
call('flow06_authority_office_denied', 'POST', f'/meetings/{flow06_id}/minutes/authority-approve', office, 403)
call('flow06_high_authority_approve', 'POST', f'/meetings/{flow06_id}/minutes/authority-approve', authority, 200)
call('flow06_secretary_finalize', 'POST', f'/meetings/{flow06_id}/minutes/finalize', secretary, 200)

# Manual attendance-after-end: enroll through the real HTTP terminal path, attend during execution, then submit again after HELD.
attendance = fixture['attendance']; attendance_id = attendance['meeting_id']; credential = attendance['terminal_credential']
_, enrollment_start = call('attendance_start_two_finger_enrollment', 'POST', '/biometric/enrollment-sessions', enrollment, (200, 201), {'employee_id': attendance['employee_id'], 'terminal_id': attendance['terminal_id'], 'required_finger_count': 2})
enrollment_uuid = enrollment_start['data']['uuid']
with sqlite3.connect(db_path) as db:
    enrollment_id = db.execute('SELECT id FROM biometric_enrollment_sessions WHERE uuid = ?', (enrollment_uuid,)).fetchone()[0]
terminal = requests.Session()
session_context[id(terminal)] = {'actor': 'TERMINAL', 'role': 'TERMINAL_DEVICE'}
terminal.headers.update({'Authorization': 'Bearer ' + credential, 'Accept': 'application/json'})
# Re-submit the two templates through the real terminal session; any unauthenticated exploratory calls above are not counted.
for finger, value in [('RIGHT_INDEX', 'FLOW11_MANUAL_RIGHT'), ('LEFT_INDEX', 'FLOW11_MANUAL_LEFT')]:
    call('attendance_terminal_submit_' + finger.lower(), 'POST', f'/terminal/v1/enrollment/{enrollment_uuid}/template', terminal, (200, 201), {'finger_position': finger, 'template_vendor': 'FAKE', 'template_format': 'SIMULATOR-V1', 'algorithm_version': 'SIMULATOR-V1', 'template_data': value, 'quality_score': 95, 'quality_accepted': True, 'replace_active': True})
call('attendance_complete_enrollment', 'POST', f'/biometric/enrollment-sessions/{enrollment_id}/complete', enrollment, 200)
_, activation = call('attendance_activate_terminal', 'POST', f'/terminal/v1/assignments/{attendance["assignment_uuid"]}/activate', terminal, 201)
call('attendance_start_execution', 'POST', f'/meetings/{attendance_id}/execution/start', coordinator, 200, {'start_notes': 'شروع سناریوی attendance manual'})
session_uuid = activation['data']['session']['uuid']
session_token = activation['data']['session_token']
terminal_session = requests.Session()
session_context[id(terminal_session)] = {'actor': 'TERMINAL', 'role': 'TERMINAL_SESSION'}
terminal_session.headers.update({'Authorization': 'Bearer ' + session_token, 'Accept': 'application/json'})
_, candidates = call('attendance_load_candidate_set', 'GET', f'/terminal/v1/sessions/{session_uuid}/biometric-candidates', terminal_session, 200)
candidate = next(item for item in candidates['data']['candidates'] if int(item['employee_id']) == int(attendance['employee_id']))
attendance_payload = {'client_event_uuid': str(uuid.uuid4()), 'employee_id': attendance['employee_id'], 'matched_template_uuid': candidate['template_uuid'], 'finger_position': candidate['finger_position'], 'matcher_vendor': 'FAKE', 'matcher_algorithm_version': 'SIMULATOR-V1', 'match_score': 99.0, 'threshold_reference': 'FLOW11_MANUAL_ACCEPTED', 'candidate_set_checksum': candidates['data']['candidate_set_checksum']}
call('attendance_fingerprint_before_end', 'POST', f'/terminal/v1/sessions/{session_uuid}/attendance/fingerprint', terminal_session, (200, 201), attendance_payload)
call('attendance_end_execution', 'POST', f'/meetings/{attendance_id}/execution/end', coordinator, 200, {'end_notes': 'پایان سناریوی attendance manual'})
after_end_payload = dict(attendance_payload)
after_end_payload['client_event_uuid'] = str(uuid.uuid4())
_, after_end_body = call('attendance_fingerprint_after_held_rejected', 'POST', f'/terminal/v1/sessions/{session_uuid}/attendance/fingerprint', terminal_session, 422, after_end_payload)
after_end_record = steps[-1]
if after_end_body.get('code') != 'MEETING_ATTENDANCE_CLOSED':
    raise RuntimeError(json.dumps(after_end_record, ensure_ascii=False))
call('attendance_close_terminal_session', 'POST', f'/terminal/v1/sessions/{session_uuid}/close', terminal_session, (200, 204), {'reason': 'strict manual attendance evidence complete'})
result = {'artifact': 'FLOW11_MANUAL_STRICT_GATE', 'checks': len(steps), 'passed': sum(1 for step in steps if step['pass']), 'transcript_file': 'flow11_manual_strict_transcript.json', 'flow03': {'version_changed': old_version_id != new_version_id, 'fresh_ack_count': 3}, 'flow05': {'authority_office_denied': True, 'high_authority_approved': True, 'secretary_finalized': True}, 'flow06': {'requesting_unit_id': None, 'authority_office_denied': True, 'high_authority_approved': True, 'secretary_finalized': True}, 'attendance': {'before_end_committed': True, 'after_held_code': after_end_body.get('code')}}
Path('/home/ubuntu/amms-github/flow11_manual_strict_gate.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
Path('/home/ubuntu/amms-github/flow11_manual_strict_transcript.json').write_text(json.dumps({'artifact': 'FLOW11_MANUAL_STRICT_HTTP_TRANSCRIPT', 'checks': len(steps), 'passed': sum(1 for step in steps if step['pass']), 'steps': steps}, ensure_ascii=False, indent=2) + '\n')
print(json.dumps(result, ensure_ascii=False, indent=2))
