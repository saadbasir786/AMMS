import { useState } from 'react'
import { Building2, ShieldCheck } from 'lucide-react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

type Any = any

export function Flow04DetailView({
  request,
  purposeLabels,
  priorityLabels,
  isCreator,
  isAuthorityOffice,
  isSecretary,
  isDirector,
  isAuthority,
  canOpenScheduling,
  onAction,
}: {
  request: Any
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  isCreator: boolean
  isAuthorityOffice: boolean
  isSecretary: boolean
  isDirector: boolean
  isAuthority: boolean
  canOpenScheduling: boolean
  onAction: (url: string, body?: Any) => Promise<void>
}) {
  const [reason, setReason] = useState('')
  const [comment, setComment] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmAction, setConfirmAction] = useState<null | { url: string; label: string; destructive?: boolean; body?: Any }>(null)

  const ctx = request.authority_context
  const run = async (url: string, body?: Any) => {
    setBusyAction(url)
    setError('')
    try {
      await onAction(url, body)
      setReason('')
      setComment('')
    } catch (e: Any) {
      setError(e?.response?.data?.message || e?.response?.data?.error?.message || 'عملیات انجام نشد.')
      throw e
    } finally {
      setBusyAction(null)
    }
  }

  const summaryItems = [
    { label: 'ریاست درخواست‌کننده', value: request.requesting_unit?.name || '—' },
    { label: 'هدف جلسه', value: purposeLabels[request.purpose] || request.purpose || '—' },
    { label: 'اولویت', value: priorityLabels[request.priority] || request.priority || '—' },
    { label: 'دفتر مسئول', value: ctx?.authority_office_unit?.name || '—' },
    { label: 'سمت مقام عالی', value: ctx?.authority_position?.title || '—' },
    { label: 'holder مقام', value: ctx?.authority_employee?.full_name || 'پس از forward تعیین می‌شود' },
    { label: 'وضعیت بررسی دفتر مقام', value: ctx?.office_review_status || '—' },
    { label: 'وضعیت تصمیم مقام', value: ctx?.authority_decision_status || '—' },
  ]

  const timelineItems = (request.actions || []).map((action: Any) => ({
    id: action.id,
    title: action.action,
    meta: `${action.actor?.name || 'سیستم'} · ${new Date(action.created_at).toLocaleString('fa-AF')}`,
    comment: action.comment,
  }))

  const authorityParticipantsConfirmed = (request.authority_participant_confirmations || []).some((item: Any) => item.side === 'AUTHORITY')

  return <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section className="space-y-6">
      <DetailSummaryCard
        eyebrow="مشخصات درخواست مقام عالی"
        title={request.subject}
        icon={<ShieldCheck className="size-5" />}
        items={summaryItems}
        description={<div className="space-y-3"><p>{request.description || 'شرح ثبت نشده است.'}</p><div><strong className="text-foreground">توجیه جلسه:</strong> {request.meeting_justification || '—'}</div><div><strong className="text-foreground">نتیجهٔ مورد انتظار:</strong> {request.requested_outcome || '—'}</div></div>}
      />

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">مشارکت و صلاحیت</p><CardTitle className="text-base">Authority context</CardTitle></div>
          <Building2 className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="grid gap-3 pt-5 sm:grid-cols-2">
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">نوع مقام عالی</span><strong className="mt-1 block text-sm">{ctx?.authority_type || '—'}</strong></div>
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">دفتر مقام</span><strong className="mt-1 block text-sm">{ctx?.authority_office_unit?.name || '—'}</strong></div>
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">سمت رسمی</span><strong className="mt-1 block text-sm">{ctx?.authority_position?.title || '—'}</strong></div>
          <div className="rounded-xl border p-4"><span className="text-xs text-muted-foreground">فهرست participant دفتر مقام</span><div className="mt-1"><Badge variant={authorityParticipantsConfirmed ? 'default' : 'outline'}>{authorityParticipantsConfirmed ? 'تأیید شده' : 'منتظر تأیید'}</Badge></div></div>
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
          {request.status === 'DRAFT' && isCreator ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('submit-high-authority')}>ارسال برای تأیید داخلی</Button> : null}
          {request.status === 'PENDING_REQUESTER_APPROVAL' && isDirector ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('high-authority/requester-approve')}>تأیید رئیس ریاست</Button>
            <Textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'high-authority/requester-return', label: 'برگشت جهت اصلاح', body: { reason } })}>برگشت جهت اصلاح</Button>
            <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'high-authority/requester-reject', label: 'رد داخلی', destructive: true, body: { reason } })}>رد داخلی</Button>
          </> : null}
          {request.status === 'SUBMITTED_TO_AUTHORITY_OFFICE' && isAuthorityOffice ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-office/review')}>آغاز بررسی دفتر مقام</Button> : null}
          {request.status === 'UNDER_AUTHORITY_OFFICE_REVIEW' && isAuthorityOffice ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-office/forward')}>ارسال به مقام عالی</Button>
            <Textarea placeholder="دلیل برگشت از دفتر مقام" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'authority-office/return', label: 'برگشت جهت اصلاح', body: { reason } })}>برگشت جهت اصلاح</Button>
          </> : null}
          {request.status === 'PENDING_HIGH_AUTHORITY_DECISION' && isAuthority ? <>
            <Textarea placeholder="نظر یا دلیل تصمیم" value={comment} onChange={event => setComment(event.target.value)} />
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority/approve', { comment })}>تأیید مقام عالی</Button>
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'authority/return', label: 'برگشت جهت اصلاح', body: { reason: comment } })}>برگشت جهت اصلاح</Button>
            <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ url: 'authority/reject', label: 'رد نهایی', destructive: true, body: { reason: comment } })}>رد نهایی</Button>
          </> : null}
          {request.status === 'HIGH_AUTHORITY_APPROVED' && isSecretary ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('authority-scheduling/start')}>آغاز زمان‌بندی مقام</Button> : null}
          {['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'].includes(request.status) && canOpenScheduling ? <Button asChild className="w-full"><a href={`/meeting-requests/${request.id}/high-authority-scheduling`}>ورود به زمان‌بندی</a></Button> : null}
          {request.meeting ? <Button asChild variant="outline" className="w-full"><a href={`/meetings/${request.meeting.id}`}>مشاهدهٔ جلسهٔ رسمی</a></Button> : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="border-b"><p className="text-xs text-muted-foreground">تصمیم ثبت‌شده</p><CardTitle className="text-base">هدایت و تصمیم رسمی</CardTitle></CardHeader>
        <CardContent className="pt-5 text-sm leading-7 text-muted-foreground">{ctx?.authority_decision_comment || 'هنوز تصمیمی ثبت نشده است.'}</CardContent>
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
        await run(current.url, current.body)
        setConfirmAction(null)
      }}
    />
  </div>
}
