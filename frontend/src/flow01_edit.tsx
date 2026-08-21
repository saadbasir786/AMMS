import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { AlertCircle, ArrowRight, Building2, CheckCircle2, Save, Users } from 'lucide-react'

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => {
  const token = sessionStorage.getItem('amms_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

const unwrap = (res:any) => res.data?.data ?? res.data

export function Flow01EditRequest() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [request, setRequest] = useState<any>(null)
  const [units, setUnits] = useState<any[]>([])
  const [employees, setEmployees] = useState<any[]>([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [form, setForm] = useState<any>({
    subject: '', purpose: 'COORDINATION', description: '', priority: 'NORMAL',
    preferred_date: '', preferred_start_time: '', estimated_duration_minutes: 60,
    proposed_location: '', participant_ids: [],
  })

  useEffect(() => {
    Promise.all([
      api.get(`/meeting-requests/${id}`),
      api.get('/organizational-units'),
      api.get('/employees'),
    ]).then(([requestRes, unitsRes, employeesRes]) => {
      const r = unwrap(requestRes)
      setRequest(r)
      setUnits(unwrap(unitsRes))
      setEmployees(unwrap(employeesRes))
      setForm({
        subject: r.subject || '',
        purpose: r.purpose || 'COORDINATION',
        description: r.description || '',
        priority: r.priority || 'NORMAL',
        preferred_date: r.preferred_date?.slice(0,10) || '',
        preferred_start_time: r.preferred_start_time?.slice(0,5) || '',
        estimated_duration_minutes: r.estimated_duration_minutes || 60,
        proposed_location: r.proposed_location || '',
        participant_ids: (r.participants || []).filter((p:any)=>p.participant_type === 'PROPOSED').map((p:any)=>p.employee?.id ?? p.employee_id),
      })
    }).catch((e:any) => setError(e.response?.data?.message || 'دریافت درخواست ممکن نشد.'))
  }, [id])

  const allowedUnitIds = useMemo(() => {
    const selected = Number(request?.requesting_unit_id)
    const ids = new Set<number>()
    if (!selected) return ids
    ids.add(selected)
    let changed = true
    while (changed) {
      changed = false
      units.forEach((unit:any) => {
        if (unit.parent_id && ids.has(Number(unit.parent_id)) && !ids.has(Number(unit.id))) {
          ids.add(Number(unit.id)); changed = true
        }
      })
    }
    return ids
  }, [units, request?.requesting_unit_id])

  const eligibleEmployees = employees.filter((employee:any) => allowedUnitIds.has(Number(employee.organizational_unit_id)))

  if (error && !request) return <div className="panel form-error">{error}</div>
  if (!request) return <div className="screen-center"><div className="spinner"/></div>
  if (request.flow_type !== 'INTERNAL_DIRECTORATE' || request.status !== 'RETURNED_FOR_REVISION') {
    return <div className="panel form-card"><div className="form-error">این صفحه فقط برای درخواست داخلی برگشت‌شده جهت اصلاح قابل استفاده است.</div><button className="button secondary" onClick={()=>navigate(`/meeting-requests/${id}`)}>بازگشت به درخواست</button></div>
  }

  const save = async (resubmit:boolean) => {
    setBusy(true); setError('')
    try {
      await api.put(`/meeting-requests/${id}`, {
        ...form,
        preferred_start_time: form.preferred_start_time || null,
        preferred_date: form.preferred_date || null,
        proposed_location: form.proposed_location || null,
        participant_ids: form.participant_ids,
      })
      if (resubmit) await api.post(`/meeting-requests/${id}/submit`)
      navigate(`/meeting-requests/${id}`)
    } catch (e:any) {
      setError(e.response?.data?.message || 'ذخیره یا ارسال مجدد درخواست ممکن نشد.')
    } finally { setBusy(false) }
  }

  return <>
    <div className="page-title"><div><span className="eyebrow">FLOW-01 · اصلاح درخواست برگشتی</span><h1>{request.subject}</h1><p>{request.request_number} · {request.requesting_unit?.name}</p></div><button className="button secondary" onClick={()=>navigate(`/meeting-requests/${id}`)}>انصراف</button></div>
    <div className="form-layout"><section className="panel form-card">
      <div className="approved-strip" style={{background:'#fff7e9',color:'#8a5b14'}}><AlertCircle size={18}/><span>این درخواست توسط رئیس ریاست برای اصلاح برگشت داده شده است. پس از اصلاح می‌توانید دوباره برای تأیید ارسال کنید.</span></div>
      <div className="section-title"><span>اطلاعات درخواست</span><small>ریاست درخواست‌کننده در این مرحله قابل تغییر نیست.</small></div>
      <div className="form-grid"><label>ریاست<input value={request.requesting_unit?.name || ''} disabled/></label><label>موضوع<input value={form.subject} onChange={e=>setForm({...form,subject:e.target.value})}/></label><label>هدف<select value={form.purpose} onChange={e=>setForm({...form,purpose:e.target.value})}><option value="DECISION">تصمیم‌گیری</option><option value="COORDINATION">هماهنگی</option><option value="CONSULTATION">مشورت</option><option value="REPORTING">گزارش‌دهی</option><option value="APPROVAL">تأیید</option><option value="FOLLOW_UP">پیگیری</option><option value="OTHER">سایر</option></select></label><label>اولویت<select value={form.priority} onChange={e=>setForm({...form,priority:e.target.value})}><option value="NORMAL">عادی</option><option value="HIGH">بلند</option><option value="URGENT">عاجل</option></select></label></div>
      <label>شرح و توضیحات<textarea rows={5} value={form.description} onChange={e=>setForm({...form,description:e.target.value})}/></label>
      <div className="section-title separated"><span>پیشنهاد اولیهٔ زمان و مکان</span><small>این‌ها همچنان پیشنهاد هستند و برنامهٔ رسمی جلسه محسوب نمی‌شوند.</small></div>
      <div className="form-grid three"><label>تاریخ پیشنهادی<input type="date" value={form.preferred_date} onChange={e=>setForm({...form,preferred_date:e.target.value})}/></label><label>زمان پیشنهادی<input type="time" value={form.preferred_start_time} onChange={e=>setForm({...form,preferred_start_time:e.target.value})}/></label><label>مدت پیشنهادی (دقیقه)<input type="number" min={15} max={480} value={form.estimated_duration_minutes} onChange={e=>setForm({...form,estimated_duration_minutes:Number(e.target.value)})}/></label></div>
      <label>محل پیشنهادی<input value={form.proposed_location} onChange={e=>setForm({...form,proposed_location:e.target.value})}/></label>
      <div className="section-title separated"><span>اشتراک‌کنندگان پیشنهادی</span><small>فقط کارمندان ریاست و واحدهای فرعی آن.</small></div>
      <div className="participant-picker">{eligibleEmployees.map((e:any)=><label className="check-row" key={e.id}><input type="checkbox" checked={form.participant_ids.includes(e.id)} onChange={ev=>setForm({...form,participant_ids:ev.target.checked?[...form.participant_ids,e.id]:form.participant_ids.filter((x:number)=>x!==e.id)})}/><span>{e.full_name}</span><small>{e.unit?.name}</small></label>)}</div>
      {error&&<div className="form-error">{error}</div>}
      <div className="form-actions"><button className="button secondary" disabled={busy} onClick={()=>save(false)}><Save size={16}/> ذخیره اصلاحات</button><button className="button primary" disabled={busy} onClick={()=>save(true)}>{busy?'در حال ارسال...':'ذخیره و ارسال مجدد'} <ArrowRight size={16}/></button></div>
    </section><aside className="panel side-note"><Building2 size={22}/><h3>بازگشت برای اصلاح</h3><p>تأیید قبلی ایجاد نشده است؛ پس از ارسال مجدد، درخواست دوباره وارد صف رئیس همان ریاست می‌شود.</p><div className="note-line"><CheckCircle2 size={16}/> حفظ شماره درخواست</div><div className="note-line"><Users size={16}/> کنترل دوباره مرز اشتراک‌کنندگان</div><div className="note-line"><CheckCircle2 size={16}/> ثبت RESUBMITTED در تاریخچه</div></aside></div>
  </>
}
