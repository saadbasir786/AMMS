#!/usr/bin/env python3
import json
import os
import urllib.error
import urllib.request
from pathlib import Path

BASE = os.environ.get('FLOW14_BASE_URL', 'http://127.0.0.1:8013/api')
PASSWORD = os.environ.get('FLOW14_TEST_PASSWORD', 'ChangeMe!2026')
ROOT = Path(__file__).resolve().parents[2]
BACKEND = ROOT / 'backend'
fixture = json.loads((ROOT / 'flow14_canonical_fixture.json').read_text())
expected = fixture['expected']
unit_id = fixture['scope_unit_id']
params = f'from=2026-08-01&to=2026-09-10&timezone=Asia%2FKabul&involved_unit_id={unit_id}&per_page=50'
results = []

def check(name, ok, detail=None):
    results.append({'name': name, 'pass': bool(ok), 'detail': detail})

def login(email):
    req = urllib.request.Request(BASE + '/login', data=json.dumps({'email': email, 'password': PASSWORD}).encode(), headers={'Content-Type': 'application/json'}, method='POST')
    with urllib.request.urlopen(req, timeout=30) as response:
        payload = json.loads(response.read())
    return payload.get('data', {}).get('token') or payload.get('token')

def request(method, path, email, body=None):
    token = login(email)
    data = None if body is None else json.dumps(body).encode()
    headers = {'Authorization': f'Bearer {token}', 'Accept': 'application/json'}
    if body is not None:
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(BASE + path, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=45) as response:
            return response.status, json.loads(response.read())
    except urllib.error.HTTPError as error:
        raw = error.read()
        try:
            payload = json.loads(raw)
        except Exception:
            payload = {'raw': raw.decode(errors='ignore')}
        return error.code, payload

def cards(payload):
    return {card['metric_id']: card.get('value') for card in payload.get('data', {}).get('cards', [])}

try:
    status, payload = request('GET', f'/reporting/dashboard/executive?{params}', 'tech.exec@example.test')
    data = payload.get('data', {})
    actual = cards(payload)
    check('executive HTTP status', status == 200, status)
    for metric, want in {
        'requests.total': expected['request_total'],
        'meetings.distinct_total': expected['meeting_distinct_total'],
        'meetings.conducted': expected['conducted_meeting_count'],
        'meetings.upcoming': expected['upcoming_meeting_count'],
        'meetings.high_authority': expected['high_authority_meeting_count'],
        'attendance.official_present': expected['official_attendance_count'],
        'decisions.finalized': expected['finalized_decision_count'],
        'followups.total': expected['followup_count'],
        'action_items.overdue': expected['action_item_overdue_count'],
    }.items():
        check(f'exact HTTP metric {metric}', actual.get(metric) == want, {'actual': actual.get(metric), 'expected': want})
    check('exact HTTP attendance rate', float(actual.get('attendance.rate', -1)) == float(expected['attendance_rate']))
    check('exact HTTP action completion rate', float(actual.get('action_items.completion_rate', -1)) == float(expected['action_item_completion_rate']))
    summary = data.get('summary', {})
    check('exact HTTP status distribution', summary.get('meeting_status_counts') == expected['meeting_status_counts'], summary.get('meeting_status_counts'))
    check('exact HTTP action status distribution', summary.get('action_item_status_counts') == expected['action_item_status_counts'], summary.get('action_item_status_counts'))
    check('HTTP response redacts internals', not any(x in json.dumps(payload, ensure_ascii=False) for x in ['storage_path', 'session_token', 'credential_hash', 'credential_secret', 'template_data']))

    status, payload = request('GET', f'/reporting/dashboard/directorate?{params}', 'tech.director@example.test')
    check('Director own canonical unit allowed', status == 200, status)
    status, payload = request('GET', '/reporting/dashboard/directorate?from=2026-08-01&to=2026-09-10&timezone=Asia%2FKabul&involved_unit_id=2', 'tech.director@example.test')
    check('Director unrelated unit denied', status == 403, status)

    personal_params = 'from=2026-08-01&to=2026-09-10&timezone=Asia%2FKabul'
    status, payload = request('GET', f'/reporting/dashboard/personal?{personal_params}', 'employee.a@example.test')
    serialized = json.dumps(payload, ensure_ascii=False)
    check('Employee personal dashboard allowed', status == 200, status)
    check('Employee personal dashboard contains no internal fields', not any(x in serialized for x in ['storage_path', 'session_token', 'credential_hash', 'credential_secret', 'template_data']))
    status, payload = request('GET', f'/reporting/meetings?{params}', 'employee.a@example.test')
    rows = payload.get('data', []) if isinstance(payload, dict) else []
    check('Employee meeting register remains resource-scoped', status in [200, 403] and all('storage_path' not in json.dumps(row, ensure_ascii=False) for row in rows), {'status': status, 'rows': len(rows)})

    status, payload = request('GET', f'/reporting/dashboard/executive?{params}', 'system.admin@example.test')
    check('SYSTEM_ADMIN business executive dashboard denied', status == 403, status)
except Exception as exc:
    check('HTTP gate completed without unhandled exception', False, repr(exc))

artifact = {'gate': 'FLOW14_EXACT_HTTP_METRIC_GATE', 'pass': all(item['pass'] for item in results), 'fixture': fixture['fixture_key'], 'scope_unit_id': unit_id, 'results': results}
(ROOT / 'flow14_exact_http_metric_gate.json').write_text(json.dumps(artifact, ensure_ascii=False, indent=2) + '\n')
print(json.dumps(artifact, ensure_ascii=False, indent=2))
raise SystemExit(0 if artifact['pass'] else 1)
