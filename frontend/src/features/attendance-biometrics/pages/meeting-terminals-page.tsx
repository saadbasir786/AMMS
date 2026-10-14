import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Monitor,
  RefreshCw,
  Trash2,
  CalendarCheck,
  Clock,
  ShieldCheck,
  AlertTriangle
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import { AssignTerminalCard } from '../components/assign-terminal-card'
import type { MeetingTerminalsData, TerminalAssignment } from '../types'
import { fetchMeetingActionCapabilities, type MeetingActionCapabilities } from '@/features/meetings'

const TERMINAL_ASSIGNMENT_ERROR_MESSAGES: Record<string, string> = {
  TERMINAL_NOT_AVAILABLE: 'ترمینال انتخاب‌شده فعال یا در دسترس نیست. وضعیت دستگاه را بررسی کنید.',
  MEETING_NOT_SCHEDULED: 'این جلسه شروع شده یا پایان یافته است؛ تخصیص ترمینال باید پیش از شروع رسمی جلسه انجام شود.',
  MEETING_VERSIONS_REQUIRED: 'نسخهٔ رسمی زمان‌بندی یا اشتراک‌کنندگان جلسه موجود نیست؛ ابتدا اطلاعات جلسه را تکمیل کنید.',
  TERMINAL_OUTSIDE_MEETING_ORGANIZATION: 'این ترمینال متعلق به ادارهٔ برگزارکنندهٔ جلسه نیست؛ ترمینال همان اداره را انتخاب کنید.',
  INVALID_TERMINAL_ASSIGNMENT_WINDOW: 'زمان پایان بازه باید پس از زمان شروع آن باشد.',
  TERMINAL_ASSIGNMENT_WINDOW_NOT_COVERING_MEETING: 'بازهٔ مجاز باید تمام مدت جلسه را پوشش دهد؛ شروع بازه را پیش از شروع جلسه و پایان آن را پس از ختم جلسه بگذارید.',
  TERMINAL_ASSIGNMENT_EXISTS: 'این ترمینال از قبل به همین جلسه تخصیص یافته است؛ تخصیص موجود را بررسی کنید.',
  TERMINAL_ASSIGNMENT_CONFLICT: 'این ترمینال در این بازه به جلسهٔ دیگری تخصیص دارد؛ ترمینال دیگری یا بازهٔ بدون تداخل انتخاب کنید.',
}

const ASSIGNMENT_FIELD_MESSAGES: Record<string, string> = {
  terminal_id: 'یک ترمینال فعال انتخاب کنید.',
  valid_from: 'زمان شروع بازهٔ مجاز را بررسی کنید.',
  valid_until: 'زمان پایان باید معتبر و پس از زمان شروع باشد.',
}

function terminalAssignmentError(err: any): string {
  const response = err?.response?.data
  const code = response?.code || response?.message
  if (typeof code === 'string' && TERMINAL_ASSIGNMENT_ERROR_MESSAGES[code]) {
    return TERMINAL_ASSIGNMENT_ERROR_MESSAGES[code]
  }

  const validationErrors = response?.errors
  if (validationErrors && typeof validationErrors === 'object') {
    const fieldMessages = Object.keys(ASSIGNMENT_FIELD_MESSAGES)
      .filter((field) => Object.prototype.hasOwnProperty.call(validationErrors, field))
      .map((field) => ASSIGNMENT_FIELD_MESSAGES[field])
    if (fieldMessages.length) return fieldMessages.join(' ')
  }

  if (code === 'VALIDATION_ERROR' || err?.response?.status === 422) {
    return 'تخصیص انجام نشد. زمان‌های انتخاب‌شده باید معتبر باشند و بازهٔ مجاز تمام مدت جلسه را پوشش دهد.'
  }

  return response?.message || 'تخصیص ترمینال انجام نشد؛ وضعیت جلسه، ترمینال و بازهٔ زمانی را بررسی کنید.'
}

