#!/usr/bin/env python3
import json
import os
import subprocess
import tempfile
import urllib.error
import urllib.request
from pathlib import Path

BASE = os.environ.get('FLOW14_BASE_URL', 'http://127.0.0.1:8013/api')
PASSWORD = os.environ.get('FLOW14_TEST_PASSWORD', 'ChangeMe!2026')
BACKEND = Path(__file__).resolve().parents[1]

results = []

def check(name, ok, detail=None):
    results.append({'name': name, 'pass': bool(ok), 'detail': detail})

def request(method, path, email, body=None):
    token = login(email)
    data = None if body is None else json.dumps(body).encode()
    headers = {'Authorization': f'Bearer {token}', 'Accept': 'application/json'}
    if body is not None:
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(BASE + path, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=45) as response:
            return response.status, response.headers, response.read(), token
    except urllib.error.HTTPError as error:
        return error.code, error.headers, error.read(), token

def login(email):
    req = urllib.request.Request(BASE + '/login', data=json.dumps({'email': email, 'password': PASSWORD}).encode(), headers={'Content-Type': 'application/json'}, method='POST')
    with urllib.request.urlopen(req, timeout=30) as response:
        payload = json.loads(response.read())
    return payload.get('data', {}).get('token') or payload.get('token')

def json_body(raw):
    try:
        return json.loads(raw.decode())
    except Exception:
        return {}

def export_and_download(fmt, owner, other):
    status, _, raw, _ = request('POST', '/reporting/exports', owner, {
        'report_type': 'MEETING_REGISTER', 'format': fmt,
        'from': '2026-01-01', 'to': '2026-12-31', 'per_page': 25,
    })
    payload = json_body(raw)
    export = payload.get('data', {})
    check(f'{fmt} export request accepted', status == 201 and export.get('status') == 'PENDING', {'status': status, 'uuid_present': bool(export.get('uuid'))})
    if not export.get('uuid'):
        return None
    subprocess.run(['php', 'artisan', 'queue:work', '--once', '--queue=default', '--no-interaction'], cwd=BACKEND, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    uuid = export['uuid']
    status, _, raw, _ = request('GET', f'/reporting/exports/{uuid}', owner)
    payload = json_body(raw)
    metadata = payload.get('data', {})
    check(f'{fmt} export completed', status == 200 and metadata.get('status') == 'COMPLETED', {'status': status, 'file_name': metadata.get('file_name'), 'checksum': metadata.get('checksum')})
    forbidden_metadata = any(field in raw.decode(errors='ignore') for field in ['storage_path', 'session_token', 'template_data', 'credential_hash', 'credential_secret'])
    check(f'{fmt} metadata has no sensitive/internal fields', not forbidden_metadata)
    status, headers, data, _ = request('GET', f'/reporting/exports/{uuid}/download', owner)
    suffix = '.pdf' if fmt == 'PDF' else '.xlsx'
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as handle:
        handle.write(data)
        path = handle.name
    check(f'{fmt} authorized private download', status == 200 and len(data) > 100, {'content_type': headers.get('Content-Type'), 'size': len(data)})
    if fmt == 'PDF':
        check('PDF can be parsed', subprocess.run(['pdfinfo', path], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0)
    else:
        check('XLSX is a valid package', subprocess.run(['unzip', '-t', path], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0)
        xml = subprocess.check_output(['unzip', '-p', path, 'xl/worksheets/sheet1.xml']).decode(errors='ignore')
        check('XLSX contains no formula tags', '<f>' not in xml)
    os.unlink(path)
    status, _, raw, _ = request('GET', f'/reporting/exports/{uuid}/download', other)
    check(f'{fmt} unauthorized owner download denied', status == 403, {'status': status})
    return uuid

try:
    high = 'high.authority@example.test'
    director = 'tech.director@example.test'
    employee = 'employee.a@example.test'
    admin = 'system.admin@example.test'

    status, _, raw, _ = request('GET', '/reporting/dashboard/executive?from=2026-01-01&to=2026-12-31', high)
    body = json_body(raw)
    data = body.get('data', {})
    check('executive dashboard returns 200', status == 200)
    check('dashboard has reporting metadata', all(key in data for key in ['scope', 'period', 'as_of', 'definition_version', 'cards', 'charts']))
    check('dashboard cards are server-defined', all('metric_id' in card and 'value' in card for card in data.get('cards', [])))
    check('dashboard payload excludes sensitive fields', not any(field in raw.decode(errors='ignore') for field in ['template_data', 'probe', 'session_token', 'credential_hash', 'credential_secret']))

    status, _, raw, _ = request('GET', '/reporting/dashboard/executive?from=2026-01-01&to=2026-12-31', admin)
    check('SYSTEM_ADMIN without business permission denied executive dashboard', status == 403, {'status': status})

    status, _, raw, _ = request('GET', '/reporting/dashboard/directorate?from=2026-01-01&to=2026-12-31', director)
    check('Directorate dashboard allowed for scoped Director', status == 200, {'status': status})
    status, _, raw, _ = request('GET', '/reporting/dashboard/personal?from=2026-01-01&to=2026-12-31', employee)
    check('Personal dashboard allowed for employee', status == 200, {'status': status})
    status, _, raw, _ = request('GET', '/reporting/dashboard/technical', admin)
    check('Technical dashboard allowed for technical admin', status == 200 and 'session_token' not in raw.decode(errors='ignore'))

    for endpoint in ['/reporting/meetings', '/reporting/meeting-requests', '/reporting/decisions', '/reporting/action-items', '/reporting/exceptions']:
        status, _, raw, _ = request('GET', endpoint + '?from=2026-01-01&to=2026-12-31&per_page=25', director)
        check(f'{endpoint} returns scoped response', status in [200, 403], {'status': status, 'has_pagination_or_error': ('meta' in raw.decode(errors='ignore') or 'data' in raw.decode(errors='ignore') or 'REPORTING_PERMISSION_DENIED' in raw.decode(errors='ignore'))})

    status, _, _, _ = request('GET', '/reporting/meetings?from=2026-01-01&to=2026-12-31&sort=meetings.id%20desc', director)
    check('arbitrary SQL sort denied', status == 422)
    status, _, _, _ = request('GET', '/reporting/meetings?from=2026-01-01&to=2026-01-01&search=' + ('x' * 121), director)
    check('overlong search denied', status == 422)
    status, _, _, _ = request('GET', '/reporting/meetings?from=2026-01-02&to=2026-01-01', director)
    check('reversed date range denied', status == 422)

    pdf_uuid = export_and_download('PDF', high, director)
    xlsx_uuid = export_and_download('XLSX', high, director)
    check('export UUIDs are distinct', pdf_uuid and xlsx_uuid and pdf_uuid != xlsx_uuid)

except Exception as exc:
    check('gate execution completed without unhandled exception', False, repr(exc))

artifact = {'pass': all(item['pass'] for item in results), 'step_count': len(results), 'results': results}
print(json.dumps(artifact, ensure_ascii=False, indent=2))
Path(os.environ.get('FLOW14_HTTP_ARTIFACT', '/tmp/flow14_manual_http_gate.json')).write_text(json.dumps(artifact, ensure_ascii=False, indent=2) + '\n')
raise SystemExit(0 if artifact['pass'] else 1)
