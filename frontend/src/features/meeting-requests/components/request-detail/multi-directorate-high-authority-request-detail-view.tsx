import { useState } from "react"
import { Building2, ShieldCheck, Users } from 'lucide-react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'
import { meetingActionLabel } from '@/app/config/labels'

type Any = any

type PeerCapability = {
  unitId: number
  canStartReview: boolean
  canDecide: boolean
}

export function MultiDirectorateHighAuthorityRequestDetailView({
  request,
  requesterUnit,
  peers,
  peerCapabilities,
  purposeLabels,
  priorityLabels,
  statusLabels,
  isRequester,
  isDirector,
  isOffice,
  isSecretary,
  isAuthority,
  onAction,
}: {
  request: Any
  requesterUnit: Any
  peers: Any[]
  peerCapabilities: PeerCapability[]
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  statusLabels: Record<string, string>
  isRequester: boolean
  isDirector: boolean
  isOffice: boolean
  isSecretary: boolean
  isAuthority: boolean
  onAction: (path: string, body?: Any) => Promise<void>
}) {
  const [reason, setReason] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmAction, setConfirmAction] = useState<null | { path: string; label: string; destructive?: boolean; body?: Any }>(null)

  const ctx = request.authority_context
  const reviews = request.unit_reviews || []

  const run = async (path: string, body?: Any) => {
    setBusyAction(path)
    setError('')
    try {
      await onAction(path, body)
      setReason('')
    } catch (e: Any) {
      setError(e?.response?.data?.message || e?.response?.data?.error?.message || 'عملیات انجام نشد.')
      throw e
    } finally {
      setBusyAction(null)
    }
  }

  const summaryItems = [
    { label: 'ریاست درخواست‌کننده', value: requesterUnit?.unit?.name || '—' },
    { label: 'تعداد ریاست‌های اشتراک‌کننده', value: String(peers.length) },
    { label: 'هدف جلسه', value: purposeLabels[request.purpose] || request.purpose || '—' },
    { label: 'اولویت', value: priorityLabels[request.priority] || request.priority || '—' },
    { label: 'سمت مقام عالی', value: ctx?.authority_position?.title || '—' },
    { label: 'holder مقام', value: ctx?.authority_employee?.full_name || 'پس از forward تعیین می‌شود' },
    { label: 'بررسی دفتر مقام', value: ctx?.office_review_status || 'PENDING' },
    { label: 'تصمیم مقام عالی', value: ctx?.authority_decision_status || 'PENDING' },
  ]

  const timelineItems = (request.actions || []).map((action: Any) => ({
    id: action.id,
    title: meetingActionLabel(action.action),
    meta: `${action.actor?.name || 'سیستم'} · ${new Date(action.created_at).toLocaleString('fa-AF')}`,
    comment: action.comment,
  }))

  return <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section className="space-y-6">
      <DetailSummaryCard
        eyebrow="درخواست چندریاستی با مقام عالی"
        title={request.subject}
        icon={<ShieldCheck className="size-5" />}
        items={summaryItems}
        description={<div className="space-y-3"><p>{request.description || 'شرح ثبت نشده است.'}</p><div><strong className="text-foreground">توجیه:</strong> {request.meeting_justification || '—'}</div><div><strong className="text-foreground">نتیجهٔ مورد انتظار:</strong> {request.requested_outcome || '—'}</div></div>}
      />

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">Matrix تأیید ریاست‌ها</p><CardTitle className="text-base">تأیید مستقل هر ریاست</CardTitle></div>
          <Users className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="space-y-3 pt-5">
          <div className="flex flex-col gap-3 rounded-xl border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><strong className="block text-sm">{requesterUnit?.unit?.name || 'ریاست درخواست‌کننده'}</strong><span className="text-xs text-muted-foreground">درخواست‌کننده</span></div>
            <Badge variant={request.requester_approved_at ? 'default' : 'outline'}>{request.requester_approved_at ? 'تأیید شده' : 'منتظر تأیید'}</Badge>
          </div>
          {peers.map((unit: Any) => {
            const unitId = Number(unit.organizational_unit_id)
            const review = reviews.find((item: Any) => Number(item.organizational_unit_id) === unitId)
            return <div key={unit.id || unitId} className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
              <div><strong className="block text-sm">{unit.unit?.name || 'ریاست اشتراک‌کننده'}</strong><span className="text-xs text-muted-foreground">ریاست اشتراک‌کننده</span></div>
              <Badge variant={review?.review_status === 'APPROVED' ? 'default' : review?.review_status === 'REJECTED' ? 'destructive' : 'outline'}>{review?.review_status || 'PENDING_REVIEW'}</Badge>
            </div>
          })}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">دفتر مقام و مقام عالی</p><CardTitle className="text-base">Attribution رسمی</CardTitle></div>
          <Building2 className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="grid gap-3 pt-5 sm:grid-cols-2">
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">دفتر مقام</span><strong className="mt-1 block text-sm">{ctx?.authority_office_unit?.name || '—'}</strong></div>
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">holder snapshot</span><strong className="mt-1 block text-sm">{ctx?.authority_employee?.full_name || 'تعیین نشده'}</strong></div>
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">Office review</span><strong className="mt-1 block text-sm">{ctx?.office_review_status || 'PENDING'}</strong></div>
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">Authority decision</span><strong className="mt-1 block text-sm">{ctx?.authority_decision_status || 'PENDING'}</strong></div>
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
          {request.status === 'DRAFT' && isRequester ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('submit')}>ارسال برای تأیید داخلی</Button> : null}
          {request.status === 'PENDING_REQUESTER_APPROVAL' && isDirector ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('requester-approve')}>تأیید ریاست درخواست‌کننده</Button>
            <Textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ path: 'requester-return', label: 'برگشت', body: { reason } })}>برگشت</Button>
            <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ path: 'requester-reject', label: 'رد داخلی', destructive: true, body: { reason } })}>رد داخلی</Button>
          </> : null}

          {['SUBMITTED_TO_PARTICIPATING_DIRECTORATES', 'UNDER_PARTICIPATING_REVIEW'].includes(request.status) ? peers.map((unit: Any) => {
            const unitId = Number(unit.organizational_unit_id)
            const capability = peerCapabilities.find(item => item.unitId === unitId)
            return <div key={unit.id || unitId} className="space-y-2 rounded-xl border p-3">
              <strong className="block text-sm">{unit.unit?.name || 'ریاست اشتراک‌کننده'}</strong>
              {capability?.canStartReview ? <Button variant="outline" className="w-full" size="sm" disabled={!!busyAction} onClick={() => run(`participating-units/${unitId}/review`)}>آغاز بررسی این ریاست</Button> : null}
              {capability?.canDecide ? <>
                <Button className="w-full" size="sm" disabled={!!busyAction} onClick={() => run(`participating-units/${unitId}/approve`)}>تأیید ریاست</Button>
                <Textarea placeholder="دلیل وضاحت یا رد" value={reason} onChange={event => setReason(event.target.value)} />
                <Button variant="outline" className="w-full" size="sm" disabled={!!busyAction} onClick={() => setConfirmAction({ path: `participating-units/${unitId}/return`, label: 'برگشت', body: { reason } })}>برگشت</Button>
                <Button variant="destructive" className="w-full" size="sm" disabled={!!busyAction} onClick={() => setConfirmAction({ path: `participating-units/${unitId}/reject`, label: 'رد ریاست', destructive: true, body: { reason } })}>رد</Button>
              </> : null}
            </div>
          }) : null}

          {request.status === 'DIRECTORATES_APPROVED' && isRequester ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-office/submit')}>ارسال به دفتر مقام</Button> : null}
          {request.status === 'SUBMITTED_TO_AUTHORITY_OFFICE' && isOffice ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-office/review')}>آغاز بررسی دفتر مقام</Button> : null}
          {request.status === 'UNDER_AUTHORITY_OFFICE_REVIEW' && isOffice ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-office/forward')}>ارسال به مقام عالی</Button>
            <Textarea placeholder="دلیل برگشت دفتر مقام" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ path: 'authority-office/return', label: 'برگشت جهت اصلاح', body: { reason } })}>برگشت جهت اصلاح</Button>
          </> : null}
          {request.status === 'PENDING_HIGH_AUTHORITY_DECISION' && isAuthority ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority/approve')}>تأیید مقام عالی</Button>
            <Textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ path: 'authority/return', label: 'برگشت', body: { reason } })}>برگشت</Button>
            <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ path: 'authority/reject', label: 'رد نهایی', destructive: true, body: { reason } })}>رد نهایی</Button>
          </> : null}
          {request.status === 'HIGH_AUTHORITY_APPROVED' && isSecretary ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-scheduling/start')}>آغاز زمان‌بندی رسمی</Button> : null}
          {['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'].includes(request.status) ? <Button asChild className="w-full"><a href={`/meeting-requests/${request.id}/multi-high-scheduling`}>فضای زمان‌بندی و اشتراک‌کنندگان</a></Button> : null}
          {request.meeting ? <Button asChild variant="outline" className="w-full"><a href={`/meetings/${request.meeting.id}`}>مشاهدهٔ جلسهٔ رسمی</a></Button> : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="border-b"><p className="text-xs text-muted-foreground">وضعیت فعلی</p><CardTitle className="text-base">{statusLabels[request.status] || request.status}</CardTitle></CardHeader>
        <CardContent className="pt-5 text-sm leading-7 text-muted-foreground">تمام تصمیم‌ها همچنان به محدودهٔ همان ریاست، دفتر مقام و صلاحیت مقام عالی محدود می‌شوند.</CardContent>
      </Card>

      <WorkflowTimeline title="خط زمانی گردش کار" items={timelineItems} />
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
        await run(current.path, current.body)
        setConfirmAction(null)
      }}
    />
  </div>
}
