#!/usr/bin/env python3
import json, os
from datetime import date, timedelta
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

def payload(response):
    try: return response.json()
    except ValueError: return {}

def code_of(data):
    value = data.get('data') if isinstance(data, dict) else None
    return data.get('code') or (value.get('code') if isinstance(value, dict) else None)

def selected(data):
    value = data.get('data', data) if isinstance(data, dict) else {}
    if isinstance(value, dict):
        return {key: value.get(key) for key in ['id','uuid','decision_id','decision_number','classification','status','action_number','progress_percent','is_overdue','requested_due_date','decision_followup_id','meeting_id','public_url'] if key in value}
    return {}

def actor_email(role):
    return {
        'owner': fixture['owner_email'],
        'responsible_employee': fixture['employee_email'],
        'authority_office': 'authority.office@example.test',
        'system_admin': 'system.admin@example.test',
    }.get(role, fixture['owner_email'])

def record(role, method, route, response, expected, data):
    passed = response.status_code == expected
    records.append({'step': len(records)+1, 'actor': actor_email(role), 'role': role, 'method': method, 'route': route, 'http_status': response.status_code, 'canonical_code': code_of(data), 'response_fields': selected(data), 'pass': passed})
    if not passed:
        print(json.dumps({'failure': {'role': role, 'method': method, 'route': route, 'expected': expected, 'actual': response.status_code, 'canonical_code': code_of(data)}}, ensure_ascii=False), flush=True)
        raise RuntimeError(f'{method} {route}: expected {expected}, got {response.status_code}')
    return data

def login(email, role):
    response = requests.post(BASE + '/api/login', json={'email': email, 'password': PASSWORD}, timeout=20)
    data = payload(response)
    token = data.get('data', {}).get('token') or data.get('token')
    if response.status_code == 200 and token: tokens[role] = token
    records.append({'step': len(records)+1, 'actor': email, 'role': role, 'method': 'POST', 'route': '/api/login', 'http_status': response.status_code, 'canonical_code': code_of(data), 'response_fields': {}, 'pass': response.status_code == 200 and bool(token)})
    if response.status_code != 200 or not token: raise RuntimeError(f'login failed for {role}')

def call(role, method, route, expected, body=None):
    response = requests.request(method, BASE + route, headers={'Authorization': 'Bearer '+tokens[role], 'Accept': 'application/json'}, json=body, timeout=20)
    return record(role, method, route, response, expected, payload(response))

def upload(role, route, expected, filename='completion.pdf', content=b'%PDF-1.4 FLOW12 evidence', mime='application/pdf'):
    response = requests.post(BASE + route, headers={'Authorization': 'Bearer '+tokens[role], 'Accept': 'application/json'}, files={'evidence': (filename, content, mime)}, timeout=30)
    return record(role, 'POST', route, response, expected, payload(response))

def download(role, route, expected, content_required=True):
    response = requests.get(BASE + route, headers={'Authorization': 'Bearer '+tokens[role], 'Accept': 'application/pdf'}, timeout=20)
    passed = response.status_code == expected and (not content_required or (response.headers.get('Content-Type', '').startswith('application/pdf') and len(response.content) > 0))
    records.append({'step': len(records)+1, 'actor': actor_email(role), 'role': role, 'method': 'GET', 'route': route, 'http_status': response.status_code, 'canonical_code': code_of(payload(response)) if response.headers.get('Content-Type', '').startswith('application/json') else None, 'response_fields': {'content_type': response.headers.get('Content-Type'), 'byte_count': len(response.content), 'public_url': False}, 'pass': passed})
    if not passed: raise RuntimeError(f'download failed: {route}')

