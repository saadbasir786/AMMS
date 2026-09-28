import React, { useEffect, useState, useMemo, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Users,
  CheckCircle2,
  AlertTriangle,
  History,
  Building2,
  UserCheck
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { formatSolarDate, formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import { ParticipantChangeCaseCard } from '../components/participant-change-case-card'
import { ParticipantChangeFormCard } from '../components/participant-change-form-card'
import type { MeetingParticipantChangeData } from '../types'
import { fetchMeetingActionCapabilities } from '../lib/meeting-capabilities'
import type { MeetingActionCapabilities } from '../types'
import { participantRoleLabels } from '../lib/workspace-labels'
import { WorkspacePagination } from '../components/workspace-pagination'
import { WorkspaceAuditCard } from '../components/workspace-audit-card'
import { MeetingLifecycleBar } from '../components/meeting-lifecycle-bar'
import { isParticipantBaselineStale } from '../lib/workspace-contracts'
import { VersionInspector } from '../components/version-inspector'

export function MeetingParticipantChangePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [workspace, setWorkspace] = useState<MeetingParticipantChangeData | null>(null)
  const [capabilities, setCapabilities] = useState<MeetingActionCapabilities | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Case creation & confirmation states
  const [reason, setReason] = useState('')
  const [reviewReason, setReviewReason] = useState('')
  const [participantVersionsPage, setParticipantVersionsPage] = useState(1)
  const [actionsPage, setActionsPage] = useState(1)
  const [confirmDialog, setConfirmDialog] = useState<'confirm' | 'approve' | 'finalize' | 'reject' | 'return' | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [wsRes, actionCapabilities] = await Promise.all([
        api.get(`/meetings/${id}/participant-change`, {
          params: { participant_versions_page: participantVersionsPage, actions_page: actionsPage },
        }),
        fetchMeetingActionCapabilities(id),
      ])
      setWorkspace(apiData(wsRes))
      setCapabilities(actionCapabilities)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'دسترسی به فضای تغییر اشتراک‌کنندگان ممکن نیست یا خطایی رخ داده است.'
      )
    } finally {
      setLoading(false)
    }
  }, [id, participantVersionsPage, actionsPage])

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
      setError(err?.response?.data?.message || 'عملیات تغییر اشتراک‌کنندگان انجام نشد.')
      return false
    } finally {
      setBusy(false)
    }
  }

  const meeting = workspace?.meeting
  const current = workspace?.current_participant_version
  const currentParticipants = workspace?.current_participant_version?.members ?? workspace?.current_participants ?? []
  const active = workspace?.active_participant_change_case
  const employeeOptions = workspace?.employee_options || []

  const authorityId = Number(meeting?.authority_context?.authority_office_unit_id || 0)
  const ownerId = Number(active?.owner_unit_id || 0)
  const canCreateCase = capabilities?.participant_change.create === true
  const isPreparer = capabilities?.participant_change.manage === true
  const isConfirmer = capabilities?.participant_change.confirm === true
  const isApprover = capabilities?.participant_change.approve === true
  const canFinalize = capabilities?.participant_change.finalize === true
  const canReturn = capabilities?.participant_change.return === true
  const canReject = capabilities?.participant_change.reject === true
  const isInternalFlow = meeting?.request?.flow_type === 'INTERNAL_DIRECTORATE'
  const latestDirectorReview = workspace?.latest_director_review
  const reviewMatchesActiveCase = active?.status === 'RETURNED_FOR_CORRECTION'
    && latestDirectorReview?.action === 'PARTICIPANT_CHANGE_RETURNED'
    && Number(latestDirectorReview?.metadata?.change_case_id) === Number(active.id)

  const isStale = isParticipantBaselineStale(active, meeting?.current_schedule_version_id, meeting?.current_participant_version_id)

  const ownerParticipants = useMemo(() => {
    if (!active) return currentParticipants
    if (active.owner_type === 'UNIT') {
      return currentParticipants.filter(
        (p: any) =>
          Number(p.owner_unit_id ?? p.organizational_unit_id) === ownerId ||
          Number(p.organizational_unit_id) === ownerId
      )
    }
    if (active.owner_type === 'AUTHORITY') {
      return currentParticipants.filter(
        (p: any) =>
          p.owner_type === 'AUTHORITY' ||
          Number(p.organizational_unit_id) === authorityId ||
          Number(p.employee_id) === Number(meeting?.authority_context?.authority_employee_id)
      )
    }
    return currentParticipants
  }, [active, currentParticipants, ownerId, authorityId, meeting])

  const history = useMemo(
    () => workspace?.participant_versions || [],
    [workspace]
  )

  const handleCreateCase = async () => {
    if (!reason.trim()) {
      setError('ثبت دلیل رسمی تغییر فهرست الزامی است.')
      return
    }
    if (await runCall(() => api.post(`/meetings/${id}/participant-change-cases`, { reason }))) setReason('')
  }

  const handleAddItem = (itemData: any) =>
    runCall(() => api.post(`/meetings/${id}/participant-change/${active?.id}/items`, itemData))

  const handleRemoveItem = (oldEmpId: number) =>
    runCall(() =>
      api.post(`/meetings/${id}/participant-change/${active?.id}/remove-item`, {
        employee_id: oldEmpId,
      })
    )

  const handleDeleteItem = (itemId: number) =>
    runCall(() => api.delete(`/meetings/${id}/participant-change/${active?.id}/items/${itemId}`))

  const handleSubmitCase = () =>
    runCall(() => api.post(`/meetings/${id}/participant-change/${active?.id}/submit`))

  const handleRefreshBaseline = () =>
    runCall(() => api.post(`/meetings/${id}/participant-change/${active?.id}/refresh-baseline`))

  const handleConfirmCase = () =>
    runCall(() => api.post(`/meetings/${id}/participant-change/${active?.id}/confirm`))

  const handleApproveCase = () =>
    runCall(() => api.post(`/meetings/${id}/participant-change/${active?.id}/approve`))

  const handleFinalizeCase = () =>
    runCall(() => api.post(`/meetings/${id}/participant-change/${active?.id}/finalize`))

  const handleReturnCase = async () => {
    if (!reviewReason.trim()) {
      setError('دلیل برگشت جهت اصلاح الزامی است.')
      return false
    }
    const saved = await runCall(() =>
      api.post(`/meetings/${id}/participant-change/${active?.id}/return`, {
        reason: reviewReason,
      })
    )
    if (saved) setReviewReason('')
    return saved
  }

  const handleRejectCase = async () => {
    if (!reviewReason.trim()) {
      setError('دلیل رد پرونده الزامی است.')
      return false
    }
    const saved = await runCall(() =>
      api.post(`/meetings/${id}/participant-change/${active?.id}/reject`, {
        reason: reviewReason,
      })
    )
    if (saved) setReviewReason('')
    return saved
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="تغییر فهرست اشتراک‌کنندگان جلسه"
        description={
          meeting
            ? `${meeting.title} • مدیریت نسخه‌محور اعضا، جایگزینی یا افزودن اشتراک‌کنندگان`
            : 'مدیریت اشتراک‌کنندگان جلسه'
        }
        action={
          <div className="flex flex-wrap items-center gap-2">
            {active && (
              <Badge variant="outline" className="text-xs px-2.5 py-1 text-indigo-700 dark:text-indigo-300 border-indigo-300">
                پرونده تغییر اعضا در جریان است
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

      {meeting && <MeetingLifecycleBar status={meeting.status} />}

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
            {/* Current Participants Card */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="text-[11px] font-semibold text-muted-foreground block">
                      فهرست رسمی و جاری اعضا
                    </span>
                    <h3 className="text-sm font-bold text-foreground">
                      اشتراک‌کنندگان مصوب (نسخه {current?.version_number || '۱'}) • {currentParticipants.length} نفر
                    </h3>
                  </div>
                  <Badge variant="outline" className="text-xs font-mono px-2 py-0.5">
                    نسخه زمان‌بندی: {meeting?.current_schedule_version_id || '—'}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-2">
                {currentParticipants.length === 0 ? (
                  <p className="text-xs text-muted-foreground py-2">
                    فهرست اشتراک‌کنندگان در این نسخه خالی است.
                  </p>
                ) : (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {currentParticipants.map((p: any) => (
                      <div
                        key={p.id}
                        className="flex items-center justify-between gap-2 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                      >
                        <div className="flex items-center gap-2 min-w-0">
                          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                            {p.employee?.full_name?.charAt(0) || 'ک'}
                          </div>
                          <div className="min-w-0 truncate">
                            <strong className="font-semibold text-foreground block truncate">
                              {p.employee?.full_name || '—'}
                            </strong>
                            <span className="text-[10px] text-muted-foreground block truncate">
                              {p.employee?.unit?.name || p.organizational_unit?.name || '—'}
                            </span>
                          </div>
                        </div>

                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 shrink-0">
                          {participantRoleLabels[p.participant_role || p.role] || p.participant_role || p.role || '\u0639\u0636\u0648'}
                        </Badge>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
            {/* Stale Baseline Warning */}
            {isStale && (
              <Alert variant="destructive" className="rounded-2xl p-4">
                <AlertTriangle className="size-4" />
                <AlertDescription className="text-xs space-y-2">
                  <p>
                    نسخه مبنای اعضا یا زمان جلسه پس از ایجاد یا تأیید این پرونده تغییر کرده است؛ تأیید قبلی قابل استفاده نیست.
                  </p>
                  {isPreparer && (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={busy}
                      onClick={handleRefreshBaseline}
                      className="h-8 text-xs font-semibold gap-1.5"
                    >
                      <RotateCw className="size-3" />
                      <span>به‌روزرسانی مبنا و ارسال دوباره برای تأیید</span>
                    </Button>
                  )}
                </AlertDescription>
              </Alert>
            )}

            {/* Active Case Card */}
            {active && (
              <ParticipantChangeCaseCard
                activeCase={active}
                canEdit={isPreparer && ['DRAFT', 'RETURNED_FOR_CORRECTION'].includes(active.status)}
                busy={busy}
                onDeleteItem={handleDeleteItem}
                onSaveReason={(nextReason) => runCall(() => api.put(`/meetings/${id}/participant-change/${active.id}`, { reason: nextReason }))}
              />
            )}

            {isInternalFlow && active?.status === 'APPROVED' && (
              <Alert className="rounded-2xl border-emerald-300/70 bg-emerald-50/70 dark:bg-emerald-950/20">
                <CheckCircle2 className="size-4 text-emerald-600" />
                <AlertDescription className="space-y-2 text-xs">
                  <p className="font-semibold text-foreground">تأیید رئیس انجام شده؛ آماده نهایی‌سازی</p>
                  <p className="text-muted-foreground">
                    فهرست رسمی اعضا هنوز تغییر نکرده است. تغییرات فقط پس از نهایی‌سازی مدیریت اجرائیه اعمال می‌شود.
                    {active.approved_by?.name ? ` تأییدکننده: ${active.approved_by.name}.` : ''}
                  </p>
                  {canFinalize && (
                    <Button disabled={busy || isStale} onClick={() => setConfirmDialog('finalize')} className="h-9 rounded-xl bg-emerald-600 px-4 text-xs font-semibold text-white hover:bg-emerald-700">
                      نهایی‌سازی تغییرات
                    </Button>
                  )}
                  {isStale && isPreparer && (
                    <Button variant="outline" disabled={busy} onClick={handleRefreshBaseline} className="h-9 rounded-xl text-xs">
                      بازبینی مبنا و ارسال دوباره برای تأیید
                    </Button>
                  )}
                  {!isStale && isPreparer && (
                    <Button variant="outline" disabled={busy} onClick={handleRefreshBaseline} className="h-9 rounded-xl text-xs">
                      لغو تأیید و بازگشت برای بازبینی
                    </Button>
                  )}
                </AlertDescription>
              </Alert>
            )}

            {isInternalFlow && latestDirectorReview && reviewMatchesActiveCase && (
              <Alert className="rounded-2xl border-amber-300/70 bg-amber-50/70 dark:bg-amber-950/20">
                <AlertDescription className="space-y-1 text-xs">
                  <p className="font-semibold text-foreground">
                    {latestDirectorReview.action === 'PARTICIPANT_CHANGE_RETURNED' ? 'آخرین نظر مدیر: برگشت برای اصلاح' : 'آخرین نظر مدیر: رد پرونده'}
                  </p>
                  <p className="text-muted-foreground">{latestDirectorReview.comment || 'دلیلی ثبت نشده است.'}</p>
                  <p className="text-[11px] text-muted-foreground">
                    {latestDirectorReview.actor?.name || 'مدیر مربوط'}
                    {latestDirectorReview.created_at ? ` · ${formatSolarDateTime(latestDirectorReview.created_at)}` : ''}
                  </p>
                </AlertDescription>
              </Alert>
            )}

            {/* Change Form Card */}
            {active && isPreparer && ['DRAFT', 'RETURNED_FOR_CORRECTION'].includes(active.status) && (
              <ParticipantChangeFormCard
                meetingId={Number(id)}
                activeCase={active}
                isInternalFlow={isInternalFlow}
                ownerParticipants={ownerParticipants}
                employeeOptions={employeeOptions}
                busy={busy}
                onAddItem={handleAddItem}
                onRemoveItem={handleRemoveItem}
                onSubmitCase={handleSubmitCase}
              />
            )}

            {/* Versions History Card */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 p-4 sm:p-5">
                <div className="flex items-center gap-2">
                  <div className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <History className="size-4" />
                  </div>
                  <div>
                    <h3 className="text-xs font-bold text-foreground sm:text-sm">
                      تاریخچه تغییرات اشتراک‌کنندگان
                    </h3>
                    <p className="text-[11px] text-muted-foreground">
                      سوابق ثبت و اعمال تغییرات؛ مشاهده و مقایسه نسخه‌ها
                    </p>
                  </div>
                </div>
                {id && <VersionInspector endpoint={`/meetings/${id}/versions/participants`} domain="participants" currentVersionId={current?.id} triggerPlacement="inline" triggerLabel="مشاهده و مقایسه نسخه‌ها" />}
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-2">
                {history.map((v) => (
                  <div
                    key={v.id}
                    className="flex flex-col gap-2 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <div className="flex items-center gap-2">
                        <Badge variant={v.id === current?.id ? 'default' : 'outline'} className="font-mono text-[10px]">
                          نسخه {v.version_number}
                        </Badge>
                        <span className="text-muted-foreground">
                          {v.created_at ? formatSolarDateTime(v.created_at) : '—'}
                        </span>
                      </div>
                      {v.id === current?.id && (
                        <span className="text-[10px] text-primary font-bold">نسخه معتبر جاری</span>
                      )}
                    </div>
                    {v.source_change_case?.items && v.source_change_case.items.length > 0 ? (
                      <ul className="space-y-1 text-muted-foreground">
                        {v.source_change_case.items.map((item) => {
                          const oldName = item.old_employee?.full_name
                          const newName = item.new_employee?.full_name
                          const detail = item.operation === 'ADD'
                            ? `افزوده شد: ${newName || 'عضو نامشخص'}`
                            : item.operation === 'REMOVE'
                              ? `حذف شد: ${oldName || 'عضو نامشخص'}`
                              : `جایگزینی: ${oldName || 'عضو نامشخص'} با ${newName || 'عضو نامشخص'}`

                          return <li key={item.id}>• {detail}</li>
                        })}
                      </ul>
                    ) : (
                      <p className="text-muted-foreground">نسخه پایه فهرست اعضا</p>
                    )}
                    {v.created_by?.name && (
                      <p className="text-muted-foreground">
                        ثبت‌کننده نسخه: <span className="font-medium text-foreground">{v.created_by.name}</span>
                      </p>
                    )}
                  </div>
                ))}
                <WorkspacePagination meta={workspace.participant_versions_meta} onChange={setParticipantVersionsPage} busy={busy || loading} />
              </CardContent>
            </Card>
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            {/* Create Case Card */}
            {!active && (
              <Card className="border border-border/70 shadow-xs">
                <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                  <h3 className="text-xs sm:text-sm font-bold text-foreground">
                    ایجاد پرونده رسمی تغییر اعضا
                  </h3>
                  <p className="text-[11px] text-muted-foreground">
                    ثبت درخواست تغییر یا جایگزینی اشتراک‌کنندگان
                  </p>
                </CardHeader>
                <CardContent className="p-4 sm:p-5 space-y-3">
                  {canCreateCase ? (
                    <>
                      <p className="text-xs text-muted-foreground leading-relaxed">
                        پرونده بر اساس حوزه مسئولیت شما به ریاست مربوطه یا دفتر مقام تخصیص می‌یابد.
                      </p>
                      <Textarea
                        rows={3}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="علت اداری و دلیل تغییر اشتراک‌کنندگان..."
                        className="text-xs rounded-xl"
                        disabled={busy}
                      />
                      <Button
                        className="w-full h-9.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white shadow-xs"
                        disabled={busy || !reason.trim()}
                        onClick={handleCreateCase}
                      >
                        {busy ? 'در حال ثبت…' : 'ایجاد پرونده تغییر اعضا'}
                      </Button>
                    </>
                  ) : (
                    <Alert className="rounded-xl">
                      <AlertDescription className="text-xs text-muted-foreground">
                        برای این جلسه صلاحیت ایجاد پرونده تغییر اشتراک‌کنندگان را ندارید.
                      </AlertDescription>
                    </Alert>
                  )}
                </CardContent>
              </Card>
            )}

            {/* Pending Confirmation Box */}
            {active && active.status === 'PENDING_CONFIRMATION' && ((isInternalFlow && isApprover) || (!isInternalFlow && isConfirmer) || canReturn || canReject) && (
              <Card className="border border-border/70 shadow-xs">
                <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                  <h3 className="text-xs sm:text-sm font-bold text-foreground">
                    {isInternalFlow ? 'بررسی و تصمیم رئیس ریاست' : 'بررسی و تصمیم‌گیری نهایی'}
                  </h3>
                  <p className="text-[11px] text-muted-foreground">
                    {isInternalFlow ? 'رئیس ریاست بررسی می‌کند؛ فهرست رسمی اعضا تا نهایی‌سازی تغییر نمی‌کند.' : 'تأیید، برگشت جهت اصلاح یا رد پرونده تغییر اعضا'}
                  </p>
                </CardHeader>
                <CardContent className="p-4 sm:p-5 space-y-3">
                  {((isInternalFlow && isApprover) || (!isInternalFlow && isConfirmer)) && <Button
                    className="w-full h-9.5 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                    disabled={busy || isStale}
                    onClick={() => setConfirmDialog(isInternalFlow ? 'approve' : 'confirm')}
                  >
                    {isInternalFlow ? 'تأیید برای نهایی‌سازی' : 'تأیید و صدور نسخه جدید فهرست'}
                  </Button>}

                  {(canReturn || canReject) && <div className="space-y-2.5 rounded-xl border border-amber-200/70 bg-amber-50/50 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                    <Textarea
                    rows={2}
                    value={reviewReason}
                    onChange={(e) => setReviewReason(e.target.value)}
                    placeholder="دلیل برگشت یا رد پرونده..."
                    className="text-xs rounded-xl"
                    disabled={busy}
                  />

                  {(canReturn || canReject) && <div className="flex flex-col gap-2 border-t border-amber-200/70 pt-3 dark:border-amber-900/50 sm:flex-row">
                    {canReturn && <Button
                      variant="outline"
                      disabled={busy || !reviewReason.trim()}
                      onClick={() => setConfirmDialog('return')}
                      className="h-9 flex-1 text-xs font-semibold rounded-xl text-amber-700 dark:text-amber-300"
                    >
                      برگشت جهت اصلاح
                    </Button>}
                    {canReject && <Button
                      variant="destructive"
                      disabled={busy || !reviewReason.trim()}
                      onClick={() => setConfirmDialog('reject')}
                      className="h-9 flex-1 text-xs font-semibold rounded-xl"
                    >
                      رد پرونده
                    </Button>}
                  </div>}
                  </div>}
                </CardContent>
              </Card>
            )}

            {/* Workflow Audit */}
            <WorkspaceAuditCard actions={workspace.participant_actions} meta={workspace.participant_actions_meta} onPageChange={setActionsPage} busy={busy || loading} />
          </aside>
        </div>
      )}

      {/* Confirmation Dialogs */}
      <ConfirmDialog open={confirmDialog === 'return'} onOpenChange={(open) => !open && setConfirmDialog(null)}
        title="پرونده برای اصلاح برگردانده شود؟" description="دلیل شما ثبت می‌شود و تهیه‌کننده می‌تواند پرونده را اصلاح کند." onConfirm={handleReturnCase}>
        {error && <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>}
      </ConfirmDialog>
      <ConfirmDialog
        open={confirmDialog === 'confirm'}
        onOpenChange={(open) => !open && setConfirmDialog(null)}
        title="تغییر فهرست اشتراک‌کنندگان تأیید شود؟"
        description="با تأیید شما، نسخه جدید فهرست اعضای جلسه صادر و مبنای ثبت حضور و غیاب قرار خواهد گرفت."
        confirmLabel="تأیید و اعمال نسخه جدید"
        onConfirm={handleConfirmCase}
      >
        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </ConfirmDialog>

      <ConfirmDialog
        open={confirmDialog === 'approve'}
        onOpenChange={(open) => !open && setConfirmDialog(null)}
        title="پرونده برای نهایی‌سازی تأیید شود؟"
        description="این تصمیم مجوز اداری برای مدیریت اجرائیه است و فهرست رسمی اعضا را تغییر نمی‌دهد."
        confirmLabel="تأیید پرونده"
        onConfirm={handleApproveCase}
      >
        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </ConfirmDialog>

      <ConfirmDialog
        open={confirmDialog === 'finalize'}
        onOpenChange={(open) => !open && setConfirmDialog(null)}
        title="تغییرات مصوب نهایی و اعمال شوند؟"
        description="فهرست رسمی اشتراک‌کنندگان به‌روزرسانی می‌شود و به کارکنان متأثر اطلاع داده خواهد شد."
        confirmLabel="نهایی‌سازی تغییرات"
        onConfirm={handleFinalizeCase}
      >
        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </ConfirmDialog>

      <ConfirmDialog
        open={confirmDialog === 'reject'}
        onOpenChange={(open) => !open && setConfirmDialog(null)}
        title="پرونده تغییر اعضا رد شود؟"
        description="دلیل واردشده در سوابق پرونده ثبت شده و تغییرات لغو می‌گردد."
        confirmLabel="تأیید و رد پرونده"
        variant="destructive"
        onConfirm={handleRejectCase}
      >
        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </ConfirmDialog>
    </div>
  )
}
