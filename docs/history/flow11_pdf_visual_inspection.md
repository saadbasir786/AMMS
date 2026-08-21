# FLOW-11 Visual PDF Inspection

**Artifact:** `flow11_manual_final_minutes.pdf`
**Rendered image:** `flow11_manual_final_minutes.png`
**Source:** Real HTTP finalized FLOW-05 manual workflow, Meeting ID 22
**Renderer:** mPDF 8.3.1 with built-in DejaVu Sans OpenType shaping
**Response:** HTTP 200, `application/pdf`, 48,709 bytes, `%PDF` prefix
**SHA-256:** `e585365bf83a78c5fc29d42547d0921f60726838996b071f87c9d29543b4d544`

## Visual result

The corrected page was rendered independently to PNG at 170 DPI and visually inspected. Persian/Dari letters are connected and naturally readable. The title `صورت‌جلسهٔ رسمی`, headings, employee names, organizational-unit names, table labels, attendance statuses, agenda text, summary, and decision text all render in correct RTL word order. Tables are laid out RTL without clipping or overlap. `DEC-001`, `FLOW-11`, meeting numbers, and the SHA-256 reference remain readable as Latin/LTR content.

The renderer uses mPDF’s native OpenType shaping and bidirectional processing. No Persian string is manually character-reversed. Explicit LTR spans keep schedule values, ISO timestamps, role codes, numeric identifiers, and the decision number readable in mixed-direction cells; the ISO timestamps remain on one line.

## Required-field checklist

| Required field | Result |
|---|---|
| صورت‌جلسهٔ رسمی | PASS |
| شماره جلسه | PASS |
| نسخه صورت‌جلسه | PASS |
| تاریخ و زمان رسمی | PASS |
| شروع واقعی | PASS |
| پایان واقعی | PASS |
| رئیس جلسه | PASS |
| ریاست‌های رسمی | PASS |
| اشتراک‌کنندگان و حضور | PASS |
| اجندا و مباحث | PASS |
| خلاصه عمومی | PASS |
| تصمیمات | PASS |
| Persian employee/unit names | PASS |
| Mixed Persian + Latin values | PASS |
| No clipping or overlap | PASS |
| Biometric-sensitive fields | PASS — none present |
