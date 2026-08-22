import { useEffect, useState } from 'react'
import axios from 'axios'
import { ArrowRight, CheckCircle2, Clock3, FileText, LockKeyhole, Plus, ShieldCheck } from 'lucide-react'

export { ActionItemsWorkspace, ActionItemDetailWorkspace } from './flow12_legacy'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { PageHeader } from '@/components/amms/page-header'

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: any) => response.data?.data ?? response.data
const labels: Record<string,string> = {UNCLASSIFIED:'طبقه‌بندی‌نشده',NO_ACTION_REQUIRED:'بدون نیاز به اقدام',ACTION_REQUIRED:'نیازمند اقدام',ACTIVE:'در حال اجرا',CLOSED:'بسته‌شده',OPEN:'باز',IN_PROGRESS:'در حال اجرا',BLOCKED:'مسدود',PENDING_VERIFICATION:'منتظر تأیید',COMPLETED:'تکمیل‌شده',CANCELLED:'لغوشده'}
const label = (value?: string) => value ? (labels[value] || value) : '—'
const apiError = (error: any) => error?.response?.data?.message || 'عملیات انجام نشد. لطفاً صلاحیت و وضعیت اقدام را بررسی نمایید.'

function StatusBadge({ value }: { value?: string }) {
  const tone = value === 'COMPLETED' || value === 'CLOSED' || value === 'NO_ACTION_REQUIRED' ? 'secondary' : value === 'BLOCKED' || value === 'CANCELLED' ? 'destructive' : 'outline'
  return <Badge variant={tone}>{label(value)}</Badge>
}

