import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { AlertCircle, CheckCircle2, Clock3, GitBranch, History, Plus, RefreshCw, ShieldCheck, Trash2, Users } from 'lucide-react'

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response:any) => response.data?.data ?? response.data
const faDate = (value:any) => value ? new Date(value).toLocaleDateString('fa-AF') : '—'
const errorOf = (error:any) => error?.response?.data?.message || error?.response?.data?.code || 'عملیات تغییر اشتراک‌کنندگان انجام نشد.'
const operationLabels: Record<string,string> = { ADD: 'افزودن', REMOVE: 'حذف', REPLACE: 'جایگزینی' }
const roleUnitId = (role:any) => Number(role?.organizational_unit_id ?? role?.unit?.id ?? 0)
const hasScopedRole = (user:any, roleName:string, unitId:number) => !!unitId && !!user?.roles?.some((role:any) => role.role === roleName && roleUnitId(role) === Number(unitId))

function Person({person, muted=false}:{person:any;muted?:boolean}) {
  return <div className={`person-row ${muted?'muted-row':''}`}><div className="avatar tiny">{person?.employee?.full_name?.slice(0,1)||'ک'}</div><div><strong>{person?.employee?.full_name||'—'}</strong><span>{person?.employee?.unit?.name||person?.organizational_unit?.name||'—'}</span></div>{person?.participant_role&&<span className="tag">{person.participant_role}</span>}</div>
}

function ScheduleContext({schedule,version}:{schedule:any;version?:number}) {
  if (!schedule) return <div className="empty"><Clock3 size={20}/><span>زمان‌بندی جاری ثبت نشده است.</span></div>
  return <div className="schedule-card"><span className="eyebrow">زمان فعلی جلسه</span><strong>{faDate(schedule.meeting_date)}</strong><span>{schedule.start_time} تا {schedule.end_time}</span><span>{schedule.location}</span>{version&&<small>نسخهٔ زمان‌بندی: {version}</small>}</div>
}

