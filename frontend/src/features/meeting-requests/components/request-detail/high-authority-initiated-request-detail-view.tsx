import { useState } from "react"
import { ShieldCheck, Users } from 'lucide-react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'

type Any = any

type UnitCapability = {
  unitId: number
  canCoordinate: boolean
  canAcknowledge: boolean
}

export function HighAuthorityInitiatedRequestDetailView({
  request,
  units,
  unitCapabilities,
  purposeLabels,
  priorityLabels,
  statusLabels,
  isOffice,
  isSecretary,
  isAuthority,
  canOpenScheduling,
  onAction,
}: {
  request: Any
  units: Any[]
  unitCapabilities: UnitCapability[]
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  statusLabels: Record<string, string>
  isOffice: boolean
  isSecretary: boolean
  isAuthority: boolean
  canOpenScheduling: boolean
  onAction: (path: string, body?: Any) => Promise<void>
}) {
  const [reason, setReason] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmAction, setConfirmAction] = useState<null | { path: string; label: string; body?: Any }>(null)

  const ctx = request.authority_context
  const currentVersionId = Number(request.current_directive_version?.id)
  const responseFor = (unitId: number) => (request.unit_responses || []).find((item: Any) => Number(item.organizational_unit_id) === unitId && Number(item.directive_version_id || item.meeting_request_directive_version_id) === currentVersionId)

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
    { label: 'منشأ', value: 'مقام عالی' },
    { label: 'ریاست درخواست‌کننده', value: 'ندارد' },
    { label: 'هدف جلسه', value: purposeLabels[request.purpose] || request.purpose || '—' },
    { label: 'اولویت', value: priorityLabels[request.priority] || request.priority || '—' },
    { label: 'نسخهٔ جاری دستور', value: request.current_directive_version?.version || '—' },
    { label: 'holder مقام', value: ctx?.authority_employee?.full_name || 'ثبت نشده' },
    { label: 'تعداد ریاست‌های دریافت‌کننده', value: String(units.length) },
    { label: 'وضعیت', value: statusLabels[request.status] || request.status || '—' },
  ]

  const timelineItems = (request.directive_versions || []).map((version: Any) => ({
    id: version.id,
    title: `نسخهٔ ${version.version}`,
    meta: `${version.version_status} · ${version.issued_at ? new Date(version.issued_at).toLocaleString('fa-AF') : 'مسوده'}`,
    comment: version.is_current ? 'نسخهٔ جاری دستور' : undefined,
  }))

  return <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <section className="space-y-6">
      <DetailSummaryCard
        eyebrow="دستور رسمی مقام عالی"
        title={request.subject}
        icon={<ShieldCheck className="size-5" />}
        items={summaryItems}
        description={<div className="space-y-3"><div><strong className="text-foreground">متن دستور:</strong> {request.directive || '—'}</div>{request.description ? <p>{request.description}</p> : null}<div><strong className="text-foreground">نتیجهٔ مورد انتظار:</strong> {request.requested_outcome || '—'}</div></div>}
      />

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
          <div className="space-y-1"><p className="text-xs text-muted-foreground">هماهنگی ریاست‌ها</p><CardTitle className="text-base">وضعیت پاسخ‌های نسخهٔ جاری</CardTitle></div>
          <Users className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="space-y-3 pt-5">
          {units.map((unit: Any) => {
            const unitId = Number(unit.organizational_unit_id)
            const response = responseFor(unitId)
            return <div key={unit.id || unitId} className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
              <div><strong className="block text-sm">{unit.unit?.name || unitId}</strong><span className="text-xs text-muted-foreground">ریاست دریافت‌کننده</span></div>
              <Badge variant={response?.response_status === 'ACKNOWLEDGED' ? 'default' : 'outline'}>{response?.response_status || 'در انتظار'}</Badge>
            </div>
          })}
        </CardContent>
      </Card>

      <WorkflowTimeline title="نسخه‌های ثبت‌شدهٔ دستور" items={timelineItems} emptyText="هنوز نسخه‌ای ثبت نشده است." />
    </section>

    <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
      <Card>
        <CardHeader className="border-b"><p className="text-xs text-muted-foreground">عملیات مجاز متناسب با وضعیت فعلی دستور</p><CardTitle className="text-base">اقدامات قابل انجام</CardTitle></CardHeader>
        <CardContent className="space-y-3 pt-5">
          {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
          {request.status === 'PENDING_HIGH_AUTHORITY_ISSUANCE' && isAuthority ? <>
            <Button className="w-full" disabled={!!busyAction} onClick={() => run('issue')}>صدور رسمی دستور</Button>
            <Textarea placeholder="دلیل برگشت مسوده" value={reason} onChange={event => setReason(event.target.value)} />
            <Button variant="outline" className="w-full" disabled={!!busyAction} onClick={() => setConfirmAction({ path: 'return', label: 'برگشت برای اصلاح', body: { reason } })}>برگشت برای اصلاح</Button>
          </> : null}

          {request.status === 'ISSUED_BY_HIGH_AUTHORITY' && isOffice ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('distribute')}>توزیع به ریاست‌ها</Button> : null}

          {['DISTRIBUTED_TO_DIRECTORATES', 'UNDER_DIRECTORATE_COORDINATION'].includes(request.status) ? units.map((unit: Any) => {
            const unitId = Number(unit.organizational_unit_id)
            const capability = unitCapabilities.find(item => item.unitId === unitId)
            return <div key={unit.id || unitId} className="space-y-2 rounded-xl border p-3">
              <strong className="block text-sm">{unit.unit?.name || unitId}</strong>
              {capability?.canCoordinate ? <Button variant="outline" className="w-full" size="sm" disabled={!!busyAction} onClick={() => run(`units/${unitId}/coordinate`)}>آغاز هماهنگی</Button> : null}
              {capability?.canAcknowledge ? <>
                <Button className="w-full" size="sm" disabled={!!busyAction} onClick={() => run(`units/${unitId}/acknowledge`)}>تأیید دریافت ریاست</Button>
                <Textarea placeholder="دلیل درخواست وضاحت" value={reason} onChange={event => setReason(event.target.value)} />
                <Button variant="outline" className="w-full" size="sm" disabled={!!busyAction} onClick={() => setConfirmAction({ path: `units/${unitId}/clarification`, label: 'درخواست وضاحت', body: { reason } })}>درخواست وضاحت</Button>
              </> : null}
            </div>
          }) : null}

          {request.status === 'PENDING_HIGH_AUTHORITY_REISSUANCE' && isAuthority ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('reissue')}>صدور مجدد نسخهٔ اصلاح‌شده</Button> : null}
          {request.status === 'DIRECTORATES_ACKNOWLEDGED' && isSecretary ? <Button className="w-full" disabled={!!busyAction} onClick={() => run('scheduling/start')}>آغاز زمان‌بندی</Button> : null}
          {['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'].includes(request.status) && canOpenScheduling ? <Button asChild className="w-full"><a href={`/meeting-requests/${request.id}/authority-initiated-scheduling`}>فضای زمان‌بندی و اشتراک‌کنندگان</a></Button> : null}
          {request.meeting ? <Button asChild variant="outline" className="w-full"><a href={`/meetings/${request.meeting.id}`}>مشاهدهٔ جلسهٔ رسمی</a></Button> : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="border-b"><p className="text-xs text-muted-foreground">راهنمای روند</p><CardTitle className="text-base">دستور آغازشده توسط مقام عالی</CardTitle></CardHeader>
        <CardContent className="pt-5 text-sm leading-7 text-muted-foreground">این دستور ریاست درخواست‌کننده ندارد؛ صدور، توزیع، هماهنگی ریاست‌ها و زمان‌بندی تنها در محدوده‌های مجاز سازمانی انجام می‌شوند.</CardContent>
      </Card>
    </aside>

    <ConfirmDialog
      open={!!confirmAction}
      onOpenChange={open => { if (!open) setConfirmAction(null) }}
      title={`${confirmAction?.label || 'عملیات'} انجام شود؟`}
      description="این اقدام در تاریخچه ثبت می‌شود و وضعیت دستور را تغییر می‌دهد."
      confirmLabel={confirmAction?.label || 'تأیید'}
      onConfirm={async () => {
        if (!confirmAction) return
        const current = confirmAction
        await run(current.path, current.body)
        setConfirmAction(null)
      }}
    />
  </div>
}
