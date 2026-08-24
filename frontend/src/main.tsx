import React, { createContext, useContext, useEffect, useMemo, useState } from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import axios from 'axios'
import { LayoutDashboard, FilePlus2, ClipboardCheck, CalendarDays, Bell, Network, Users, ShieldCheck, LogOut, Menu, X, ChevronLeft, Search, Plus, CheckCircle2, Clock3, AlertCircle, ArrowRight, Building2, UserRound, Eye, Paperclip, Save, Monitor, Fingerprint, BarChart3, Download } from 'lucide-react'
import './styles.css'
import { ApplicationShell } from '@/components/layout/application-shell'
import { PageHeader } from '@/components/amms/page-header'
import { MultiDirectorateDetail, MultiDirectorateScheduling } from './flow03'
import { HighAuthorityDetail, HighAuthorityScheduling, HighAuthorityInbox } from './flow04'
import { MultiHighAuthorityDetail, MultiHighAuthorityScheduling, MultiHighAuthorityInbox } from './flow05'
import { AuthorityInitiatedDetail, AuthorityInitiatedScheduling, AuthorityInitiatedInbox } from './flow06'
import { ReportingCenter } from './flow14'
import { Flow01EditRequest } from './flow01_edit'
import { CreateInterDirectorateRequestV2, Flow02DetailV2, Flow02SchedulingV2 } from './flow02'
import { LoginPage } from '@/features/auth'
import { DashboardPage } from '@/features/dashboard'
import {
  RequestsPage,
  IncomingRequestsPage,
  CreateRequestPage,
  RequestDetailPage,
} from '@/features/meeting-requests'
import { SchedulingPage } from '@/features/scheduling'
import {
  MeetingsPage,
  MeetingDetailPage,
  MeetingExecutionPage,
  MeetingMinutesPage,
  MeetingReschedulePage,
  MeetingParticipantChangePage,
  MeetingExceptionsPage,
} from '@/features/meetings'
import {
  ActionItemsPage,
  ActionItemDetailPage,
  MeetingFollowUpPage,
} from '@/features/action-items'
import {
  MeetingAttendancePage,
  MeetingTerminalsPage,
  TerminalsRegistryPage,
  TerminalDetailPage,
  BiometricsRegistryPage,
  EmployeeBiometricsPage,
} from '@/features/attendance-biometrics'
import { api, apiData, apiPage } from '@/lib/api-client'

type User = { id:number; name:string; email:string; employee?: { full_name:string; unit?: {name:string} }; roles?: {role:string; unit?: {name:string}}[] }
type AuthState = { user: User|null; loading:boolean; login:(email:string,password:string)=>Promise<void>; logout:()=>Promise<void> }
const AuthContext = createContext<AuthState>({user:null,loading:true,login:async()=>{},logout:async()=>{}})
const useAuth = () => useContext(AuthContext)
const statusLabels: Record<string,string> = {SCHEDULED:'زمان‌بندی‌شده',POSTPONED:'به تعویق افتاده',CANCELLED:'لغو شده',IN_PROGRESS:'در حال برگزاری',HELD:'پایان‌یافته',COMPLETED:'تکمیل‌شده',DRAFT:'پیش‌نویس',PENDING_INTERNAL_APPROVAL:'منتظر تأیید',RETURNED_FOR_REVISION:'برگشت جهت اصلاح',REJECTED:'رد شده',APPROVED:'تأیید شده',SCHEDULING:'در حال زمان‌بندی',SCHEDULE_CONFIRMED:'زمان‌بندی نهایی',CONVERTED_TO_MEETING:'تبدیل به جلسه',PENDING_REQUESTER_APPROVAL:'منتظر تأیید ریاست درخواست‌کننده',RETURNED_TO_REQUESTER:'برگشت به درخواست‌کننده',REQUESTER_APPROVED:'تأیید ریاست درخواست‌کننده',REJECTED_INTERNAL:'رد داخلی',SUBMITTED_TO_TARGET:'ارسال‌شده به ریاست مقصد',UNDER_TARGET_REVIEW:'در حال بررسی ریاست مقصد',RETURNED_BY_TARGET:'برگشت از ریاست مقصد',RESUBMITTED_TO_TARGET:'ارسال مجدد به ریاست مقصد',TARGET_APPROVED:'تأیید ریاست مقصد',REJECTED_BY_TARGET:'رد توسط ریاست مقصد',BILATERAL_APPROVED:'تأیید دوطرفه',SUBMITTED_TO_TARGETS:'ارسال‌شده به ریاست‌های مقصد',UNDER_MULTI_TARGET_REVIEW:'در حال بررسی ریاست‌های مقصد',TARGET_ACTION_REQUIRED:'نیازمند اصلاح از سوی ریاست‌های مقصد',MULTILATERAL_APPROVED:'تأیید چندجانبه',SUBMITTED_TO_PARTICIPATING_DIRECTORATES:'ارسال‌شده به ریاست‌های اشتراک‌کننده',UNDER_PARTICIPATING_REVIEW:'در حال بررسی ریاست‌های اشتراک‌کننده',DIRECTORATE_ACTION_REQUIRED:'نیازمند اصلاح از سوی ریاست اشتراک‌کننده',DIRECTORATES_APPROVED:'تأیید همهٔ ریاست‌ها',REJECTED_BY_PARTICIPATING_DIRECTORATE:'رد توسط ریاست اشتراک‌کننده',SUBMITTED_TO_AUTHORITY_OFFICE:'ارسال‌شده به دفتر مقام',UNDER_AUTHORITY_OFFICE_REVIEW:'در حال بررسی دفتر مقام',RETURNED_BY_AUTHORITY_OFFICE:'برگشت از دفتر مقام',PENDING_HIGH_AUTHORITY_DECISION:'منتظر تصمیم مقام عالی',RETURNED_BY_HIGH_AUTHORITY:'برگشت از مقام عالی',REJECTED_BY_HIGH_AUTHORITY:'رد توسط مقام عالی',HIGH_AUTHORITY_APPROVED:'تأیید مقام عالی',AUTHORITY_SCHEDULING:'در حال زمان‌بندی دفتر مقام',PENDING_HIGH_AUTHORITY_ISSUANCE:'منتظر صدور مقام عالی',RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION:'برگشت برای اصلاح مسوده',ISSUED_BY_HIGH_AUTHORITY:'صادرشده توسط مقام عالی',DISTRIBUTED_TO_DIRECTORATES:'توزیع‌شده به ریاست‌ها',UNDER_DIRECTORATE_COORDINATION:'در حال هماهنگی ریاست‌ها',DIRECTORATE_CLARIFICATION_REQUIRED:'نیازمند وضاحت ریاست',PENDING_HIGH_AUTHORITY_REISSUANCE:'منتظر صدور مجدد مقام عالی',DIRECTORATES_ACKNOWLEDGED:'تأیید دریافت همهٔ ریاست‌ها'}
const purposeLabels: Record<string,string> = {DECISION:'تصمیم‌گیری',COORDINATION:'هماهنگی',CONSULTATION:'مشورت',REPORTING:'گزارش‌دهی',APPROVAL:'تأیید',FOLLOW_UP:'پیگیری',OTHER:'سایر'}
const priorityLabels: Record<string,string> = {NORMAL:'عادی',HIGH:'بلند',URGENT:'عاجل'}

