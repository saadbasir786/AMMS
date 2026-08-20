#!/usr/bin/env python3
import json, os, sys
from datetime import date
import requests

BASE = os.environ.get('AMMS_MANUAL_BASE_URL', 'http://127.0.0.1:8013').rstrip('/')
FIXTURE = os.environ.get('AMMS_FLOW13_STRICT_FIXTURE', '/home/ubuntu/amms-github/flow13_strict_http_fixture.json')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD', 'ChangeMe!2026')
with open(FIXTURE, encoding='utf-8') as fh: fixture = json.load(fh)
transcript = []

def session(email):
    s = requests.Session()
    r = s.post(BASE + '/api/login', json={'email': email, 'password': PASSWORD}, timeout=20)
    payload = r.json()
    token = payload.get('data', {}).get('token') or payload.get('token')
    if not token: raise AssertionError(f'login failed for {email}: {payload}')
    s.headers.update({'Authorization': f'Bearer {token}', 'Accept': 'application/json'})
    return s

def call(label, email, method, path, expected, body=None, headers=None):
    r = session(email).request(method, BASE + path, json=body, headers=headers or {}, timeout=30)
    try: payload = r.json()
    except Exception: payload = {'raw': r.text[:500]}
    expects = expected if isinstance(expected, list) else [expected]
    row = {'label': label, 'email': email, 'method': method, 'path': path, 'status': r.status_code, 'expected': expects, 'pass': r.status_code in expects, 'body': payload}
    transcript.append(row)
    if not row['pass']: raise AssertionError(f'{label}: expected {expects}, got {r.status_code}: {payload}')
    return payload

def data(p): return p.get('data', p)
def reviews(case):
    x = data(call('read exception case', 'tech.coordinator@example.test', 'GET', f"/api/meeting-exceptions/{case['id']}", 200))
    return x, x.get('reviews') or x.get('current_version', {}).get('reviews') or []
def by_type(rows, typ, unit=None):
    for row in rows:
        if row.get('reviewer_type') == typ and (unit is None or int(row.get('organizational_unit_id') or 0) == int(unit)):
            return row
    raise AssertionError(f'missing review {typ}/{unit}: {rows}')

def exception(meeting_id, owner, case_type='POSTPONEMENT'):
    c = data(call(f'{case_type} case created', owner, 'POST', f'/api/meetings/{meeting_id}/exceptions', 201, {'case_type':case_type,'reason':f'دلیل رسمی آزمون {case_type}.'}))
    call(f'{case_type} case submitted', owner, 'POST', f"/api/meeting-exceptions/{c['id']}/submit", 200)
    return c

owner = fixture['owner_email']; director = fixture['director_email']
finance = 'finance.director@example.test'; hr = 'hr.director@example.test'; secretary = 'executive.secretary@example.test'; high = 'high.authority@example.test'; office = 'authority.office@example.test'

# FLOW-03: V1 approvals, correction, draft V2, resubmission, fresh V2 approvals.
c = exception(fixture['multi_meeting_id'], owner)
_, rows = reviews(c)
v1 = {u: by_type(rows, 'UNIT', u) for u in (1,2,3)}
call('FLOW-03 V1 Technology approves', director, 'POST', f"/api/meeting-exception-reviews/{v1[1]['id']}/approve", 200)
call('FLOW-03 V1 Finance approves', finance, 'POST', f"/api/meeting-exception-reviews/{v1[2]['id']}/approve", 200)
call('FLOW-03 HR requests correction', hr, 'POST', f"/api/meeting-exception-reviews/{v1[3]['id']}/correction", 200, {'reason':'اصلاح نسخهٔ اول لازم است.'})
v2 = data(call('FLOW-03 owner creates V2 draft', owner, 'PUT', f"/api/meeting-exceptions/{c['id']}/draft", 200, {'reason':'نسخهٔ دوم اصلاح‌شده.'}))
if int(v2.get('current_version', {}).get('id', 0)) == int(c.get('current_version', {}).get('id', 0)): raise AssertionError('FLOW-03 did not create V2')
call('FLOW-03 V2 submitted with fresh reviews', owner, 'POST', f"/api/meeting-exceptions/{c['id']}/submit", 200)
_, rows = reviews(c)
if len(rows) != 3 or any(row.get('status') != 'PENDING' for row in rows): raise AssertionError(f'FLOW-03 V2 reviews not fresh: {rows}')
transcript.append({'label':'FLOW-03 V2 has three fresh pending reviews','pass':True,'status':200,'expected':[200],'body':{'review_count':len(rows)}})
for email, unit in ((director,1),(finance,2),(hr,3)):
    call(f'FLOW-03 V2 {unit} approves', email, 'POST', f"/api/meeting-exception-reviews/{by_type(rows,'UNIT',unit)['id']}/approve", 200)
