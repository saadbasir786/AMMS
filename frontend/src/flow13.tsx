import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { AlertCircle, ArrowRight, CalendarClock, Clock3, LockKeyhole, ShieldCheck } from 'lucide-react'

import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: any) => response.data?.data ?? response.data
const labels: Record<string, string> = { SCHEDULED: 'زمان‌بندی‌شده', POSTPONED: 'به تعویق افتاده', CANCELLED: 'لغو شده', DRAFT: 'پیش‌نویس', UNDER_REVIEW: 'در حال بررسی', RETURNED_FOR_CORRECTION: 'برگشت برای اصلاح', READY_TO_APPLY: 'آمادهٔ اجرا', APPLIED: 'اجراشده', REJECTED: 'رد شده', WITHDRAWN: 'پس‌گرفته‌شده', POSTPONEMENT: 'تعویق', CANCELLATION: 'لغو', REOPENING: 'بازگشایی', RESUMPTION: 'فعال‌سازی مجدد', PENDING: 'در انتظار', ACKNOWLEDGED: 'تأیید دریافت', APPROVED: 'تأییدشده', CORRECTION_REQUESTED: 'نیازمند اصلاح', UNIT: 'ریاست', HIGH_AUTHORITY: 'مقام عالی', APPROVAL: 'تأیید', ACKNOWLEDGEMENT: 'تأیید دریافت' }
const label = (value: string) => labels[value] || value
const apiError = (error: any) => error?.response?.data?.message || 'عملیات انجام نشد. صلاحیت، نسخه یا وضعیت جلسه را بررسی نمایید.'
function Badge({ value }: { value: string }) { return <span className={`status ${String(value).toLowerCase()}`}>{label(value)}</span> }
function ErrorBox({ text }: { text: string }) { return text ? <div className="form-error">{text}</div> : null }

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

  const load = async () => { try { setError(''); setData(dataOf(await api.get(`/meetings/${id}/exceptions`))) } catch (e) { setError(apiError(e)) } }
  useEffect(() => { load() }, [id])
  const cases = useMemo(() => Array.isArray(data) ? data : data?.data || [], [data])
  if (!data) return <div className="screen-center"><div className="spinner"/></div>

  const run = async (method: 'post' | 'put', url: string, body?: any) => { try { setBusy(true); setError(''); await api[method](url, body); setSelected(null); setReason(''); await load() } catch (e) { setError(apiError(e)) } finally { setBusy(false) } }
  const create = () => { if (!reason.trim()) return setError('دلیل رسمی الزامی است.'); run('post', `/meetings/${id}/exceptions`, { case_type: caseType, reason }) }
  const action = (kind: string, item: any) => {
    if (kind === 'submit') return run('post', `/meeting-exceptions/${item.id}/submit`)
    if (kind === 'apply') return run('post', `/meeting-exceptions/${item.id}/apply`)
    if (kind === 'resume') { if (!schedule.meeting_date || !schedule.start_time || !schedule.end_time || !schedule.location) return setError('تاریخ، آغاز، پایان و محل زمان‌بندی جدید الزامی است.'); return run('post', `/meeting-exceptions/${item.id}/resume`, schedule) }
    if (kind === 'withdraw') { if (!reason.trim()) return setError('دلیل پس‌گرفتن الزامی است.'); return run('post', `/meeting-exceptions/${item.id}/withdraw`, { reason }) }
  }

  return <>
    <div className="page-title"><div><span className="eyebrow">{data.meeting?.meeting_number} · FLOW-13</span><h1>مدیریت وضعیت استثنایی جلسه</h1><p>تعویق، لغو، بازگشایی و تعیین زمان جدید با حفظ کامل تاریخچهٔ جلسه.</p></div><div className="page-actions"><Badge value={data.meeting?.status}/><a className="button secondary" href={`/meetings/${id}`}>بازگشت به جلسه <ArrowRight size={16}/></a></div></div>
    {error && <ErrorBox text={error}/>}<div className="panel"><div className="info-grid"><div><span>شماره جلسه</span><strong>{data.meeting?.meeting_number || '—'}</strong></div><div><span>وضعیت فعلی</span><strong><Badge value={data.meeting?.status}/></strong></div><div><span>نسخهٔ زمان‌بندی</span><strong>{data.meeting?.current_schedule_version_id || '—'}</strong></div><div><span>درخواست اصلی</span><strong>{data.meeting?.request_status || 'CONVERTED_TO_MEETING'}</strong></div></div></div>
    <div className="detail-grid"><section className="detail-main"><div className="panel form-card"><div className="panel-head"><div><span className="eyebrow">ثبت پروندهٔ جدید</span><h2>درخواست اقدام استثنایی</h2></div><CalendarClock size={21}/></div><div className="form-grid"><label>نوع پرونده<select value={caseType} onChange={e => setCaseType(e.target.value)}><option value="POSTPONEMENT">تعویق جلسه</option><option value="CANCELLATION">لغو جلسه</option><option value="REOPENING">بازگشایی جلسهٔ لغوشده</option><option value="RESUMPTION">تعیین زمان جدید و فعال‌سازی مجدد</option></select></label></div><label>دلیل رسمی<textarea rows={4} value={reason} onChange={e => setReason(e.target.value)} placeholder="دلیل اداری و رسمی را درج نمایید..."/></label>{caseType === 'POSTPONEMENT' && <div className="action-hint"><AlertCircle size={16}/> اگر تاریخ جایگزین از قبل معلوم است، از FLOW-07 زمان‌بندی مجدد استفاده نمایید.</div>}<button className="button primary" disabled={busy} onClick={create}>ایجاد پرونده <ArrowRight size={16}/></button></div>{cases.map((item: any) => <div className="panel" key={item.id}><div className="panel-head"><div><span className="eyebrow">پروندهٔ #{item.id} · نسخه {item.current_version?.version_number || '—'}</span><h2>{label(item.case_type)}</h2></div><Badge value={item.status}/></div><div className="info-grid"><div><span>وضعیت مبنا</span><strong><Badge value={item.baseline?.meeting_status}/></strong></div><div><span>نوع حاکمیت</span><strong>{item.governance_mode}</strong></div><div><span>ثبت‌شده در</span><strong>{item.created_at ? new Date(item.created_at).toLocaleString('fa-AF') : '—'}</strong></div><div><span>نسخهٔ فعلی</span><strong>{item.current_version?.submitted_at ? 'منجمد و ارسال‌شده' : 'قابل ویرایش'}</strong></div></div><div className="description-block"><span>دلیل</span><p>{item.current_version?.reason || '—'}</p></div><div className="review-matrix"><div className="section-title"><span><ShieldCheck size={16}/> ماتریس بررسی نسخهٔ فعلی</span></div>{(item.reviews || []).map((review: any) => <div className="proposal-row" key={review.id}><div><strong>{label(review.reviewer_type)} · {label(review.review_requirement)}</strong><span>{review.organizational_unit_id ? `واحد ${review.organizational_unit_id}` : review.authority_employee_id ? `کارمند مقام ${review.authority_employee_id}` : '—'}</span></div><Badge value={review.status}/><div className="form-actions">{review.status === 'PENDING' && <><button className="button small secondary" disabled={busy} onClick={() => run('post', `/meeting-exception-reviews/${review.id}/acknowledge`)}>تأیید دریافت</button><button className="button small success" disabled={busy} onClick={() => run('post', `/meeting-exception-reviews/${review.id}/approve`)}>تأیید</button></>}</div></div>)}{!(item.reviews || []).length && <p className="muted">بررسی‌ها پس از ارسال پرونده ایجاد می‌شوند.</p>}</div><div className="form-actions">{item.status === 'DRAFT' && <button className="button primary" disabled={busy} onClick={() => action('submit', item)}>ارسال برای بررسی</button>}{['DRAFT','UNDER_REVIEW','RETURNED_FOR_CORRECTION'].includes(item.status) && <button className="button secondary" disabled={busy} onClick={() => { setSelected(item); setReason(item.current_version?.reason || '') }}>پس‌گرفتن پرونده</button>}{item.status === 'READY_TO_APPLY' && item.case_type !== 'RESUMPTION' && <button className="button success" disabled={busy} onClick={() => setConfirmApply(item)}>اجرای تصمیم</button>}{item.status === 'READY_TO_APPLY' && item.case_type === 'RESUMPTION' && <button className="button success" disabled={busy} onClick={() => setSelected(item)}>تعیین زمان جدید و فعال‌سازی</button>}</div></div>)}</section><aside className="detail-side"><div className="panel side-note"><LockKeyhole size={21}/><h3>حفاظت از تاریخچه</h3><p>پرونده‌ها، نسخه‌های ارسال‌شده، زمان‌بندی‌های قبلی و حضورهای ثبت‌شده حذف یا بازنویسی نمی‌شوند. اجرای تعویق یا لغو فقط وضعیت جاری و لایه‌های افزوده را تغییر می‌دهد.</p></div><div className="panel timeline"><div className="panel-head"><div><span className="eyebrow">تاریخچهٔ غیرقابل حذف</span><h3>خط زمانی پرونده‌ها</h3></div><Clock3 size={19}/></div>{cases.map((item: any) => <div className="timeline-item" key={`timeline-${item.id}`}><div className="timeline-dot"/><div><strong>{label(item.case_type)} · {label(item.status)}</strong><span>پروندهٔ #{item.id} · نسخهٔ {item.current_version?.version_number || '—'}</span></div></div>)}</div></aside></div>

    <Dialog open={Boolean(selected)} onOpenChange={(open) => { if (!open) setSelected(null) }}>
      <DialogContent dir="rtl" className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{selected?.case_type === 'RESUMPTION' ? 'زمان‌بندی رسمی جدید' : 'پس‌گرفتن پرونده'}</DialogTitle>
          <DialogDescription>
            {selected?.case_type === 'RESUMPTION'
              ? 'زمان‌بندی جدید به‌صورت یک نسخهٔ تازه ثبت می‌شود و تاریخچهٔ قبلی دست‌نخورده می‌ماند.'
              : 'دلیل اداری پس‌گرفتن را ثبت کنید. تاریخچهٔ پرونده حفظ خواهد شد.'}
          </DialogDescription>
        </DialogHeader>

        {selected?.case_type === 'RESUMPTION' ? <>
          <div className="grid gap-4 sm:grid-cols-3">
            <label className="grid gap-2 text-sm font-medium">تاریخ جدید<Input type="date" value={schedule.meeting_date} onChange={e => setSchedule({ ...schedule, meeting_date: e.target.value })}/></label>
            <label className="grid gap-2 text-sm font-medium">آغاز<Input type="time" value={schedule.start_time} onChange={e => setSchedule({ ...schedule, start_time: e.target.value })}/></label>
            <label className="grid gap-2 text-sm font-medium">پایان<Input type="time" value={schedule.end_time} onChange={e => setSchedule({ ...schedule, end_time: e.target.value })}/></label>
          </div>
          <label className="grid gap-2 text-sm font-medium">محل جدید<Input value={schedule.location} onChange={e => setSchedule({ ...schedule, location: e.target.value })}/></label>
          <DialogFooter>
            <Button variant="outline" disabled={busy} onClick={() => setSelected(null)}>انصراف</Button>
            <Button disabled={busy} onClick={() => action('resume', selected)}>{busy ? 'در حال اجرا…' : 'ایجاد نسخهٔ جدید و فعال‌سازی'}</Button>
          </DialogFooter>
        </> : <>
          <label className="grid gap-2 text-sm font-medium">دلیل پس‌گرفتن<Textarea rows={4} value={reason} onChange={e => setReason(e.target.value)} placeholder="دلیل رسمی پس‌گرفتن پرونده را درج نمایید…"/></label>
          <DialogFooter>
            <Button variant="outline" disabled={busy} onClick={() => setSelected(null)}>انصراف</Button>
            <Button variant="outline" className="border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:text-amber-900" disabled={busy} onClick={() => action('withdraw', selected)}>{busy ? 'در حال اجرا…' : 'پس‌گرفتن پرونده'}</Button>
          </DialogFooter>
        </>}
      </DialogContent>
    </Dialog>

    <ConfirmDialog
      open={Boolean(confirmApply)}
      onOpenChange={(open) => { if (!open) setConfirmApply(null) }}
      title={confirmApply?.case_type === 'CANCELLATION' ? 'لغو این جلسه اجرا شود؟' : 'این تصمیم اجرا شود؟'}
      description={confirmApply?.case_type === 'CANCELLATION'
        ? 'با تأیید، وضعیت جاری جلسه لغو می‌شود. تاریخچه و نسخه‌های قبلی حذف نخواهند شد.'
        : `با تأیید، تصمیم «${label(confirmApply?.case_type || '')}» روی وضعیت جاری جلسه اعمال می‌شود و تاریخچه حفظ خواهد شد.`}
      confirmLabel={confirmApply?.case_type === 'CANCELLATION' ? 'تأیید لغو جلسه' : 'اجرای تصمیم'}
      variant={confirmApply?.case_type === 'CANCELLATION' ? 'destructive' : 'default'}
      onConfirm={async () => { if (confirmApply) await action('apply', confirmApply) }}
    />
  </>
}