function AuthProvider({children}:{children:React.ReactNode}) {
  const [user,setUser] = useState<User|null>(null); const [loading,setLoading] = useState(true)
  useEffect(()=>{ if(!sessionStorage.getItem('amms_token')) return setLoading(false); api.get('/me').then(r=>setUser(apiData(r))).catch(()=>sessionStorage.removeItem('amms_token')).finally(()=>setLoading(false)) },[])
  const login = async (email:string,password:string) => { const data=apiData(await api.post('/login',{email,password})); sessionStorage.setItem('amms_token',data.token); setUser(data.user) }
  const logout = async () => { try { await api.post('/logout') } finally { sessionStorage.removeItem('amms_token'); setUser(null) } }
  return <AuthContext.Provider value={{user,loading,login,logout}}>{children}</AuthContext.Provider>
}
function Protected({children}:{children:React.ReactNode}) { const {user,loading}=useAuth(); if(loading) return <div className="screen-center"><div className="spinner"/></div>; return user?<>{children}</>:<Navigate to="/login" replace/> }
function RoleGate({role,children}:{role:string;children:React.ReactNode}) { const {user}=useAuth(); return user?.roles?.some(r=>r.role===role)?<>{children}</>:null }

const navItems = [
  {to:'/dashboard',label:'داشبورد',icon:LayoutDashboard}, {to:'/meeting-requests',label:'درخواست‌های جلسه',icon:FilePlus2,roles:['EMPLOYEE','DIRECTOR','EXECUTIVE_MANAGEMENT','AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','HIGH_AUTHORITY']}, {to:'/incoming-meeting-requests',label:'درخواست‌های وارده',icon:ClipboardCheck,roles:['EXECUTIVE_MANAGEMENT','DIRECTOR']},   {to:'/incoming-high-authority',label:'درخواست‌های دفتر مقام',icon:ShieldCheck,roles:['AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','HIGH_AUTHORITY']}, {to:'/incoming-authority-initiated',label:'دستورهای ابتکاری مقام',icon:ShieldCheck,roles:['AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','HIGH_AUTHORITY']},
 {to:'/approvals',label:'تأیید درخواست‌ها',icon:ClipboardCheck,role:'DIRECTOR'}, {to:'/meetings',label:'جلسات',icon:CalendarDays,roles:['EMPLOYEE','DIRECTOR','EXECUTIVE_MANAGEMENT','AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','HIGH_AUTHORITY']}, {to:'/action-items',label:'اقدامات من',icon:CheckCircle2,roles:['EMPLOYEE','DIRECTOR','EXECUTIVE_MANAGEMENT','AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','HIGH_AUTHORITY']}, {to:'/notifications',label:'اعلان‌ها',icon:Bell}, {to:'/admin/organization',label:'ساختار تشکیلاتی',icon:Network,role:'SYSTEM_ADMIN'}, {to:'/admin/employees',label:'کارمندان',icon:Users,role:'SYSTEM_ADMIN'}, {to:'/admin/roles',label:'کاربران و صلاحیت‌ها',icon:ShieldCheck,role:'SYSTEM_ADMIN'}, {to:'/admin/terminals',label:'ترمینال‌ها',icon:Monitor,role:'SYSTEM_ADMIN'}, {to:'/admin/biometrics',label:'بایومتریک',icon:Fingerprint,role:'SYSTEM_ADMIN'}, {to:'/reports',label:'گزارش‌ها و تحلیل',icon:BarChart3,roles:['EXECUTIVE_MANAGEMENT','HIGH_AUTHORITY','AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','DIRECTOR']}, {to:'/reports/exports',label:'صادرات گزارش‌ها',icon:Download,roles:['EXECUTIVE_MANAGEMENT','HIGH_AUTHORITY','AUTHORITY_OFFICE','DIRECTOR']},
]
function Shell({children}:{children:React.ReactNode}) {
  const {user,logout}=useAuth()
  const location=useLocation()
  const navigate=useNavigate()
  return <ApplicationShell user={user} navItems={navItems} currentPath={location.pathname} onNavigate={(target)=>navigate(target)} onLogout={logout}>{children}</ApplicationShell>
}
function PageTitle({eyebrow,title,description,action}:{eyebrow?:string;title:string;description?:string;action?:React.ReactNode}) {
  return <PageHeader eyebrow={eyebrow} title={title} description={description} action={action}/>
}
function Dashboard() {
  const { user } = useAuth()
  return <DashboardPage user={user} statusLabels={statusLabels} />
}
function StatusBadge({value}:{value:string}) { return <span className={`status ${value?.toLowerCase()}`}>{statusLabels[value]||value}</span> }
function EmptyState({text}:{text:string}) { return <div className="empty"><ClipboardCheck size={24}/><span>{text}</span></div> }