call('FLOW-03 applies only after V2 approvals', owner, 'POST', f"/api/meeting-exceptions/{c['id']}/apply", 200)

# FLOW-05: all formal acknowledgements, exact High Authority, no office substitution.
c = exception(fixture['authority_meeting_id'], secretary)
_, rows = reviews(c)
for email, unit in ((director,1),(finance,2),(hr,3)):
    call(f'FLOW-05 unit {unit} acknowledges', email, 'POST', f"/api/meeting-exception-reviews/{by_type(rows,'UNIT',unit)['id']}/acknowledge", 200)
ha = by_type(rows, 'HIGH_AUTHORITY')
call('FLOW-05 Authority Office cannot substitute for High Authority', office, 'POST', f"/api/meeting-exception-reviews/{ha['id']}/approve", 403)
call('FLOW-05 exact High Authority approves', high, 'POST', f"/api/meeting-exception-reviews/{ha['id']}/approve", 200)
call('FLOW-05 Executive Secretary applies', secretary, 'POST', f"/api/meeting-exceptions/{c['id']}/apply", 200)

# FLOW-06: no requester review, three formal acknowledgements, exact High Authority approval.
c = exception(fixture['authority_meeting_id'], secretary, 'CANCELLATION')
_, rows = reviews(c)
if len(rows) != 4 or any(row.get('reviewer_type') == 'REQUESTING_UNIT' for row in rows): raise AssertionError(f'FLOW-06 requester review found: {rows}')
transcript.append({'label':'FLOW-06 has no requester review and has three unit plus one HA review','pass':True,'status':200,'expected':[200],'body':{'review_count':len(rows)}})
for email, unit in ((director,1),(finance,2),(hr,3)):
    call(f'FLOW-06 unit {unit} acknowledges', email, 'POST', f"/api/meeting-exception-reviews/{by_type(rows,'UNIT',unit)['id']}/acknowledge", 200)
ha = by_type(rows, 'HIGH_AUTHORITY')
call('FLOW-06 exact High Authority approves', high, 'POST', f"/api/meeting-exception-reviews/{ha['id']}/approve", 200)
call('FLOW-06 Executive Secretary applies cancellation', secretary, 'POST', f"/api/meeting-exceptions/{c['id']}/apply", 200)

# Attendance invalidation and terminal session revocation are proven on a dedicated meeting.
attendance_id = fixture['attendance_meeting_id']
call('attendance exists before cancellation', owner, 'POST', f'/api/meetings/{attendance_id}/attendance/manual', [200,201], {'employee_id':fixture['attendance_employee_id'],'reason':'ثبت رسمی پیش از لغو.'})
assignments_before = data(call('terminal assignment is ASSIGNED before cancellation', owner, 'GET', f'/api/meetings/{attendance_id}/terminal-assignments', 200))
assignment_rows = assignments_before if isinstance(assignments_before, list) else assignments_before.get('assignments', assignments_before.get('data', []))
if not any(int(row.get('id', row.get('assignment_id', 0))) == int(fixture['terminal_assignment_id']) and row.get('status') in ('ASSIGNED', 'ACTIVE') for row in assignment_rows):
    raise AssertionError(f'Expected ASSIGNED terminal assignment before cancellation: {assignments_before}')
transcript.append({'label':'terminal session is ACTIVE before cancellation','pass':True,'status':200,'expected':[200],'body':{'assignment_id':fixture['terminal_assignment_id'],'session_id':fixture['terminal_session_id'],'assignment_status':'ASSIGNED','session_status':'ACTIVE'}})
terminal_headers = {'Authorization': 'Bearer ' + fixture['terminal_session_token'], 'Accept':'application/json'}
pre_manifest = requests.get(BASE + f"/api/terminal/v1/sessions/{fixture['terminal_session_uuid']}/manifest", headers=terminal_headers, timeout=30)
pre_manifest_payload = pre_manifest.json()
if pre_manifest.status_code != 200: raise AssertionError({'label':'active terminal manifest before cancellation','status':pre_manifest.status_code,'body':pre_manifest_payload})
c = exception(attendance_id, owner, 'CANCELLATION')
_, rows = reviews(c)
call('attendance cancellation review approves', director, 'POST', f"/api/meeting-exception-reviews/{by_type(rows,'UNIT')['id']}/approve", 200)
call('cancellation revokes terminal and invalidates attendance', owner, 'POST', f"/api/meeting-exceptions/{c['id']}/apply", 200)
r = requests.get(BASE + f"/api/terminal/v1/sessions/{fixture['terminal_session_uuid']}/manifest", headers=terminal_headers, timeout=30)
try: p = r.json()
except Exception: p={'raw':r.text[:500]}
row={'label':'revoked terminal manifest is denied after cancellation','email':'terminal-session','method':'GET','path':f"/api/terminal/v1/sessions/{fixture['terminal_session_uuid']}/manifest",'status':r.status_code,'expected':[401],'pass':r.status_code==401,'body':p}; transcript.append(row)
if not row['pass']: raise AssertionError(row)
for method, suffix, label, body in [
    ('GET', 'biometric-candidates', 'revoked terminal biometric candidates are denied', None),
    ('POST', 'attendance/fingerprint', 'revoked terminal fingerprint attendance is denied', {'client_event_uuid':'00000000-0000-4000-8000-000000000013','employee_id':fixture['attendance_employee_id'],'matched_template_uuid':'00000000-0000-4000-8000-000000000014','candidate_set_checksum':'0'*64}),
]:
    r = requests.request(method, BASE + f"/api/terminal/v1/sessions/{fixture['terminal_session_uuid']}/{suffix}", headers=terminal_headers, json=body, timeout=30)
    try: p = r.json()
    except Exception: p={'raw':r.text[:500]}
    row={'label':label,'email':'terminal-session','method':method,'path':f"/api/terminal/v1/sessions/{fixture['terminal_session_uuid']}/{suffix}",'status':r.status_code,'expected':[401],'pass':r.status_code==401,'body':p}; transcript.append(row)
    if not row['pass']: raise AssertionError(row)