export function MeetingFollowUpWorkspace() {
  const id = window.location.pathname.split('/')[2]
  const [data, setData] = useState<any>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [selected, setSelected] = useState<any>(null)
  const [dialogMode, setDialogMode] = useState<'classify'|'action'|null>(null)
  const [classification, setClassification] = useState('ACTION_REQUIRED')
  const [reason, setReason] = useState('')
  const [form, setForm] = useState<any>({ title: '', description: '', responsible_unit_id: '', assigned_employee_id: '', due_date: '' })

  const load = async () => {
    try { setError(''); setData(dataOf(await api.get(`/meetings/${id}/follow-up`))) }
    catch (e) { setError(apiError(e)) }
  }
  useEffect(() => { load() }, [id])

  const initialize = async () => {
    try { setBusy(true); setError(''); await api.post(`/meetings/${id}/follow-up/initialize`); await load() }
    catch (e) { setError(apiError(e)) }
    finally { setBusy(false) }
  }
  const closeDialog = () => { setSelected(null); setDialogMode(null); setReason('') }
  const classify = async () => {
    if (!selected) return
    if (classification === 'NO_ACTION_REQUIRED' && !reason.trim()) return setError('دلیل «بدون نیاز به اقدام» الزامی است.')
    try {
      setBusy(true); setError('')
      await api.post(`/decisions/${selected.id}/follow-up/classify`, { classification, reason: reason || undefined })
      closeDialog(); await load()
    } catch (e) { setError(apiError(e)) } finally { setBusy(false) }
  }
  const createItem = async () => {
    if (!selected) return
    try {
      setBusy(true); setError('')
      await api.post(`/decisions/${selected.id}/action-items`, { ...form, responsible_unit_id: Number(form.responsible_unit_id), assigned_employee_id: form.assigned_employee_id ? Number(form.assigned_employee_id) : undefined })
      setForm({ title: '', description: '', responsible_unit_id: '', assigned_employee_id: '', due_date: '' })
      closeDialog(); await load()
    } catch (e) { setError(apiError(e)) } finally { setBusy(false) }
  }
  const openClassify = (followup: any) => { setSelected(followup); setDialogMode('classify'); setClassification('ACTION_REQUIRED'); setReason('') }
  const openAction = (followup: any) => {
    setSelected(followup); setDialogMode('action')
    setForm({ title: '', description: '', responsible_unit_id: followup.decision?.responsible_unit_id || '', assigned_employee_id: followup.decision?.responsible_employee_id || '', due_date: followup.decision?.target_date || '' })
  }

  if (!data) return <div className="screen-center"><div className="spinner"/></div>
  const metrics = [
    ['کل تصمیمات', data.summary?.total_decisions || 0], ['نیازمند اقدام', data.summary?.action_required || 0], ['بدون نیاز به اقدام', data.summary?.no_action_required || 0],
    ['باز', data.summary?.open || 0], ['در حال اجرا', data.summary?.in_progress || 0], ['مسدود', data.summary?.blocked || 0],
    ['منتظر تأیید', data.summary?.pending_verification || 0], ['تکمیل‌شده', data.summary?.completed || 0], ['تأخیری', data.summary?.overdue || 0],
  ]

  return <div className="space-y-6" dir="rtl">
    <PageHeader eyebrow={`${data.meeting?.meeting_number || 'جلسه'} · FLOW-12`} title="فضای پیگیری تصمیمات" description="پیگیری عملیاتی تصمیمات مندرج در صورت‌جلسهٔ نهایی‌شده، بدون تغییر در سند رسمی." action={<div className="flex flex-wrap items-center gap-2"><StatusBadge value={data.meeting?.status}/><Button variant="outline" asChild><a href={`/meetings/${id}`}>بازگشت به جلسه <ArrowRight className="size-4"/></a></Button></div>}/>

    {error && <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>}

    <DetailSummaryCard title="مبنای پیگیری تصمیمات" items={[
      { label: 'صورت‌جلسه', value: data.minutes?.status === 'FINALIZED' ? 'نهایی‌شده' : label(data.minutes?.status) },
      { label: 'نسخهٔ نهایی', value: data.minutes?.version_number || '—' },
      { label: 'تاریخ نهایی‌سازی', value: data.minutes?.finalized_at ? new Date(data.minutes.finalized_at).toLocaleString('fa-AF') : '—' },
      { label: 'تعداد تصمیمات', value: data.decision_count ?? 0 },
    ]}/>

    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
      {metrics.map(([name,value]) => <Card key={String(name)}><CardContent className="flex items-center gap-3 p-4"><div className="rounded-lg border bg-muted p-2"><Clock3 className="size-4"/></div><div><p className="text-xs text-muted-foreground">{name}</p><p className="text-xl font-semibold">{value}</p></div></CardContent></Card>)}
    </div>

    {!data.followup_initialized && <Card className="border-dashed"><CardContent className="flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center sm:justify-between"><div className="flex gap-3"><FileText className="mt-1 size-5 text-muted-foreground"/><div><p className="font-medium">پیگیری تصمیمات هنوز ایجاد نشده است.</p><p className="text-sm text-muted-foreground">ایجاد پیگیری، تصمیمات نسخهٔ نهایی را وارد حوزهٔ عملیاتی FLOW-12 می‌کند.</p></div></div><Button disabled={busy} onClick={initialize}><CheckCircle2 className="size-4"/>{busy ? 'در حال ایجاد…' : 'ایجاد پیگیری تصمیمات'}</Button></CardContent></Card>}

    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div className="space-y-4">
        {(data.followups || []).map((f: any) => <Card key={f.id}><CardHeader className="gap-3 border-b"><div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p className="text-xs font-medium text-muted-foreground">{f.decision_number}</p><CardTitle className="mt-1 text-base leading-7">{f.decision?.decision_text}</CardTitle></div><StatusBadge value={f.status}/></div></CardHeader><CardContent className="space-y-5 p-5">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><p className="text-xs text-muted-foreground">ریاست مستند</p><p className="mt-1 font-medium">{f.decision?.responsible_unit_id || '—'}</p></div>
            <div><p className="text-xs text-muted-foreground">کارمند مستند</p><p className="mt-1 font-medium">{f.decision?.responsible_employee_id || '—'}</p></div>
            <div><p className="text-xs text-muted-foreground">تاریخ هدف مستند</p><p className="mt-1 font-medium">{f.decision?.target_date || '—'}</p></div>
            <div><p className="text-xs text-muted-foreground">تعداد اقدامات</p><p className="mt-1 font-medium">{f.action_item_count}</p></div>
          </div>
          {f.status === 'NO_ACTION_REQUIRED' && <Alert><ShieldCheck className="size-4"/><AlertDescription>{f.no_action_reason}</AlertDescription></Alert>}
          {f.action_item_count > 0 && <div className="divide-y rounded-lg border">{(f.action_items || []).map((item:any) => <a className="flex items-center justify-between gap-4 p-3 hover:bg-muted/50" href={`/action-items/${item.id}`} key={item.id}><div><p className="font-medium">{item.action_number} · {item.title}</p><p className="text-xs text-muted-foreground">{item.responsible_unit?.name || '—'} · {item.due_date || 'بدون مهلت'}</p></div><StatusBadge value={item.status}/></a>)}</div>}
          <div className="flex flex-wrap gap-2">{f.status === 'UNCLASSIFIED' && <Button onClick={() => openClassify(f)}>طبقه‌بندی تصمیم</Button>}{f.status !== 'NO_ACTION_REQUIRED' && <Button variant="outline" onClick={() => openAction(f)}><Plus className="size-4"/> ایجاد اقدام</Button>}</div>
        </CardContent></Card>)}
        {data.followup_initialized && !(data.followups || []).length && <Card className="border-dashed"><CardContent className="p-8 text-center text-sm text-muted-foreground">تصمیمی در نسخهٔ نهایی یافت نشد.</CardContent></Card>}
      </div>
      <Card className="h-fit"><CardHeader><LockKeyhole className="size-5 text-muted-foreground"/><CardTitle className="text-base">مرز سند رسمی</CardTitle></CardHeader><CardContent className="space-y-4 text-sm text-muted-foreground"><p>اقدام‌ها در حوزهٔ عملیاتی جداگانه ثبت می‌شوند. وضعیت جلسه، صورت‌جلسه و متن تصمیم با پیشرفت اقدام تغییر نمی‌کند.</p><Button variant="outline" className="w-full" asChild><a href="/action-items">فضای اقدامات من <ArrowRight className="size-4"/></a></Button></CardContent></Card>
    </div>

    <Dialog open={Boolean(selected && dialogMode)} onOpenChange={(open) => { if (!open) closeDialog() }}>
      <DialogContent dir="rtl" className="sm:max-w-xl">
        <DialogHeader><DialogTitle>{dialogMode === 'action' ? 'ایجاد اقدام عملیاتی' : 'طبقه‌بندی تصمیم'}</DialogTitle><DialogDescription>{selected?.decision_number ? `تصمیم ${selected.decision_number}` : 'تصمیم انتخاب‌شده'}</DialogDescription></DialogHeader>
        {dialogMode === 'action' ? <div className="space-y-4"><label className="grid gap-2 text-sm font-medium">عنوان اقدام<Input value={form.title} onChange={e => setForm({ ...form, title: e.target.value })}/></label><label className="grid gap-2 text-sm font-medium">شرح اقدام<Textarea rows={3} value={form.description} onChange={e => setForm({ ...form, description: e.target.value })}/></label><div className="grid gap-4 sm:grid-cols-2"><label className="grid gap-2 text-sm font-medium">واحد مسئول<Input type="number" value={form.responsible_unit_id} onChange={e => setForm({ ...form, responsible_unit_id: e.target.value })}/></label><label className="grid gap-2 text-sm font-medium">کارمند مسئول<Input type="number" value={form.assigned_employee_id} onChange={e => setForm({ ...form, assigned_employee_id: e.target.value })}/></label></div><label className="grid gap-2 text-sm font-medium">مهلت<Input type="date" value={form.due_date} onChange={e => setForm({ ...form, due_date: e.target.value })}/></label><DialogFooter><Button variant="outline" disabled={busy} onClick={closeDialog}>انصراف</Button><Button disabled={busy} onClick={createItem}>{busy ? 'در حال ثبت…' : 'ثبت اقدام'}</Button></DialogFooter></div> : <div className="space-y-4"><label className="grid gap-2 text-sm font-medium">طبقه‌بندی<select className="h-10 rounded-md border bg-background px-3 text-sm" value={classification} onChange={e => setClassification(e.target.value)}><option value="ACTION_REQUIRED">نیازمند اقدام</option><option value="NO_ACTION_REQUIRED">بدون نیاز به اقدام</option></select></label>{classification === 'NO_ACTION_REQUIRED' && <label className="grid gap-2 text-sm font-medium">دلیل الزامی<Textarea rows={4} value={reason} onChange={e => setReason(e.target.value)}/></label>}<DialogFooter><Button variant="outline" disabled={busy} onClick={closeDialog}>انصراف</Button><Button disabled={busy} onClick={classify}>{busy ? 'در حال ثبت…' : 'ثبت طبقه‌بندی'}</Button></DialogFooter></div>}
      </DialogContent>
    </Dialog>
  </div>
}
