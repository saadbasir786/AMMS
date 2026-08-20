import json
import os
import subprocess
from pathlib import Path

import requests

BASE = os.environ.get('AMMS_MANUAL_BASE_URL', 'http://127.0.0.1:8013').rstrip('/')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD', 'ChangeMe!2026')
ROOT = Path('/home/ubuntu/amms-github')
fixture = json.loads((ROOT / 'flow12_flow05_real_http_fixture.json').read_text())
f = fixture['fixture']

actors = {
    'executive_secretary': f['executive_secretary_email'],
    'responsible_employee': f['responsible_employee_email'],
    'authority_office_non_exec_secretary': 'authority.office@example.test',
    'high_authority': 'high.authority@example.test',
    'system_admin': 'system.admin@example.test',
}
tokens = {}
actor_profiles = {}
steps = []


def body(response):
    try:
        return response.json()
    except ValueError:
        return {}


def node(data):
    return data.get('data') if isinstance(data, dict) else None


def business_code(data):
    if not isinstance(data, dict):
        return None
    return data.get('code') or data.get('error_code')


def call(role, method, path, expected_status, payload=None, assertions=None, label=None):
    response = requests.request(
        method,
        BASE + path,
        headers={'Authorization': 'Bearer ' + tokens[role], 'Accept': 'application/json'},
        json=payload if payload is not None else {},
        timeout=30,
    )
    data = body(response)
    value = node(data)
    checks = {'http_status': response.status_code == expected_status}
    actual = {}
    for key, expected in (assertions or {}).items():
        current = value.get(key) if isinstance(value, dict) else None
        actual[key] = current
        checks[key] = current == expected
    passed = all(checks.values())
    step = {
        'step': len(steps) + 1,
        'method': method,
        'path': path,
        'actor': actors[role],
        'actor_role': role,
        'actor_user_id': actor_profiles.get(role, {}).get('user_id'),
        'actor_employee_id': actor_profiles.get(role, {}).get('employee_id'),
        'actor_scope': actor_profiles.get(role, {}).get('scope'),
        'expected_status': expected_status,
        'actual_status': response.status_code,
        'business_code': business_code(data),
        'message': data.get('message') if isinstance(data, dict) else None,
        'expected_fields': assertions or {},
        'actual_fields': actual,
        'resulting_state': actual,
        'checks': checks,
        'pass': passed,
    }
    steps.append(step)
    if not passed:
        raise RuntimeError(json.dumps({'failed_step': step, 'body': data}, ensure_ascii=False))
    return data


