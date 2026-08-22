import { useEffect, useState } from 'react'
import axios from 'axios'
import { CheckCircle2, Clock3, FileText, ShieldAlert } from 'lucide-react'

export { MeetingFollowUpWorkspace } from './flow12_followup'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { PageHeader } from '@/components/amms/page-header'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

type Any = any
const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: Any) => response.data?.data ?? response.data
const labels: Record<string,string> = {OPEN:'باز',IN_PROGRESS:'در حال اجرا',BLOCKED:'مسدود',PENDING_VERIFICATION:'منتظر تأیید',COMPLETED:'تکمیل‌شده',CANCELLED:'لغوشده',STARTED:'شروع‌شده',PROGRESS:'ثبت پیشرفت',BLOCKED_UPDATE:'ثبت مانع',UNBLOCKED:'رفع مانع',COMPLETION_SUBMITTED:'ارسال برای تأیید',COMPLETION_VERIFIED:'تأیید تکمیل',COMPLETION_RETURNED:'برگشت برای اصلاح'}
const label = (value?: string) => value ? (labels[value] || value) : '—'
const apiError = (error: Any) => error?.response?.data?.message || 'عملیات انجام نشد. لطفاً صلاحیت و وضعیت اقدام را بررسی نمایید.'
const badgeVariant = (value?: string) => value === 'COMPLETED' ? 'secondary' : value === 'BLOCKED' || value === 'CANCELLED' ? 'destructive' : 'outline'

export function ActionItemsWorkspace() {
  const [items, setItems] = useState<Any[]>([])
  const [status, setStatus] = useState('')
  const [overdue, setOverdue] = useState(false)
  const [error, setError] = useState('')
  const load = async () => {
    try {
      setError('')
      const res = await api.get('/action-items', { params: { status: status || undefined, overdue: overdue || undefined } })
      const payload = res.data?.data ?? res.data
      setItems(Array.isArray(payload) ? payload : payload?.data || [])
    } catch (e) { setError(apiError(e)) }
  }
  useEffect(() => { load() }, [status, overdue])

  return <div className="space-y-6" dir="rtl">
    <PageHeader eyebrow="FLOW-12" title="اقدامات من" description="اقدام‌های عملیاتی در حوزهٔ صلاحیت شما؛ بدون تغییر در متن تصمیم یا صورت‌جلسهٔ رسمی." action={<Button asChild variant="outline"><a href="/meetings">جلسات</a></Button>} />
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
    <Card><CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-end"><label className="grid min-w-56 gap-2 text-sm font-medium">وضعیت<select className="h-10 rounded-md border bg-background px-3 text-sm" value={status} onChange={e => setStatus(e.target.value)}><option value="">همه وضعیت‌ها</option>{['OPEN','IN_PROGRESS','BLOCKED','PENDING_VERIFICATION','COMPLETED','CANCELLED'].map(key => <option key={key} value={key}>{label(key)}</option>)}</select></label><label className="flex h-10 items-center gap-2 rounded-md border px-3 text-sm"><input type="checkbox" checked={overdue} onChange={e => setOverdue(e.target.checked)}/> فقط تأخیری</label><div className="sm:mr-auto"><Badge variant="outline">{items.length} مورد</Badge></div></CardContent></Card>
    <Card><CardHeader className="border-b"><CardTitle className="text-base">فهرست اقدامات</CardTitle></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>شماره اقدام</TableHead><TableHead>تصمیم</TableHead><TableHead>عنوان</TableHead><TableHead>مسئول</TableHead><TableHead>مهلت</TableHead><TableHead>پیشرفت</TableHead><TableHead>وضعیت</TableHead></TableRow></TableHeader><TableBody>{items.map(item => <TableRow key={item.id}><TableCell><a className="font-mono font-medium text-primary hover:underline" href={`/action-items/${item.id}`}>{item.action_number}</a></TableCell><TableCell>{item.decision_number || '—'}</TableCell><TableCell className="min-w-52 font-medium">{item.title}</TableCell><TableCell>{item.responsible_unit?.name || '—'}</TableCell><TableCell><span className={item.is_overdue ? 'font-medium text-destructive' : ''}>{item.due_date || '—'}{item.is_overdue ? ` · ${item.days_overdue} روز تأخیر` : ''}</span></TableCell><TableCell>{item.progress_percent}%</TableCell><TableCell><Badge variant={badgeVariant(item.status)}>{label(item.status)}</Badge></TableCell></TableRow>)}</TableBody></Table></div>{!items.length ? <div className="flex flex-col items-center gap-2 p-10 text-sm text-muted-foreground"><Clock3 className="size-6"/><span>اقدامی در حوزهٔ شما یافت نشد.</span></div> : null}</CardContent></Card>
  </div>
}

