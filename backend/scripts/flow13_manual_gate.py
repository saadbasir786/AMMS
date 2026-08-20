#!/usr/bin/env python3
import json, os, sys
from datetime import date, timedelta
import requests

BASE = os.environ.get('AMMS_MANUAL_BASE_URL', 'http://127.0.0.1:8013').rstrip('/')
FIXTURE = os.environ.get('AMMS_FLOW13_FIXTURE', '/tmp/flow13_manual_fixture.json')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD', 'ChangeMe!2026')
with open(FIXTURE, encoding='utf-8') as fh: fixture = json.load(fh)

transcript = []
def call(label, email, method, path, expected, body=None):
    session = requests.Session()
    login = session.post(BASE + '/api/login', json={'email': email, 'password': PASSWORD}, timeout=20)
    login_payload = login.json()
    token = login_payload.get('data', {}).get('token') or login_payload.get('token')
    if not token:
        raise AssertionError(f'{label}: login failed for {email}: {login_payload}')
    session.headers.update({'Authorization': f'Bearer {token}', 'Accept': 'application/json'})
    response = session.request(method, BASE + path, json=body, timeout=30)
    try: payload = response.json()
    except Exception: payload = {'raw': response.text[:500]}
    row = {'label': label, 'email': email, 'method': method, 'path': path, 'status': response.status_code, 'expected': expected, 'pass': response.status_code == expected, 'body': payload}
    transcript.append(row)
    if not row['pass']:
        raise AssertionError(f'{label}: expected {expected}, got {response.status_code}: {payload}')
    return payload

def data(payload): return payload.get('data', payload)

