import { useEffect, useState } from 'react'
import axios from 'axios'
import { CheckCircle2, Clock3, Download, FileText, Plus, Save, Send, Users } from 'lucide-react'

export { MeetingExecutionWorkspace } from './flow11_execution'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { PageHeader } from '@/components/amms/page-header'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

type Any = any
const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: Any) => response.data?.data ?? response.data
const labels: Record<string,string> = {DRAFT:'پیش‌نویس',UNDER_REVIEW:'در حال بررسی',RETURNED_FOR_CORRECTION:'برگشت برای اصلاح',READY_TO_FINALIZE:'آمادهٔ نهایی‌سازی',FINALIZED:'نهایی‌شده',PENDING:'در انتظار',ACKNOWLEDGED:'تأییدشده',APPROVED:'تأیید مقام عالی',CORRECTION_REQUESTED:'درخواست اصلاح'}
const label = (value?: string) => value ? (labels[value] || value) : '—'
const apiError = (error: Any) => error?.response?.data?.message || 'عملیات انجام نشد. لطفاً صلاحیت و وضعیت جلسه را بررسی نمایید.'

export function MeetingMinutesWorkspace() {
  const id = window.location.pathname.split('/')[2]
  const [minutes, setMinutes] = useState<Any>(null)
  const [meeting, setMeeting] = useState<Any>(null)
  const [form, setForm] = useState<Any>({ summary: '', general_notes: '', items: [{ agenda_title: '', discussion_summary: '' }], decisions: [{ decision_text: '', responsible_unit_id: '', responsible_employee_id: '', target_date: '', notes: '' }] })
  const [correction, setCorrection] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [confirmFinalize, setConfirmFinalize] = useState(false)

  const load = async () => {
    try {
      const [m, detail] = await Promise.all([api.get(`/meetings/${id}/minutes`), api.get(`/meetings/${id}`)])
      setMinutes(dataOf(m)); setMeeting(dataOf(detail)); setError('')
    } catch (e) { setError(apiError(e)) }
  }
  useEffect(() => { load() }, [id])
  useEffect(() => {
    const version = minutes?.current_version
    if (version && !form.summary) setForm({
      summary: version.summary || '', general_notes: version.general_notes || '',
      items: version.items?.length ? version.items.map((x: Any) => ({ agenda_title: x.agenda_title, discussion_summary: x.discussion_summary })) : [{ agenda_title: '', discussion_summary: '' }],
      decisions: version.decisions?.length ? version.decisions.map((x: Any) => ({ decision_text: x.decision_text, responsible_unit_id: x.responsible_unit_id || '', responsible_employee_id: x.responsible_employee_id || '', target_date: x.target_date || '', notes: x.notes || '' })) : [{ decision_text: '', responsible_unit_id: '', responsible_employee_id: '', target_date: '', notes: '' }],
    })
  }, [minutes])

  const run = async (work: () => Promise<unknown>) => { try { setBusy(true); setError(''); await work(); await load() } catch (e) { setError(apiError(e)); throw e } finally { setBusy(false) } }
  const createDraft = () => run(() => api.post(`/meetings/${id}/minutes/draft`))
  const saveDraft = () => run(async () => {
    if (!minutes) await api.post(`/meetings/${id}/minutes/draft`)
    await api.put(`/meetings/${id}/minutes/draft`, { ...form, items: form.items.filter((x: Any) => x.agenda_title && x.discussion_summary), decisions: form.decisions.filter((x: Any) => x.decision_text) })
  })
  const submit = () => run(() => api.post(`/meetings/${id}/minutes/submit`))
  const acknowledge = (unitId: number) => run(() => api.post(`/meetings/${id}/minutes/reviews/${unitId}/acknowledge`))
  const requestCorrection = (review: Any) => {
    if (!correction.trim()) { setError('دلیل اصلاح الزامی است.'); return }
    run(async () => { await api.post(`/meetings/${id}/minutes/reviews/correction`, { reviewer_type: review.reviewer_type, organizational_unit_id: review.organizational_unit_id, reason: correction }); setCorrection('') })
  }
  const approveAuthority = () => run(() => api.post(`/meetings/${id}/minutes/authority-approve`))
  const finalize = () => run(() => api.post(`/meetings/${id}/minutes/finalize`))
  const addItem = () => setForm((f: Any) => ({ ...f, items: [...f.items, { agenda_title: '', discussion_summary: '' }] }))
  const addDecision = () => setForm((f: Any) => ({ ...f, decisions: [...f.decisions, { decision_text: '', responsible_unit_id: '', responsible_employee_id: '', target_date: '', notes: '' }] }))

  if (!meeting) return <div className="screen-center">{error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="spinner" />}</div>
  const version = minutes?.current_version
  const editable = !minutes || ['DRAFT', 'RETURNED_FOR_CORRECTION'].includes(minutes.status)

  return <div className="space-y-6" dir="rtl">
    <PageHeader eyebrow={`${meeting.meeting_number} · FLOW-11`} title="صورت‌جلسهٔ رسمی" description="ثبت نسخه‌محور مباحث، تصمیمات، حضور و بررسی‌های رسمی جلسه." action={<div className="flex flex-wrap items-center gap-2">{minutes ? <Badge variant="outline">{label(minutes.status)}</Badge> : null}<Button asChild variant="outline"><a href={`/meetings/${id}`}>بازگشت به جلسه</a></Button>{minutes?.status === 'FINALIZED' ? <Button asChild><a href={`/api/meetings/${id}/minutes/final.pdf`} target="_blank" rel="noreferrer"><Download className="size-4"/>PDF رسمی</a></Button> : null}</div>} />
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}

    {!minutes ? <Card className="border-dashed"><CardContent className="flex flex-col items-start justify-between gap-4 p-6 sm:flex-row sm:items-center"><div><p className="font-medium">برای این جلسه هنوز صورت‌جلسه‌ای ایجاد نشده است.</p><p className="text-sm text-muted-foreground">پیش‌نویس جدید با همان قرارداد موجود Backend ایجاد می‌شود.</p></div><Button disabled={busy} onClick={createDraft}><FileText className="size-4"/>ایجاد پیش‌نویس</Button></CardContent></Card> : null}

    {minutes ? <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section className="space-y-6">
        <DetailSummaryCard title="نسخهٔ جاری صورت‌جلسه" eyebrow={`نسخه ${version?.version_number || '—'}`} items={[
          { label: 'وضعیت', value: <Badge variant="outline">{label(minutes.status)}</Badge> },
          { label: 'شماره جلسه', value: meeting.meeting_number || '—' },
          { label: 'نسخه جاری', value: version?.version_number || '—' },
          { label: 'تاریخ ارسال', value: version?.submitted_at ? new Date(version.submitted_at).toLocaleString('fa-AF') : 'پیش‌نویس' },
        ]} />

        <Card><CardHeader className="flex flex-row items-center justify-between border-b"><CardTitle className="text-base">محتوای صورت‌جلسه</CardTitle><FileText className="size-5 text-muted-foreground"/></CardHeader><CardContent className="space-y-5 pt-5">
          {editable ? <>
            <label className="grid gap-2 text-sm font-medium">خلاصهٔ عمومی<Textarea rows={4} value={form.summary} onChange={e => setForm({ ...form, summary: e.target.value })}/></label>
            <label className="grid gap-2 text-sm font-medium">یادداشت عمومی<Textarea rows={3} value={form.general_notes} onChange={e => setForm({ ...form, general_notes: e.target.value })}/></label>
            <div className="flex items-center justify-between"><h3 className="text-sm font-semibold">اجندا و مباحث</h3><Button size="sm" variant="outline" onClick={addItem}><Plus className="size-4"/>افزودن</Button></div>
            <div className="space-y-3">{form.items.map((item: Any, index: number) => <Card key={index}><CardContent className="grid gap-4 p-4 md:grid-cols-2"><label className="grid gap-2 text-sm font-medium">عنوان اجندا<Input value={item.agenda_title} onChange={e => { const items=[...form.items]; items[index]={...items[index],agenda_title:e.target.value}; setForm({...form,items}) }}/></label><label className="grid gap-2 text-sm font-medium">خلاصهٔ بحث<Textarea rows={2} value={item.discussion_summary} onChange={e => { const items=[...form.items]; items[index]={...items[index],discussion_summary:e.target.value}; setForm({...form,items}) }}/></label></CardContent></Card>)}</div>
            <div className="flex items-center justify-between"><h3 className="text-sm font-semibold">تصمیمات</h3><Button size="sm" variant="outline" onClick={addDecision}><Plus className="size-4"/>افزودن</Button></div>
            <div className="space-y-3">{form.decisions.map((decision: Any, index: number) => <Card key={index}><CardContent className="grid gap-4 p-4 md:grid-cols-[1fr_14rem]"><label className="grid gap-2 text-sm font-medium">متن تصمیم<Textarea rows={2} value={decision.decision_text} onChange={e => { const decisions=[...form.decisions]; decisions[index]={...decisions[index],decision_text:e.target.value}; setForm({...form,decisions}) }}/></label><label className="grid gap-2 text-sm font-medium">تاریخ هدف<Input type="date" value={decision.target_date} onChange={e => { const decisions=[...form.decisions]; decisions[index]={...decisions[index],target_date:e.target.value}; setForm({...form,decisions}) }}/></label></CardContent></Card>)}</div>
            <div className="flex flex-wrap gap-2"><Button variant="outline" disabled={busy} onClick={saveDraft}><Save className="size-4"/>ذخیرهٔ پیش‌نویس</Button>{minutes.status === 'DRAFT' || minutes.status === 'RETURNED_FOR_CORRECTION' ? <Button disabled={busy} onClick={submit}><Send className="size-4"/>{minutes.status === 'DRAFT' ? 'ارسال برای بررسی' : 'ارسال نسخهٔ اصلاح‌شده'}</Button> : null}</div>
          </> : <>
            <div className="rounded-xl border bg-muted/20 p-4"><p className="text-xs text-muted-foreground">خلاصهٔ عمومی</p><p className="mt-2 text-sm leading-7">{version?.summary || '—'}</p>{version?.general_notes ? <p className="mt-3 text-sm leading-7 text-muted-foreground">{version.general_notes}</p> : null}</div>
            <div><h3 className="mb-3 text-sm font-semibold">اجندا و مباحث</h3><div className="divide-y rounded-xl border">{(version?.items || []).map((item: Any) => <div className="p-4" key={item.id}><p className="font-medium">{item.sequence_number}. {item.agenda_title}</p><p className="mt-1 text-sm text-muted-foreground">{item.discussion_summary}</p></div>)}</div></div>
            <div><h3 className="mb-3 text-sm font-semibold">تصمیمات</h3><div className="divide-y rounded-xl border">{(version?.decisions || []).map((decision: Any) => <div className="p-4" key={decision.id}><p className="font-medium">{decision.decision_number}</p><p className="mt-1 text-sm text-muted-foreground">{decision.decision_text}</p></div>)}</div></div>
          </>}
        </CardContent></Card>

        <Card><CardHeader className="flex flex-row items-center justify-between border-b"><CardTitle className="text-base">حضور رسمی</CardTitle><Users className="size-5 text-muted-foreground"/></CardHeader><CardContent className="divide-y pt-2">{(version?.attendance || []).map((person: Any) => <div className="flex items-center justify-between gap-4 py-3" key={person.employee_id}><div><p className="font-medium">{person.display_name}</p><p className="text-xs text-muted-foreground">{person.organizational_unit_id} · {person.participant_role}</p></div><Badge variant="outline">{person.attendance_status}</Badge></div>)}{!(version?.attendance || []).length ? <p className="py-5 text-sm text-muted-foreground">دادهٔ حضور در نسخهٔ جاری موجود نیست.</p> : null}</CardContent></Card>
      </section>

      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
        <Card><CardHeader className="border-b"><CardTitle className="text-base">ماتریس بررسی</CardTitle></CardHeader><CardContent className="space-y-4 pt-5">{(version?.reviews || []).map((review: Any) => <div className="space-y-3 rounded-xl border p-3" key={review.id}><div className="flex items-start justify-between gap-3"><div><p className="font-medium">{review.reviewer_type === 'HIGH_AUTHORITY' ? 'مقام عالی' : `ریاست ${review.organizational_unit_id}`}</p><p className="text-xs text-muted-foreground">{label(review.status)}</p></div><Badge variant="outline">{label(review.status)}</Badge></div><div className="flex flex-wrap gap-2">{review.reviewer_type === 'UNIT' && review.status === 'PENDING' ? <Button size="sm" disabled={busy} onClick={() => acknowledge(review.organizational_unit_id)}>تأیید</Button> : null}{['PENDING','ACKNOWLEDGED'].includes(review.status) ? <Button size="sm" variant="outline" disabled={busy} onClick={() => requestCorrection(review)}>برگشت</Button> : null}{review.reviewer_type === 'HIGH_AUTHORITY' && review.status === 'PENDING' ? <Button size="sm" disabled={busy} onClick={approveAuthority}>تأیید مقام عالی</Button> : null}</div></div>)}{!(version?.reviews || []).length ? <p className="text-sm text-muted-foreground">بررسی‌ها پس از ارسال نسخه ایجاد می‌شوند.</p> : null}<label className="grid gap-2 text-sm font-medium">دلیل درخواست اصلاح<Textarea rows={3} value={correction} onChange={e => setCorrection(e.target.value)} placeholder="دلیل اجباری برای برگشت نسخه"/></label></CardContent></Card>

        <Card><CardHeader className="border-b"><CardTitle className="text-base">نهایی‌سازی</CardTitle></CardHeader><CardContent className="space-y-4 pt-5">{minutes.status === 'READY_TO_FINALIZE' ? <Button className="w-full" disabled={busy} onClick={() => setConfirmFinalize(true)}><CheckCircle2 className="size-4"/>نهایی‌سازی صورت‌جلسه</Button> : null}{minutes.status === 'FINALIZED' ? <Alert><CheckCircle2 className="size-4"/><AlertDescription>صورت‌جلسه نهایی شده و دیگر قابل ویرایش نیست.</AlertDescription></Alert> : null}{minutes.status !== 'READY_TO_FINALIZE' && minutes.status !== 'FINALIZED' ? <p className="text-sm text-muted-foreground">پس از تکمیل همهٔ بررسی‌های لازم، نهایی‌سازی مطابق Backend فعال می‌شود.</p> : null}</CardContent></Card>

        <WorkflowTimeline title="تاریخچهٔ نسخه‌ها" items={(minutes.versions || []).map((item: Any) => ({ id: String(item.id), title: `نسخهٔ ${item.version_number}`, meta: item.submitted_at ? 'ارسال‌شده برای بررسی' : 'پیش‌نویس' }))}/>
      </aside>
    </div> : null}

    <ConfirmDialog open={confirmFinalize} onOpenChange={setConfirmFinalize} title="صورت‌جلسه نهایی شود؟" description="نهایی‌سازی طبق قواعد موجود Backend انجام می‌شود و نسخهٔ نهایی دیگر قابل ویرایش نخواهد بود." confirmLabel="نهایی‌سازی" onConfirm={finalize}/>
  </div>
}
