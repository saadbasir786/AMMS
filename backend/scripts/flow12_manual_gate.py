#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
import requests

BASE = os.environ.get('AMMS_MANUAL_BASE_URL', 'http://127.0.0.1:8013').rstrip('/')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD')
FIXTURE = os.environ.get('AMMS_FLOW12_FIXTURE', '/tmp/flow12_manual_fixture.json')
if not PASSWORD:
    raise SystemExit('AMMS_TEST_PASSWORD is required')
fixture = json.loads(Path(FIXTURE).read_text())
records = []
tokens = {}

def body_of(response):
    try: return response.json()
    except ValueError: return {}

def code_of(payload):
    data = payload.get('data') if isinstance(payload, dict) else None
    return payload.get('code') or (data.get('code') if isinstance(data, dict) else None)

def selected(payload):
    data = payload.get('data', payload)
    if isinstance(data, dict):
        return {k: data.get(k) for k in ['id','uuid','status','action_number','progress_percent','decision_number','decision_text','meeting_id','decision_followup_id','requested_due_date'] if k in data}
    return {}

def login(email, role):
    response = requests.post(BASE + '/api/login', json={'email': email, 'password': PASSWORD}, timeout=20)
    payload = body_of(response)
    token = payload.get('data', {}).get('token') or payload.get('token')
    if response.status_code == 200 and token: tokens[role] = token
    records.append({'step': len(records)+1, 'actor': email, 'role': role, 'method': 'POST', 'route': '/api/login', 'http_status': response.status_code, 'canonical_code': code_of(payload), 'response_fields': selected(payload), 'pass': response.status_code == 200 and bool(token)})
    return token

def call(role, method, route, expected, json_body=None):
    headers = {'Authorization': 'Bearer ' + tokens[role], 'Accept': 'application/json'}
    response = requests.request(method, BASE + route, headers=headers, json=json_body, timeout=20)
    payload = body_of(response)
    passed = response.status_code == expected
    records.append({'step': len(records)+1, 'actor': fixture['owner_email'] if role == 'owner' else fixture['employee_email'], 'role': role, 'method': method, 'route': route, 'http_status': response.status_code, 'canonical_code': code_of(payload), 'response_fields': selected(payload), 'pass': passed})
    if not passed:
        raise RuntimeError(f'{method} {route}: expected {expected}, got {response.status_code}')
    return payload

login(fixture['owner_email'], 'owner')
login(fixture['employee_email'], 'responsible_employee')
meeting_id = fixture['meeting_id']
call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
call('owner', 'POST', f'/api/meetings/{meeting_id}/follow-up/initialize', 201)
followups = call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200).get('data', {}).get('followups', [])
followup_id = followups[0]['id']
call('owner', 'POST', f'/api/decisions/{followup_id}/follow-up/classify', 200, {'classification': 'ACTION_REQUIRED'})
created = call('owner', 'POST', f'/api/decisions/{followup_id}/action-items', 201, {'title': 'اقدام آزمایشی HTTP', 'description': 'سناریوی کامل FLOW-12', 'responsible_unit_id': fixture['unit_id'], 'assigned_employee_id': fixture['employee_id'], 'due_date': '2099-12-30'})
item_id = created['data']['id']
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/start', 200)
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/block', 200, {'reason': 'آزمون مانع اجرایی'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/unblock', 200, {'note': 'مانع برطرف شد'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/progress', 200, {'progress_percent': 100, 'note': 'کار تکمیل شد'})
extension = call('responsible_employee', 'POST', f'/api/action-items/{item_id}/extensions', 201, {'requested_due_date': '2099-12-31', 'reason': 'تمدید برای سناریوی آزمایشی'})
extension_id = extension['data']['id']
call('owner', 'POST', f'/api/action-item-extensions/{extension_id}/approve', 200)
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/completion/submit', 200, {'completion_note': 'برای تأیید مالک ارسال شد'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/completion/verify', 403)
call('owner', 'POST', f'/api/action-items/{item_id}/completion/verify', 200)
final = call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
followup = next(x for x in final['data']['followups'] if x['id'] == followup_id)
source_ok = followup.get('decision', {}).get('decision_text') == 'اقدام آزمایشی اجرا گردد.' and final['data']['meeting']['status'] == 'COMPLETED' and final['data']['minutes']['status'] == 'FINALIZED'
records.append({'step': len(records)+1, 'actor': fixture['owner_email'], 'role': 'owner', 'method': 'ASSERT', 'route': f'/api/meetings/{meeting_id}/follow-up', 'http_status': 200, 'canonical_code': None, 'response_fields': {'followup_status': followup.get('status'), 'meeting_status': final['data']['meeting']['status'], 'minutes_status': final['data']['minutes']['status'], 'source_decision_unchanged': source_ok}, 'pass': source_ok})
if not all(r['pass'] for r in records): raise SystemExit('FLOW12_MANUAL_GATE_FAILED')
out = {'gate': 'FLOW-12 real HTTP manual gate', 'base_url': BASE, 'fixture_tag': fixture.get('tag'), 'steps': records, 'passed_steps': sum(1 for r in records if r['pass']), 'total_steps': len(records), 'pass': all(r['pass'] for r in records)}
Path('/home/ubuntu/amms-github/flow12_manual_gate.json').write_text(json.dumps(out, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'gate': out['gate'], 'passed_steps': out['passed_steps'], 'total_steps': out['total_steps'], 'pass': out['pass']}, ensure_ascii=False))
