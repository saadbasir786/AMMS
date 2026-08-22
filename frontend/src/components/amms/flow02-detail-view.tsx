import { useState } from 'react'
import { Building2, CalendarDays, Paperclip, Users } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

export function Flow02DetailView({
  request,
  requester,
  target,
  canRequesterExec,
  canRequesterDirector,
  canTargetExec,
  canTargetDirector,
  purposeLabels,
  priorityLabels,
  onAction,
  editPanel,
}: {
  request: any
  requester: any
  target: any
  canRequesterExec: boolean
  canRequesterDirector: boolean
  canTargetExec: boolean
  canTargetDirector: boolean
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  onAction: (url: string, body?: any) => Promise<void>
  editPanel?: React.ReactNode
}) {
  const [reason, setReason] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmAction, setConfirmAction] = useState<null | {url: string; label: string; destructive?: boolean; body?: any}>(null)
  const nominations = (request.nominations || []).filter((n: any) => n.participant_stage === 'FINAL')

  const run = async (url: string, body?: any) => {
    setBusyAction(url)
    setError('')
    try {
      await onAction(url, body)
      setReason('')
    } catch (e: any) {
      setError(e?.response?.data?.message || e?.response?.data?.error?.message || 'عملیات انجام نشد.')
      throw e
    } finally {
      setBusyAction(null)
    }
  }

  const summaryItems = [
    { label: 'ریاست درخواست‌کننده', value: requester?.unit?.name || request.requesting_unit?.name || '—' },
    { label: 'ریاست مقصد', value: target?.unit?.name || '—' },
    { label: 'هدف جلسه', value: purposeLabels[request.purpose] || request.purpose || '—' },
    { label: 'اولویت', value: priorityLabels[request.priority] || request.priority || '—' },
    { label: 'تأیید درخواست‌کننده', value: request.requester_approved_at ? 'تأیید شده' : 'در انتظار' },
    { label: 'تأیید مقصد', value: request.target_approved_at ? 'تأیید شده' : 'در انتظار' },
    { label: 'زمان پیشنهادی', value: `${request.preferred_date ? new Date(request.preferred_date).toLocaleDateString('fa-AF') : 'تعیین نشده'} ${request.preferred_start_time?.slice?.(0, 5) || ''}`.trim() },
    { label: 'محل پیشنهادی', value: request.proposed_location || 'تعیین نشده' },
  ]

  const timelineItems = (request.actions || []).map((action: any) => ({
    id: action.id,
    title: action.action,
    meta: `${action.actor?.name || 'سیستم'} · ${new Date(action.created_at).toLocaleString('fa-AF')}`,
    comment: action.comment,
  }))

  const renderActionButtons = () => {
    if (request.status === 'DRAFT' && canRequesterExec) {
      return <Button className="w-full" disabled={!!busyAction} onClick={() => run('submit-inter-directorate')}>ارسال برای تأیید داخلی</Button>
    }
    if (request.status === 'PENDING_REQUESTER_APPROVAL' && canRequesterDirector) {
      return <>
        <Button className="w-full" disabled={!!busyAction} onClick={() => run('requester-approve')}>تأیید ریاست درخواست‌کننده</Button>
        <Textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={(e) => setReason(e.target.value)} />
        <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({url:'requester-return',label:'برگشت جهت اصلاح',body:{reason}})}>برگشت جهت اصلاح</Button>
        <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({url:'requester-reject',label:'رد داخلی',destructive:true,body:{reason}})}>رد داخلی</Button>
      </>
    }
    if (request.status === 'SUBMITTED_TO_TARGET' && canTargetExec) {
      return <Button className="w-full" disabled={!!busyAction} onClick={() => run('target-review')}>آغاز بررسی ریاست مقصد</Button>
    }
    if (request.status === 'UNDER_TARGET_REVIEW' && canTargetDirector) {
      return <>
        <Button className="w-full" disabled={!!busyAction} onClick={() => run('target-approve')}>تأیید ریاست مقصد</Button>
        <Textarea placeholder="دلیل برگشت یا رد" value={reason} onChange={(e) => setReason(e.target.value)} />
        <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({url:'target-return',label:'برگشت جهت وضاحت',body:{reason}})}>برگشت جهت وضاحت</Button>
        <Button variant="destructive" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({url:'target-reject',label:'رد توسط مقصد',destructive:true,body:{reason}})}>رد توسط مقصد</Button>
      </>
    }
    if (request.status === 'BILATERAL_APPROVED' && canRequesterExec) {
      return <Button className="w-full" disabled={!!busyAction} onClick={() => run('start-inter-directorate-scheduling')}>آغاز مذاکرهٔ زمان‌بندی</Button>
    }
    if (['SCHEDULING','SCHEDULE_CONFIRMED'].includes(request.status) && (canRequesterExec || canTargetExec)) {
      return <Button asChild className="w-full"><a href={`/meeting-requests/${request.id}/scheduling`}>ورود به فضای زمان‌بندی</a></Button>
    }
    return null
  }

  return <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section className="space-y-6">
      <DetailSummaryCard
        eyebrow="مشخصات درخواست بین‌ریاستی"
        title={request.subject}
        icon={<Building2 className="size-5" />}
        items={summaryItems}
        description={request.description || undefined}
      />

      {editPanel}

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">اشتراک‌کنندگان نهایی</p><CardTitle className="text-base">مالکیت اشتراک‌کنندگان</CardTitle></div>
          <Users className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="grid gap-4 pt-5 md:grid-cols-2">
          {[requester, target].map((unit: any, index: number) => {
            const title = unit?.unit?.name || (index === 0 ? 'ریاست درخواست‌کننده' : 'ریاست مقصد')
            const people = nominations.filter((n: any) => Number(n.nominated_by_unit_id) === Number(unit?.organizational_unit_id))
            return <div key={`${unit?.organizational_unit_id || index}`} className="rounded-xl border p-4">
              <strong className="text-sm">{title}</strong>
              <div className="mt-3 space-y-2">
                {people.length ? people.map((n: any) => <div key={n.id} className="flex items-center justify-between gap-3 rounded-lg bg-muted/30 p-2.5">
                  <div><strong className="block text-sm">{n.employee?.full_name || '—'}</strong><span className="text-xs text-muted-foreground">{n.nomination_status === 'CONFIRMED' ? 'تأیید و قفل شده' : 'نامزدشده'}</span></div>
                  <Badge variant="outline">{n.nomination_status === 'CONFIRMED' ? 'تأیید' : 'نامزد'}</Badge>
                </div>) : <p className="text-sm text-muted-foreground">اشتراک‌کنندهٔ نهایی ثبت نشده است.</p>}
              </div>
            </div>
          })}
        </CardContent>
      </Card>

      {(request.schedule_proposals || []).length > 0 ? <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">مذاکرهٔ زمان‌بندی</p><CardTitle className="text-base">تاریخچهٔ پیشنهادها</CardTitle></div>
          <CalendarDays className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="space-y-3 pt-5">
          {request.schedule_proposals.map((proposal: any) => <div key={proposal.id} className="flex flex-col gap-2 rounded-xl border p-3 sm:flex-row sm:items-center sm:justify-between">
            <div><strong className="block text-sm">{proposal.proposed_by_unit?.name || 'ریاست'}</strong><span className="text-xs text-muted-foreground">{new Date(proposal.meeting_date).toLocaleDateString('fa-AF')} · {String(proposal.start_time).slice(0,5)} تا {String(proposal.end_time).slice(0,5)} · {proposal.location}</span></div>
            <Badge variant={proposal.status === 'ACCEPTED' ? 'default' : 'outline'}>{proposal.status === 'ACCEPTED' ? 'پذیرفته‌شده' : proposal.status === 'SUPERSEDED' ? 'منسوخ' : proposal.status === 'COUNTER_PROPOSED' ? 'پیشنهاد متقابل' : 'پیشنهاد'}</Badge>
          </div>)}
        </CardContent>
      </Card> : null}
    </section>

    <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
      <Card>
        <CardHeader className="border-b"><p className="text-xs text-muted-foreground">اقدامات FLOW-02</p><CardTitle className="text-base">عملیات مجاز</CardTitle></CardHeader>
        <CardContent className="space-y-3 pt-5">
          {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
          {renderActionButtons()}
          {['RETURNED_TO_REQUESTER','RETURNED_BY_TARGET'].includes(request.status) && canRequesterExec ? <p className="flex items-center gap-2 rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground"><Paperclip className="size-4" /> اصلاح و ادامهٔ گردش کار از پنل جزئیات انجام می‌شود.</p> : null}
          {request.meeting ? <Button asChild variant="outline" className="w-full"><a href={`/meetings/${request.meeting.id}`}>مشاهدهٔ جلسهٔ رسمی</a></Button> : null}
        </CardContent>
      </Card>
      <WorkflowTimeline title="خط زمانی دوطرفه" items={timelineItems} />
    </aside>

    <ConfirmDialog
      open={!!confirmAction}
      onOpenChange={(open) => { if (!open) setConfirmAction(null) }}
      title={`${confirmAction?.label || 'عملیات'} انجام شود؟`}
      description="این اقدام در تاریخچهٔ درخواست ثبت می‌شود و وضعیت گردش کار را تغییر می‌دهد."
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
