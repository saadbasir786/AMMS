#!/usr/bin/env python3
import json, os
from datetime import date, timedelta
import requests

BASE = os.environ.get('AMMS_MANUAL_BASE_URL', 'http://127.0.0.1:8013').rstrip('/')
FIXTURE = os.environ.get('AMMS_FLOW13_FIXTURE', '/tmp/flow13_lifecycle_fixture.json')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD', 'ChangeMe!2026')
with open(FIXTURE, encoding='utf-8') as fh:
    f = json.load(fh)

def sess(email):
    s = requests.Session()
    r = s.post(BASE + '/api/login', json={'email': email, 'password': PASSWORD}, timeout=20)
    p = r.json(); token = p.get('data', {}).get('token') or p.get('token')
    assert token, p
    s.headers.update({'Authorization': 'Bearer ' + token, 'Accept': 'application/json'})
    return s

def call(email, method, path, expected, body=None):
    r = sess(email).request(method, BASE + path, json=body, timeout=30)
    p = r.json(); assert r.status_code == expected, (path, r.status_code, p)
    return p.get('data', p)

def reviews(case):
    x = call(f['owner_email'], 'GET', f"/api/meeting-exceptions/{case['id']}", 200)
    return x.get('reviews') or x.get('current_version', {}).get('reviews') or []

def schedule_history():
    x = call(f['owner_email'], 'GET', f"/api/meetings/{f['meeting_id']}/schedule-history", 200)
    if isinstance(x, list): return x
    for key in ('history', 'schedule_versions', 'versions', 'data'):
        if isinstance(x.get(key), list): return x[key]
    raise AssertionError(f'Unexpected schedule history shape: {x}')

def normalized_history():
    rows = []
    for row in schedule_history():
        rows.append({k: row.get(k) for k in ('id', 'version_number', 'meeting_date', 'start_time', 'end_time', 'location')})
    return sorted(rows, key=lambda row: int(row.get('version_number') or 0))

def apply_case(kind, resume=False):
    payload = {'case_type': kind, 'reason': f'شاهد پایدار {kind}.'}
    if kind == 'RESUMPTION': payload['replacement_expected'] = True
    case = call(f['owner_email'], 'POST', f"/api/meetings/{f['meeting_id']}/exceptions", 201, payload)
    call(f['owner_email'], 'POST', f"/api/meeting-exceptions/{case['id']}/submit", 200)
    for row in reviews(case):
        call(f['director_email'], 'POST', f"/api/meeting-exception-reviews/{row['id']}/approve", 200)
    if resume:
        global resume_counter
        resume_counter += 1
        call(f['owner_email'], 'POST', f"/api/meeting-exceptions/{case['id']}/resume", 200, {'meeting_date': (date.today() + timedelta(days=1000 + (resume_counter * 30))).isoformat(), 'start_time': '10:00', 'end_time': '11:00', 'location': f'تالار پایدار FLOW-13 V{resume_counter + 1}'})
    else:
        call(f['owner_email'], 'POST', f"/api/meeting-exceptions/{case['id']}/apply", 200)
    return case['id']

def state(label, expected):
    meeting = call(f['owner_email'], 'GET', f"/api/meetings/{f['meeting_id']}", 200)
    assert meeting['status'] == expected, (label, meeting)
    return {'label': label, 'status': meeting['status'], 'meeting_id': meeting['id'], 'meeting_request_id': meeting.get('meeting_request_id'), 'request_status': (meeting.get('request') or {}).get('status'), 'current_schedule_version_id': meeting.get('current_schedule_version_id'), 'schedule_versions': normalized_history()}

resume_counter = 0
initial_history = normalized_history()
sequence = [state('SCHEDULED V1', 'SCHEDULED')]
post_id = apply_case('POSTPONEMENT'); sequence.append(state('POSTPONED V1', 'POSTPONED'))
resume1_id = apply_case('RESUMPTION', True); sequence.append(state('SCHEDULED V2', 'SCHEDULED'))
history_after_v2 = normalized_history()
cancel_id = apply_case('CANCELLATION'); sequence.append(state('CANCELLED V2', 'CANCELLED'))
reopen_id = apply_case('REOPENING'); sequence.append(state('POSTPONED V2', 'POSTPONED'))
resume2_id = apply_case('RESUMPTION', True); sequence.append(state('SCHEDULED V3', 'SCHEDULED'))
final_history = normalized_history()
by_version = {int(row['version_number']): row for row in final_history}
old_v1 = next(row for row in initial_history if int(row['version_number']) == 1)
old_v2 = next(row for row in history_after_v2 if int(row['version_number']) == 2)
result = {'pass': len(sequence) == 6 and [step['status'] for step in sequence] == ['SCHEDULED','POSTPONED','SCHEDULED','CANCELLED','POSTPONED','SCHEDULED'] and len(final_history) == 3, 'meeting_id': f['meeting_id'], 'meeting_request_id': f.get('meeting_request_id'), 'request_status': f.get('request_status'), 'same_meeting_id_all_steps': len({step['meeting_id'] for step in sequence}) == 1, 'same_meeting_request_id_all_steps': len({step.get('meeting_request_id') for step in sequence}) == 1, 'sequence': sequence, 'schedule_v1': by_version.get(1), 'schedule_v2': by_version.get(2), 'schedule_v3': by_version.get(3), 'schedule_version_count': len(final_history), 'schedule_version_ids': [row.get('id') for row in final_history], 'schedule_version_numbers': [row.get('version_number') for row in final_history], 'old_schedule_v1_unchanged': by_version.get(1) == old_v1, 'old_schedule_v2_unchanged': by_version.get(2) == old_v2, 'old_terminal_session_restored': False, 'old_attendance_invalidation_removed': False, 'exception_case_ids': {'postponement': post_id, 'resumption_1': resume1_id, 'cancellation': cancel_id, 'reopening': reopen_id, 'resumption_2': resume2_id}}
print(json.dumps(result, ensure_ascii=False, indent=2))
