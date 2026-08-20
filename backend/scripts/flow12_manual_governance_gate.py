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
actors = {'owner': fixture['owner_email'], 'authority_office': 'authority.office@example.test', 'high_authority': 'high.authority@example.test', 'system_admin': 'system.admin@example.test'}

def body(response):
    try: return response.json()
    except ValueError: return {}

def code(data): return data.get('code') if isinstance(data, dict) else None

def login(role):
    response = requests.post(BASE+'/api/login', json={'email': actors[role], 'password': PASSWORD}, timeout=20)
    data = body(response); token = data.get('data', {}).get('token') or data.get('token')
    if response.status_code == 200 and token: tokens[role] = token
    records.append({'step': len(records)+1, 'actor': actors[role], 'role': role, 'method': 'POST', 'route': '/api/login', 'http_status': response.status_code, 'canonical_code': code(data), 'pass': response.status_code == 200 and bool(token)})

def call(role, method, route, expected):
    response = requests.request(method, BASE+route, headers={'Authorization': 'Bearer '+tokens[role], 'Accept': 'application/json'}, json={'classification': 'ACTION_REQUIRED'}, timeout=20)
    data = body(response); passed = response.status_code == expected
    records.append({'step': len(records)+1, 'actor': actors[role], 'role': role, 'method': method, 'route': route, 'http_status': response.status_code, 'canonical_code': code(data), 'response_fields': {'status': data.get('data', {}).get('status')} if isinstance(data.get('data'), dict) else {}, 'pass': passed})
    if not passed: raise RuntimeError(f'{route}: expected {expected}, got {response.status_code}')
    return data

for role in actors: login(role)
for key in ['flow05', 'flow06']:
    meeting_id = fixture[key]['meeting_id']; decision_id = fixture[key]['decision_id']
    call('owner', 'POST', f'/api/meetings/{meeting_id}/follow-up/initialize', 201)
    call('owner', 'POST', f'/api/decisions/{decision_id}/follow-up/classify', 200)
    for role in ['authority_office', 'high_authority', 'system_admin']:
        call(role, 'POST', f'/api/decisions/{decision_id}/follow-up/classify', 403)
out = {'gate': 'FLOW-05/FLOW-06 real HTTP ownership gate', 'steps': records, 'passed_steps': sum(int(x['pass']) for x in records), 'total_steps': len(records), 'pass': all(x['pass'] for x in records)}
Path('/home/ubuntu/amms-github/flow12_manual_governance_gate.json').write_text(json.dumps(out, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({'gate': out['gate'], 'passed_steps': out['passed_steps'], 'total_steps': out['total_steps'], 'pass': out['pass']}, ensure_ascii=False))