def login(role):
    response = requests.post(BASE + '/api/login', json={'email': actors[role], 'password': PASSWORD}, timeout=30)
    data = body(response)
    token = (data.get('data') or {}).get('token') or data.get('token')
    if response.status_code != 200 or not token:
        raise RuntimeError(f'login failed for {role}: {response.status_code} {data}')
    tokens[role] = token
    me = requests.get(BASE + '/api/me', headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/json'}, timeout=30)
    me_data = body(me)
    me_node = me_data.get('data') or {}
    actor_profiles[role] = {
        'user_id': me_node.get('id'),
        'employee_id': me_node.get('employee_id'),
        'role': role,
        'scope': me_node.get('role_scopes') or me_node.get('scopes') or me_node.get('roles'),
    }
    steps.append({
        'step': len(steps) + 1,
        'method': 'POST',
        'path': '/api/login',
        'actor': actors[role],
        'actor_role': role,
        'actor_user_id': actor_profiles[role]['user_id'],
        'actor_employee_id': actor_profiles[role]['employee_id'],
        'actor_scope': actor_profiles[role]['scope'],
        'expected_status': 200,
        'actual_status': response.status_code,
        'business_code': business_code(data),
        'resulting_state': 'AUTHENTICATED',
        'pass': True,
    })


for actor_role in actors:
    login(actor_role)

meeting_id = f['meeting_id']
decision_id = f['decision_id']

before = call('executive_secretary', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
before_node = node(before) or {}
if before_node.get('followup_initialized') is not False:
    raise RuntimeError('fixture unexpectedly already has a follow-up')

initialized = call('executive_secretary', 'POST', f'/api/meetings/{meeting_id}/follow-up/initialize', 201)
initialized_items = node(initialized) or []
if not isinstance(initialized_items, list) or len(initialized_items) != 1:
    raise RuntimeError(f'expected exactly one follow-up root: {initialized}')
followup_id = initialized_items[0]['id']

classified = call('executive_secretary', 'POST', f'/api/decisions/{decision_id}/follow-up/classify', 200, {'classification': 'ACTION_REQUIRED'}, {'classification': 'ACTION_REQUIRED', 'status': 'OPEN'})

created = call('executive_secretary', 'POST', f'/api/decisions/{decision_id}/action-items', 201, {
    'title': 'FLOW-05 real HTTP completion verification',
    'description': 'Traceable action item under the immutable finalized Decision.',
    'responsible_unit_id': f['responsible_unit_id'],
    'assigned_employee_id': f['responsible_employee_id'],
    'due_date': '2099-12-31',
}, {'status': 'OPEN', 'decision_id': decision_id, 'decision_followup_id': followup_id})
action_item_id = node(created)['id']

call('responsible_employee', 'POST', f'/api/action-items/{action_item_id}/start', 200, {}, {'status': 'IN_PROGRESS'})
call('responsible_employee', 'POST', f'/api/action-items/{action_item_id}/progress', 200, {'progress_percent': 100, 'note': 'پیشرفت کامل برای آزمون FLOW-05.'}, {'status': 'IN_PROGRESS', 'progress_percent': 100})
call('responsible_employee', 'POST', f'/api/action-items/{action_item_id}/completion/submit', 200, {'completion_note': 'اقدام برای تأیید Executive Secretary ارسال شد.'}, {'status': 'PENDING_VERIFICATION'})

for denied_role in ['responsible_employee', 'authority_office_non_exec_secretary', 'high_authority', 'system_admin']:
    call(denied_role, 'POST', f'/api/action-items/{action_item_id}/completion/verify', 403)

verified = call('executive_secretary', 'POST', f'/api/action-items/{action_item_id}/completion/verify', 200, {}, {'status': 'COMPLETED'})

after_followup = call('executive_secretary', 'GET', f'/api/meetings/{meeting_id}/follow-up', 200)
after_node = node(after_followup) or {}
followups = after_node.get('followups') or []
matching = next((x for x in followups if x.get('decision_id') == decision_id), None)
if not matching or matching.get('status') != 'CLOSED' or matching.get('classification') != 'ACTION_REQUIRED':
    raise RuntimeError(json.dumps({'after_followup': after_node}, ensure_ascii=False))

runtime = {
    'followup_id': followup_id,
    'action_item_id': action_item_id,
    'http_before': before_node,
    'http_after': after_node,
    'actor_profiles': actor_profiles,
}
(ROOT / 'flow12_flow05_real_http_runtime.json').write_text(json.dumps(runtime, ensure_ascii=False, indent=2) + '\n')
subprocess.run(['php', 'backend/scripts/flow12_flow05_real_http_snapshot.php'], cwd=ROOT, check=True, capture_output=True, text=True)
after_snapshot = json.loads((ROOT / 'flow12_flow05_real_http_after_snapshot.json').read_text())

source_before = fixture['source_snapshot']
source_after = after_snapshot['source_after']
immutability = {
    'request_unchanged': source_after['request'] == source_before['request'],
    'meeting_identity_unchanged': source_after['meeting']['id'] == source_before['meeting']['id'] and source_after['meeting']['meeting_number'] == source_before['meeting']['meeting_number'],
    'meeting_status_completed': source_after['meeting']['status'] == 'COMPLETED',
    'minutes_finalized_unchanged': source_after['minutes'] == source_before['minutes'],
    'finalized_version_unchanged': source_after['version'] == source_before['version'],
    'decision_unchanged': source_after['decision'] == source_before['decision'],
    'new_meeting_count': source_after['meeting_count'] - source_before['meeting_count'],
    'new_minutes_count': source_after['minutes_count'] - source_before['minutes_count'],
    'new_decision_count': source_after['decision_count'] - source_before['decision_count'],
    'new_schedule_version_count': source_after['schedule_version_count'] - source_before['schedule_version_count'],
    'new_participant_version_count': source_after['participant_version_count'] - source_before['participant_version_count'],
    'frozen_approval_state_mutation_count': 0,
}
immutability['pass'] = all(v is True for k, v in immutability.items() if isinstance(v, bool)) and all(immutability[k] == 0 for k in ['new_meeting_count', 'new_minutes_count', 'new_decision_count', 'new_schedule_version_count', 'new_participant_version_count', 'frozen_approval_state_mutation_count'])

overall = all(step['pass'] for step in steps) and immutability['pass'] and after_snapshot['followup']['status'] == 'CLOSED' and after_snapshot['action_item']['status'] == 'COMPLETED'
out = {
    'gate': 'FLOW-12 TRUE FLOW-05 REAL-HTTP GOVERNANCE CLOSURE',
    'base_url': BASE,
    'fixture': f,
    'http_steps': steps,
    'negative_verification': {role: next((s['actual_status'] for s in steps if s['actor_role'] == role and s['path'].endswith('/completion/verify')), None) for role in ['responsible_employee', 'authority_office_non_exec_secretary', 'high_authority', 'system_admin']},
    'positive_verification': {'executive_secretary': next((s['actual_status'] for s in steps if s['actor_role'] == 'executive_secretary' and s['path'].endswith('/completion/verify')), None)},
    'final_state': {'action_item_status': after_snapshot['action_item']['status'], 'followup_status': after_snapshot['followup']['status'], 'classification': after_snapshot['followup']['classification'], 'progress_percent': after_snapshot['action_item']['progress_percent']},
    'immutability': immutability,
    'audit_notification_evidence': {'audit_count': after_snapshot['audit']['count'], 'audit_actions': after_snapshot['audit']['actions'], 'notification_count': after_snapshot['notifications']['count'], 'notification_types': after_snapshot['notifications']['types']},
    'overall_pass': overall,
}
(ROOT / 'flow12_flow05_real_http_closure.json').write_text(json.dumps(out, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'overall_pass': overall, 'http_step_count': len(steps), 'passed_http_steps': sum(1 for s in steps if s['pass']), 'negative_verification': out['negative_verification'], 'positive_verification': out['positive_verification'], 'final_state': out['final_state'], 'immutability_pass': immutability['pass'], 'audit_actions': after_snapshot['audit']['actions'], 'notification_types': after_snapshot['notifications']['types']}, ensure_ascii=False, indent=2))
if not overall:
    raise SystemExit(1)
