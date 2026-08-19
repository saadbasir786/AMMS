import sqlite3
from pathlib import Path

path = Path('/home/ubuntu/amms/backend/database/database.sqlite')
conn = sqlite3.connect(path)
conn.row_factory = sqlite3.Row
for table in ['users','employees','employee_biometric_templates','meetings','meeting_requests','organizational_units','terminals','meeting_terminal_assignments','terminal_meeting_sessions','meeting_attendance_records']:
    try:
        row = conn.execute(f'SELECT COUNT(*) AS n FROM {table}').fetchone()
        print(f'{table}: {row["n"]}')
    except Exception as exc:
        print(f'{table}: ERROR {exc}')
print('\nEmployee columns:', [r['name'] for r in conn.execute('PRAGMA table_info(employees)')])
print('\nEmployees:')
for r in conn.execute("SELECT id,employee_number,email,first_name,last_name,status,organizational_unit_id FROM employees ORDER BY id LIMIT 30"):
    print(dict(r))
print('\nMeetings:')
for r in conn.execute("SELECT id,meeting_number,status,current_schedule_version_id,current_participant_version_id FROM meetings ORDER BY id LIMIT 30"):
    print(dict(r))
print('\nTerminals:')
for r in conn.execute("SELECT id,code,status,organization_id FROM terminals ORDER BY id"):
    print(dict(r))
conn.close()
