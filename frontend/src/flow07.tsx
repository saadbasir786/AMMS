import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { CalendarDays, CheckCircle2, GitBranch } from 'lucide-react'

import { PageHeader } from '@/components/amms/page-header'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'

type Any = any
const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: Any) => response.data?.data ?? response.data
const roleUnitId = (role: Any) => Number(role?.organizational_unit_id ?? role?.unit?.id ?? 0)
const hasScopedRole = (user: Any, roleName: string, unitId: number) => !!unitId && !!user?.roles?.some((role: Any) => role.role === roleName && roleUnitId(role) === Number(unitId))
const faDate = (value: Any) => value ? new Date(value).toLocaleDateString('fa-AF') : '—'

function ScheduleForm({ initial, onSubmit, label, busy }: { initial?: Any; onSubmit: (data: Any) => Promise<void>; label: string; busy: boolean }) {
  const [form, setForm] = useState<Any>({ meeting_date: initial?.meeting_date?.slice(0, 10) || '', start_time: initial?.start_time?.slice(0, 5) || '09:00', end_time: initial?.end_time?.slice(0, 5) || '10:00', location: initial?.location || '', comment: '' })
  return <Card><CardHeader className="border-b"><CardTitle className="text-base">{label}</CardTitle></CardHeader><CardContent className="grid gap-4 pt-5 sm:grid-cols-2"><label className="grid gap-2 text-sm">تاریخ<Input type="date" value={form.meeting_date} onChange={e => setForm({ ...form, meeting_date: e.target.value })}/></label><label className="grid gap-2 text-sm">محل<Input value={form.location} onChange={e => setForm({ ...form, location: e.target.value })}/></label><label className="grid gap-2 text-sm">از ساعت<Input type="time" value={form.start_time} onChange={e => setForm({ ...form, start_time: e.target.value })}/></label><label className="grid gap-2 text-sm">تا ساعت<Input type="time" value={form.end_time} onChange={e => setForm({ ...form, end_time: e.target.value })}/></label><label className="grid gap-2 text-sm sm:col-span-2">یادداشت اختیاری<Textarea rows={2} value={form.comment} onChange={e => setForm({ ...form, comment: e.target.value })}/></label><div className="sm:col-span-2"><Button disabled={busy} onClick={() => onSubmit(form)}>{busy ? 'در حال ثبت…' : 'ثبت پیشنهاد'}</Button></div></CardContent></Card>
}

