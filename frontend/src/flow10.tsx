import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { CalendarCheck, Fingerprint, ShieldCheck, UserRound } from 'lucide-react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { PageHeader } from '@/components/amms/page-header'

type Any = any
const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: Any) => response.data?.data ?? response.data
const errorOf = (error: Any, fallback: string) => error?.response?.data?.message || fallback

export function BiometricRegistry() {
  const [employees, setEmployees] = useState<Any[]>([])
  const [error, setError] = useState('')
  const navigate = useNavigate()
  useEffect(() => { api.get('/employees').then(r => setEmployees(dataOf(r) || [])).catch(e => setError(errorOf(e, 'دریافت فهرست کارمندان ممکن نیست.'))) }, [])
  return <div className="space-y-6">
    <PageHeader eyebrow="FLOW-10A · مدیریت بایومتریک" title="ثبت بایومتریک کارمندان" description="فقط وضعیت و metadata امن نمایش داده می‌شود؛ قالب خام اثر انگشت به مرورگر ارسال نمی‌شود." action={<Fingerprint className="size-6 text-muted-foreground" />} />
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
    <Card><CardHeader className="border-b"><CardTitle className="text-base">کارمندان</CardTitle></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>شماره کارمندی</TableHead><TableHead>کارمند</TableHead><TableHead>ریاست</TableHead><TableHead>مدیریت</TableHead></TableRow></TableHeader><TableBody>{employees.map(employee => <TableRow key={employee.id}><TableCell className="font-mono text-xs">{employee.employee_number}</TableCell><TableCell>{employee.full_name}</TableCell><TableCell>{employee.unit?.name || '—'}</TableCell><TableCell><Button size="sm" variant="outline" onClick={() => navigate(`/employees/${employee.id}/biometrics`)}><Fingerprint className="size-4"/> مشاهدهٔ وضعیت</Button></TableCell></TableRow>)}</TableBody></Table></div>{!employees.length ? <p className="p-6 text-center text-sm text-muted-foreground">کارمندی یافت نشد.</p> : null}</CardContent></Card>
    <Alert><ShieldCheck className="size-4"/><AlertDescription>FLOW-10A در وب فقط metadata امن را نمایش می‌دهد. Capture/Matching و هرگونه اتصال فیزیکی DERMALOG در این سطح انجام نمی‌شود؛ FLOW-10B همچنان deferred است.</AlertDescription></Alert>
  </div>
}

export function BiometricEmployee() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [data, setData] = useState<Any>(null)
  const [error, setError] = useState('')
  useEffect(() => { api.get(`/employees/${id}/biometrics`).then(r => setData(dataOf(r))).catch(e => setError(errorOf(e, 'دریافت وضعیت بایومتریک ممکن نیست.'))) }, [id])
  return <div className="space-y-6">
    <PageHeader eyebrow="FLOW-10A · پروفایل بایومتریک" title={data?.employee?.full_name || 'وضعیت بایومتریک کارمند'} description={`شماره کارمندی: ${data?.employee?.employee_number || '—'}`} action={<Button variant="outline" onClick={() => navigate('/admin/biometrics')}>بازگشت</Button>} />
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
    {data ? <><DetailSummaryCard eyebrow="وضعیت امن ثبت" title="خلاصهٔ بایومتریک" icon={<Fingerprint className="size-5" />} items={[{ label: 'وضعیت ثبت', value: <Badge variant="outline">{data.biometric_status}</Badge> },{ label: 'انگشت‌های فعال', value: `${data.active_finger_count} / ${data.required_active_fingers}` },{ label: 'کارمند', value: data.employee?.full_name || '—' },{ label: 'شماره کارمندی', value: data.employee?.employee_number || '—' }]} description="FLOW-10A فقط metadata و وضعیت امن را در وب نمایش می‌دهد؛ قالب خام اثر انگشت در این صفحه دریافت یا نمایش داده نمی‌شود." /><Card><CardHeader className="border-b"><CardTitle className="text-base">نسخه‌های ثبت‌شده</CardTitle></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>انگشت</TableHead><TableHead>نسخه</TableHead><TableHead>وضعیت</TableHead><TableHead>کیفیت</TableHead><TableHead>تاریخ ثبت</TableHead></TableRow></TableHeader><TableBody>{(data.templates || []).map((template: Any) => <TableRow key={template.uuid}><TableCell>{template.finger_position}</TableCell><TableCell>{template.version_number}</TableCell><TableCell><Badge variant="outline">{template.status}</Badge></TableCell><TableCell>{template.quality_score ?? '—'}</TableCell><TableCell>{template.enrolled_at ? new Date(template.enrolled_at).toLocaleString('fa-AF') : '—'}</TableCell></TableRow>)}</TableBody></Table></div>{!(data.templates || []).length ? <p className="p-5 text-sm text-muted-foreground">نسخهٔ بایومتریک ثبت نشده است.</p> : null}</CardContent></Card></> : null}
  </div>
}

