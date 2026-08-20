#!/usr/bin/env python3
import json, os
import requests

BASE=os.environ.get('AMMS_MANUAL_BASE_URL','http://127.0.0.1:8013').rstrip('/')
FIXTURE=os.environ.get('AMMS_FLOW13_FIXTURE','/tmp/flow13_cancellation_fixture.json')
PASSWORD=os.environ.get('AMMS_TEST_PASSWORD','ChangeMe!2026')
with open(FIXTURE,encoding='utf-8') as fh:f=json.load(fh)

def sess(email):
 s=requests.Session(); r=s.post(BASE+'/api/login',json={'email':email,'password':PASSWORD},timeout=20); p=r.json(); t=p.get('data',{}).get('token') or p.get('token'); assert t,p; s.headers.update({'Authorization':'Bearer '+t,'Accept':'application/json'}); return s

def call(email,m,path,e,b=None):
 r=sess(email).request(m,BASE+path,json=b,timeout=30); p=r.json(); expected=e if isinstance(e,list) else [e]; assert r.status_code in expected,(path,r.status_code,p); return p.get('data',p)
owner=f['owner_email']; director=f['director_email']; mid=f['attendance_meeting_id'] if 'attendance_meeting_id' in f else f['meeting_id']
attendance=call(owner,'POST',f'/api/meetings/{mid}/attendance/manual', [200,201], {'employee_id':f.get('attendance_employee_id',1),'reason':'ثبت پیش از لغو.'})
case=call(owner,'POST',f'/api/meetings/{mid}/exceptions',201,{'case_type':'CANCELLATION','reason':'لغو نهایی برای شاهد کاننیکال.'})
call(owner,'POST',f"/api/meeting-exceptions/{case['id']}/submit",200)
read=call(owner,'GET',f"/api/meeting-exceptions/{case['id']}",200); rows=read.get('reviews') or read.get('current_version',{}).get('reviews') or []
for row in rows: call(director,'POST',f"/api/meeting-exception-reviews/{row['id']}/approve",200)
call(owner,'POST',f"/api/meeting-exceptions/{case['id']}/apply",200)
terminal_status = None
session_status = None
if f.get('terminal_session_token'):
    terminal_headers = {'Authorization': 'Bearer ' + f['terminal_session_token'], 'Accept': 'application/json'}
    terminal_response = requests.get(BASE + f"/api/terminal/v1/sessions/{f['terminal_session_uuid']}/manifest", headers=terminal_headers, timeout=30)
    terminal_payload = terminal_response.json()
    assert terminal_response.status_code == 401, (terminal_response.status_code, terminal_payload)
    terminal_status = 'REVOKED'
    session_status = 'REVOKED'
meeting=call(owner,'GET',f'/api/meetings/{mid}',200)
att=call(owner,'GET',f'/api/meetings/{mid}/attendance',200)
assert meeting.get('status')=='CANCELLED',meeting
assert int(att.get('official_count',-1))==0,att
print(json.dumps({'pass':True,'meeting_id':mid,'meeting_status':meeting.get('status'),'cancellation_case_id':case['id'],'attendance_record_id':attendance.get('id'),'official_count':att.get('official_count'),'terminal_status':terminal_status,'terminal_session_status':session_status,'attendance_invalidation':'MEETING_CANCELLED'},ensure_ascii=False,indent=2))
