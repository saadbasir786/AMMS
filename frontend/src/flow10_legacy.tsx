import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { Fingerprint, UserRound, CalendarCheck, ArrowRight, ShieldCheck } from 'lucide-react'

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: any) => response.data?.data ?? response.data

export function BiometricRegistry() {
  const [employees, setEmployees] = useState<any[]>([]); const [error, setError] = useState(''); const navigate = useNavigate()
  useEffect(() => { api.get('/employees').then(r => setEmployees(dataOf(r))).catch(e => setError(e.response?.data?.message || 'دریافت فهرست کارمندان ممکن نیست.')) }, [])
  return <div dir="rtl"><div className="page-title"><div><span className="eyebrow">مدیریت بایومتریک</span><h1>ثبت بایومتریک کارمندان</h1><p>فقط وضعیت و metadata امن نمایش داده می‌شود؛ قالب خام هرگز به مرورگر ارسال نمی‌شود.</p></div></div>{error && <div className="form-error">{error}</div>}<div className="panel table-panel"><table><thead><tr><th>شماره کارمندی</th><th>کارمند</th><th>ریاست</th><th>مدیریت</th></tr></thead><tbody>{employees.map(employee => <tr key={employee.id}><td className="mono">{employee.employee_number}</td><td>{employee.full_name}</td><td>{employee.unit?.name || '—'}</td><td><button className="button small secondary" onClick={() => navigate(`/employees/${employee.id}/biometrics`)}><Fingerprint size={15}/> مشاهدهٔ وضعیت</button></td></tr>)}</tbody></table></div></div>
}

export function BiometricEmployee() {
  const { id } = useParams(); const [data, setData] = useState<any>(null); const [error, setError] = useState(''); const navigate = useNavigate()
  const load = () => api.get(`/employees/${id}/biometrics`).then(r => setData(dataOf(r))).catch(e => setError(e.response?.data?.message || 'دریافت وضعیت بایومتریک ممکن نیست.'))
  useEffect(() => { load() }, [id])
  return <div dir="rtl"><div className="page-title"><div><span className="eyebrow">پروفایل بایومتریک</span><h1>{data?.employee?.full_name || 'کارمند'}</h1><p>شماره کارمندی: {data?.employee?.employee_number || '—'}</p></div><button className="button secondary" onClick={() => navigate('/admin/biometrics')}><ArrowRight size={16}/> بازگشت</button></div>{error && <div className="form-error">{error}</div>}{data && <><div className="stats-grid"><div className="stat-card blue"><div className="stat-icon"><Fingerprint size={20}/></div><div><span>وضعیت ثبت</span><strong>{data.biometric_status}</strong></div></div><div className="stat-card green"><div className="stat-icon"><ShieldCheck size={20}/></div><div><span>انگشت‌های فعال</span><strong>{data.active_finger_count} / {data.required_active_fingers}</strong></div></div></div><div className="panel table-panel"><table><thead><tr><th>انگشت</th><th>نسخه</th><th>وضعیت</th><th>کیفیت</th><th>تاریخ ثبت</th></tr></thead><tbody>{(data.templates || []).map((template:any) => <tr key={template.uuid}><td>{template.finger_position}</td><td>{template.version_number}</td><td>{template.status}</td><td>{template.quality_score ?? '—'}</td><td>{template.enrolled_at ? new Date(template.enrolled_at).toLocaleString('fa-AF') : '—'}</td></tr>)}</tbody></table></div></>}</div>
}

export function MeetingAttendanceWorkspace() {
  const { id } = useParams(); const [data, setData] = useState<any>(null); const [reason, setReason] = useState(''); const [selected, setSelected] = useState(''); const [error, setError] = useState('')
  const load = () => api.get(`/meetings/${id}/attendance`).then(r => setData(dataOf(r))).catch(e => setError(e.response?.data?.message || 'دریافت حضور جلسه ممکن نیست.'))
  useEffect(() => { load() }, [id])
  const manual = async () => { if (!selected || !reason.trim()) return setError('کارمند و دلیل ثبت دستی الزامی است.'); try { await api.post(`/meetings/${id}/attendance/manual`, { employee_id: Number(selected), reason }); setReason(''); load() } catch (e:any) { setError(e.response?.data?.message || 'ثبت دستی حضور انجام نشد.') } }
  return <div dir="rtl"><div className="page-title"><div><span className="eyebrow">حضور جلسه</span><h1>ثبت و مشاهدهٔ حضور</h1><p>ثبت اثر انگشت از مسیر ترمینال انجام می‌شود؛ این صفحه فقط وضعیت امن و fallback دستی مجاز را نشان می‌دهد.</p></div><CalendarCheck size={24}/></div>{error && <div className="form-error">{error}</div>}<div className="panel"><div className="section-title"><span>ثبت دستی حضور</span><small>فقط برای اشتراک‌کنندهٔ رسمی و با صلاحیت governance.</small></div><div className="form-grid"><label>شناسهٔ کارمند<input dir="ltr" value={selected} onChange={e => setSelected(e.target.value)} /></label><label>دلیل اجباری<input value={reason} onChange={e => setReason(e.target.value)} placeholder="مثلاً: اثر انگشت ثبت نشده است" /></label></div><button className="button primary" onClick={manual}><UserRound size={16}/> ثبت حضور دستی</button></div><div className="panel table-panel"><table><thead><tr><th>کارمند</th><th>روش</th><th>نسخهٔ اشتراک‌کنندگان</th><th>زمان رسمی</th><th>دلیل</th></tr></thead><tbody>{(data?.records || []).map((record:any) => <tr key={record.uuid}><td>{record.employee_id}</td><td>{record.attendance_method === 'FINGERPRINT' ? 'حاضر — اثر انگشت' : 'حاضر — ثبت دستی'}</td><td>{record.participant_version_id}</td><td>{record.recorded_at_server ? new Date(record.recorded_at_server).toLocaleString('fa-AF') : '—'}</td><td>{record.manual_reason || '—'}</td></tr>)}</tbody></table>{!(data?.records || []).length && <div className="empty">هنوز حضور ثبت نشده است.</div>}</div></div>
}
