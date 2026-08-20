# FLOW-11 Visual PDF Inspection

**Artifact:** `flow11_manual_final_minutes.pdf`
**Source:** Real HTTP finalized FLOW-05 manual workflow, Meeting ID 22
**Renderer:** Dompdf 3.1.6 + CPDF
**Page:** One A4 page, portrait, 595.28 × 841.89 pt
**Response:** HTTP 200, `application/pdf`, 24,038 bytes, `%PDF` prefix
**SHA-256:** `5ed86b604bd4113d3a689d6a5bde35d57c359642f851ccfa68acbb59a50f4e98`

## Visual result

The rendered page was inspected as an image at full-page scale. The document has a clear Persian/Dari RTL visual hierarchy: centered title, right-aligned headings, right-to-left table columns, readable Persian labels, and consistent blue-gray table styling. The page is not clipped and all major sections fit on one A4 page.

| Required visual/content gate | Observation | Status |
|---|---|---|
| Persian/Dari RTL | Persian/Dari title, headings, labels, and right-to-left table layout are visible | **PASS** |
| Meeting number | Visible in the meeting metadata table | **PASS** |
| Subject/title | Visible in the document subtitle and meeting metadata | **PASS** |
| Official schedule | Meeting date, start time, end time, and location are visible | **PASS** |
| Actual start/end | Server-authoritative start and end timestamps are visible | **PASS** |
| Chairperson | Chairperson label and Persian employee name are visible | **PASS** |
| Formal units/participants | Participant, unit, role, and status table is visible | **PASS** |
| Present/Absent status | Attendance status column is visible and rendered in Persian | **PASS** |
| Discussion/agenda | Agenda and discussion summary section is visible | **PASS** |
| Decisions | Decisions section contains `DEC-001` and decision text | **PASS** |
| Version | Minutes version `1` is visible | **PASS** |
| Checksum | SHA-256 content reference is visible in the footer | **PASS** |
| Biometric implementation fields | No template bytes, raw frames, matcher fields, candidate data, or terminal secrets are visible | **PASS** |

The extracted text was also checked for the required subject, meeting number, Persian headings, decision number, version, and checksum reference. The visual gate is therefore **PASS**.