export function MeetingTerminalsPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [data, setData] = useState<MeetingTerminalsData | null>(null)
  const [capabilities, setCapabilities] = useState<MeetingActionCapabilities | null>(null)
  const [assignmentsPage, setAssignmentsPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Revoke Dialog state
  const [revokeTarget, setRevokeTarget] = useState<TerminalAssignment | null>(null)
  const [revokeReason, setRevokeReason] = useState('')

  const loadData = useCallback(async (targetPage = assignmentsPage) => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [res, actionCapabilities] = await Promise.all([
        api.get(`/meetings/${id}/terminals`, { params: { page: targetPage, per_page: 15 } }),
        fetchMeetingActionCapabilities(id),
      ])
      setData(apiData(res))
      setCapabilities(actionCapabilities)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات ترمینال‌های جلسه با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [id, assignmentsPage])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleAssign = async (formData: {
    terminal_id: number
    valid_from: string
    valid_until: string
  }) => {
    if (!id) return false
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/terminal-assignments`, formData)
      setAssignmentsPage(1)
      await loadData(1)
      return true
    } catch (err: any) {
      setError(terminalAssignmentError(err))
      return false
    } finally {
      setBusy(false)
    }
  }

  const handleRefreshAssignment = async (assignment: TerminalAssignment) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/terminal-assignments/${assignment.id}/refresh`)
      await loadData(assignmentsPage)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'به‌روزرسانی مبنای تخصیص با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const handleRevokeAssignment = async () => {
    if (!id || !revokeTarget) return
    if (!revokeReason.trim()) {
      setError('ثبت دلیل لغو تخصیص دستگاه الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/terminal-assignments/${revokeTarget.id}/revoke`, {
        reason: revokeReason,
      })
      setRevokeTarget(null)
      setRevokeReason('')
      await loadData(assignmentsPage)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'لغو تخصیص با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const meeting = data?.meeting
  const assignments = data?.assignments || []
  const availableTerminals = data?.available_terminals || []
  const meetingSchedule = meeting?.current_schedule_version
  const canAssign = capabilities?.terminals.assign === true
  const canRefresh = capabilities?.terminals.refresh === true
  const canRevoke = capabilities?.terminals.revoke === true

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="ترمینال‌ها و دستگاه‌های اختصاصی جلسه"
        description={
          meeting
            ? `${meeting.title} • تخصیص ترمینال فیزیکی جهت ثبت حضور امن بایومتریک`
            : 'مدیریت ترمینال‌های جلسه'
        }
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              asChild
              variant="outline"
              size="sm"
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <Link to={`/meetings/${id}/attendance`}>
                <CalendarCheck className="size-3.5" />
                <span>حاضری جلسه</span>
              </Link>
            </Button>

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
              onClick={() => loadData(assignmentsPage)}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <Monitor className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">دستگاه‌های تخصیص‌یافته</span>
            <strong className="text-base font-bold text-foreground font-mono">{data?.stats?.total ?? assignments.length}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <ShieldCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">دستگاه‌های آماده تخصیص</span>
            <strong className="text-base font-bold text-foreground font-mono">
              {availableTerminals.length}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Clock className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">نسخه زمان مبنا</span>
            <strong className="text-base font-bold text-foreground font-mono">
              نسخه {meeting?.current_schedule_version_id || '۱'}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CalendarCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">نسخه اعضای مبنا</span>
            <strong className="text-base font-bold text-foreground font-mono">
              نسخه {meeting?.current_participant_version_id || '۱'}
            </strong>
          </div>
        </div>
      </div>

      {/* Assign Terminal Card */}
      {canAssign && (
        <AssignTerminalCard
          availableTerminals={availableTerminals}
          scheduleDate={meetingSchedule?.meeting_date || meeting?.meeting_date || ''}
          scheduleStartTime={meetingSchedule?.start_time || meeting?.start_time || ''}
          scheduleEndTime={meetingSchedule?.end_time || meeting?.end_time || ''}
          scheduleVersionId={meeting?.current_schedule_version_id}
          busy={busy}
          onAssign={handleAssign}
        />
      )}

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/3" />
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-12 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Assignments Table */}
      {!loading && (
        <Card className="border border-border/70 shadow-xs" dir="rtl">
          <CardHeader className="p-4 sm:p-5 border-b border-border/60">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              دستگاه‌های تخصیص‌یافته به این جلسه
            </h3>
            <p className="text-[11px] text-muted-foreground">
              فهرست ترمینال‌ها، وضعیت همگام‌سازی با نسخه‌ها و پنجره زمانی فعالیت
            </p>
          </CardHeader>

          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-right border-collapse">
                <thead>
                  <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                    <th className="py-3.5 px-4 font-bold">کد و نام ترمینال</th>
                    <th className="py-3.5 px-4 font-bold">نسخه‌های مبنا (Baseline)</th>
                    <th className="py-3.5 px-4 font-bold">پنجره زمانی مجاز</th>
                    <th className="py-3.5 px-4 font-bold">وضعیت تخصیص</th>
                    <th className="py-3.5 px-4 font-bold text-center">عملیات</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/50">
                  {assignments.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-8 text-center text-xs text-muted-foreground">
                        برای این جلسه ترمینالی تخصیص نیافته است.
                      </td>
                    </tr>
                  ) : (
                    assignments.map((a) => (
                      <tr key={a.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30">
                        {/* Terminal Info */}
                        <td className="py-3.5 px-4">
                          <strong className="text-xs font-semibold text-foreground block font-mono">
                            {a.terminal?.code || `دستگاه #${a.terminal_id}`}
                          </strong>
                          <span className="text-[10px] text-muted-foreground block">
                            {a.terminal?.name || ''}
                          </span>
                        </td>

                        {/* Baseline */}
                        <td className="py-3.5 px-4 whitespace-nowrap font-mono text-[11px]">
                          <div>زمان‌بندی: نسخه {a.baseline_schedule_version_id}</div>
                          <div className="text-muted-foreground text-[10px]">
                            اشتراک‌کنندگان: نسخه {a.baseline_participant_version_id}
                          </div>
                        </td>

                        {/* Valid Window */}
                        <td className="py-3.5 px-4 whitespace-nowrap text-muted-foreground">
                          <div>از: {a.valid_from ? formatSolarDateTime(a.valid_from) : '—'}</div>
                          <div>تا: {a.valid_until ? formatSolarDateTime(a.valid_until) : '—'}</div>
                        </td>

                        {/* Status */}
                        <td className="py-3.5 px-4 whitespace-nowrap">
                          {a.is_stale ? (
                            <div className="space-y-1">
                              <Badge variant="destructive" className="text-[10px]">
                                نیازمند همگام‌سازی (STALE)
                              </Badge>
                              <span className="text-[10px] text-rose-600 block">
                                {a.schedule_changed ? 'زمان تغییر کرده ' : ''}
                                {a.participants_changed ? 'اعضا تغییر کرده‌اند' : ''}
                              </span>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-[10px]">
                              {a.status}
                            </Badge>
                          )}
                        </td>

                        {/* Actions */}
                        <td className="py-3.5 px-4 whitespace-nowrap text-center">
                          <div className="flex items-center justify-center gap-2">
                            {a.is_stale && canRefresh && (
                              <Button
                                size="sm"
                                disabled={busy}
                                onClick={() => handleRefreshAssignment(a)}
                                className="h-7.5 text-xs font-semibold rounded-lg gap-1 bg-primary hover:bg-primary/90 text-white"
                              >
                                <RefreshCw className="size-3" />
                                <span>همگام‌سازی</span>
                              </Button>
                            )}

                            {!['REVOKED', 'EXPIRED'].includes(a.status) && canRevoke && (
                              <Button
                                size="sm"
                                variant="ghost"
                                disabled={busy}
                                onClick={() => {
                                  setRevokeTarget(a)
                                  setRevokeReason('')
                                }}
                                className="h-7.5 text-xs font-semibold rounded-lg text-rose-600 hover:bg-rose-50"
                              >
                                <span>لغو تخصیص</span>
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
            {data?.assignments_meta && data.assignments_meta.last_page > 1 && (
              <div className="flex items-center justify-between gap-3 px-4 py-3 border-t border-border/60 text-xs">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={data.assignments_meta.current_page <= 1 || busy}
                  onClick={() => setAssignmentsPage((page) => page - 1)}
                  className="h-8 rounded-lg"
                >
                  قبلی
                </Button>
                <span className="text-muted-foreground">
                  صفحهٔ {data.assignments_meta.current_page} از {data.assignments_meta.last_page} · {data.assignments_meta.total} تخصیص
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={data.assignments_meta.current_page >= data.assignments_meta.last_page || busy}
                  onClick={() => setAssignmentsPage((page) => page + 1)}
                  className="h-8 rounded-lg"
                >
                  بعدی
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Revoke Assignment Dialog */}
      <Dialog open={!!revokeTarget} onOpenChange={(open) => !open && setRevokeTarget(null)}>
        <DialogContent dir="rtl" className="rounded-2xl sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="text-sm font-bold">
              لغو تخصیص ترمینال از جلسه
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              درج دلیل رسمی برای غیرفعال‌سازی مجوز فعالیت این دستگاه در جلسه
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 py-2 text-xs">
            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">دلیل لغو تخصیص (اجباری)</label>
              <Textarea
                rows={3}
                value={revokeReason}
                onChange={(e) => setRevokeReason(e.target.value)}
                placeholder="توضیح دهید چرا این دستگاه از جلسه لغو می‌شود..."
                className="text-xs rounded-xl"
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:justify-start">
            <Button
              variant="destructive"
              disabled={busy || !revokeReason.trim()}
              onClick={handleRevokeAssignment}
              className="h-8.5 text-xs font-semibold rounded-xl"
            >
              لغو قطعی تخصیص
            </Button>
            <Button
              variant="outline"
              onClick={() => setRevokeTarget(null)}
              className="h-8.5 text-xs font-semibold rounded-xl"
            >
              انصراف
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