meeting_id = fixture['meeting_id']
owner = fixture['owner_email']
director = fixture['director_email']
case = data(call('owner creates postponement case', owner, 'POST', f'/api/meetings/{meeting_id}/exceptions', 201, {'case_type':'POSTPONEMENT','reason':'تعویق رسمی برای تکمیل هماهنگی اداری.'}))
call('High Authority cannot create business exception', 'high.authority@example.test', 'POST', f'/api/meetings/{meeting_id}/exceptions', 403, {'case_type':'CANCELLATION','reason':'تلاش خارج از حوزه.'})
call('SYSTEM_ADMIN cannot create business exception', 'system.admin@example.test', 'POST', f'/api/meetings/{meeting_id}/exceptions', 403, {'case_type':'CANCELLATION','reason':'تلاش خارج از حوزه.'})
case_id = case['id']
call('owner submits frozen version', owner, 'POST', f'/api/meeting-exceptions/{case_id}/submit', 200)
case = data(call('owner reads version-specific review', owner, 'GET', f'/api/meeting-exceptions/{case_id}', 200))
reviews = case.get('reviews') or case.get('current_version', {}).get('reviews') or []
review = next((item for item in reviews if item.get('status') == 'PENDING'), None)
if review is None: raise AssertionError(f'No pending review in {case}')
call('wrong authority actor cannot approve unit review', 'authority.office@example.test', 'POST', f"/api/meeting-exception-reviews/{review['id']}/approve", 403)
call('scoped Director approves unit review', director, 'POST', f"/api/meeting-exception-reviews/{review['id']}/approve", 200)
call('owner applies postponement', owner, 'POST', f'/api/meeting-exceptions/{case_id}/apply', 200)
meeting = data(call('meeting is POSTPONED', owner, 'GET', f'/api/meetings/{meeting_id}', 200))
if meeting.get('status') != 'POSTPONED': raise AssertionError(f'Expected POSTPONED, got {meeting}')
baseline = fixture['baseline_schedule_version_id']
resume = data(call('owner creates resumption case', owner, 'POST', f'/api/meetings/{meeting_id}/exceptions', 201, {'case_type':'RESUMPTION','reason':'تعیین زمان جدید برای برگزاری جلسه.','replacement_expected':True}))
resume_id = resume['id']
call('owner submits resumption version', owner, 'POST', f'/api/meeting-exceptions/{resume_id}/submit', 200)
resume = data(call('owner reads resumption review', owner, 'GET', f'/api/meeting-exceptions/{resume_id}', 200))
review = next(item for item in (resume.get('reviews') or resume.get('current_version', {}).get('reviews') or []) if item.get('status') == 'PENDING')
call('Director approves resumption review', director, 'POST', f"/api/meeting-exception-reviews/{review['id']}/approve", 200)
new_date = (date.today() + timedelta(days=20)).isoformat()
call('owner resumes with exact new schedule', owner, 'POST', f'/api/meeting-exceptions/{resume_id}/resume', 200, {'meeting_date':new_date,'start_time':'10:00','end_time':'11:00','location':'تالار جدید FLOW-13'})
meeting = data(call('meeting is SCHEDULED after resumption', owner, 'GET', f'/api/meetings/{meeting_id}', 200))
if meeting.get('status') != 'SCHEDULED': raise AssertionError(f'Expected SCHEDULED, got {meeting}')
if int(meeting.get('current_schedule_version_id', baseline)) == int(baseline): raise AssertionError('Resumption did not create a new current schedule version')
cancel = data(call('owner creates cancellation case', owner, 'POST', f'/api/meetings/{meeting_id}/exceptions', 201, {'case_type':'CANCELLATION','reason':'لغو نهایی به دلیل تصمیم اداری جدید.'}))
cancel_id = cancel['id']
call('owner submits cancellation', owner, 'POST', f'/api/meeting-exceptions/{cancel_id}/submit', 200)
cancel = data(call('owner reads cancellation review', owner, 'GET', f'/api/meeting-exceptions/{cancel_id}', 200))
review = next(item for item in (cancel.get('reviews') or cancel.get('current_version', {}).get('reviews') or []) if item.get('status') == 'PENDING')
call('Director approves cancellation', director, 'POST', f"/api/meeting-exception-reviews/{review['id']}/approve", 200)
call('owner applies cancellation', owner, 'POST', f'/api/meeting-exceptions/{cancel_id}/apply', 200)
meeting = data(call('meeting is CANCELLED', owner, 'GET', f'/api/meetings/{meeting_id}', 200))
if meeting.get('status') != 'CANCELLED': raise AssertionError(f'Expected CANCELLED, got {meeting}')
reopen = data(call('owner creates reopening case', owner, 'POST', f'/api/meetings/{meeting_id}/exceptions', 201, {'case_type':'REOPENING','reason':'بازگشایی رسمی جلسهٔ لغوشده برای ادامهٔ روند اداری.'}))
reopen_id = reopen['id']
call('owner submits reopening', owner, 'POST', f'/api/meeting-exceptions/{reopen_id}/submit', 200)
reopen = data(call('owner reads reopening review', owner, 'GET', f'/api/meeting-exceptions/{reopen_id}', 200))
review = next(item for item in (reopen.get('reviews') or reopen.get('current_version', {}).get('reviews') or []) if item.get('status') == 'PENDING')
call('Director approves reopening', director, 'POST', f"/api/meeting-exception-reviews/{review['id']}/approve", 200)
call('owner applies reopening', owner, 'POST', f'/api/meeting-exceptions/{reopen_id}/apply', 200)
meeting = data(call('meeting is POSTPONED after reopening', owner, 'GET', f'/api/meetings/{meeting_id}', 200))
if meeting.get('status') != 'POSTPONED': raise AssertionError(f'Expected POSTPONED after reopening, got {meeting}')
summary = {'pass': all(row['pass'] for row in transcript), 'steps': len(transcript), 'meeting_id': meeting_id, 'baseline_schedule_version_id': baseline, 'final_status': meeting.get('status'), 'transcript': transcript}
print(json.dumps(summary, ensure_ascii=False, indent=2))
