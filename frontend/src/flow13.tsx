import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { AlertCircle, ArrowRight, CalendarClock, Clock3, LockKeyhole, ShieldCheck } from 'lucide-react'

import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { PageHeader } from '@/components/amms/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: any) => response.data?.data ?? response.data
const labels: Record<string, string> = { SCHEDULED: 'زمان‌بندی‌شده', POSTPONED: 'به تعویق افتاده', CANCELLED: 'لغو شده', DRAFT: 'پیش‌نویس', UNDER_REVIEW: 'در حال بررسی', RETURNED_FOR_CORRECTION: 'برگشت برای اصلاح', READY_TO_APPLY: 'آمادهٔ اجرا', APPLIED: 'اجراشده', REJECTED: 'رد شده', WITHDRAWN: 'پس‌گرفته‌شده', POSTPONEMENT: 'تعویق', CANCELLATION: 'لغو', REOPENING: 'بازگشایی', RESUMPTION: 'فعال‌سازی مجدد', PENDING: 'در انتظار', ACKNOWLEDGED: 'تأیید دریافت', APPROVED: 'تأییدشده', CORRECTION_REQUESTED: 'نیازمند اصلاح', UNIT: 'ریاست', HIGH_AUTHORITY: 'مقام عالی', APPROVAL: 'تأیید', ACKNOWLEDGEMENT: 'تأیید دریافت' }
const label = (value?: string) => value ? (labels[value] || value) : '—'
const apiError = (error: any) => error?.response?.data?.message || 'عملیات انجام نشد. صلاحیت، نسخه یا وضعیت جلسه را بررسی نمایید.'

function StatusBadge({ value }: { value?: string }) {
  const tone = value === 'APPLIED' || value === 'APPROVED' || value === 'ACKNOWLEDGED' ? 'secondary' : value === 'REJECTED' || value === 'CANCELLED' ? 'destructive' : 'outline'
  return <Badge variant={tone}>{label(value)}</Badge>
}