login(fixture['owner_email'], 'owner')
login(fixture['employee_email'], 'responsible_employee')
login('authority.office@example.test', 'authority_office')
login('system.admin@example.test', 'system_admin')
meeting_id = fixture['meeting_id']
target_decision_id = fixture['decision_id']
no_action_decision_id = fixture['no_action_decision_id']
finance_unit_id = fixture['finance_unit_id']
overdue_date = (date.today() - timedelta(days=1)).isoformat()
call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
call('owner', 'POST', f'/api/meetings/{meeting_id}/follow-up/initialize', 201)
workspace = call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
followups = workspace.get('data', {}).get('followups', [])
target_followup = next(item for item in followups if item.get('decision_id') == target_decision_id)
no_action_followup = next(item for item in followups if item.get('decision_id') == no_action_decision_id)
assert target_followup['id'] != target_decision_id
assert no_action_followup['id'] != no_action_decision_id
records.append({'step': len(records)+1, 'actor': fixture['owner_email'], 'role': 'owner', 'method': 'ASSERT', 'route': f'/api/meetings/{meeting_id}/follow-up', 'http_status': 200, 'canonical_code': None, 'response_fields': {'decision_id': target_decision_id, 'followup_id': target_followup['id'], 'ids_differ': target_followup['id'] != target_decision_id}, 'pass': target_followup['id'] != target_decision_id})
call('owner', 'POST', f'/api/decisions/{no_action_decision_id}/follow-up/classify', 200, {'classification': 'NO_ACTION_REQUIRED', 'reason': 'اقدام جداگانه لازم نیست؛ تصمیم در سند اجرایی شد.'})
call('owner', 'POST', f'/api/decisions/{target_decision_id}/follow-up/classify', 200, {'classification': 'ACTION_REQUIRED'})
created = call('owner', 'POST', f'/api/decisions/{target_decision_id}/action-items', 201, {'title': 'اقدام آزمایشی HTTP', 'description': 'سناریوی کامل FLOW-12', 'responsible_unit_id': fixture['unit_id'], 'assigned_employee_id': fixture['employee_id'], 'due_date': overdue_date})
item_id = created['data']['id']
second_created = call('owner', 'POST', f'/api/decisions/{target_decision_id}/action-items', 201, {'title': 'اقدام دوم برای آزمون حریم خصوصی', 'description': 'سناریوی جستجوی متقاطع evidence', 'responsible_unit_id': fixture['unit_id'], 'assigned_employee_id': fixture['employee_id'], 'due_date': '2099-12-31'})
second_item_id = second_created['data']['id']
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/start', 200)
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/assignment', 403, {'responsible_unit_id': finance_unit_id, 'assigned_employee_id': None, 'reason': 'تغییر حوزه توسط کارمند'})
call('owner', 'POST', f'/api/action-items/{item_id}/assignment', 200, {'responsible_unit_id': finance_unit_id, 'assigned_employee_id': None, 'reason': 'انتقال رسمی مسئولیت'})
call('owner', 'POST', f'/api/action-items/{item_id}/assignment', 200, {'responsible_unit_id': fixture['unit_id'], 'assigned_employee_id': fixture['employee_id'], 'reason': 'بازگشت مسئولیت برای تکمیل'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/block', 200, {'reason': 'در انتظار اسناد'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/unblock', 200, {'note': 'اسناد دریافت شد'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/progress', 200, {'progress_percent': 100, 'note': 'کار تکمیل شد'})
call('owner', 'GET', f'/api/action-items/{item_id}', 200)
overdue = call('owner', 'GET', f'/api/action-items/{item_id}', 200)
records.append({'step': len(records)+1, 'actor': fixture['owner_email'], 'role': 'owner', 'method': 'ASSERT', 'route': f'/api/action-items/{item_id}', 'http_status': 200, 'canonical_code': None, 'response_fields': {'is_overdue_before_extension': bool(overdue.get('data', {}).get('is_overdue'))}, 'pass': bool(overdue.get('data', {}).get('is_overdue'))})
extension = call('responsible_employee', 'POST', f'/api/action-items/{item_id}/extensions', 201, {'requested_due_date': '2099-12-31', 'reason': 'تمدید برای سناریوی آزمایشی'})
extension_id = extension['data']['id']
call('owner', 'POST', f'/api/action-item-extensions/{extension_id}/approve', 200)
upload('responsible_employee', f'/api/action-items/{item_id}/evidence', 422, 'evidence.exe', b'MZ FLOW12 forbidden mime', 'application/x-msdownload')
upload('responsible_employee', f'/api/action-items/{item_id}/evidence', 422, 'oversized.pdf', b'0' * 2049, 'application/pdf')
evidence = upload('responsible_employee', f'/api/action-items/{item_id}/evidence', 201)
second_evidence = upload('responsible_employee', f'/api/action-items/{second_item_id}/evidence', 201, 'second.pdf', b'%PDF-1.4 FLOW12 second evidence', 'application/pdf')
download('authority_office', f'/api/action-items/{item_id}/evidence/{evidence["data"]["id"]}', 403, False)
download('owner', f'/api/action-items/{item_id}/evidence/{second_evidence["data"]["id"]}', 404, False)
download('owner', f'/api/action-items/{item_id}/evidence/{evidence["data"]["id"]}', 200)
call('owner', 'POST', f'/api/action-items/{second_item_id}/cancel', 200, {'reason': 'اقدام دوم فقط برای آزمون حریم خصوصی بود.'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/completion/submit', 200, {'completion_note': 'برای بررسی مالک ارسال شد'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/completion/verify', 403)
call('owner', 'POST', f'/api/action-items/{item_id}/completion/return', 200, {'reason': 'لطفاً سند تکمیلی اضافه شود.'})
download('owner', f'/api/action-items/{item_id}/evidence/{evidence["data"]["id"]}', 200)
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/progress', 200, {'progress_percent': 100, 'note': 'اصلاح انجام شد'})
call('responsible_employee', 'POST', f'/api/action-items/{item_id}/completion/submit', 200, {'completion_note': 'ارسال مجدد برای تأیید'})
call('owner', 'POST', f'/api/action-items/{item_id}/completion/verify', 200)
download('owner', f'/api/action-items/{item_id}/evidence/{evidence["data"]["id"]}', 200)
final = call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
final_target = next(item for item in final['data']['followups'] if item.get('decision_id') == target_decision_id)
final_no_action = next(item for item in final['data']['followups'] if item.get('decision_id') == no_action_decision_id)
source_ok = final_target.get('decision', {}).get('decision_text') == 'اقدام آزمایشی اجرا گردد.' and final['data']['meeting']['status'] == 'COMPLETED' and final['data']['minutes']['status'] == 'FINALIZED' and final_target.get('status') == 'CLOSED' and final_target.get('classification') == 'ACTION_REQUIRED' and final_no_action.get('status') == 'CLOSED'
records.append({'step': len(records)+1, 'actor': fixture['owner_email'], 'role': 'owner', 'method': 'ASSERT', 'route': f'/api/meetings/{meeting_id}/follow-up', 'http_status': 200, 'canonical_code': None, 'response_fields': {'followup_status': final_target.get('status'), 'no_action_status': final_no_action.get('status'), 'meeting_status': final['data']['meeting']['status'], 'minutes_status': final['data']['minutes']['status'], 'source_decision_unchanged': source_ok}, 'pass': source_ok})
if not all(record['pass'] for record in records): raise SystemExit('FLOW12_MANUAL_GATE_FAILED')
out = {'gate': 'FLOW-12 extended real HTTP manual gate', 'base_url': BASE, 'fixture_tag': fixture.get('tag'), 'steps': records, 'passed_steps': sum(1 for record in records if record['pass']), 'total_steps': len(records), 'pass': all(record['pass'] for record in records)}
Path('/home/ubuntu/amms-github/flow12_manual_gate.json').write_text(json.dumps(out, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'gate': out['gate'], 'passed_steps': out['passed_steps'], 'total_steps': out['total_steps'], 'pass': out['pass']}, ensure_ascii=False))