function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  if (user) return <Navigate to="/dashboard" replace />
  const handleLogin = async (email: string, pass: string) => {
    await login(email, pass)
    navigate('/dashboard')
  }
  return <LoginPage onLogin={handleLogin} />
}

function Requests() {
  return (
    <RequestsPage
      statusLabels={statusLabels}
      priorityLabels={priorityLabels}
      purposeLabels={purposeLabels}
    />
  )
}

function IncomingRequests() {
  return (
    <IncomingRequestsPage
      statusLabels={statusLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function CreateInterDirectorateRequest() {
  return (
    <CreateRequestPage
      mode="inter-directorate"
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function Flow02EditPanel({r,reload}:{r:any;reload:()=>void}) { const [form,setForm]=useState<any>({subject:r.subject||'',description:r.description||'',preferred_date:r.preferred_date?.slice(0,10)||'',preferred_start_time:r.preferred_start_time||'',estimated_duration_minutes:r.estimated_duration_minutes||60,proposed_location:r.proposed_location||''});const [file,setFile]=useState<File|null>(null);const [busy,setBusy]=useState(false);const [error,setError]=useState('');const saveChanges=async(submit:boolean)=>{setBusy(true);setError('');try{await api.put(`/meeting-requests/${r.id}`,form);if(file){const fd=new FormData();fd.append('attachment',file);await api.post(`/meeting-requests/${r.id}/attachments`,fd,{headers:{'Content-Type':'multipart/form-data'}})}if(submit) await api.post(`/meeting-requests/${r.id}/resubmit-to-target`);setFile(null);reload()}catch(e:any){setError(e.response?.data?.message||'ذخیره یا ارسال مجدد انجام نشد.')}finally{setBusy(false)}};return <div className="panel form-card flow02-edit"><div className="section-title"><span><Paperclip size={16}/> اصلاح و ضمیمهٔ درخواست</span><small>پس از برگشت مقصد، اصلاحات و ضمیمه‌ها قبل از ارسال مجدد ثبت می‌شوند.</small></div><div className="form-grid"><label>موضوع<input value={form.subject} onChange={e=>setForm({...form,subject:e.target.value})}/></label><label>محل پیشنهادی<input value={form.proposed_location} onChange={e=>setForm({...form,proposed_location:e.target.value})}/></label></div><label>شرح اصلاح‌شده<textarea rows={4} value={form.description} onChange={e=>setForm({...form,description:e.target.value})}/></label><div className="form-grid three"><label>تاریخ<input type="date" value={form.preferred_date} onChange={e=>setForm({...form,preferred_date:e.target.value})}/></label><label>زمان آغاز<input type="time" value={form.preferred_start_time} onChange={e=>setForm({...form,preferred_start_time:e.target.value})}/></label><label>مدت<input type="number" value={form.estimated_duration_minutes} onChange={e=>setForm({...form,estimated_duration_minutes:Number(e.target.value)})}/></label></div><div className="file-input-row"><label><Paperclip size={16}/> ضمیمهٔ جدید<input type="file" onChange={e=>setFile(e.target.files?.[0]||null)}/></label>{file&&<span>{file.name}</span>}</div>{(r.attachments||[]).length>0&&<div className="attachment-list">{r.attachments.map((a:any)=><a key={a.id} href={`/api/meeting-request-attachments/${a.id}/download`} target="_blank" rel="noreferrer"><Paperclip size={14}/>{a.original_name}</a>)}</div>}{error&&<div className="form-error">{error}</div>}<div className="form-actions"><button className="button secondary" disabled={busy} onClick={()=>saveChanges(false)}><Save size={16}/> ذخیرهٔ اصلاحات</button><button className="button primary" disabled={busy} onClick={()=>saveChanges(true)}>{busy?'در حال ذخیره...':'ذخیره و ارسال مجدد'} <ArrowRight size={16}/></button></div></div> }

function Flow02Detail({r,reload}:{r:any;reload:()=>void}) { const {user}=useAuth(); const [reason,setReason]=useState('');const action=async(url:string,body?:any)=>{try{await api.post(`/meeting-requests/${r.id}/${url}`,body);setReason('');reload()}catch(e:any){alert(e.response?.data?.message||'عملیات انجام نشد.')}};const requester=r.units?.find((u:any)=>u.role==='REQUESTER');const target=r.units?.find((u:any)=>u.role==='TARGET');const nominations=r.nominations||[];const canStartScheduling=!!user?.roles?.some((role:any)=>['EXECUTIVE_MANAGEMENT'].includes(role.role)&&[requester?.organizational_unit_id,target?.organizational_unit_id].includes(Number(role.unit?.id||role.organizational_unit_id)));return <><PageTitle eyebrow={`${r.request_number||'پیش‌نویس'} · جلسه بین دو ریاست`} title={r.subject} description={`${requester?.unit?.name||r.requesting_unit?.name||'—'} ↔ ${target?.unit?.name||'—'}`} action={<div className="page-actions"><StatusBadge value={r.status}/><a className="button secondary" href="/meeting-requests">بازگشت به فهرست</a></div>}/><div className="detail-grid"><section className="detail-main"><div className="panel"><div className="detail-header"><div><span className="eyebrow">مشخصات درخواست بین‌ریاستی</span><h2>{r.subject}</h2></div><Building2 size={22}/></div><div className="info-grid"><div><span>ریاست درخواست‌کننده</span><strong>{requester?.unit?.name||r.requesting_unit?.name}</strong></div><div><span>ریاست مقصد</span><strong>{target?.unit?.name||'—'}</strong></div><div><span>هدف جلسه</span><strong>{purposeLabels[r.purpose]||r.purpose}</strong></div><div><span>اولویت</span><strong>{priorityLabels[r.priority]}</strong></div><div><span>تأیید درخواست‌کننده</span><strong>{r.requester_approved_at?'تأیید شده':'در انتظار'}</strong></div><div><span>تأیید مقصد</span><strong>{r.target_approved_at?'تأیید شده':'در انتظار'}</strong></div><div><span>زمان پیشنهادی</span><strong>{r.preferred_date?new Date(r.preferred_date).toLocaleDateString('fa-AF'):'تعیین نشده'} {r.preferred_start_time||''}</strong></div><div><span>محل پیشنهادی</span><strong>{r.proposed_location||'تعیین نشده'}</strong></div></div>{r.description&&<div className="description-block"><span>شرح</span><p>{r.description}</p></div>}</div>{r.status==='RETURNED_BY_TARGET'&&<Flow02EditPanel r={r} reload={reload}/>}<div className="panel"><div className="panel-head"><div><span className="eyebrow">اشتراک‌کنندگان نهایی</span><h2>تفکیک دو ریاست</h2></div><Users size={20}/></div><div className="two-column-cards"><div className="subpanel"><strong>{requester?.unit?.name||'ریاست درخواست‌کننده'}</strong>{nominations.filter((n:any)=>n.nominated_by_unit_id===requester?.organizational_unit_id).map((n:any)=><div className="person-row" key={n.id}><div className="avatar tiny">{n.employee?.full_name?.slice(0,1)}</div><div><strong>{n.employee?.full_name}</strong><span>{n.nomination_status==='CONFIRMED'?'تأیید نهایی':'نامزدشده'}</span></div></div>)}</div><div className="subpanel"><strong>{target?.unit?.name||'ریاست مقصد'}</strong>{nominations.filter((n:any)=>n.nominated_by_unit_id===target?.organizational_unit_id).map((n:any)=><div className="person-row" key={n.id}><div className="avatar tiny">{n.employee?.full_name?.slice(0,1)}</div><div><strong>{n.employee?.full_name}</strong><span>{n.nomination_status==='CONFIRMED'?'تأیید نهایی':'نامزدشده'}</span></div></div>)}</div></div></div>{(r.schedule_proposals||[]).length>0&&<div className="panel"><div className="panel-head"><div><span className="eyebrow">مذاکرهٔ زمان‌بندی</span><h2>تاریخچهٔ پیشنهادها</h2></div><CalendarDays size={20}/></div>{r.schedule_proposals.map((p:any)=><div className={`proposal-row ${p.status==='ACCEPTED'?'current':''}`} key={p.id}><div><strong>{p.proposed_by_unit?.name||'ریاست'}</strong><span>{new Date(p.meeting_date).toLocaleDateString('fa-AF')} · {p.start_time} تا {p.end_time} · {p.location}</span></div><span className="tag">{p.status==='ACCEPTED'?'پذیرفته‌شده':p.status==='SUPERSEDED'?'منسوخ':'پیشنهاد'}</span></div>)}</div>}</section><aside className="detail-side"><div className="panel action-panel"><h3>اقدامات قابل انجام</h3>{r.status==='DRAFT'&&<button className="button primary full" onClick={()=>action('submit-inter-directorate')}>ارسال برای تأیید داخلی</button>}{r.status==='PENDING_REQUESTER_APPROVAL'&&<><button className="button success full" onClick={()=>action('requester-approve')}>تأیید ریاست درخواست‌کننده</button><textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={e=>setReason(e.target.value)}/><button className="button warning full" onClick={()=>action('requester-return',{reason})}>برگشت جهت اصلاح</button><button className="button danger full" onClick={()=>action('requester-reject',{reason})}>رد داخلی</button></>}{r.status==='SUBMITTED_TO_TARGET'&&<button className="button primary full" onClick={()=>action('target-review')}>آغاز بررسی ریاست مقصد</button>}{r.status==='UNDER_TARGET_REVIEW'&&<><button className="button success full" onClick={()=>action('target-approve')}>تأیید ریاست مقصد</button><textarea placeholder="دلیل وضاحت یا رد" value={reason} onChange={e=>setReason(e.target.value)}/><button className="button warning full" onClick={()=>action('target-return',{reason})}>برگشت جهت وضاحت</button><button className="button danger full" onClick={()=>action('target-reject',{reason})}>رد توسط مقصد</button></>}{r.status==='RETURNED_BY_TARGET'&&<div className="action-hint"><Paperclip size={16}/> اصلاحات و ارسال مجدد از پنل جزئیات انجام می‌شود.</div>}{r.status==='BILATERAL_APPROVED'&&canStartScheduling&&<button className="button primary full" onClick={()=>action('start-inter-directorate-scheduling')}>آغاز مذاکرهٔ زمان‌بندی</button>}{['SCHEDULING','SCHEDULE_CONFIRMED'].includes(r.status)&&canStartScheduling&&<a className="button primary full" href={`/meeting-requests/${r.id}/scheduling`}>ورود به فضای زمان‌بندی</a>}{r.meeting&&<a className="button secondary full" href={`/meetings/${r.meeting.id}`}>مشاهدهٔ جلسهٔ ایجادشده</a>}</div><div className="panel timeline"><div className="panel-head"><div><span className="eyebrow">تاریخچهٔ غیرقابل حذف</span><h3>خط زمانی دوطرفه</h3></div><Clock3 size={19}/></div>{(r.actions||[]).map((a:any)=><div className="timeline-item" key={a.id}><div className="timeline-dot"/><div><strong>{a.action}</strong><span>{a.actor?.name} · {new Date(a.created_at).toLocaleString('fa-AF')}</span>{a.comment&&<p>{a.comment}</p>}</div></div>)}</div></aside></div></> }

function CreateRequest() {
  return (
    <CreateRequestPage
      mode="internal"
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function CreateMultiDirectorate() {
  return (
    <CreateRequestPage
      mode="multi-directorate"
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function CreateHighAuthority() {
  return (
    <CreateRequestPage
      mode="high-authority"
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function CreateMultiHighAuthority() {
  return (
    <CreateRequestPage
      mode="multi-high-authority"
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function CreateAuthorityInitiated() {
  return (
    <CreateRequestPage
      mode="authority-initiated"
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
    />
  )
}

function RequestDetail() {
  const id = location.pathname.split('/')[2]
  const { user } = useAuth()
  const [r, setR] = useState<any>(null)
  const load = () => api.get(`/meeting-requests/${id}`).then((x) => setR(apiData(x)))

  useEffect(() => {
    load()
  }, [id])

  if (!r) return <div className="screen-center"><div className="spinner" /></div>
  if (r.flow_type === 'INTER_DIRECTORATE') return <Flow02DetailV2 r={r} reload={load} />
  if (r.flow_type === 'MULTI_DIRECTORATE') return <MultiDirectorateDetail />
  if (r.flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') return <HighAuthorityDetail />
  if (r.flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY') return <MultiHighAuthorityDetail />
  if (r.flow_type === 'HIGH_AUTHORITY_INITIATED') return <AuthorityInitiatedDetail />

  return (
    <RequestDetailPage
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      currentUser={user}
    />
  )
}

function Approvals() { const [data,setData]=useState<any>({data:[]});useEffect(()=>{const load=async()=>{const [generic,authority]=await Promise.all([api.get('/approvals'),api.get('/meeting-requests',{params:{status:'PENDING_REQUESTER_APPROVAL'}})]);const rows=[...(apiPage(generic).data||[]),...(apiPage(authority).data||[])];const unique=Array.from(new Map(rows.map((r:any)=>[r.id,r])).values());setData({data:unique})};load()},[]);return <><PageTitle eyebrow="صلاحیت ریاست" title="درخواست‌های منتظر تأیید" description="فقط درخواست‌های ریاست‌های تحت صلاحیت شما نمایش داده می‌شود."/><div className="panel table-panel"><table><thead><tr><th>شماره</th><th>موضوع</th><th>درخواست‌کننده</th><th>اولویت</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>{(data.data||[]).map((r:any)=><tr key={r.id}><td className="mono">{r.request_number}</td><td><a className="table-link" href={`/meeting-requests/${r.id}`}>{r.subject}</a></td><td>{r.creator?.name}</td><td><span className={`priority ${r.priority?.toLowerCase()}`}>{priorityLabels[r.priority]}</span></td><td>{new Date(r.created_at).toLocaleDateString('fa-AF')}</td><td><a className="button small primary" href={`/meeting-requests/${r.id}`}>بررسی <Eye size={14}/></a></td></tr>)}</tbody></table>{!(data.data||[]).length&&<EmptyState text="درخواست منتظر تأیید وجود ندارد."/>}</div></> }

function Scheduling() {
  const { user } = useAuth()
  return <SchedulingPage currentUser={user} />
}

function Flow02Scheduling({initial}:{initial:any}) { const [r,setR]=useState<any>(initial);const [employees,setEmployees]=useState<any[]>([]);const [proposal,setProposal]=useState<any>({proposed_by_unit_id:'',meeting_date:'',start_time:'09:00',end_time:'10:00',location:'',comment:''});const [nomination,setNomination]=useState<any>({nominated_by_unit_id:'',employee_id:''});const [final,setFinal]=useState<any>({chairperson_employee_id:'',location:'',end_time:'11:00',agenda:''});const requester=r.units?.find((u:any)=>u.role==='REQUESTER');const target=r.units?.find((u:any)=>u.role==='TARGET');const load=()=>api.get(`/meeting-requests/${r.id}`).then(x=>setR(apiData(x)));useEffect(()=>{api.get('/employees').then(x=>setEmployees(apiData(x)));setProposal((p:any)=>({...p,proposed_by_unit_id:requester?.organizational_unit_id||''}));setNomination((n:any)=>({...n,nominated_by_unit_id:requester?.organizational_unit_id||''}))},[r.id,requester?.organizational_unit_id]);const call=async(url:string,body?:any)=>{try{await api.post(`/meeting-requests/${r.id}/${url}`,body);load()}catch(e:any){alert(e.response?.data?.message||'عملیات زمان‌بندی انجام نشد.')}};const propose=async()=>{await call('schedule-proposals',proposal)};const nominate=async()=>{await call('participant-nominations',nomination)};const finalize=async()=>{try{await api.post(`/meeting-requests/${r.id}/finalize-inter-directorate`,final);location.href=`/meeting-requests/${r.id}`}catch(e:any){alert(e.response?.data?.message||'پیش‌شرط‌های ایجاد جلسه تکمیل نیست.')}};return <><PageTitle eyebrow="مذاکرهٔ زمان‌بندی" title="فضای زمان‌بندی دوطرفه" description={`${r.request_number} · ${r.subject}`} action={<a className="button secondary" href={`/meeting-requests/${r.id}`}>بازگشت به درخواست</a>}/>{r.status==='BILATERAL_APPROVED'&&<div className="panel approved-strip"><CheckCircle2 size={18}/><span>هر دو ریاست موافقت کرده‌اند؛ مذاکرهٔ تاریخ و مکان را آغاز کنید.</span><button className="button primary" onClick={()=>call('start-inter-directorate-scheduling')}>آغاز مذاکره</button></div>}<div className="form-layout"><section className="detail-main"><div className="panel"><div className="panel-head"><div><span className="eyebrow">تاریخچهٔ مذاکره</span><h2>پیشنهادهای زمان‌بندی</h2></div><CalendarDays size={20}/></div>{(r.schedule_proposals||[]).map((p:any)=><div className={`proposal-row ${p.status==='ACCEPTED'?'current':''}`} key={p.id}><div><strong>{p.proposed_by_unit?.name||'ریاست'}</strong><span>{new Date(p.meeting_date).toLocaleDateString('fa-AF')} · {p.start_time} تا {p.end_time} · {p.location}</span>{p.comment&&<small>{p.comment}</small>}</div><div><span className="tag">{p.status==='ACCEPTED'?'پذیرفته‌شده':p.status==='SUPERSEDED'?'منسوخ':p.status==='COUNTER_PROPOSED'?'پیشنهاد متقابل':'پیشنهاد'}</span>{['PROPOSED','COUNTER_PROPOSED'].includes(p.status)&&<button className="button small secondary" onClick={()=>call(`schedule-proposals/${p.id}/accept`)}>پذیرش</button>}</div></div>)}{!(r.schedule_proposals||[]).length&&<EmptyState text="هنوز پیشنهاد زمانی ثبت نشده است."/>}</div>{['SCHEDULING'].includes(r.status)&&<div className="panel form-card"><div className="section-title"><span>ثبت پیشنهاد یا پیشنهاد متقابل</span><small>پیشنهادهای قبلی حذف نمی‌شوند.</small></div><div className="form-grid"><label>ریاست پیشنهاددهنده<select value={proposal.proposed_by_unit_id} onChange={e=>setProposal({...proposal,proposed_by_unit_id:e.target.value})}><option value="">انتخاب ریاست</option>{[requester,target].filter(Boolean).map((u:any)=><option value={u.organizational_unit_id} key={u.organizational_unit_id}>{u.unit?.name||'ریاست'}</option>)}</select></label><label>محل<input value={proposal.location} onChange={e=>setProposal({...proposal,location:e.target.value})}/></label></div><div className="form-grid three"><label>تاریخ<input type="date" value={proposal.meeting_date} onChange={e=>setProposal({...proposal,meeting_date:e.target.value})}/></label><label>از ساعت<input type="time" value={proposal.start_time} onChange={e=>setProposal({...proposal,start_time:e.target.value})}/></label><label>تا ساعت<input type="time" value={proposal.end_time} onChange={e=>setProposal({...proposal,end_time:e.target.value})}/></label></div><label>یادداشت<textarea value={proposal.comment} onChange={e=>setProposal({...proposal,comment:e.target.value})}/></label><button className="button primary" onClick={propose}>ثبت پیشنهاد</button></div>}<div className="panel form-card"><div className="section-title"><span>نامزدی اشتراک‌کنندگان</span><small>هر ریاست فقط فهرست خودش را مدیریت می‌کند.</small></div><div className="form-grid"><label>ریاست مالک فهرست<select value={nomination.nominated_by_unit_id} onChange={e=>setNomination({...nomination,nominated_by_unit_id:e.target.value})}><option value="">انتخاب ریاست</option>{[requester,target].filter(Boolean).map((u:any)=><option value={u.organizational_unit_id} key={u.organizational_unit_id}>{u.unit?.name||'ریاست'}</option>)}</select></label><label>کارمند<select value={nomination.employee_id} onChange={e=>setNomination({...nomination,employee_id:e.target.value})}><option value="">انتخاب کارمند</option>{employees.filter(e=>Number(e.unit?.id||e.organizational_unit_id)===Number(nomination.nominated_by_unit_id)).map(e=><option value={e.id} key={e.id}>{e.full_name} · {e.unit?.name}</option>)}</select></label></div><button className="button secondary" onClick={nominate}>ثبت نامزد</button><div className="form-actions"><button className="button success" onClick={()=>call('requester-participants/confirm')}>تأیید اشتراک‌کنندگان ریاست درخواست‌کننده</button><button className="button success" onClick={()=>call('target-participants/confirm')}>تأیید اشتراک‌کنندگان ریاست مقصد</button></div></div></section><aside className="detail-side"><div className="panel"><span className="eyebrow">وضعیت دو طرف</span><h3>{requester?.unit?.name||'درخواست‌کننده'}</h3><div className="compare"><span>تأیید فهرست</span><strong>{r.requester_participants_confirmed_at?'تکمیل شد':'در انتظار'}</strong></div><h3>{target?.unit?.name||'مقصد'}</h3><div className="compare"><span>تأیید فهرست</span><strong>{r.target_participants_confirmed_at?'تکمیل شد':'در انتظار'}</strong></div></div>{r.status==='SCHEDULING'&&r.requester_participants_confirmed_at&&r.target_participants_confirmed_at&&<div className="panel action-panel"><span className="eyebrow">تأیید نهایی</span><h3>هر دو فهرست تأیید شده‌اند</h3><p className="muted">زمان پذیرفته‌شدهٔ ریاست مالی آمادهٔ ثبت نهایی است.</p><button className="button primary full" onClick={()=>call('confirm-inter-directorate-schedule')}>تأیید زمان‌بندی نهایی</button></div>}{r.status==='SCHEDULE_CONFIRMED'&&<div className="panel form-card"><span className="eyebrow">ایجاد جلسه</span><h3>پیش‌شرط‌ها تکمیل شد</h3><label>رئیس جلسه<select value={final.chairperson_employee_id} onChange={e=>setFinal({...final,chairperson_employee_id:e.target.value})}><option value="">انتخاب رئیس جلسه</option>{employees.map(e=><option value={e.id} key={e.id}>{e.full_name} · {e.unit?.name}</option>)}</select></label><label>محل نهایی<input value={final.location} onChange={e=>setFinal({...final,location:e.target.value})}/></label><label>زمان ختم<input type="time" value={final.end_time} onChange={e=>setFinal({...final,end_time:e.target.value})}/></label><label>آجندا<textarea value={final.agenda} onChange={e=>setFinal({...final,agenda:e.target.value})}/></label><button className="button primary full" onClick={finalize}>ایجاد جلسهٔ رسمی</button></div>}</aside></div></> }

function MeetingDetail() {
  return <MeetingDetailPage />
}

function Meetings() {
  return <MeetingsPage />
}
function Notifications() { const [data,setData]=useState<any>({data:[]});useEffect(()=>{api.get('/notifications').then(r=>setData(apiPage(r)))},[]);return <><PageTitle eyebrow="مرکز پیام‌ها" title="اعلان‌ها" description="اعلان‌های مربوط به گردش کار درخواست‌ها و جلسات."/><div className="panel notification-list">{(data.data||[]).map((n:any)=><div className={`notification-row ${n.read_at?'read':''}`} key={n.id}><div className="notification-icon"><Bell size={18}/></div><div><strong>{n.title}</strong><p>{n.message}</p><small>{new Date(n.created_at).toLocaleString('fa-AF')}</small></div>{!n.read_at&&<span className="unread">جدید</span>}</div>)}{!(data.data||[]).length&&<EmptyState text="اعلان جدیدی ندارید."/>}</div></> }
function Organization() { const [units,setUnits]=useState<any[]>([]);useEffect(()=>{api.get('/organizational-units').then(r=>setUnits(apiData(r)))},[]);return <><PageTitle eyebrow="مدیریت منابع" title="ساختار تشکیلاتی" description="ساختار سلسله‌مراتبی سازمان و ریاست‌های فعال."/><div className="org-grid">{units.filter(u=>!u.parent_id).map(u=><div className="org-tree panel" key={u.id}><div className="org-root"><Building2 size={20}/><div><strong>{u.name}</strong><span>{u.code} · ریاست</span></div></div><div className="org-children">{units.filter(c=>c.parent_id===u.id).map(c=><div className="org-child" key={c.id}><Network size={16}/><div><strong>{c.name}</strong><span>{c.unit_type==='MANAGEMENT'?'مدیریت اجرائیه':'دیپارتمنت'} · {c.code}</span></div></div>)}</div></div>)}</div></> }
function Employees() { const [employees,setEmployees]=useState<any[]>([]);useEffect(()=>{api.get('/employees').then(r=>setEmployees(apiData(r)))},[]);return <><PageTitle eyebrow="مدیریت منابع انسانی" title="کارمندان" description="فهرست کارمندان فعال سازمان."/><div className="panel table-panel"><table><thead><tr><th>شماره کارمندی</th><th>نام و نام خانوادگی</th><th>ریاست / واحد</th><th>سمت</th><th>وضعیت</th></tr></thead><tbody>{employees.map(e=><tr key={e.id}><td className="mono">{e.employee_number}</td><td><div className="person-cell"><div className="avatar tiny">{e.full_name.slice(0,1)}</div>{e.full_name}</div></td><td>{e.unit?.name}</td><td>{e.position?.title||'—'}</td><td><span className="status approved">فعال</span></td></tr>)}</tbody></table></div></> }
function ReportingPage({exportsOnly=false}:{exportsOnly?:boolean}) { const {user}=useAuth(); return <ReportingCenter user={user} exportsOnly={exportsOnly}/> }
function App() { return <AuthProvider><Routes><Route path="/login" element={<Login/>}/><Route path="*" element={<Protected><Shell><Routes><Route path="/dashboard" element={<Dashboard/>}/><Route path="/meeting-requests" element={<Requests/>}/><Route path="/meeting-requests/create" element={<CreateRequest/>}/><Route path="/meeting-requests/create-inter-directorate" element={<CreateInterDirectorateRequest/>}/><Route path="/meeting-requests/create-multi-directorate" element={<CreateMultiDirectorate/>}/><Route path="/meeting-requests/create-high-authority" element={<CreateHighAuthority/>}/><Route path="/meeting-requests/create-multi-high-authority" element={<CreateMultiHighAuthority/>}/><Route path="/meeting-requests/create-authority-initiated" element={<CreateAuthorityInitiated/>}/>
<Route path="/incoming-meeting-requests" element={<IncomingRequests/>}/><Route path="/incoming-high-authority" element={<HighAuthorityInbox/>}/><Route path="/incoming-multi-high-authority" element={<MultiHighAuthorityInbox/>}/><Route path="/incoming-authority-initiated" element={<AuthorityInitiatedInbox/>}/><Route path="/meeting-requests/:id" element={<RequestDetail/>}/><Route path="/meeting-requests/:id/edit" element={<Flow01EditRequest/>}/><Route path="/meeting-requests/:id/scheduling" element={<Scheduling/>}/><Route path="/meeting-requests/:id/multi-scheduling" element={<MultiDirectorateScheduling/>}/><Route path="/meeting-requests/:id/high-authority-scheduling" element={<HighAuthorityScheduling/>}/><Route path="/meeting-requests/:id/multi-high-scheduling" element={<MultiHighAuthorityScheduling/>}/><Route path="/meeting-requests/:id/authority-initiated-scheduling" element={<AuthorityInitiatedScheduling/>}/>
<Route path="/approvals" element={<Approvals/>}/><Route path="/meetings" element={<Meetings/>}/><Route path="/meetings/:id/reschedule" element={<MeetingReschedulePage/>}/><Route path="/meetings/:id/participants/change" element={<MeetingParticipantChangePage/>}/><Route path="/meetings/:id/terminals" element={<MeetingTerminalsPage/>}/><Route path="/meetings/:id/attendance" element={<MeetingAttendancePage/>}/><Route path="/meetings/:id/execution" element={<MeetingExecutionPage/>}/><Route path="/meetings/:id/minutes" element={<MeetingMinutesPage/>}/><Route path="/meetings/:id/follow-up" element={<MeetingFollowUpPage/>}/><Route path="/meetings/:id/exceptions" element={<MeetingExceptionsPage/>}/><Route path="/action-items" element={<ActionItemsPage/>}/><Route path="/action-items/:id" element={<ActionItemDetailPage/>}/><Route path="/employees/:id/biometrics" element={<EmployeeBiometricsPage/>}/><Route path="/meetings/:id" element={<MeetingDetail/>}/><Route path="/notifications" element={<Notifications/>}/><Route path="/reports" element={<ReportingPage/>}/><Route path="/reports/exports" element={<ReportingPage exportsOnly/>}/><Route path="/admin/organization" element={<Organization/>}/><Route path="/admin/employees" element={<Employees/>}/><Route path="/admin/roles" element={<Employees/>}/><Route path="/admin/terminals" element={<TerminalsRegistryPage/>}/><Route path="/admin/terminals/:id" element={<TerminalDetailPage/>}/><Route path="/admin/biometrics" element={<BiometricsRegistryPage/>}/><Route path="/" element={<Navigate to="/dashboard" replace/>}/></Routes></Shell></Protected>}/></Routes></AuthProvider> }
ReactDOM.createRoot(document.getElementById('root')!).render(<React.StrictMode><BrowserRouter><App/></BrowserRouter></React.StrictMode>)
