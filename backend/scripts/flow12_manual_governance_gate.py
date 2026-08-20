#!/usr/bin/env python3
import json, os
from pathlib import Path
import requests

BASE = os.environ.get('AMMS_MANUAL_BASE_URL', 'http://127.0.0.1:8013').rstrip('/')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD')
FIXTURE = os.environ.get('AMMS_FLOW12_GOV_FIXTURE', '/tmp/flow12_manual_governance_fixture.json')
if not PASSWORD:
    raise SystemExit('AMMS_TEST_PASSWORD is required')
fixture = json.loads(Path(FIXTURE).read_text())
records = []
tokens = {}
actors = {
    'owner': fixture['owner_email'],
    'responsible_employee': fixture['flow05'].get('responsible_employee_email', 'tech.director@example.test'),
    'authority_office': 'authority.office@example.test',
    'high_authority': 'high.authority@example.test',
    'system_admin': 'system.admin@example.test',
}

def body(response):
    try:
        return response.json()
    except ValueError:
        return {}

def code(data):
    return data.get('code') if isinstance(data, dict) else None

def data_node(data):
    return data.get('data') if isinstance(data, dict) else None

def matches(actual, expected):
    if isinstance(expected, dict):
        return isinstance(actual, dict) and all(key in actual and matches(actual[key], value) for key, value in expected.items())
    return actual == expected

def login(role):
    response = requests.post(BASE+'/api/login', json={'email': actors[role], 'password': PASSWORD}, timeout=20)
    data = body(response)
    token = data.get('data', {}).get('token') or data.get('token')
    if response.status_code == 200 and token:
        tokens[role] = token
    records.append({
        'step': len(records)+1, 'actor': actors[role], 'role': role, 'method': 'POST',
        'route': '/api/login', 'http_status': response.status_code,
        'canonical_code': code(data), 'pass': response.status_code == 200 and bool(token),
    })
    if response.status_code != 200 or not token:
        raise RuntimeError(f'login {role}: expected 200 with token, got {response.status_code}')

def call(role, method, route, expected, payload=None, expected_fields=None):
    response = requests.request(
        method,
        BASE+route,
        headers={'Authorization': 'Bearer '+tokens[role], 'Accept': 'application/json'},
        json=payload or {},
        timeout=20,
    )
    data = body(response)
    node = data_node(data)
    passed = response.status_code == expected
    field_values = {}
    if isinstance(node, dict):
        for key in (expected_fields or {}):
            value = node.get(key)
            field_values[key] = value
            if not matches(value, expected_fields[key]):
                passed = False
    records.append({
        'step': len(records)+1, 'actor': actors[role], 'role': role, 'method': method,
        'route': route, 'http_status': response.status_code,
        'canonical_code': code(data), 'response_fields': field_values, 'pass': passed,
    })
    if not passed:
        debug_me = None
        if role == 'responsible_employee' and role in tokens:
            me_response = requests.get(BASE+'/api/me', headers={'Authorization': 'Bearer '+tokens[role], 'Accept': 'application/json'}, timeout=20)
            me_data = body(me_response)
            debug_me = {'http_status': me_response.status_code, 'user_id': me_data.get('data', {}).get('id'), 'email': me_data.get('data', {}).get('email'), 'employee_id': me_data.get('data', {}).get('employee_id')}
        raise RuntimeError(f'{role} {route}: expected {expected}/{expected_fields}, got {response.status_code}/{field_values}, code={code(data)}, body={data}, me={debug_me}')
    return data

for role in actors:
    login(role)

for key in ['flow05', 'flow06']:
    meeting_id = fixture[key]['meeting_id']
    decision_id = fixture[key]['decision_id']
    responsible_unit_id = fixture[key]['responsible_unit_id']
    responsible_employee_id = fixture[key]['responsible_employee_id']

    call('owner', 'POST', f'/api/meetings/{meeting_id}/follow-up/initialize', 201)
    call('owner', 'POST', f'/api/decisions/{decision_id}/follow-up/classify', 200, {'classification': 'ACTION_REQUIRED'}, {'classification': 'ACTION_REQUIRED', 'status': 'OPEN'})
    created = call('owner', 'POST', f'/api/decisions/{decision_id}/action-items', 201, {
        'title': f'{key} real completion governance',
        'description': 'Runtime governance gate',
        'responsible_unit_id': responsible_unit_id,
        'assigned_employee_id': responsible_employee_id,
        'due_date': '2099-12-31',
    }, {'status': 'OPEN'})
    action_item_id = data_node(created)['id']

    call('responsible_employee', 'POST', f'/api/action-items/{action_item_id}/start', 200, {}, {'status': 'IN_PROGRESS'})
    call('responsible_employee', 'POST', f'/api/action-items/{action_item_id}/progress', 200, {'progress_percent': 100, 'note': 'پیشرفت کامل برای آزمون مالکیت'}, {'status': 'IN_PROGRESS', 'progress_percent': 100})
    call('responsible_employee', 'POST', f'/api/action-items/{action_item_id}/completion/submit', 200, {'completion_note': 'تکمیل برای تأیید مالک ارسال شد.'}, {'status': 'PENDING_VERIFICATION'})

    for role in ['authority_office', 'system_admin', 'high_authority']:
        call(role, 'POST', f'/api/action-items/{action_item_id}/completion/verify', 403)

    call('owner', 'POST', f'/api/action-items/{action_item_id}/completion/verify', 200, {}, {'status': 'COMPLETED'})
    call('owner', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200, None, {'meeting': {'id': meeting_id, 'status': 'COMPLETED'}, 'minutes': {'status': 'FINALIZED'}})

out = {
    'gate': 'FLOW-05/FLOW-06 real HTTP completion governance gate',
    'steps': records,
    'passed_steps': sum(int(x['pass']) for x in records),
    'total_steps': len(records),
    'pass': all(x['pass'] for x in records),
}
Path('/home/ubuntu/amms-github/flow12_manual_governance_gate.json').write_text(json.dumps(out, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({'gate': out['gate'], 'passed_steps': out['passed_steps'], 'total_steps': out['total_steps'], 'pass': out['pass']}, ensure_ascii=False))