export function MeetingExceptionWorkspace() {
  const id = window.location.pathname.split('/')[2]
  const [data, setData] = useState<any>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [selected, setSelected] = useState<any>(null)
  const [confirmApply, setConfirmApply] = useState<any>(null)
  const [caseType, setCaseType] = useState('POSTPONEMENT')
  const [reason, setReason] = useState('')
  const [schedule, setSchedule] = useState({ meeting_date: '', start_time: '', end_time: '', location: '' })

  const load = async () => {
    try { setError(''); setData(dataOf(await api.get(`/meetings/${id}/exceptions`))) }
    catch (e) { setError(apiError(e)) }
  }
  useEffect(() => { load() }, [id])
  const cases = useMemo(() => Array.isArray(data) ? data : data?.data || [], [data])

  const run = async (method: 'post' | 'put', url: string, body?: any) => {
    try { setBusy(true); setError(''); await api[method](url, body); setSelected(null); setReason(''); await load() }
    catch (e) { setError(apiError(e)) }
    finally { setBusy(false) }
  }
  const create = () => {
    if (!reason.trim()) return setError('دلیل رسمی الزامی است.')
    run('post', `/meetings/${id}/exceptions`, { case_type: caseType, reason })
  }
  const action = (kind: string, item: any) => {
    if (kind === 'submit') return run('post', `/meeting-exceptions/${item.id}/submit`)
    if (kind === 'apply') return run('post', `/meeting-exceptions/${item.id}/apply`)
    if (kind === 'resume') {
      if (!schedule.meeting_date || !schedule.start_time || !schedule.end_time || !schedule.location) return setError('تاریخ، آغاز، پایان و محل زمان‌بندی جدید الزامی است.')
      return run('post', `/meeting-exceptions/${item.id}/resume`, schedule)
    }
    if (kind === 'withdraw') {
      if (!reason.trim()) return setError('دلیل پس‌گرفتن الزامی است.')
      return run('post', `/meeting-exceptions/${item.id}/withdraw`, { reason })
    }
  }

  if (!data) return <div className="screen-center"><div className="spinner"/></div>

  return <div className="space-y-6" dir="rtl">
    <PageHeader eyebrow={`${data.meeting?.meeting_number || 'جلسه'} · FLOW-13`} title="مدیریت وضعیت استثنایی جلسه" description="تعویق، لغو، بازگشایی و تعیین زمان جدید با حفظ کامل تاریخچهٔ جلسه." action={<div className="flex flex-wrap items-center gap-2"><StatusBadge value={data.meeting?.status}/><Button variant="outline" asChild><a href={`/meetings/${id}`}>بازگشت به جلسه <ArrowRight className="size-4"/></a></Button></div>}/>

    {error && <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>}

    <DetailSummaryCard items={[
      { label: 'شماره جلسه', value: data.meeting?.meeting_number || '—' },
      { label: 'وضعیت فعلی', value: label(data.meeting?.status) },
      { label: 'نسخهٔ زمان‌بندی', value: data.meeting?.current_schedule_version_id || '—' },
      { label: 'درخواست اصلی', value: data.meeting?.request_status || 'CONVERTED_TO_MEETING' },
    ]}/>

    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div className="space-y-4">
        <Card><CardHeader className="border-b"><div className="flex items-center justify-between gap-3"><div><p className="text-xs font-medium text-muted-foreground">ثبت پروندهٔ جدید</p><CardTitle className="mt-1 text-lg">درخواست اقدام استثنایی</CardTitle></div><CalendarClock className="size-5 text-muted-foreground"/></div></CardHeader><CardContent className="space-y-4 p-5">
          <label className="grid gap-2 text-sm font-medium">نوع پرونده<select className="h-10 rounded-md border bg-background px-3 text-sm" value={caseType} onChange={e => setCaseType(e.target.value)}><option value="POSTPONEMENT">تعویق جلسه</option><option value="CANCELLATION">لغو جلسه</option><option value="REOPENING">بازگشایی جلسهٔ لغوشده</option><option value="RESUMPTION">تعیین زمان جدید و فعال‌سازی مجدد</option></select></label>
          <label className="grid gap-2 text-sm font-medium">دلیل رسمی<Textarea rows={4} value={reason} onChange={e => setReason(e.target.value)} placeholder="دلیل اداری و رسمی را درج نمایید…"/></label>
          {caseType === 'POSTPONEMENT' && <Alert><AlertCircle className="size-4"/><AlertDescription>اگر تاریخ جایگزین از قبل معلوم است، از FLOW-07 زمان‌بندی مجدد استفاده نمایید.</AlertDescription></Alert>}
          <Button disabled={busy} onClick={create}>{busy ? 'در حال ایجاد…' : 'ایجاد پرونده'} <ArrowRight className="size-4"/></Button>
        </CardContent></Card>

        {cases.map((item: any) => <Card key={item.id}><CardHeader className="border-b"><div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p className="text-xs font-medium text-muted-foreground">پروندهٔ #{item.id} · نسخه {item.current_version?.version_number || '—'}</p><CardTitle className="mt-1 text-lg">{label(item.case_type)}</CardTitle></div><StatusBadge value={item.status}/></div></CardHeader><CardContent className="space-y-5 p-5">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><p className="text-xs text-muted-foreground">وضعیت مبنا</p><div className="mt-1"><StatusBadge value={item.baseline?.meeting_status}/></div></div><div><p className="text-xs text-muted-foreground">نوع حاکمیت</p><p className="mt-1 font-medium">{item.governance_mode}</p></div><div><p className="text-xs text-muted-foreground">ثبت‌شده در</p><p className="mt-1 font-medium">{item.created_at ? new Date(item.created_at).toLocaleString('fa-AF') : '—'}</p></div><div><p className="text-xs text-muted-foreground">نسخهٔ فعلی</p><p className="mt-1 font-medium">{item.current_version?.submitted_at ? 'منجمد و ارسال‌شده' : 'قابل ویرایش'}</p></div></div>
          <div className="rounded-lg border bg-muted/30 p-4"><p className="text-xs font-medium text-muted-foreground">دلیل</p><p className="mt-2 text-sm leading-7">{item.current_version?.reason || '—'}</p></div>
          <div className="space-y-3"><div className="flex items-center gap-2 text-sm font-semibold"><ShieldCheck className="size-4"/> ماتریس بررسی نسخهٔ فعلی</div>{(item.reviews || []).map((review:any) => <div className="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between" key={review.id}><div><p className="font-medium">{label(review.reviewer_type)} · {label(review.review_requirement)}</p><p className="text-xs text-muted-foreground">{review.organizational_unit_id ? `واحد ${review.organizational_unit_id}` : review.authority_employee_id ? `کارمند مقام ${review.authority_employee_id}` : '—'}</p></div><div className="flex flex-wrap items-center gap-2"><StatusBadge value={review.status}/>{review.status === 'PENDING' && <><Button size="sm" variant="outline" disabled={busy} onClick={() => run('post', `/meeting-exception-reviews/${review.id}/acknowledge`)}>تأیید دریافت</Button><Button size="sm" disabled={busy} onClick={() => run('post', `/meeting-exception-reviews/${review.id}/approve`)}>تأیید</Button></>}</div></div>)}{!(item.reviews || []).length && <p className="text-sm text-muted-foreground">بررسی‌ها پس از ارسال پرونده ایجاد می‌شوند.</p>}</div>
          <div className="flex flex-wrap gap-2">{item.status === 'DRAFT' && <Button disabled={busy} onClick={() => action('submit', item)}>ارسال برای بررسی</Button>}{['DRAFT','UNDER_REVIEW','RETURNED_FOR_CORRECTION'].includes(item.status) && <Button variant="outline" disabled={busy} onClick={() => { setSelected(item); setReason(item.current_version?.reason || '') }}>پس‌گرفتن پرونده</Button>}{item.status === 'READY_TO_APPLY' && item.case_type !== 'RESUMPTION' && <Button disabled={busy} onClick={() => setConfirmApply(item)}>اجرای تصمیم</Button>}{item.status === 'READY_TO_APPLY' && item.case_type === 'RESUMPTION' && <Button disabled={busy} onClick={() => { setSchedule({ meeting_date: '', start_time: '', end_time: '', location: '' }); setSelected(item) }}>تعیین زمان جدید و فعال‌سازی</Button>}</div>
        </CardContent></Card>)}
      </div>

      <div className="space-y-4"><Card><CardHeader><LockKeyhole className="size-5 text-muted-foreground"/><CardTitle className="text-base">حفاظت از تاریخچه</CardTitle></CardHeader><CardContent><p className="text-sm leading-7 text-muted-foreground">پرونده‌ها، نسخه‌های ارسال‌شده، زمان‌بندی‌های قبلی و حضورهای ثبت‌شده حذف یا بازنویسی نمی‌شوند. اجرای تعویق یا لغو فقط وضعیت جاری و لایه‌های افزوده را تغییر می‌دهد.</p></CardContent></Card><Card><CardHeader><div className="flex items-center gap-2"><Clock3 className="size-4 text-muted-foreground"/><CardTitle className="text-base">خط زمانی پرونده‌ها</CardTitle></div></CardHeader><CardContent className="space-y-3">{cases.map((item:any) => <div className="border-r-2 pr-3" key={`timeline-${item.id}`}><p className="text-sm font-medium">{label(item.case_type)} · {label(item.status)}</p><p className="text-xs text-muted-foreground">پروندهٔ #{item.id} · نسخهٔ {item.current_version?.version_number || '—'}</p></div>)}{!cases.length && <p className="text-sm text-muted-foreground">پرونده‌ای ثبت نشده است.</p>}</CardContent></Card></div>
    </div>

    <Dialog open={Boolean(selected)} onOpenChange={(open) => { if (!open) setSelected(null) }}><DialogContent dir="rtl" className="sm:max-w-2xl"><DialogHeader><DialogTitle>{selected?.case_type === 'RESUMPTION' ? 'زمان‌بندی رسمی جدید' : 'پس‌گرفتن پرونده'}</DialogTitle><DialogDescription>{selected?.case_type === 'RESUMPTION' ? 'زمان‌بندی جدید به‌صورت یک نسخهٔ تازه ثبت می‌شود و تاریخچهٔ قبلی دست‌نخورده می‌ماند.' : 'دلیل اداری پس‌گرفتن را ثبت کنید. تاریخچهٔ پرونده حفظ خواهد شد.'}</DialogDescription></DialogHeader>{selected?.case_type === 'RESUMPTION' ? <div className="space-y-4"><div className="grid gap-4 sm:grid-cols-3"><label className="grid gap-2 text-sm font-medium">تاریخ جدید<Input type="date" value={schedule.meeting_date} onChange={e => setSchedule({ ...schedule, meeting_date: e.target.value })}/></label><label className="grid gap-2 text-sm font-medium">آغاز<Input type="time" value={schedule.start_time} onChange={e => setSchedule({ ...schedule, start_time: e.target.value })}/></label><label className="grid gap-2 text-sm font-medium">پایان<Input type="time" value={schedule.end_time} onChange={e => setSchedule({ ...schedule, end_time: e.target.value })}/></label></div><label className="grid gap-2 text-sm font-medium">محل جدید<Input value={schedule.location} onChange={e => setSchedule({ ...schedule, location: e.target.value })}/></label><DialogFooter><Button variant="outline" disabled={busy} onClick={() => setSelected(null)}>انصراف</Button><Button disabled={busy} onClick={() => action('resume', selected)}>{busy ? 'در حال اجرا…' : 'ایجاد نسخهٔ جدید و فعال‌سازی'}</Button></DialogFooter></div> : <div className="space-y-4"><label className="grid gap-2 text-sm font-medium">دلیل پس‌گرفتن<Textarea rows={4} value={reason} onChange={e => setReason(e.target.value)} placeholder="دلیل رسمی پس‌گرفتن پرونده را درج نمایید…"/></label><DialogFooter><Button variant="outline" disabled={busy} onClick={() => setSelected(null)}>انصراف</Button><Button variant="outline" className="border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:text-amber-900" disabled={busy} onClick={() => action('withdraw', selected)}>{busy ? 'در حال اجرا…' : 'پس‌گرفتن پرونده'}</Button></DialogFooter></div>}</DialogContent></Dialog>

    <ConfirmDialog open={Boolean(confirmApply)} onOpenChange={(open) => { if (!open) setConfirmApply(null) }} title="اجرای تصمیم استثنایی" description={confirmApply ? `آیا از اجرای ${label(confirmApply.case_type)} اطمینان دارید؟ این عملیات وضعیت جاری جلسه را مطابق پروندهٔ تأییدشده تغییر می‌دهد و تاریخچه حفظ می‌شود.` : ''} confirmLabel="اجرای تصمیم" pending={busy} onConfirm={async () => { if (!confirmApply) return; const item = confirmApply; setConfirmApply(null); await action('apply', item) }}/>
  </div>
}
