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
    p = r.json()
    token = p.get('data', {}).get('token') or p.get('token')
    assert token, p
    s.headers.update({'Authorization': 'Bearer ' + token, 'Accept': 'application/json'})
    return s

def call(email, method, path, expected, body=None):
    r = sess(email).request(method, BASE + path, json=body, timeout=30)
    p = r.json()
    assert r.status_code == expected, (path, r.status_code, p)
    return p.get('data', p)

def reviews(case):
    x = call(f['owner_email'], 'GET', f"/api/meeting-exceptions/{case['id']}", 200)
    return x.get('reviews') or x.get('current_version', {}).get('reviews') or []

resume_counter = 0

def apply_case(kind, resume=False):
    global resume_counter
    payload = {'case_type': kind, 'reason': f'شاهد پایدار {kind}.'}
    if kind == 'RESUMPTION':
        payload['replacement_expected'] = True
    case = call(f['owner_email'], 'POST', f"/api/meetings/{f['meeting_id']}/exceptions", 201, payload)
    call(f['owner_email'], 'POST', f"/api/meeting-exceptions/{case['id']}/submit", 200)
    for row in reviews(case):
        call(f['director_email'], 'POST', f"/api/meeting-exception-reviews/{row['id']}/approve", 200)
    if resume:
        resume_counter += 1
        call(f['owner_email'], 'POST', f"/api/meeting-exceptions/{case['id']}/resume", 200, {
            'meeting_date': (date.today() + timedelta(days=30 + resume_counter)).isoformat(),
            'start_time': '10:00', 'end_time': '11:00', 'location': 'تالار پایدار FLOW-13',
        })
    else:
        call(f['owner_email'], 'POST', f"/api/meeting-exceptions/{case['id']}/apply", 200)
    return case['id']

def state(label, expected):
    meeting = call(f['owner_email'], 'GET', f"/api/meetings/{f['meeting_id']}", 200)
    assert meeting['status'] == expected, (label, meeting)
    return {'label': label, 'status': meeting['status'], 'meeting_id': f['meeting_id'], 'current_schedule_version_id': meeting.get('current_schedule_version_id')}

sequence = [state('initial', 'SCHEDULED')]
post_id = apply_case('POSTPONEMENT')
sequence.append(state('postponement applied', 'POSTPONED'))
resume1_id = apply_case('RESUMPTION', True)
sequence.append(state('first resumption applied', 'SCHEDULED'))
cancel_id = apply_case('CANCELLATION')
sequence.append(state('cancellation applied', 'CANCELLED'))
reopen_id = apply_case('REOPENING')
sequence.append(state('reopening applied', 'POSTPONED'))
resume2_id = apply_case('RESUMPTION', True)
sequence.append(state('second resumption applied', 'SCHEDULED'))
print(json.dumps({'pass': len(sequence) == 6, 'meeting_id': f['meeting_id'], 'sequence': sequence, 'exception_case_ids': {'postponement': post_id, 'resumption_1': resume1_id, 'cancellation': cancel_id, 'reopening': reopen_id, 'resumption_2': resume2_id}}, ensure_ascii=False, indent=2))
