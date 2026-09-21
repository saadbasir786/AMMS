import React, { useEffect, useState, useMemo, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  CalendarDays,
  Clock,
  MapPin,
  CheckCircle2,
  GitBranch,
  ShieldCheck,
  CalendarClock
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { formatSolarDate, getTripleDateSummary } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import { RescheduleCaseCard } from '../components/reschedule-case-card'
import { RescheduleProposalForm } from '../components/reschedule-proposal-form'
import { RescheduleAcknowledgementsCard } from '../components/reschedule-acknowledgements-card'
import type { MeetingRescheduleData } from '../types'
import { fetchMeetingActionCapabilities } from '../lib/meeting-capabilities'
import type { MeetingActionCapabilities } from '../types'
import { WorkspacePagination } from '../components/workspace-pagination'
import { WorkspaceAuditCard } from '../components/workspace-audit-card'

export function MeetingReschedulePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [workspace, setWorkspace] = useState<MeetingRescheduleData | null>(null)
  const [capabilities, setCapabilities] = useState<MeetingActionCapabilities | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [venues, setVenues] = useState<Array<{ id: number; name: string; location_details?: string | null }>>([])

  // Form states
  const [caseReason, setCaseReason] = useState('')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [scheduleVersionsPage, setScheduleVersionsPage] = useState(1)
  const [actionsPage, setActionsPage] = useState(1)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [wsRes, actionCapabilities] = await Promise.all([
        api.get(`/meetings/${id}/reschedule`, {
          params: { schedule_versions_page: scheduleVersionsPage, actions_page: actionsPage },
        }),
        fetchMeetingActionCapabilities(id),
      ])
      let venueData: any[] = []
      try {
        const venueRes = await api.get('/meeting-venues', { params: { per_page: 50 } })
        venueData = apiData(venueRes) || []
      } catch {
        // Venue catalogue access is optional until an internal venue is selected.
      }
      setWorkspace(apiData(wsRes))
      setCapabilities(actionCapabilities)
      setVenues(venueData)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'دسترسی به فضای تغییر زمان جلسه ممکن نیست یا خطایی رخ داده است.'
      )
    } finally {
      setLoading(false)
    }
  }, [id, scheduleVersionsPage, actionsPage])

  useEffect(() => {
    loadData()
  }, [loadData])

  const runCall = async (fn: () => Promise<any>) => {
    setBusy(true)
    setError(null)
    try {
      await fn()
      await loadData()
      return true
    } catch (err: any) {
      setError(err?.response?.data?.message || 'عملیات تغییر زمان انجام نشد.')
      return false
    } finally {
      setBusy(false)
    }
  }

  const meeting = workspace?.meeting
  const current = workspace?.current_schedule_version
  const activeCase = workspace?.active_reschedule_case
  const activeProposal = activeCase?.proposals?.find((p) =>
    ['ACTIVE', 'DRAFT', 'SUBMITTED', 'RETURNED_FOR_CORRECTION', 'APPROVED'].includes(p.status)
  )
  const flowType = meeting?.request?.flow_type
  const internalDirectorate = flowType === 'INTERNAL_DIRECTORATE'
  const authorityControlled =
    ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'HIGH_AUTHORITY_INITIATED'].includes(
      flowType || ''
    ) || !!meeting?.authority_context

  const requiredUnits = useMemo(() => {
    if (!meeting?.units) return []
    return flowType === 'HIGH_AUTHORITY_INITIATED'
      ? meeting.units.filter((u: any) => u.role === 'PARTICIPATING')
      : meeting.units.filter((u: any) => ['REQUESTING', 'PARTICIPATING'].includes(u.role))
  }, [meeting, flowType])

  const canCreateCase = capabilities?.reschedule.create === true
  const canPropose = capabilities?.reschedule.propose === true
  const canSubmitProposal = capabilities?.reschedule.submit === true
  const canAcknowledge = capabilities?.reschedule.acknowledge === true
  const canReturnForCorrection = capabilities?.reschedule.return_for_correction === true
  const canRequestAlternative = capabilities?.reschedule.request_alternative === true
  const canConfirm = capabilities?.reschedule.confirm === true
  const canResolveAlternative = capabilities?.reschedule.resolve_alternative === true

  const ackCount = activeProposal?.acknowledgements?.length || 0
  const openAlternatives = (activeCase?.alternative_requests || []).filter((a) => a.status === 'OPEN')
  const history = useMemo(
    () => workspace?.schedule_versions || [],
    [workspace]
  )

  const handleCreateCase = async () => {
    if (!caseReason.trim()) {
      setError('ثبت دلیل رسمی تغییر زمان الزامی است.')
      return
    }
    if (await runCall(() => api.post(`/meetings/${id}/reschedule-cases`, { reason: caseReason }))) setCaseReason('')
  }

  const handleSubmitProposal = (proposalData: any) =>
    runCall(() =>
      api.post(
        `/meetings/${id}/reschedule/${authorityControlled ? 'authority-proposals' : 'proposals'}`,
        proposalData
      )
    )

  const handleSaveInternalDraft = (proposalData: any) =>
    runCall(() => activeProposal
      ? api.put(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/draft`, proposalData)
      : api.post(`/meetings/${id}/reschedule/proposals`, proposalData))

  const handleSubmitInternalProposal = () =>
    activeProposal
      ? runCall(() => api.post(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/submit`))
      : Promise.resolve(false)

  const handleAcknowledge = () =>
    activeProposal
      ? runCall(() => api.post(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/acknowledge`))
      : Promise.resolve(false)

  const handleReturnForCorrection = (reason: string) =>
    activeProposal
      ? runCall(() => api.post(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/return`, { reason }))
      : Promise.resolve(false)

  const handleRequestAlternative = (reasonText: string) =>
    activeProposal
      ? runCall(() =>
          api.post(`/meetings/${id}/reschedule/proposals/${activeProposal.id}/request-alternative`, {
            reason: reasonText,
          })
        )
      : Promise.resolve(false)

  const handleConfirmNewVersion = () => runCall(() => api.post(`/meetings/${id}/reschedule/confirm`))

  const handleResolveAlternative = (alt: any) =>
    runCall(() => api.post(`/meetings/${id}/reschedule/alternative-requests/${alt.id}/resolve`))

  const currentTripleDate = getTripleDateSummary(current?.meeting_date)

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="فضای تغییر و مذاکره زمان جلسه"
        description={internalDirectorate ? 'تأیید زمان پیشنهادی در همین اداره' : (
          meeting ? `${meeting.title} • هماهنگی زمان‌بندی جدید میان تمامی ریاست‌های دخیل` : 'مدیریت تغییر زمان جلسه'
        )}
        action={
          <div className="flex flex-wrap items-center gap-2">
            {activeCase && (
              <Badge variant="outline" className="text-xs px-2.5 py-1 text-amber-700 dark:text-amber-300 border-amber-300">
                تغییر زمان در جریان است
              </Badge>
            )}
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate(`/meetings/${id}`)}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به جلسه</span>
            </Button>
          </div>
        }
      />

      {/* Error Alert */}
      {error && (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertCircle className="size-4" />
          <AlertDescription className="text-xs flex items-center justify-between gap-2">
            <span>{error}</span>
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="space-y-6">
            <div className="h-44 rounded-2xl bg-card border border-border/70 animate-pulse" />
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
          <div className="space-y-6">
            <div className="h-52 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
        </div>
      )}

      {/* Main Content */}
      {!loading && workspace && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            {/* Current Schedule Summary */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="text-[11px] font-semibold text-muted-foreground block">
                      زمان‌بندی مصوب فعلی
                    </span>
                    <h3 className="text-sm font-bold text-foreground">
                      نسخه معتبر جاری (نسخه {current?.version_number || '۱'})
                    </h3>
                  </div>
                  {currentTripleDate && (
                    <div className="text-[10px] font-medium text-muted-foreground p-1.5 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border">
                      <span>{currentTripleDate.solar}</span>
                      <span> · </span>
                      <span>{currentTripleDate.lunar}</span>
                    </div>
                  )}
                </div>
              </CardHeader>
              <CardContent className="p-4 sm:p-5">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-center gap-2.5">
                    <CalendarDays className="size-4 text-primary shrink-0" />
                    <div>
                      <span className="text-[10px] text-muted-foreground block">تاریخ مصوب</span>
                      <strong className="text-xs font-bold text-foreground">
                        {current?.meeting_date ? `${formatSolarDate(current.meeting_date)} هـ.ش` : '—'}
                      </strong>
                    </div>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-center gap-2.5">
                    <Clock className="size-4 text-primary shrink-0" />
                    <div>
                      <span className="text-[10px] text-muted-foreground block">ساعات برگزاری</span>
                      <strong className="text-xs font-bold text-foreground font-mono">
                        {current ? `${current.start_time || '—'} تا ${current.end_time || '—'}` : '—'}
                      </strong>
                    </div>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-center gap-2.5">
                    <MapPin className="size-4 text-primary shrink-0" />
                    <div className="min-w-0">
                      <span className="text-[10px] text-muted-foreground block">مکان برگزاری</span>
                      <strong className="text-xs font-bold text-foreground truncate block">
                        {current?.location || '—'}
                      </strong>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Active Reschedule Case */}
            {activeCase && (
              <RescheduleCaseCard
                activeCase={activeCase}
                requiredUnitCount={requiredUnits.length}
              />
            )}

            {/* Proposal Form */}
            {activeCase && canPropose && (!internalDirectorate || !activeProposal || ['DRAFT', 'RETURNED_FOR_CORRECTION'].includes(activeProposal.status)) && (
              <RescheduleProposalForm
                initial={internalDirectorate ? (activeProposal || current) : current}
                onSubmit={internalDirectorate ? handleSaveInternalDraft : handleSubmitProposal}
                submitLabel={internalDirectorate ? 'ذخیره پیش‌نویس' : undefined}
                label={authorityControlled ? 'پیشنهاد رسمی دفتر مقام' : internalDirectorate ? 'پیش‌نویس زمان پیشنهادی' : 'ثبت پیشنهاد تغییر زمان'}
                busy={busy}
                venues={venues}
              />
            )}

            {internalDirectorate && activeProposal && canSubmitProposal && ['DRAFT', 'RETURNED_FOR_CORRECTION'].includes(activeProposal.status) && (
              <Button disabled={busy} onClick={handleSubmitInternalProposal} className="w-full h-9.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white shadow-xs">
                ارسال برای تأیید ریاست
              </Button>
            )}

            {/* Approval status */}
            {activeProposal && (!internalDirectorate || ['SUBMITTED', 'APPROVED'].includes(activeProposal.status)) && (
              <RescheduleAcknowledgementsCard
                activeProposal={activeProposal}
                requiredUnits={requiredUnits}
                canAcknowledge={canAcknowledge}
                canRequestAlternative={internalDirectorate ? false : canRequestAlternative}
                canReturnForCorrection={internalDirectorate && activeProposal.status === 'SUBMITTED' ? canReturnForCorrection : false}
                singleDirectorate={internalDirectorate}
                busy={busy}
                onAcknowledge={handleAcknowledge}
                onRequestAlternative={handleRequestAlternative}
                onReturnForCorrection={handleReturnForCorrection}
              />
            )}

            {/* Open Alternatives */}
            {openAlternatives.length > 0 && (
              <Card className="border border-amber-200 dark:border-amber-900/50 shadow-xs">
                <CardHeader className="p-4 sm:p-5 border-b border-amber-100 dark:border-amber-900/30">
                  <h3 className="text-xs sm:text-sm font-bold text-foreground">
                    درخواست‌های زمان بدیل باز ({openAlternatives.length})
                  </h3>
                </CardHeader>
                <CardContent className="p-4 sm:p-5 space-y-2">
                  {openAlternatives.map((alt) => (
                    <div
                      key={alt.id}
                      className="flex items-center justify-between gap-3 p-3 rounded-xl bg-amber-50/40 dark:bg-amber-950/20 border border-amber-200/60 text-xs"
                    >
                      <div>
                        <strong className="font-semibold text-foreground block">
                          {alt.unit?.name || 'ریاست درخواست‌کننده'}
                        </strong>
                        <p className="text-[11px] text-muted-foreground mt-0.5">{alt.reason}</p>
                      </div>
                      {canResolveAlternative && (
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={busy}
                          onClick={() => handleResolveAlternative(alt)}
                          className="h-8 text-xs font-semibold rounded-lg shrink-0"
                        >
                          حل درخواست
                        </Button>
                      )}
                    </div>
                  ))}
                </CardContent>
              </Card>
            )}

            {/* Schedule History */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center gap-2">
                  <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <CalendarClock className="size-4" />
                  </div>
                  <div>
                    <h3 className="text-xs sm:text-sm font-bold text-foreground">
                      تاریخچه نسخه‌های زمان‌بندی جلسه
                    </h3>
                    <p className="text-[11px] text-muted-foreground">
                      سوابق کلیه تغییرات و نسخه‌های معتبر ثبت‌شده
                    </p>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-2">
                {history.map((v) => (
                  <div
                    key={v.id}
                    className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                  >
                    <div className="flex items-center gap-2">
                      <Badge variant={v.id === current?.id ? 'default' : 'outline'} className="font-mono text-[10px]">
                        نسخه {v.version_number}
                      </Badge>
                      <span className="font-medium text-foreground">
                        {formatSolarDate(v.meeting_date)} هـ.ش • {v.start_time} تا {v.end_time} • {v.location}
                      </span>
                    </div>
                    {v.id === current?.id && (
                      <span className="text-[10px] text-primary font-bold">نسخه جاری</span>
                    )}
                  </div>
                ))}
                <WorkspacePagination meta={workspace.schedule_versions_meta} onChange={setScheduleVersionsPage} busy={busy || loading} />
              </CardContent>
            </Card>
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            {/* Create Case Card */}
            {!activeCase && (
              <Card className="border border-border/70 shadow-xs">
                <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                  <h3 className="text-xs sm:text-sm font-bold text-foreground">
                    ایجاد پرونده رسمی تغییر زمان
                  </h3>
                  <p className="text-[11px] text-muted-foreground">
                    آغاز فرآیند هماهنگی تغییر ساعت یا تاریخ جلسه
                  </p>
                </CardHeader>
                <CardContent className="p-4 sm:p-5 space-y-3">
                  {canCreateCase ? (
                    <>
                      <Textarea
                        rows={3}
                        value={caseReason}
                        onChange={(e) => setCaseReason(e.target.value)}
                        placeholder="علت اداری و ضرورت تغییر زمان جلسه..."
                        className="text-xs rounded-xl"
                        disabled={busy}
                      />
                      <Button
                        className="w-full h-9.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white shadow-xs"
                        disabled={busy || !caseReason.trim()}
                        onClick={handleCreateCase}
                      >
                        {busy ? 'در حال ایجاد…' : 'ایجاد پرونده تغییر زمان'}
                      </Button>
                    </>
                  ) : (
                    <Alert className="rounded-xl">
                      <AlertDescription className="text-xs text-muted-foreground">
                        برای این جلسه صلاحیت ایجاد پرونده تغییر زمان را ندارید.
                      </AlertDescription>
                    </Alert>
                  )}
                </CardContent>
              </Card>
            )}

            {/* Final Confirmation Card */}
            {activeCase && canConfirm && (
              <Card className="border border-border/70 shadow-xs">
                <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                  <h3 className="text-xs sm:text-sm font-bold text-foreground">
                    تأیید و صدور نسخه جدید
                  </h3>
                  <p className="text-[11px] text-muted-foreground">
                    اعمال نهایی پس از اخذ کلیه تأییدات و رفع بدیل‌ها
                  </p>
                </CardHeader>
                <CardContent className="p-4 sm:p-5 space-y-3">
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    با تأیید نهایی، نسخه جدید زمان‌بندی به عنوان زمان رسمی جلسه ثبت و به اطلاع همه اعضا خواهد رسید.
                  </p>
                  <Button
                    className="w-full h-9.5 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                    disabled={
                      busy ||
                      !activeProposal ||
                      ackCount < requiredUnits.length ||
                      openAlternatives.length > 0
                    }
                    onClick={() => setConfirmOpen(true)}
                  >
                    تأیید و صدور نسخه جدید زمان‌بندی
                  </Button>
                </CardContent>
              </Card>
            )}

            {/* Workflow Audit */}
            <WorkspaceAuditCard actions={workspace.actions} meta={workspace.actions_meta} onPageChange={setActionsPage} busy={busy || loading} />
          </aside>
        </div>
      )}

      {/* Confirm Dialog */}
      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title="نسخه جدید زمان‌بندی جلسه اعمال شود؟"
        description="با تأیید شما، پیشنهاد جاری تبدیل به نسخه معتبر جلسه شده و تقویم سیستم به‌روزرسانی می‌شود."
        confirmLabel="تأیید و صدور نسخه جدید"
        onConfirm={handleConfirmNewVersion}
      >{error && <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>}</ConfirmDialog>
    </div>
  )
}
