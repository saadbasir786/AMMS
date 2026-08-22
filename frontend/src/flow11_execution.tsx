import { useEffect, useState } from 'react'
import axios from 'axios'
import { CalendarDays, FileText, LockKeyhole, Users } from 'lucide-react'

export { MeetingMinutesWorkspace } from './flow11_legacy'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { PageHeader } from '@/components/amms/page-header'

type Any = any
const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: Any) => response.data?.data ?? response.data
const statusLabels: Record<string, string> = { SCHEDULED: 'زمان‌بندی‌شده', IN_PROGRESS: 'در حال برگزاری', HELD: 'پایان‌یافته', COMPLETED: 'تکمیل‌شده' }
const apiError = (error: Any) => error?.response?.data?.message || 'عملیات انجام نشد. لطفاً صلاحیت و وضعیت جلسه را بررسی نمایید.'

export function MeetingExecutionWorkspace() {
  const id = window.location.pathname.split('/')[2]
  const [state, setState] = useState<Any>(null)
  const [meeting, setMeeting] = useState<Any>(null)
  const [notes, setNotes] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [confirmEnd, setConfirmEnd] = useState(false)

  const load = async () => {
    try {
      const [execution, detail] = await Promise.all([api.get(`/meetings/${id}/execution`), api.get(`/meetings/${id}`)])
      setState(dataOf(execution)); setMeeting(dataOf(detail)); setError('')
    } catch (e) { setError(apiError(e)) }
  }
  useEffect(() => { load() }, [id])

  const run = async (kind: 'start' | 'end') => {
    setBusy(true); setError('')
    try {
      await api.post(`/meetings/${id}/execution/${kind}`, kind === 'start' ? { start_notes: notes || undefined } : { end_notes: notes || undefined })
      setNotes(''); await load()
    } catch (e) { setError(apiError(e)); throw e }
    finally { setBusy(false) }
  }

  if (!state || !meeting) return <div className="screen-center">{error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="spinner" />}</div>
  const session = state.execution_session

  return <div className="space-y-6">
    <PageHeader eyebrow={`${meeting.meeting_number} · FLOW-11`} title="فضای اجرای جلسه" description="ثبت شروع و پایان واقعی جلسه با تکیه بر نسخه‌های رسمی زمان‌بندی و اشتراک‌کنندگان." action={<div className="flex flex-wrap items-center gap-2"><Badge variant="outline">{statusLabels[state.status] || state.status}</Badge><Button asChild variant="outline"><a href={`/meetings/${id}`}>بازگشت به جلسه</a></Button></div>} />
    {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section className="space-y-6">
        <DetailSummaryCard eyebrow="اجرای رسمی" title={meeting.title} icon={<CalendarDays className="size-5" />} items={[
          { label: 'شماره جلسه', value: meeting.meeting_number || '—' },
          { label: 'وضعیت', value: <Badge variant="outline">{statusLabels[state.status] || state.status}</Badge> },
          { label: 'نسخه زمان‌بندی', value: state.schedule_version_id || '—' },
          { label: 'نسخه اشتراک‌کنندگان', value: state.participant_version_id || '—' },
          { label: 'زمان رسمی', value: `${meeting.meeting_date || '—'} · ${meeting.start_time || '—'} تا ${meeting.end_time || '—'}` },
          { label: 'مکان', value: meeting.location || '—' },
        ]} />
        <Card><CardHeader className="flex flex-row items-start justify-between border-b"><div><p className="text-xs text-muted-foreground">Execution session</p><CardTitle className="text-base">زمان واقعی اجرا</CardTitle></div><LockKeyhole className="size-5 text-muted-foreground" /></CardHeader><CardContent className="space-y-5 pt-5">
          <div className="grid gap-4 sm:grid-cols-3"><div className="rounded-xl border p-3"><span className="text-xs text-muted-foreground">شروع واقعی</span><strong className="mt-1 block text-sm">{session?.started_at ? new Date(session.started_at).toLocaleString('fa-AF') : '—'}</strong></div><div className="rounded-xl border p-3"><span className="text-xs text-muted-foreground">پایان واقعی</span><strong className="mt-1 block text-sm">{session?.ended_at ? new Date(session.ended_at).toLocaleString('fa-AF') : session ? 'در جریان' : '—'}</strong></div><div className="rounded-xl border p-3"><span className="text-xs text-muted-foreground">وضعیت نشست</span><strong className="mt-1 block text-sm">{session?.status || 'شروع نشده'}</strong></div></div>
          <div className="space-y-2"><label className="text-sm font-medium">یادداشت اجرایی</label><Textarea rows={3} value={notes} onChange={e => setNotes(e.target.value)} placeholder="یادداشت اختیاری برای شروع یا پایان جلسه" /></div>
          <div className="flex flex-wrap gap-2">{state.status === 'SCHEDULED' ? <Button disabled={busy} onClick={() => run('start')}>{busy ? 'در حال اجرا…' : 'شروع جلسه'}</Button> : null}{state.status === 'IN_PROGRESS' ? <Button variant="outline" disabled={busy} onClick={() => setConfirmEnd(true)}>پایان جلسه</Button> : null}{['HELD', 'COMPLETED'].includes(state.status) ? <Button asChild><a href={`/meetings/${id}/minutes`}><FileText className="size-4" />ورود به صورت‌جلسه</a></Button> : null}</div>
        </CardContent></Card>
        <Card><CardHeader className="border-b"><CardTitle className="text-base">مرزهای مصئونیت پس از شروع</CardTitle></CardHeader><CardContent className="pt-5 text-sm leading-7 text-muted-foreground">شروع اجرا، نسخه‌های رسمی زمان‌بندی و اشتراک‌کنندگان را به‌عنوان مبنای جلسه حفظ می‌کند. پس از پایان جلسه، ثبت حضور جدید مطابق قواعد Backend پذیرفته نمی‌شود و سابقهٔ قبلی حفظ می‌گردد.</CardContent></Card>
      </section>
      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start"><Card><CardHeader className="border-b"><CardTitle className="text-base">حضور جلسه</CardTitle></CardHeader><CardContent className="pt-5"><Button asChild variant="outline" className="w-full"><a href={`/meetings/${id}/attendance`}><Users className="size-4" />مشاهدهٔ حضور</a></Button></CardContent></Card><Card><CardHeader className="border-b"><CardTitle className="text-base">صورت‌جلسهٔ رسمی</CardTitle></CardHeader><CardContent className="space-y-3 pt-5"><p className="text-sm leading-6 text-muted-foreground">ویرایش، review، acknowledgement و finalization صورت‌جلسه در این slice بدون تغییر و در implementation قبلی باقی مانده است.</p><Button asChild className="w-full"><a href={`/meetings/${id}/minutes`}><FileText className="size-4" />فضای صورت‌جلسه</a></Button></CardContent></Card></aside>
    </div>
    <ConfirmDialog open={confirmEnd} onOpenChange={setConfirmEnd} title="پایان جلسه تأیید شود؟" description="با پایان اجرای جلسه، محدودیت‌های پس از اجرا مطابق قواعد موجود Backend اعمال می‌شوند." confirmLabel="پایان جلسه" variant="destructive" onConfirm={() => run('end')} />
  </div>
}