att = data(call('official attendance excludes invalidated row', owner, 'GET', f'/api/meetings/{attendance_id}/attendance', 200))
if int(att.get('official_count', -1)) != 0: raise AssertionError(f'official attendance was not invalidated: {att}')
transcript.append({'label':'official attendance count is zero after cancellation invalidation','pass':True,'status':200,'expected':[200],'body':{'official_count':att.get('official_count')}})
call('attendance after cancellation is rejected', owner, 'POST', f'/api/meetings/{attendance_id}/attendance/manual', 422, {'employee_id':fixture['attendance_employee_id'],'reason':'نباید ثبت شود.'})

# Completed meeting boundary: all four exception operations are denied and immutable FLOW-12 responses remain unchanged.
completed_id = fixture['completed_meeting_id']
completed_before = data(call('read COMPLETED meeting before exception attempts', owner, 'GET', f'/api/meetings/{completed_id}', 200))
minutes_before = data(call('read FINALIZED minutes before exception attempts', owner, 'GET', f'/api/meetings/{completed_id}/minutes', 200))
followup_before = data(call('read FLOW-12 history before exception attempts', owner, 'GET', f'/api/meetings/{completed_id}/follow-up', 200))
for case_type, body in [('POSTPONEMENT', {'case_type':'POSTPONEMENT','reason':'نباید ایجاد شود.'}), ('CANCELLATION', {'case_type':'CANCELLATION','reason':'نباید ایجاد شود.'}), ('REOPENING', {'case_type':'REOPENING','reason':'نباید ایجاد شود.'}), ('RESUMPTION', {'case_type':'RESUMPTION','reason':'نباید ایجاد شود.','replacement_expected':True})]:
    call(f'COMPLETED meeting rejects {case_type}', owner, 'POST', f'/api/meetings/{completed_id}/exceptions', 409, body)
completed_after = data(call('read COMPLETED meeting after exception attempts', owner, 'GET', f'/api/meetings/{completed_id}', 200))
minutes_after = data(call('read FINALIZED minutes after exception attempts', owner, 'GET', f'/api/meetings/{completed_id}/minutes', 200))
followup_after = data(call('read FLOW-12 history after exception attempts', owner, 'GET', f'/api/meetings/{completed_id}/follow-up', 200))
if completed_before.get('status') != 'COMPLETED' or completed_after.get('status') != 'COMPLETED': raise AssertionError('COMPLETED status changed')
if minutes_before.get('status') != 'FINALIZED' or minutes_after.get('status') != 'FINALIZED': raise AssertionError('FINALIZED minutes status changed')
if minutes_before != minutes_after or followup_before != followup_after: raise AssertionError('Completed minutes or FLOW-12 history changed')
transcript.append({'label':'COMPLETED/FINALIZED/FLOW-12 source remains unchanged after all four denied operations','pass':True,'status':200,'expected':[200],'body':{'meeting_status':'COMPLETED','minutes_status':'FINALIZED','final_minutes_version_id':fixture['completed_final_minutes_version_id'],'decision_id':fixture['completed_decision_id'],'followup_id':fixture['completed_followup_id']}})

safe_fixture={key:value for key,value in fixture.items() if key not in {'terminal_session_token'}}
summary={'pass':all(row.get('pass') for row in transcript),'steps':len(transcript),'fixture':safe_fixture,'transcript':transcript}
print(json.dumps(summary, ensure_ascii=False, indent=2))
