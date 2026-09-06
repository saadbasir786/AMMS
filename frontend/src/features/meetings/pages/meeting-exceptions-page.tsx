import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  ShieldCheck,
  LockKeyhole,
  Clock3,
  CalendarClock
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'
import { api, apiData } from '@/lib/api-client'
import { MeetingExceptionFormCard } from '../components/meeting-exception-form-card'
import { MeetingExceptionCaseCard } from '../components/meeting-exception-case-card'
import type { MeetingExceptionData, ExceptionCase, ExceptionCaseType } from '../types'
import { fetchMeetingActionCapabilities } from '../lib/meeting-capabilities'
import type { MeetingActionCapabilities } from '../types'

export function MeetingExceptionsPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [data, setData] = useState<MeetingExceptionData | null>(null)
  const [capabilities, setCapabilities] = useState<MeetingActionCapabilities | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Modals state
  const [confirmApplyTarget, setConfirmApplyTarget] = useState<ExceptionCase | null>(null)
  const [resumeTarget, setResumeTarget] = useState<ExceptionCase | null>(null)
  const [resumeSchedule, setResumeSchedule] = useState({
    meeting_date: '',
    start_time: '09:00',
    end_time: '10:00',
    location: '',
  })
  const [withdrawTarget, setWithdrawTarget] = useState<ExceptionCase | null>(null)
  const [withdrawReason, setWithdrawReason] = useState('')

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [res, actionCapabilities] = await Promise.all([
        api.get(`/meetings/${id}/exceptions`),
        fetchMeetingActionCapabilities(id),
      ])
      setData(apiData(res))
      setCapabilities(actionCapabilities)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات پرونده‌های استثنایی با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const runCall = async (method: 'post' | 'put', url: string, body?: any) => {
    setBusy(true)
    setError(null)
    try {
      await api[method](url, body)
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'عملیات با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const handleCreateCase = async (caseType: ExceptionCaseType, reason: string) => {
    if (!id) return
    await runCall('post', `/meetings/${id}/exceptions`, {
      case_type: caseType,
      reason,
    })
  }

  const handleAction = (kind: string, item: ExceptionCase) => {
    if (kind === 'submit') {
      runCall('post', `/meeting-exceptions/${item.id}/submit`)
    } else if (kind === 'apply') {
      setConfirmApplyTarget(item)
    } else if (kind === 'resume_modal') {
      setResumeTarget(item)
      setResumeSchedule({
        meeting_date: '',
        start_time: '09:00',
        end_time: '10:00',
        location: '',
      })
    } else if (kind === 'withdraw') {
      setWithdrawTarget(item)
      setWithdrawReason(item.current_version?.reason || '')
    }
  }

  const handleReviewAction = async (reviewId: number, actionType: 'acknowledge' | 'approve') => {
    await runCall('post', `/meeting-exception-reviews/${reviewId}/${actionType}`)
  }

  const handleExecuteApply = async () => {
    if (!confirmApplyTarget) return
    await runCall('post', `/meeting-exceptions/${confirmApplyTarget.id}/apply`)
    setConfirmApplyTarget(null)
  }

  const handleExecuteResume = async () => {
    if (!resumeTarget) return
    if (!resumeSchedule.meeting_date || !resumeSchedule.start_time || !resumeSchedule.location) {
      setError('تاریخ، ساعت و محل زمان‌بندی جدید الزامی است.')
      return
    }
    await runCall('post', `/meeting-exceptions/${resumeTarget.id}/resume`, resumeSchedule)
    setResumeTarget(null)
  }

  const handleExecuteWithdraw = async () => {
    if (!withdrawTarget) return
    if (!withdrawReason.trim()) {
      setError('ثبت دلیل پس‌گرفتن پرونده الزامی است.')
      return
    }
    await runCall('post', `/meeting-exceptions/${withdrawTarget.id}/withdraw`, {
      reason: withdrawReason,
    })
    setWithdrawTarget(null)
    setWithdrawReason('')
  }

  const cases = Array.isArray(data) ? data : data?.data || []
  const meeting = data?.meeting

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="مدیریت وضعیت‌های استثنایی جلسه"
        description="تعویق، لغو، بازگشایی و تعیین زمان‌بندی جدید با حفظ کامل تاریخچه غیرقابل حذف"
        action={
          <div className="flex flex-wrap items-center gap-2">
            {meeting?.status && (
              <Badge variant="outline" className="text-xs px-2.5 py-1">
                {meeting.status}
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
      {!loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            {/* Meeting Baseline Summary Card */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <h3 className="text-xs sm:text-sm font-bold text-foreground">
                  وضعیت و مبنای پرونده استثنایی
                </h3>
              </CardHeader>
              <CardContent className="p-4 sm:p-5">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">شماره جلسه</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      {meeting?.meeting_number || '—'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">وضعیت فعلی</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5">
                      {meeting?.status || '—'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">نسخه زمان‌بندی</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      نسخه {meeting?.current_schedule_version_id || '۱'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">درخواست اصلی</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5">
                      {meeting?.request_status || 'تأییدشده'}
                    </strong>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Create Exception Case Form */}
            <MeetingExceptionFormCard
              busy={busy}
              canCreate={capabilities?.exceptions.create === true}
              onCreateCase={handleCreateCase}
            />

            {/* Existing Exception Cases */}
            <div className="space-y-4">
              {cases.map((c: any) => (
                <MeetingExceptionCaseCard
                  key={c.id}
                  item={c}
                  busy={busy}
                  onAction={handleAction}
                  onReviewAction={handleReviewAction}
                  canSubmit={capabilities?.exceptions.submit === true}
                  canWithdraw={capabilities?.exceptions.withdraw === true}
                  canApply={capabilities?.exceptions.apply === true}
                  canAcknowledge={capabilities?.exceptions.acknowledge === true}
                  canApprove={capabilities?.exceptions.approve === true}
                />
              ))}
            </div>
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            {/* Audit & Preservation Notice */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center gap-2">
                  <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <LockKeyhole className="size-4" />
                  </div>
                  <div>
                    <h3 className="text-xs sm:text-sm font-bold text-foreground">
                      حفاظت از تاریخچه و اسناد
                    </h3>
                    <p className="text-[11px] text-muted-foreground">
                      حفظ جامعیت سوابق و عدم امکان حذف اطلاعات
                    </p>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 text-xs text-muted-foreground leading-relaxed">
                تمامی پرونده‌ها، نسخه‌های ارسال‌شده، تاریخچه زمان‌بندی و فهرست اعضا در سیستم به صورت تغییرناپذیر ثبت می‌شوند و با تعویق یا لغو جلسه، سوابق قبلی هرگز پاک نخواهند شد.
              </CardContent>
            </Card>

            {/* Timeline of Cases */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center gap-2">
                  <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Clock3 className="size-4" />
                  </div>
                  <div>
                    <h3 className="text-xs sm:text-sm font-bold text-foreground">
                      خط زمانی پرونده‌های استثنایی
                    </h3>
                    <p className="text-[11px] text-muted-foreground">
                      سوابق اقدامات ثبت‌شده برای این جلسه
                    </p>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-3">
                {cases.length === 0 ? (
                  <p className="text-xs text-muted-foreground py-2">
                    هنوز پرونده استثنایی ثبت نشده است.
                  </p>
                ) : (
                  <div className="space-y-2.5">
                    {cases.map((c: any) => (
                      <div
                        key={`timeline-${c.id}`}
                        className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                      >
                        <strong className="font-semibold text-foreground block">
                          پرونده #{c.id} • {c.case_type}
                        </strong>
                        <span className="text-[10px] text-muted-foreground block mt-0.5">
                          وضعیت: {c.status}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </aside>
        </div>
      )}

      {/* Confirm Apply Dialog */}
      <ConfirmDialog
        open={!!confirmApplyTarget}
        onOpenChange={(open) => !open && setConfirmApplyTarget(null)}
        title="اجرای تصمیم پرونده استثنایی تأیید شود؟"
        description="با اجرای این تصمیم، وضعیت رسمی جلسه تغییر یافته و به اطلاع تمامی اعضا و واحدهای مربوطه خواهد رسید."
        confirmLabel="تأیید و اجرای تصمیم"
        onConfirm={handleExecuteApply}
      />

      {/* Resume Modal */}
      <Dialog open={!!resumeTarget} onOpenChange={(open) => !open && setResumeTarget(null)}>
        <DialogContent dir="rtl" className="rounded-2xl sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-sm font-bold">
              تعیین زمان جدید و فعال‌سازی مجدد جلسه
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              تنظیم مشخصات زمان و مکان جدید جهت احیای جلسه لغوشده یا معوق
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 py-2 text-xs">
            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">تاریخ جلسه جدید</label>
              <MultiCalendarDatePicker
                value={resumeSchedule.meeting_date}
                onChange={(iso) => setResumeSchedule({ ...resumeSchedule, meeting_date: iso })}
                placeholder="انتخاب تاریخ جدید جلسه..."
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">ساعت آغاز</label>
                <TimePicker
                  value={resumeSchedule.start_time}
                  onChange={(time) => setResumeSchedule({ ...resumeSchedule, start_time: time })}
                  className="h-9.5 text-xs rounded-xl"
                />
              </div>

              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">ساعت پایان</label>
                <TimePicker
                  value={resumeSchedule.end_time}
                  onChange={(time) => setResumeSchedule({ ...resumeSchedule, end_time: time })}
                  className="h-9.5 text-xs rounded-xl"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">محل برگزاری جلسه</label>
              <Input
                value={resumeSchedule.location}
                onChange={(e) => setResumeSchedule({ ...resumeSchedule, location: e.target.value })}
                placeholder="اتاق کنفرانس، تالار جلسات، یا..."
                className="h-9.5 text-xs rounded-xl"
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:justify-start">
            <Button
              disabled={busy}
              onClick={handleExecuteResume}
              className="h-8.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white"
            >
              ثبت و فعال‌سازی مجدد
            </Button>
            <Button
              variant="outline"
              onClick={() => setResumeTarget(null)}
              className="h-8.5 text-xs font-semibold rounded-xl"
            >
              انصراف
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Withdraw Modal */}
      <Dialog open={!!withdrawTarget} onOpenChange={(open) => !open && setWithdrawTarget(null)}>
        <DialogContent dir="rtl" className="rounded-2xl sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="text-sm font-bold">
              پس‌گرفتن پرونده استثنایی (#{withdrawTarget?.id})
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              ثبت دلیل پس‌گرفتن پرونده و توقف فرآیند بررسی
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 py-2 text-xs">
            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">دلیل پس‌گرفتن پرونده</label>
              <Textarea
                rows={3}
                value={withdrawReason}
                onChange={(e) => setWithdrawReason(e.target.value)}
                placeholder="علت انصراف از ادامه این پرونده..."
                className="text-xs rounded-xl"
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:justify-start">
            <Button
              disabled={busy || !withdrawReason.trim()}
              onClick={handleExecuteWithdraw}
              className="h-8.5 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white"
            >
              پس‌گرفتن پرونده
            </Button>
            <Button
              variant="outline"
              onClick={() => setWithdrawTarget(null)}
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
