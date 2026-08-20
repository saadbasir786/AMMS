import json
import os
from pathlib import Path
import requests

fixture = json.loads(Path('/tmp/flow11_manual_strict_fixture.json').read_text())
password = os.environ.get('AMMS_MANUAL_PASSWORD')
if not password:
    raise RuntimeError('AMMS_MANUAL_PASSWORD must be supplied for PDF capture')
base = fixture['base_url'] + '/api'
meeting_id = fixture['meetings']['flow05']['id']
s = requests.Session()
r = s.post(base + '/login', json={'email': fixture['accounts']['secretary'], 'password': password}, timeout=30)
r.raise_for_status()
s.headers.update({'Authorization': 'Bearer ' + r.json()['data']['token'], 'Accept': 'application/pdf'})
pdf = s.get(base + f'/meetings/{meeting_id}/minutes/final.pdf', timeout=30)
pdf.raise_for_status()
Path('/home/ubuntu/amms-github/flow11_manual_final_minutes.pdf').write_bytes(pdf.content)
print(json.dumps({'meeting_id': meeting_id, 'status': pdf.status_code, 'content_type': pdf.headers.get('Content-Type'), 'bytes': len(pdf.content), 'sha256': __import__('hashlib').sha256(pdf.content).hexdigest(), 'prefix': pdf.content[:4].decode('latin1')}, ensure_ascii=False))
