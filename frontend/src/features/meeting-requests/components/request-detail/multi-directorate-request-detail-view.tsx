import { useState } from "react"
import { Building2, CheckCircle2, Users } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

type Any = any

type TargetCapability = {
  unitId: number
  canStartReview: boolean
  canDecide: boolean
}

export function MultiDirectorateRequestDetailView({
  request,
  requester,
  targets,
  targetCapabilities,
  canRequesterManage,
  canRequesterApprove,
  canEnterScheduling,
  purposeLabels,
  priorityLabels,
  statusLabels,
  reviewLabels,
  onAction,
}: {
  request: Any
  requester: Any
  targets: Any[]
  targetCapabilities: TargetCapability[]
  canRequesterManage: boolean
  canRequesterApprove: boolean
  canEnterScheduling: boolean
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  statusLabels: Record<string, string>
  reviewLabels: Record<string, string>
  onAction: (url: string, body?: Any) => Promise<void>
}) {
  const [reasonByUnit, setReasonByUnit] = useState<Record<number, string>>({})
  const [requesterReason, setRequesterReason] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmAction, setConfirmAction] = useState<null | { url: string; label: string; destructive?: boolean; body?: Any }>(null)

  const run = async (url: string, body?: Any) => {
    setBusyAction(url)
    setError('')
    try {
      await onAction(url, body)
    } catch (e: Any) {
      setError(e?.response?.data?.message || e?.response?.data?.error?.message || 'عملیات انجام نشد.')
      throw e
    } finally {
      setBusyAction(null)
    }
  }

  const summaryItems = [
    { label: 'ریاست درخواست‌کننده', value: requester?.unit?.name || request.requesting_unit?.name || '—' },
    { label: 'تعداد ریاست‌های مقصد', value: String(targets.length) },
    { label: 'هدف جلسه', value: purposeLabels[request.purpose] || request.purpose || '—' },
    { label: 'اولویت', value: priorityLabels[request.priority] || request.priority || '—' },
    { label: 'تاریخ پیشنهادی', value: request.preferred_date ? new Date(request.preferred_date).toLocaleDateString('fa-AF') : 'تعیین نشده' },
    { label: 'زمان پیشنهادی', value: request.preferred_start_time?.slice(0, 5) || 'تعیین نشده' },
    { label: 'محل پیشنهادی', value: request.proposed_location || 'تعیین نشده' },
    { label: 'وضعیت', value: statusLabels[request.status] || request.status || '—' },
  ]

  const timelineItems = (request.actions || []).map((action: Any) => ({
    id: action.id,
    title: action.action,
    meta: `${action.actor?.name || 'سیستم'} · ${new Date(action.created_at).toLocaleString('fa-AF')}`,
    comment: action.comment,
  }))

  const proposedPeople = (request.nominations || []).filter((item: Any) => item.participant_stage === 'PROPOSED')

  return <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section className="space-y-6">
      <DetailSummaryCard
        eyebrow="مشخصات درخواست چندریاستی"
        title={request.subject}
        icon={<Building2 className="size-5" />}
        items={summaryItems}
        description={request.description || undefined}
      />

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">ماتریس پاسخ ریاست‌ها</p><CardTitle className="text-base">بررسی مستقل هر ریاست مقصد</CardTitle></div>
          <Building2 className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="space-y-3 pt-5">
          <div className="flex flex-col gap-3 rounded-xl border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><strong className="block text-sm">{requester?.unit?.name || 'ریاست درخواست‌کننده'}</strong><span className="text-xs text-muted-foreground">درخواست‌کننده</span></div>
            <Badge>تأیید داخلی</Badge>
          </div>
          {targets.map((row: Any) => {
            const unitId = Number(row.organizational_unit_id)
            const review = (request.unit_reviews || []).find((item: Any) => Number(item.organizational_unit_id) === unitId)
            const capability = targetCapabilities.find(item => item.unitId === unitId)
            const reason = reasonByUnit[unitId] || ''
            return <div key={row.id || unitId} className="rounded-xl border p-4">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div><strong className="block text-sm">{row.unit?.name || 'ریاست مقصد'}</strong><span className="text-xs text-muted-foreground">{reviewLabels[review?.review_status] || 'منتظر بررسی'}</span>{review?.comment ? <p className="mt-2 text-xs text-muted-foreground">دلیل: {review.comment}</p> : null}</div>
                <Badge variant={review?.review_status === 'APPROVED' ? 'default' : review?.review_status === 'REJECTED' ? 'destructive' : 'outline'}>{reviewLabels[review?.review_status] || 'منتظر بررسی'}</Badge>
              </div>
              {capability?.canStartReview && review?.review_status === 'PENDING_REVIEW' && ['SUBMITTED_TO_TARGETS', 'UNDER_MULTI_TARGET_REVIEW'].includes(request.status) ? <div className="mt-4"><Button size="sm" disabled={!!busyAction} onClick={() => run(`multi/target-units/${unitId}/review`)}>آغاز بررسی</Button></div> : null}
              {capability?.canDecide && review?.review_status === 'UNDER_REVIEW' ? <div className="mt-4 space-y-3">
                <Textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={event => setReasonByUnit(current => ({ ...current, [unitId]: event.target.value }))} />
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" disabled={!!busyAction} onClick={() => run(`multi/target-units/${unitId}/approve`)}>تأیید</Button>
                  <Button size="sm" variant="outline" disabled={!!busyAction} onClick={() => setConfirmAction({ url: `multi/target-units/${unitId}/return`, label: 'برگشت جهت وضاحت', body: { reason } })}>برگشت</Button>
                  <Button size="sm" variant="destructive" disabled={!!busyAction} onClick={() => setConfirmAction({ url: `multi/target-units/${unitId}/reject`, label: 'رد توسط ریاست مقصد', destructive: true, body: { reason } })}>رد</Button>
                </div>
              </div> : null}
            </div>
          })}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">اشتراک‌کنندگان</p><CardTitle className="text-base">پیشنهادهای اولیه</CardTitle></div>
          <Users className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="space-y-2 pt-5">
          {proposedPeople.length ? proposedPeople.map((item: Any) => <div key={item.id} className="flex items-center justify-between gap-3 rounded-lg border p-3"><div><strong className="block text-sm">{item.employee?.full_name || '—'}</strong><span className="text-xs text-muted-foreground">{item.unit?.name || item.employee?.unit?.name || '—'}</span></div><Badge variant="outline">پیشنهاد اولیه</Badge></div>) : <p className="text-sm text-muted-foreground">اشتراک‌کنندهٔ پیشنهادی ثبت نشده است.</p>}
        </CardContent>
      </Card>
    </section>

    <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
      <Card>
        <CardHeader className="border-b">
          <CardTitle className="text-sm font-bold text-foreground">اقدامات قابل انجام</CardTitle>
          <p className="text-xs text-muted-foreground">عملیات مجاز متناسب با وضعیت فعلی درخواست</p>
        </CardHeader>
        <CardContent className="space-y-3 pt-5">
          {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
          {request.status === 'DRAFT' && canRequesterManage ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('submit-multi')}>ارسال برای تأیید داخلی</Button> : null}
          {request.status === 'PENDING_REQUESTER_APPROVAL' && canRequesterApprove ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('multi/requester-approve')}>تأیید ریاست درخواست‌کننده</Button>
            <Textarea placeholder="دلیل برگشت یا رد" value={requesterReason} onChange={event => setRequesterReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'multi/requester-return', label: 'برگشت جهت اصلاح', body: { reason: requesterReason } })}>برگشت جهت اصلاح</Button>
            <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'multi/requester-reject', label: 'رد داخلی', destructive: true, body: { reason: requesterReason } })}>رد داخلی</Button>
          </> : null}
          {request.status === 'MULTILATERAL_APPROVED' && canRequesterManage ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('start-multi-scheduling')}>آغاز زمان‌بندی چندریاستی</Button> : null}
          {['SCHEDULING', 'SCHEDULE_CONFIRMED'].includes(request.status) && canEnterScheduling ? <Button asChild className="w-full"><a href={`/meeting-requests/${request.id}/multi-scheduling`}>ورود به فضای زمان‌بندی</a></Button> : null}
          {request.meeting ? <Button asChild variant="outline" className="w-full"><a href={`/meetings/${request.meeting.id}`}>مشاهدهٔ جلسهٔ ایجادشده</a></Button> : null}
          {['RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'].includes(request.status) ? <Alert><CheckCircle2 className="size-4" /><AlertDescription>برای حفظ منطق اصلاح و ضمیمه، این وضعیت در نسخهٔ legacy نمایش داده می‌شود.</AlertDescription></Alert> : null}
        </CardContent>
      </Card>
      <WorkflowTimeline title="خط زمانی چندریاستی" items={timelineItems} />
    </aside>

    <ConfirmDialog
      open={!!confirmAction}
      onOpenChange={open => { if (!open) setConfirmAction(null) }}
      title={`${confirmAction?.label || 'عملیات'} انجام شود؟`}
      description="این اقدام در تاریخچه ثبت می‌شود و وضعیت گردش کار را تغییر می‌دهد."
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
