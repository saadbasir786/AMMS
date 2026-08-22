import { useState } from 'react'
import { Users } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

type Any = any

export function Flow01DetailView({
  request,
  purposeLabels,
  priorityLabels,
  canApprove,
  canManage,
  onAction,
  onEdit,
  onScheduling,
}: {
  request: Any
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  canApprove: boolean
  canManage: boolean
  onAction: (url: string, body?: Any) => Promise<void>
  onEdit: () => void
  onScheduling: () => void
}) {
  const [reason, setReason] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmAction, setConfirmAction] = useState<null | { url: string; label: string; destructive?: boolean; body?: Any }>(null)

  const run = async (url: string, body?: Any) => {
    setBusyAction(url)
    setError('')
    try {
      await onAction(url, body)
      setReason('')
    } catch (e: Any) {
      setError(e?.response?.data?.message || 'عملیات انجام نشد.')
      throw e
    } finally {
      setBusyAction(null)
    }
  }

  const summaryItems = [
    { label: 'هدف جلسه', value: purposeLabels[request.purpose] || request.purpose || '—' },
    { label: 'ریاست درخواست‌کننده', value: request.requesting_unit?.name || '—' },
    { label: 'ایجادکننده', value: request.creator?.name || '—' },
    { label: 'اولویت', value: priorityLabels[request.priority] || request.priority || '—' },
    { label: 'زمان پیشنهادی', value: `${request.preferred_date ? new Date(request.preferred_date).toLocaleDateString('fa-AF') : 'تعیین نشده'} ${request.preferred_start_time?.slice(0, 5) || ''}`.trim() },
    { label: 'محل پیشنهادی', value: request.proposed_location || 'تعیین نشده' },
    { label: 'مدت', value: request.estimated_duration_minutes ? `${request.estimated_duration_minutes} دقیقه` : 'تعیین نشده' },
    { label: 'شماره درخواست', value: request.request_number || 'پیش‌نویس', mono: true },
  ]

  const timelineItems = (request.actions || []).map((action: Any) => ({
    id: action.id,
    title: action.action === 'CREATED' ? 'درخواست ایجاد شد' : action.action === 'SUBMITTED' ? 'درخواست برای تأیید ارسال شد' : action.action === 'RETURNED' ? 'درخواست جهت اصلاح برگشت داده شد' : action.action === 'RESUBMITTED' ? 'درخواست دوباره ارسال شد' : action.action === 'APPROVED' ? 'درخواست تأیید شد' : action.action === 'REJECTED' ? 'درخواست رد شد' : action.action === 'MEETING_CREATED' ? 'جلسه ایجاد گردید' : action.action === 'SCHEDULING_STARTED' ? 'زمان‌بندی آغاز شد' : action.action === 'SCHEDULE_CONFIRMED' ? 'زمان‌بندی نهایی شد' : action.action,
    meta: `${action.actor?.name || 'سیستم'} · ${new Date(action.created_at).toLocaleString('fa-AF')}`,
    comment: action.comment,
  }))

  return <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section className="space-y-6">
      <DetailSummaryCard
        eyebrow="مشخصات درخواست داخلی"
        title={request.subject}
        items={summaryItems}
        description={request.description || undefined}
      />

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b"><div className="space-y-1"><p className="text-xs text-muted-foreground">فهرست اشتراک‌کنندگان</p><CardTitle className="text-base">اشتراک‌کنندگان پیشنهادی</CardTitle></div><Users className="size-5 text-muted-foreground" /></CardHeader>
        <CardContent className="space-y-2 pt-5">
          {(request.participants || []).length ? request.participants.map((participant: Any) => <div key={participant.id} className="flex items-center justify-between gap-3 rounded-lg border p-3"><div><strong className="block text-sm">{participant.employee?.full_name || '—'}</strong><span className="text-xs text-muted-foreground">{participant.employee?.unit?.name || '—'}</span></div><Badge variant="outline">{participant.participant_type === 'FINAL' ? 'نهایی' : 'پیشنهادی'}</Badge></div>) : <p className="text-sm text-muted-foreground">اشتراک‌کننده‌ای ثبت نشده است.</p>}
        </CardContent>
      </Card>
    </section>

    <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
      <Card>
        <CardHeader className="border-b"><p className="text-xs text-muted-foreground">عملیات FLOW-01</p><CardTitle className="text-base">اقدامات گردش کار</CardTitle></CardHeader>
        <CardContent className="space-y-3 pt-5">
          {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
          {request.status === 'DRAFT' ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('submit')}>ارسال برای تأیید</Button> : null}
          {request.status === 'RETURNED_FOR_REVISION' ? <Button className="w-full" onClick={onEdit}>اصلاح درخواست</Button> : null}
          {request.status === 'PENDING_INTERNAL_APPROVAL' && canApprove ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('approve')}>تأیید درخواست</Button>
            <Textarea placeholder="دلیل برگشت یا رد در صورت نیاز" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'return', label: 'برگشت جهت اصلاح', body: { reason } })}>برگشت جهت اصلاح</Button>
            <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'reject', label: 'رد درخواست', destructive: true, body: { reason } })}>رد درخواست</Button>
          </> : null}
          {request.status === 'APPROVED' && canManage ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('start-scheduling')}>شروع زمان‌بندی</Button> : null}
          {['SCHEDULING', 'SCHEDULE_CONFIRMED'].includes(request.status) && canManage ? <Button className="w-full" onClick={onScheduling}>{request.status === 'SCHEDULE_CONFIRMED' ? 'بازبینی و ایجاد جلسه' : 'ورود به فضای زمان‌بندی'}</Button> : null}
          {request.meeting ? <Button asChild variant="outline" className="w-full"><a href={`/meetings/${request.meeting.id}`}>مشاهده جلسه ایجادشده</a></Button> : null}
        </CardContent>
      </Card>
      <WorkflowTimeline title="خط زمانی درخواست" items={timelineItems} />
    </aside>

    <ConfirmDialog
      open={!!confirmAction}
      onOpenChange={open => { if (!open) setConfirmAction(null) }}
      title={`${confirmAction?.label || 'عملیات'} انجام شود؟`}
      description="این اقدام در تاریخچه ثبت می‌شود و وضعیت درخواست را تغییر می‌دهد."
      confirmLabel={confirmAction?.label || 'تأیید'}
      variant={confirmAction?.destructive ? 'destructive' : 'default'}
      onConfirm={async () => {
        if (!confirmAction) return
        const current = confirmAction
        await run(current.url, current.body)
        setConfirmAction(null)
      }}
    />
  </div>
}