export function MeetingRescheduleWorkspace() {
  const id = location.pathname.split('/')[2]
  const [workspace, setWorkspace] = useState<Any>(null)
  const [user, setUser] = useState<Any>(null)
  const [reason, setReason] = useState('')
  const [alternative, setAlternative] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const load = async () => { try { setWorkspace(dataOf(await api.get(`/meetings/${id}/reschedule`))); setError('') } catch (e: Any) { setError(e?.response?.data?.message || e?.response?.data?.code || 'دسترسی به فضای تغییر زمان ممکن نیست.') } }
  useEffect(() => { load(); api.get('/me').then(r => setUser(dataOf(r))).catch(() => {}) }, [id])
  if (!workspace) return error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="screen-center"><div className="spinner"/></div>

  const meeting = workspace.meeting
  const current = workspace.current_schedule_version
  const activeCase = workspace.active_reschedule_case
  const activeProposal = activeCase?.proposals?.find((p: Any) => p.status === 'ACTIVE')
  const flowType = meeting?.request?.flow_type
  const authorityControlled = ['DIRECTORATE_WITH_HIGH_AUTHORITY','MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY','HIGH_AUTHORITY_INITIATED'].includes(flowType) || !!meeting?.authority_context
  const authorityOfficeId = Number(meeting?.authority_context?.authority_office_unit_id || 0)
  const required = flowType === 'HIGH_AUTHORITY_INITIATED' ? (meeting?.units || []).filter((u: Any) => u.role === 'PARTICIPATING') : (meeting?.units || []).filter((u: Any) => ['REQUESTING','PARTICIPATING'].includes(u.role))
  const formalUnitIds = required.map((u: Any) => Number(u.organizational_unit_id)).filter(Boolean)
  const leadUnitId = Number((meeting?.units || []).find((u: Any) => u.role === 'REQUESTING')?.organizational_unit_id || formalUnitIds[0] || 0)
  const actorFormalUnitId = formalUnitIds.find((unitId: number) => hasScopedRole(user, 'EXECUTIVE_MANAGEMENT', unitId) || hasScopedRole(user, 'DIRECTOR', unitId)) || 0
  const isSecretary = authorityControlled && hasScopedRole(user, 'EXECUTIVE_SECRETARY', authorityOfficeId)
  const isPeerExecutive = !!actorFormalUnitId && hasScopedRole(user, 'EXECUTIVE_MANAGEMENT', actorFormalUnitId)
  const isFormalDirector = !!actorFormalUnitId && hasScopedRole(user, 'DIRECTOR', actorFormalUnitId)
  const canCreateCase = authorityControlled ? isSecretary : (isPeerExecutive || isFormalDirector)
  const canPropose = authorityControlled ? isSecretary : isPeerExecutive
  const canAcknowledge = authorityControlled ? (isPeerExecutive || isFormalDirector) : (isPeerExecutive || isFormalDirector)
  const canRequestAlternative = canAcknowledge
  const canConfirm = authorityControlled ? isSecretary : hasScopedRole(user, 'EXECUTIVE_MANAGEMENT', leadUnitId)
  const canResolveAlternative = authorityControlled && isSecretary
  const ackCount = activeProposal?.acknowledgements?.length || 0
  const openAlternatives = (activeCase?.alternative_requests || []).filter((a: Any) => a.status === 'OPEN')
  const history = useMemo(() => [...(workspace.schedule_versions || [])].sort((a: Any, b: Any) => a.version_number - b.version_number), [workspace])
  const call = async (fn: () => Promise<Any>) => { setBusy(true); setError(''); try { await fn(); await load() } catch (e: Any) { setError(e?.response?.data?.message || e?.response?.data?.code || 'عملیات تغییر زمان انجام نشد.') } finally { setBusy(false) } }
  const createCase = () => call(() => api.post(`/meetings/${id}/reschedule-cases`, { reason }))
  const submitProposal = (data: Any) => call(() => api.post(`/meetings/${id}/reschedule/${authorityControlled ? 'authority-proposals' : 'proposals'}`, data))
  const acknowledge = () => activeProposal ? call(() => api.post(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/acknowledge`)) : Promise.resolve()
  const requestAlternative = () => activeProposal ? call(() => api.post(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/request-alternative`, { reason: alternative })) : Promise.resolve()
  const confirm = () => call(() => api.post(`/meetings/${id}/reschedule/confirm`))
  const resolve = (alt: Any) => call(() => api.post(`/meetings/${id}/reschedule/alternative-requests/${alt.id}/resolve`))

  return <div className="space-y-6">
    <PageHeader eyebrow="FLOW-07 · تغییر زمان‌بندی" title="فضای تغییر زمان جلسه" description={`${meeting?.meeting_number || 'جلسه'} · ${meeting?.title || ''}`} action={<div className="flex gap-2"><Button asChild variant="outline"><a href={`/meetings/${id}`}>بازگشت به جلسه</a></Button>{activeCase ? <Badge variant="outline">تغییر زمان در جریان است</Badge> : null}</div>}/>
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"><section className="space-y-6"><DetailSummaryCard eyebrow="زمان معتبر فعلی" title={meeting?.title || 'جلسه'} icon={<CalendarDays className="size-5"/>} items={[{label:'تاریخ',value:faDate(current?.meeting_date)},{label:'ساعت',value:current ? `${current.start_time || '—'} تا ${current.end_time || '—'}` : '—'},{label:'محل',value:current?.location || '—'},{label:'نسخه',value:current?.version_number || '—'}]} description="نسخهٔ فعلی تا confirmation نهایی معتبر باقی می‌ماند."/>
      {activeCase ? <Card><CardHeader className="border-b"><div className="flex items-start justify-between gap-4"><div><p className="text-xs text-muted-foreground">پرونده فعال · {activeCase.governance_mode}</p><CardTitle className="mt-1 text-base">درخواست تغییر زمان در جریان است</CardTitle></div><Badge variant="outline">{activeCase.status}</Badge></div></CardHeader><CardContent className="space-y-5 pt-5"><p className="text-sm leading-7 text-muted-foreground">{activeCase.reason}</p><div className="grid gap-3 sm:grid-cols-3"><div className="rounded-xl border p-3"><span className="text-xs text-muted-foreground">نسخه مبنا</span><strong className="mt-1 block">{activeCase.baseline_schedule_version_id}</strong></div><div className="rounded-xl border p-3"><span className="text-xs text-muted-foreground">تأییدها</span><strong className="mt-1 block">{ackCount}/{required.length}</strong></div><div className="rounded-xl border p-3"><span className="text-xs text-muted-foreground">بدیل‌های باز</span><strong className="mt-1 block">{openAlternatives.length}</strong></div></div>{activeProposal ? <div className="rounded-xl border bg-muted/20 p-4"><div className="flex items-center gap-2"><GitBranch className="size-4"/><strong className="text-sm">پیشنهاد فعال</strong></div><p className="mt-2 text-sm">{faDate(activeProposal.meeting_date)} · {activeProposal.start_time} تا {activeProposal.end_time} · {activeProposal.location}</p></div> : null}</CardContent></Card> : null}
      {activeCase && canPropose ? <ScheduleForm initial={current} onSubmit={submitProposal} label={authorityControlled ? 'پیشنهاد رسمی دبیر اجرائیه' : 'پیشنهاد ریاست / پیشنهاد متقابل'} busy={busy}/> : null}
      {activeProposal ? <Card><CardHeader className="border-b"><CardTitle className="text-base">ماتریس تأیید پیشنهاد فعال</CardTitle></CardHeader><CardContent className="space-y-4 pt-5"><div className="space-y-2">{required.map((unit: Any) => <div key={unit.id} className="flex items-center justify-between rounded-xl border p-3"><span className="text-sm">{unit.unit?.name || unit.name || `واحد ${unit.organizational_unit_id}`}</span><Badge variant={activeProposal.acknowledgements?.some((a: Any) => Number(a.organizational_unit_id) === Number(unit.organizational_unit_id)) ? 'default' : 'outline'}>{activeProposal.acknowledgements?.some((a: Any) => Number(a.organizational_unit_id) === Number(unit.organizational_unit_id)) ? 'تأیید شده' : 'منتظر'}</Badge></div>)}</div>{canAcknowledge ? <Button disabled={busy} onClick={acknowledge}><CheckCircle2 className="size-4"/> تأیید پیشنهاد برای ریاست من</Button> : null}{canRequestAlternative ? <div className="space-y-2"><Textarea value={alternative} onChange={e=>setAlternative(e.target.value)} placeholder="دلیل درخواست زمان بدیل"/><Button variant="outline" disabled={busy || !alternative.trim()} onClick={requestAlternative}>درخواست زمان بدیل</Button></div> : null}</CardContent></Card> : null}
      {openAlternatives.length ? <Card><CardHeader className="border-b"><CardTitle className="text-base">درخواست‌های بدیل باز</CardTitle></CardHeader><CardContent className="space-y-3 pt-5">{openAlternatives.map((alt: Any)=><div key={alt.id} className="flex items-center justify-between gap-4 rounded-xl border p-4"><div><strong className="block text-sm">{alt.unit?.name || 'ریاست'}</strong><p className="mt-1 text-sm text-muted-foreground">{alt.reason}</p></div>{canResolveAlternative ? <Button size="sm" onClick={()=>resolve(alt)}>حل درخواست</Button> : null}</div>)}</CardContent></Card> : null}
      <WorkflowTimeline title="تاریخچه زمان‌بندی" items={history.map((v: Any)=>({id:v.id,title:`نسخهٔ ${v.version_number}`,meta:`${faDate(v.meeting_date)} · ${v.start_time || '—'} تا ${v.end_time || '—'} · ${v.location || '—'}`,comment:v.id===current?.id?'نسخه جاری':undefined}))}/></section>
      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">{!activeCase ? <Card><CardHeader className="border-b"><CardTitle className="text-base">ایجاد پرونده رسمی</CardTitle></CardHeader><CardContent className="space-y-3 pt-5">{canCreateCase ? <><Textarea value={reason} onChange={e=>setReason(e.target.value)} placeholder="دلیل رسمی تغییر زمان"/><Button className="w-full" disabled={busy || !reason.trim()} onClick={createCase}>ایجاد پرونده تغییر زمان</Button></> : <Alert><AlertDescription>برای این جلسه صلاحیت ایجاد پروندهٔ تغییر زمان ندارید.</AlertDescription></Alert>}</CardContent></Card> : null}{activeCase && canConfirm ? <Card><CardHeader className="border-b"><CardTitle className="text-base">تأیید نهایی</CardTitle></CardHeader><CardContent className="space-y-3 pt-5"><p className="text-sm text-muted-foreground">اعمال نسخهٔ جدید فقط پس از کنترل acknowledgementها و بدیل‌های باز انجام می‌شود.</p><Button className="w-full" disabled={busy || !activeProposal || ackCount < required.length || openAlternatives.length > 0} onClick={()=>setConfirmOpen(true)}>تأیید و اعمال نسخه جدید</Button></CardContent></Card> : null}</aside></div>
    <ConfirmDialog open={confirmOpen} onOpenChange={setConfirmOpen} title="نسخهٔ جدید زمان‌بندی اعمال شود؟" description="نسخهٔ فعلی تنها پس از موفقیت endpoint confirmation جایگزین می‌شود." confirmLabel="تأیید و اعمال" onConfirm={confirm}/>
  </div>
}
