import json
from pathlib import Path
import requests

BASE = "http://127.0.0.1:8000/api"
PASSWORD = "ChangeMe!2026"
CHECKS = [
    {
        "label": "authority_office_high_authority_issue_denied",
        "email": "authority.office@example.test",
        "path": "/meeting-requests/9/authority-initiated/issue",
    },
    {
        "label": "director_high_authority_issue_denied",
        "email": "tech.director@example.test",
        "path": "/meeting-requests/9/authority-initiated/issue",
    },
    {
        "label": "executive_management_authority_initiated_scheduling_denied",
        "email": "tech.exec@example.test",
        "path": "/meeting-requests/9/authority-initiated/scheduling/start",
    },
    {
        "label": "system_admin_authority_initiated_finalize_denied",
        "email": "system.admin@example.test",
        "path": "/meeting-requests/9/authority-initiated/finalize",
    },
]

out = []
for check in CHECKS:
    session = requests.Session()
    login = session.post(
        f"{BASE}/login",
        json={"email": check["email"], "password": PASSWORD},
        timeout=15,
    )
    token = login.json().get("data", {}).get("token") or login.json().get("token")
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    response = session.post(f"{BASE}{check['path']}", json={}, headers=headers, timeout=15)
    try:
        payload = response.json()
    except ValueError:
        payload = {"raw": response.text}
    out.append(
        {
            **check,
            "method": "POST",
            "login_status": login.status_code,
            "status": response.status_code,
            "response": payload,
            "pass": response.status_code == 403,
        }
    )

path = Path("/home/ubuntu/amms-github/flow06_negative_http_checks.ndjson")
path.write_text("\n".join(json.dumps(item, ensure_ascii=False, separators=(",", ":")) for item in out) + "\n", encoding="utf-8")
print(json.dumps(out, ensure_ascii=False, indent=2))
if not all(item["pass"] for item in out):
    raise SystemExit(1)