export function ActionItemDetailWorkspace() {
  const id = window.location.pathname.split('/')[2]
  const [item, setItem] = useState<Any>(null)
  const [error, setError] = useState('')
  const [progress, setProgress] = useState(0)
  const [note, setNote] = useState('')
  const [reason, setReason] = useState('')
  const [extensionDate, setExtensionDate] = useState('')
  const [extensionReason, setExtensionReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [confirmCancel, setConfirmCancel] = useState(false)

  const load = async () => { try { setError(''); setItem(dataOf(await api.get(`/action-items/${id}`))) } catch (e) { setError(apiError(e)) } }
  useEffect(() => { load() }, [id])
  const action = async (url: string, body?: Any) => { try { setBusy(true); setError(''); await api.post(`/action-items/${id}/${url}`, body); await load() } catch (e) { setError(apiError(e)); throw e } finally { setBusy(false) } }
  const submitCompletion = () => { if (!note.trim()) { setError('یادداشت تکمیل الزامی است.'); return } action('completion/submit', { completion_note: note }) }
  const cancel = async () => { if (!reason.trim()) { setError('دلیل لغو الزامی است.'); throw new Error('reason required') } await action('cancel', { reason }) }

  if (!item) return <div className="screen-center">{error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="spinner"/>}</div>

  return <div className="space-y-6" dir="rtl">
    <PageHeader eyebrow={`${item.action_number} · FLOW-12`} title={item.title} description={item.decision_number ? `منبع تصمیم رسمی ${item.decision_number}` : undefined} action={<div className="flex flex-wrap items-center gap-2"><Badge variant={badgeVariant(item.status)}>{label(item.status)}</Badge><Button asChild variant="outline"><a href="/action-items">بازگشت</a></Button></div>} />
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section className="space-y-6">
        <DetailSummaryCard title="خلاصهٔ اقدام" items={[
          { label: 'جلسه', value: item.meeting_number || '—' },
          { label: 'واحد مسئول', value: item.responsible_unit?.name || '—' },
          { label: 'کارمند مسئول', value: item.assigned_employee?.name || '—' },
          { label: 'مهلت', value: <span className={item.is_overdue ? 'text-destructive' : ''}>{item.due_date || '—'}{item.is_overdue ? ` · ${item.days_overdue} روز تأخیر` : ''}</span> },
          { label: 'پیشرفت', value: `${item.progress_percent}%` },
          { label: 'وضعیت', value: <Badge variant={badgeVariant(item.status)}>{label(item.status)}</Badge> },
        ]} description={<div className="space-y-3"><div><p className="text-xs font-medium text-foreground">متن تصمیم رسمی</p><p className="mt-1">{item.decision_text || '—'}</p></div><div><p className="text-xs font-medium text-foreground">شرح اقدام</p><p className="mt-1">{item.description || '—'}</p></div></div>} />

        <Card><CardHeader className="border-b"><CardTitle className="text-base">ثبت پیشرفت و نتیجه</CardTitle></CardHeader><CardContent className="space-y-5 pt-5"><div className="grid gap-4 sm:grid-cols-[12rem_1fr]"><label className="grid gap-2 text-sm font-medium">درصد پیشرفت<Input type="number" min="0" max="100" value={progress} onChange={e => setProgress(Number(e.target.value))}/></label><label className="grid gap-2 text-sm font-medium">یادداشت<Textarea rows={2} value={note} onChange={e => setNote(e.target.value)} placeholder="یادداشت پیشرفت یا تکمیل"/></label></div><label className="grid gap-2 text-sm font-medium">دلیل مانع / برگشت / لغو<Textarea rows={3} value={reason} onChange={e => setReason(e.target.value)}/></label><div className="flex flex-wrap gap-2">{item.status === 'OPEN' ? <Button disabled={busy} onClick={() => action('start')}><CheckCircle2 className="size-4"/>شروع کار</Button> : null}{['IN_PROGRESS','BLOCKED'].includes(item.status) ? <Button variant="outline" disabled={busy} onClick={() => action('progress', { progress_percent: progress, note: note || undefined })}>ثبت پیشرفت</Button> : null}{item.status === 'IN_PROGRESS' ? <Button variant="outline" disabled={busy} onClick={() => { if (!reason.trim()) return setError('دلیل مانع الزامی است.'); action('block', { reason }) }}>اعلام مانع</Button> : null}{item.status === 'BLOCKED' ? <Button variant="outline" disabled={busy} onClick={() => { if (!reason.trim()) return setError('یادداشت رفع مانع الزامی است.'); action('unblock', { note: reason }) }}>رفع مانع</Button> : null}{['IN_PROGRESS','BLOCKED'].includes(item.status) && item.progress_percent === 100 ? <Button disabled={busy} onClick={submitCompletion}>ارسال برای تأیید</Button> : null}{item.status === 'PENDING_VERIFICATION' ? <><Button disabled={busy} onClick={() => action('completion/verify')}>تأیید تکمیل</Button><Button variant="outline" disabled={busy} onClick={() => { if (!reason.trim()) return setError('دلیل برگشت الزامی است.'); action('completion/return', { reason }) }}>برگشت برای اصلاح</Button></> : null}{['OPEN','IN_PROGRESS','BLOCKED'].includes(item.status) ? <Button variant="destructive" disabled={busy} onClick={() => setConfirmCancel(true)}>لغو اقدام</Button> : null}</div></CardContent></Card>

        <Card><CardHeader className="flex flex-row items-center justify-between border-b"><CardTitle className="text-base">درخواست تمدید مهلت</CardTitle><Clock3 className="size-5 text-muted-foreground"/></CardHeader><CardContent className="space-y-4 pt-5"><div className="grid gap-4 sm:grid-cols-[14rem_1fr]"><label className="grid gap-2 text-sm font-medium">تاریخ جدید<Input type="date" value={extensionDate} onChange={e => setExtensionDate(e.target.value)}/></label><label className="grid gap-2 text-sm font-medium">دلیل تمدید<Textarea rows={2} value={extensionReason} onChange={e => setExtensionReason(e.target.value)}/></label></div><Button variant="outline" disabled={busy} onClick={() => { if (!extensionDate || !extensionReason.trim()) return setError('تاریخ و دلیل تمدید الزامی است.'); action('extensions', { requested_due_date: extensionDate, reason: extensionReason }) }}>درخواست تمدید</Button></CardContent></Card>
      </section>

      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
        <WorkflowTimeline title="تاریخچهٔ غیرقابل حذف" items={(item.updates || []).map((u: Any) => ({ id: String(u.id), title: label(u.update_type), meta: `${u.progress_percent !== null ? `${u.progress_percent}% · ` : ''}${u.created_at ? new Date(u.created_at).toLocaleString('fa-AF') : ''}`, comment: u.note || undefined }))}/>
        <Card><CardHeader className="flex flex-row items-center justify-between border-b"><CardTitle className="text-base">شواهد</CardTitle><FileText className="size-5 text-muted-foreground"/></CardHeader><CardContent className="space-y-2 pt-5">{(item.evidence || []).map((e: Any) => <a className="block rounded-xl border p-3 hover:bg-muted/40" key={e.id} href={e.download_url}><p className="font-medium">{e.original_filename}</p><p className="mt-1 text-xs text-muted-foreground">{e.mime_type} · {e.file_size} بایت</p></a>)}{!(item.evidence || []).length ? <p className="text-sm text-muted-foreground">هنوز شاهدی بارگذاری نشده است.</p> : null}</CardContent></Card>
        {item.is_overdue ? <Alert variant="destructive"><ShieldAlert className="size-4"/><AlertDescription>این اقدام از مهلت ثبت‌شده عبور کرده است.</AlertDescription></Alert> : null}
      </aside>
    </div>
    <ConfirmDialog open={confirmCancel} onOpenChange={setConfirmCancel} title="اقدام لغو شود؟" description="دلیل درج‌شده همراه با لغو ثبت می‌شود و تاریخچهٔ اقدام حفظ خواهد شد." confirmLabel="لغو اقدام" variant="destructive" onConfirm={cancel}/>
  </div>
}