export function MeetingAttendanceWorkspace() {
  const { id } = useParams()
  const [data, setData] = useState<Any>(null)
  const [reason, setReason] = useState('')
  const [selected, setSelected] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const load = () => api.get(`/meetings/${id}/attendance`).then(r => { setData(dataOf(r)); setError('') }).catch(e => setError(errorOf(e, 'دریافت حضور جلسه ممکن نیست.')))
  useEffect(() => { load() }, [id])
  const manual = async () => { if (!selected || !reason.trim()) { setError('کارمند و دلیل ثبت دستی الزامی است.'); return } setBusy(true); setError(''); try { await api.post(`/meetings/${id}/attendance/manual`, { employee_id: Number(selected), reason }); setReason(''); setSelected(''); await load() } catch (e: Any) { setError(errorOf(e, 'ثبت دستی حضور انجام نشد.')) } finally { setBusy(false) } }
  const records = data?.records || []
  return <div className="space-y-6"><PageHeader eyebrow="FLOW-10A · حضور جلسه" title="ثبت و مشاهدهٔ حضور" description="ثبت اثر انگشت از مسیر ترمینال انجام می‌شود؛ این صفحه فقط وضعیت امن و fallback دستی مجاز را نشان می‌دهد." action={<CalendarCheck className="size-6 text-muted-foreground" />} />{error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}<DetailSummaryCard eyebrow="Attendance" title="خلاصهٔ حضور" icon={<ShieldCheck className="size-5" />} items={[{ label: 'رکوردهای ثبت‌شده', value: String(records.length) },{ label: 'ثبت دستی', value: String(records.filter((r: Any) => r.attendance_method !== 'FINGERPRINT').length) },{ label: 'ثبت بایومتریک', value: String(records.filter((r: Any) => r.attendance_method === 'FINGERPRINT').length) },{ label: 'شناسه جلسه', value: id || '—' }]} /><Card><CardHeader className="border-b"><CardTitle className="text-base">ثبت دستی حضور</CardTitle></CardHeader><CardContent className="grid gap-4 pt-5 md:grid-cols-[1fr_2fr_auto]"><div className="space-y-2"><label className="text-sm font-medium">شناسهٔ کارمند</label><Input dir="ltr" value={selected} onChange={e => setSelected(e.target.value)} /></div><div className="space-y-2"><label className="text-sm font-medium">دلیل اجباری</label><Input value={reason} onChange={e => setReason(e.target.value)} /></div><div className="flex items-end"><Button disabled={busy || !selected || !reason.trim()} onClick={manual}><UserRound className="size-4" />{busy ? 'در حال ثبت…' : 'ثبت حضور دستی'}</Button></div></CardContent></Card><Card><CardHeader className="border-b"><CardTitle className="text-base">رکوردهای حضور</CardTitle></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>کارمند</TableHead><TableHead>روش</TableHead><TableHead>نسخه اشتراک‌کنندگان</TableHead><TableHead>زمان رسمی</TableHead><TableHead>دلیل</TableHead></TableRow></TableHeader><TableBody>{records.map((record: Any) => <TableRow key={record.uuid}><TableCell>{record.employee_id}</TableCell><TableCell><Badge variant={record.attendance_method === 'FINGERPRINT' ? 'default' : 'secondary'}>{record.attendance_method === 'FINGERPRINT' ? 'اثر انگشت' : 'ثبت دستی'}</Badge></TableCell><TableCell>{record.participant_version_id}</TableCell><TableCell>{record.recorded_at_server ? new Date(record.recorded_at_server).toLocaleString('fa-AF') : '—'}</TableCell><TableCell>{record.manual_reason || '—'}</TableCell></TableRow>)}</TableBody></Table></div>{!records.length ? <p className="p-5 text-sm text-muted-foreground">هنوز حضور ثبت نشده است.</p> : null}</CardContent></Card></div>
}
