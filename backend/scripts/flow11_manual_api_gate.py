import hashlib
import json
import os
import sys
from datetime import datetime
from pathlib import Path

import requests

BASE = os.environ.get('FLOW11_MANUAL_BASE', 'http://127.0.0.1:8011/api')
PASSWORD = os.environ.get('AMMS_TEST_PASSWORD', 'ChangeMe!2026')
fixture = json.loads(Path('/tmp/flow11_manual_fixture.json').read_text())
meeting_id = fixture['meeting_id']
unit_id = fixture['unit_id']
steps = []

def safe(value):
    if isinstance(value, dict):
        return {k: safe(v) for k, v in value.items() if k.lower() not in {'token', 'access_token', 'authorization'} and 'template_data' not in k.lower()}
    if isinstance(value, list):
        return [safe(v) for v in value]
    return value

def call(name, method, path, session, expected=None, **kwargs):
    response = session.request(method, BASE + path, timeout=30, **kwargs)
    body = None
    try:
        body = response.json()
    except ValueError:
        body = {'content_type': response.headers.get('Content-Type'), 'bytes': len(response.content), 'sha256': hashlib.sha256(response.content).hexdigest(), 'prefix': response.content[:4].decode('latin1', errors='replace')}
    record = {'step': name, 'method': method, 'path': path, 'status': response.status_code, 'body': safe(body)}
    if expected is not None:
        ok = response.status_code == expected
    elif isinstance(expected, tuple):
        ok = response.status_code in expected
    else:
        ok = 200 <= response.status_code < 300
    record['pass'] = ok
    steps.append(record)
    if not ok:
        raise RuntimeError(json.dumps(record, ensure_ascii=False))
    return response, body

def login(email):
    s = requests.Session()
    r, body = call('login_' + email.split('@')[0], 'POST', '/login', s, expected=200, json={'email': email, 'password': PASSWORD})
    token = body.get('data', body).get('token')
    s.headers.update({'Authorization': 'Bearer ' + token})
    return s

coordinator = login('tech.exec@example.test')
director = login('tech.director@example.test')
call('execution_state_before_start', 'GET', f'/meetings/{meeting_id}/execution', coordinator, expected=200)
call('start_meeting_execution', 'POST', f'/meetings/{meeting_id}/execution/start', coordinator, expected=200, json={'start_notes': 'شروع واقعی برای manual FLOW-11'})
call('execution_state_in_progress', 'GET', f'/meetings/{meeting_id}/execution', coordinator, expected=200)
call('create_minutes_draft', 'POST', f'/meetings/{meeting_id}/minutes/draft', coordinator, expected=201)
payload = {'summary': 'خلاصهٔ رسمی manual FLOW-11.', 'general_notes': 'یادداشت جلسه برای آزمون واقعی HTTP.', 'items': [{'agenda_title': 'موضوع نخست', 'discussion_summary': 'بحث موضوع نخست ثبت شد.'}], 'decisions': [{'decision_text': 'تصمیم نخست ثبت شد.', 'target_date': datetime.now().strftime('%Y-%m-%d')}]}
call('update_minutes_draft', 'PUT', f'/meetings/{meeting_id}/minutes/draft', coordinator, expected=200, json=payload)
rejected = coordinator.post(BASE + f'/meetings/{meeting_id}/minutes/submit', timeout=30)
steps.append({'step': 'submit_while_in_progress_rejected', 'method': 'POST', 'path': f'/meetings/{meeting_id}/minutes/submit', 'status': rejected.status_code, 'body': safe(rejected.json()), 'pass': rejected.status_code == 422})
if rejected.status_code != 422:
    raise RuntimeError('Minutes submission was not rejected before execution end')
call('end_meeting_execution', 'POST', f'/meetings/{meeting_id}/execution/end', coordinator, expected=200, json={'end_notes': 'پایان واقعی برای manual FLOW-11'})
call('submit_minutes_version', 'POST', f'/meetings/{meeting_id}/minutes/submit', coordinator, expected=200)
call('director_unit_acknowledges', 'POST', f'/meetings/{meeting_id}/minutes/reviews/{unit_id}/acknowledge', director, expected=200)
call('finalize_minutes_and_complete_meeting', 'POST', f'/meetings/{meeting_id}/minutes/finalize', coordinator, expected=200)
call('download_official_pdf', 'GET', f'/meetings/{meeting_id}/minutes/final.pdf', coordinator, expected=200)
duplicate = coordinator.post(BASE + f'/meetings/{meeting_id}/minutes/finalize', timeout=30)
steps.append({'step': 'duplicate_finalize_rejected', 'method': 'POST', 'path': f'/meetings/{meeting_id}/minutes/finalize', 'status': duplicate.status_code, 'body': safe(duplicate.json()), 'pass': duplicate.status_code == 422})
if duplicate.status_code != 422:
    raise RuntimeError('Duplicate finalization was not rejected')
result = {'artifact': 'FLOW11_MANUAL_HTTP_GATE', 'base': BASE, 'meeting_id': meeting_id, 'checks': len(steps), 'passed': sum(1 for x in steps if x['pass']), 'steps': steps}
Path('/home/ubuntu/amms-github/flow11_manual_api_gate.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps(result, ensure_ascii=False, indent=2))