export function MeetingParticipantChangeWorkspace() {
  const id = location.pathname.split('/')[2]
  const [workspace,setWorkspace] = useState<any>(null)
  const [user,setUser] = useState<any>(null)
  const [reason,setReason] = useState('')
  const [operation,setOperation] = useState('REPLACE')
  const [oldEmployeeId,setOldEmployeeId] = useState('')
  const [newEmployeeId,setNewEmployeeId] = useState('')
  const [participantRole,setParticipantRole] = useState('PARTICIPATING')
  const [returnReason,setReturnReason] = useState('')
  const [error,setError] = useState('')
  const [loadError,setLoadError] = useState('')
  const [busy,setBusy] = useState(false)

  const load = () => api.get(`/meetings/${id}/participant-change`).then(r => { setLoadError(''); setWorkspace(dataOf(r)) }).catch(e => { setLoadError(errorOf(e)); throw e })
  useEffect(() => { load().catch(()=>{}); api.get('/me').then(r=>setUser(dataOf(r))).catch(()=>{}) }, [id])

  const call = async (fn:()=>Promise<any>) => { setBusy(true); setError(''); try { await fn(); await load() } catch (e:any) { setError(errorOf(e)) } finally { setBusy(false) } }
  const meeting = workspace?.meeting
  const current = workspace?.current_participant_version
  const activeCase = workspace?.active_participant_change_case
  const currentParticipants = workspace?.current_participants || []
  const history = useMemo(() => [...(workspace?.participant_versions||[])].sort((a:any,b:any)=>a.version_number-b.version_number), [workspace])
  const activeItems = activeCase?.items || []
  const formalUnitIds=(meeting?.units||[]).filter((unit:any)=>['REQUESTING','PARTICIPATING'].includes(unit.role)).map((unit:any)=>Number(unit.organizational_unit_id)).filter(Boolean)
  const authorityOfficeId=Number(meeting?.authority_context?.authority_office_unit_id||0)
  const actorExecutiveUnitId=formalUnitIds.find((unitId:number)=>hasScopedRole(user,'EXECUTIVE_MANAGEMENT',unitId))||0
  const isAuthorityOffice=hasScopedRole(user,'AUTHORITY_OFFICE',authorityOfficeId)
  const canCreateCase=!!actorExecutiveUnitId || isAuthorityOffice
  const activeOwnerUnitId=Number(activeCase?.owner_unit_id||0)
  const ownsActiveUnit=activeCase?.owner_type==='UNIT' && activeOwnerUnitId>0
  const isPreparer=!!activeCase ? (activeCase.owner_type==='AUTHORITY' ? isAuthorityOffice : ownsActiveUnit && hasScopedRole(user,'EXECUTIVE_MANAGEMENT',activeOwnerUnitId)) : canCreateCase
  const isConfirmer=!!activeCase && (activeCase.owner_type==='AUTHORITY' ? isAuthorityOffice : ownsActiveUnit && hasScopedRole(user,'DIRECTOR',activeOwnerUnitId))
  const staleSchedule=!!activeCase && Number(activeCase.baseline_schedule_version_id)!==Number(meeting?.current_schedule_version_id)
  const employeeOptions=workspace?.employee_options || []
  const ownerCurrentParticipants=activeCase?.owner_type==='UNIT'
    ? currentParticipants.filter((p:any)=>Number(p.owner_unit_id ?? p.organizational_unit_id)===activeOwnerUnitId || Number(p.organizational_unit_id)===activeOwnerUnitId)
    : activeCase?.owner_type==='AUTHORITY'
      ? currentParticipants.filter((p:any)=>p.owner_type==='AUTHORITY' || Number(p.organizational_unit_id)===authorityOfficeId || Number(p.employee_id)===Number(meeting?.authority_context?.authority_employee_id))
      : currentParticipants

  const createCase = () => call(() => api.post(`/meetings/${id}/participant-change-cases`, { reason }))
  const addItem = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/items`, {
    operation,
    old_employee_id: operation === 'ADD' ? null : Number(oldEmployeeId),
    new_employee_id: operation === 'REMOVE' ? null : Number(newEmployeeId),
    participant_role: participantRole,
  }))
  const removeItem = (item:any) => call(() => api.delete(`/meetings/${id}/participant-change/${activeCase.id}/items/${item.id}`))
  const removeParticipant = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/remove-item`, { employee_id: Number(oldEmployeeId) }))
  const submit = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/submit`))
  const confirm = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/confirm`))
  const refresh = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/refresh-baseline`))
  const returnForCorrection = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/return`, { reason: returnReason }))
  const reject = () => call(() => api.post(`/meetings/${id}/participant-change/${activeCase.id}/reject`, { reason: returnReason }))

  if (!workspace) return <div className="screen-center">{loadError?<div className="panel form-error"><AlertCircle size={18}/><div><strong>دسترسی به فضای تغییر اشتراک‌کنندگان ممکن نیست.</strong><p>{loadError}</p><a className="button secondary" href={`/meetings/${id}`}>بازگشت به جلسه</a></div></div>:<div className="spinner"/>}</div>

  return <>
    <div className="page-title"><div><span className="eyebrow">FLOW-08 · کنترل پس از ایجاد جلسه</span><h1>تغییر فهرست اشتراک‌کنندگان</h1><p>{meeting?.meeting_number} · {meeting?.title}</p></div><div className="page-actions"><a className="button secondary" href={`/meetings/${id}`}>بازگشت به جلسه</a>{activeCase&&<span className="status pending">تغییر فهرست اشتراک‌کنندگان در جریان است</span>}</div></div>
    {error&&<div className="form-error"><AlertCircle size={16}/><span>{error}</span></div>}
    <div className="schedule-diff"><ScheduleContext schedule={workspace?.current_schedule_version} version={workspace?.current_schedule_version?.version_number}/>{activeCase?<><div className="diff-arrow"><GitBranch size={20}/></div><div className="compare"><span>نسخهٔ مبنای پرونده</span><strong>{activeCase.baseline_schedule_version_id}</strong><span>نسخهٔ فهرست جاری</span><strong>{current?.version_number||'—'}</strong></div></>:<div className="diff-arrow"><RefreshCw size={20}/></div>}</div>

    <div className="detail-grid"><section className="detail-main">
      <div className="panel"><div className="panel-head"><div><span className="eyebrow">نسخهٔ جاری · {current?.version_number||'—'}</span><h2>اشتراک‌کنندگان فعلی</h2></div><Users size={20}/></div><p className="muted">فهرست رسمی تا زمان confirmation تغییر نمی‌کند.</p><div className="people-list">{currentParticipants.map((p:any)=><Person key={p.id} person={p}/>)}</div>{currentParticipants.length===0&&<div className="empty">فهرست فعلی خالی است.</div>}</div>

      {!activeCase&&!canCreateCase&&<div className="panel form-error"><ShieldCheck size={18}/><span>برای این جلسه پروندهٔ فعال وجود ندارد و شما صلاحیت ایجاد تغییر ندارید.</span></div>}
      {!activeCase&&canCreateCase&&<div className="panel form-card"><div className="section-title"><span><Plus size={16}/> ایجاد پروندهٔ رسمی تغییر</span><small>پرونده بر اساس scope فعلی شما به ریاست یا دفتر مقام تعلق می‌گیرد.</small></div><textarea rows={3} placeholder="مثلاً: به دلیل مأموریت رسمی نماینده قبلی..." value={reason} onChange={e=>setReason(e.target.value)}/><button className="button primary" disabled={busy||!reason.trim()} onClick={createCase}>ایجاد پروندهٔ تغییر فهرست</button></div>}

      {activeCase&&<div className="panel"><div className="panel-head"><div><span className="eyebrow">پروندهٔ {activeCase.status}</span><h2>تغییر پیشنهادی</h2><p>{activeCase.reason}</p></div><span className="tag">مالک: {activeCase.owner_type==='AUTHORITY'?'دفتر مقام':activeCase.ownerUnit?.name||'ریاست'}</span></div><div className="compare"><span>وضعیت جلسه</span><strong>{meeting?.status==='SCHEDULED'?'زمان‌بندی‌شده':meeting?.status}</strong><span>نسخهٔ مبنای فهرست</span><strong>{activeCase.baseline_participant_version_id}</strong><span>آیتم‌ها</span><strong>{activeItems.length}</strong></div>{activeItems.length>0&&<div className="proposal-list">{activeItems.map((item:any)=><div className="proposal-row" key={item.id}><div><strong>{operationLabels[item.operation]}</strong><span>{item.oldEmployee?.full_name||'—'} {item.operation==='REPLACE'?'←':''} {item.newEmployee?.full_name||'—'}</span></div>{isPreparer&&['DRAFT','RETURNED_FOR_CORRECTION'].includes(activeCase.status)&&<button className="icon-button" onClick={()=>removeItem(item)} title="حذف آیتم"><Trash2 size={16}/></button>}</div>)}</div>}</div>}

      {activeCase&&isPreparer&&['DRAFT','RETURNED_FOR_CORRECTION'].includes(activeCase.status)&&<div className="panel form-card"><div className="section-title"><span><Plus size={16}/> افزودن تغییر پیشنهادی</span><small>گزینه‌ها مستقیماً از employee_options محدودشدهٔ Backend می‌آیند.</small></div><label>نوع عملیات<select value={operation} onChange={e=>setOperation(e.target.value)}><option value="REPLACE">جایگزینی</option><option value="ADD">افزودن</option><option value="REMOVE">حذف</option></select></label>{operation!=='ADD'&&<label>اشتراک‌کنندهٔ فعلی<select value={oldEmployeeId} onChange={e=>setOldEmployeeId(e.target.value)}><option value="">انتخاب کارمند</option>{ownerCurrentParticipants.map((p:any)=><option value={p.employee_id} key={p.employee_id}>{p.employee?.full_name}</option>)}</select></label>}{operation!=='REMOVE'&&<label>اشتراک‌کنندهٔ جدید<select value={newEmployeeId} onChange={e=>setNewEmployeeId(e.target.value)}><option value="">انتخاب کارمند</option>{employeeOptions.map((e:any)=><option value={e.id} key={e.id}>{e.full_name} · {e.unit?.name}</option>)}</select></label>}<label>نقش مشارکت<input value={participantRole} onChange={e=>setParticipantRole(e.target.value)}/></label><button className="button primary" disabled={busy||(operation!=='ADD'&&!oldEmployeeId)||(operation!=='REMOVE'&&!newEmployeeId)} onClick={operation==='REMOVE'?removeParticipant:addItem}>ثبت تغییر پیشنهادی</button></div>}

      {activeCase&&staleSchedule&&<div className="panel form-error"><AlertCircle size={18}/><div><strong>زمان جلسه پس از ایجاد این پرونده تغییر کرده است.</strong><p>قبل از confirmation باید baseline زمان‌بندی بازنگری شود.</p>{isPreparer&&<button className="button secondary" disabled={busy} onClick={refresh}>بازنگری بر اساس زمان فعلی</button>}</div></div>}

      {activeCase&&activeCase.status==='PENDING_CONFIRMATION'&&<div className="panel action-panel"><span className="eyebrow">تأیید صریح مالک</span><h3>تغییرات پس از بررسی نهایی اعمال می‌شود</h3><p className="muted">Backend نسخهٔ participant و schedule را دوباره کنترل می‌کند.</p>{isConfirmer&&<><button className="button success full" disabled={busy||staleSchedule} onClick={confirm}><CheckCircle2 size={16}/> تأیید و ایجاد نسخهٔ جدید</button><textarea placeholder="دلیل برگشت یا رد" value={returnReason} onChange={e=>setReturnReason(e.target.value)}/><div className="form-actions"><button className="button warning" disabled={busy||!returnReason.trim()} onClick={returnForCorrection}>برگشت جهت اصلاح</button><button className="button danger" disabled={busy} onClick={reject}>رد پرونده</button></div></>}</div>}

      {activeCase&&['DRAFT','RETURNED_FOR_CORRECTION'].includes(activeCase.status)&&isPreparer&&<div className="panel action-panel"><span className="eyebrow">ارسال برای بررسی</span><h3>{activeCase.status==='RETURNED_FOR_CORRECTION'?'اصلاحات را تکمیل و دوباره ارسال کنید':'فهرست رسمی هنوز تغییر نکرده است'}</h3><button className="button primary full" disabled={busy||activeItems.length===0} onClick={submit}>ارسال برای confirmation</button></div>}
    </section>

    <aside className="detail-side"><div className="panel"><div className="panel-head"><div><span className="eyebrow">تاریخچهٔ immutable</span><h3>نسخه‌های فهرست</h3></div><History size={19}/></div>{history.map((version:any)=><div className={`proposal-row ${version.id===current?.id?'current':''}`} key={version.id}><div><strong>نسخهٔ {version.version_number}</strong><span>{version.members?.length||0} اشتراک‌کننده · {faDate(version.created_at)}</span></div><span className="tag">{version.id===current?.id?'جاری':'تاریخی'}</span></div>)}</div><div className="panel"><div className="panel-head"><div><span className="eyebrow">رویدادهای participant</span><h3>خط زمانی</h3></div><Clock3 size={19}/></div>{(workspace?.participant_actions||[]).map((a:any)=><div className="timeline-item" key={a.id}><div className="timeline-dot"/><div><strong>{a.action}</strong><span>{a.actor?.name||'کاربر'} · {new Date(a.created_at).toLocaleString('fa-AF')}</span></div></div>)}</div></aside>
    </div>
  </>
}
