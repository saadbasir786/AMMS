import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  FileCheck2,
  Clock3,
  CheckCircle2,
  Plus,
  Tag,
  ShieldCheck,
  ListTodo,
  AlertTriangle,
  Building2,
  Calendar
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { formatSolarDate, formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import { cn } from '@/lib/utils'
import type { MeetingFollowUpData, FollowUpDecision } from '../types'

const CLASSIFICATION_LABELS: Record<string, { label: string; color: string }> = {
  UNCLASSIFIED: {
    label: 'طبقه‌بندی‌نشده',
    color: 'text-slate-600 bg-slate-100 border-slate-200',
  },
  ACTION_REQUIRED: {
    label: 'نیازمند اقدام',
    color: 'text-indigo-700 bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200',
  },
  NO_ACTION_REQUIRED: {
    label: 'بدون نیاز به اقدام',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200',
  },
}

const MEETING_STATUS_LABELS: Record<string, string> = {
  SCHEDULED: 'برنامه‌ریزی‌شده',
  IN_PROGRESS: 'در حال برگزاری',
  HELD: 'برگزارشده',
  COMPLETED: 'تکمیل‌شده',
  POSTPONED: 'به تعویق‌افتاده',
  CANCELLED: 'لغوشده',
}

const ACTION_ITEM_STATUS_LABELS: Record<string, string> = {
  OPEN: 'باز',
  IN_PROGRESS: 'در حال اجرا',
  BLOCKED: 'دارای مانع',
  PENDING_VERIFICATION: 'منتظر تأیید',
  COMPLETED: 'تکمیل‌شده',
  CANCELLED: 'لغوشده',
}

export function MeetingFollowUpPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [data, setData] = useState<MeetingFollowUpData | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Dialog states
  const [selectedDecision, setSelectedDecision] = useState<FollowUpDecision | null>(null)
  const [dialogMode, setDialogMode] = useState<'classify' | 'action' | null>(null)
  const [classification, setClassification] = useState('ACTION_REQUIRED')
  const [classifyReason, setClassifyReason] = useState('')

  const [actionForm, setActionForm] = useState({
    title: '',
    description: '',
    responsible_unit_id: '',
    assigned_employee_id: '',
    due_date: '',
  })

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(`/meetings/${id}/follow-up`)
      setData(apiData(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری فضای پیگیری تصمیمات با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleInitialize = async () => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/follow-up/initialize`)
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'خطا در ایجاد پیگیری تصمیمات.')
    } finally {
      setBusy(false)
    }
  }

  const closeDialog = () => {
    setSelectedDecision(null)
    setDialogMode(null)
    setClassifyReason('')
  }

  const handleClassify = async () => {
    if (!selectedDecision) return
    if (classification === 'NO_ACTION_REQUIRED' && !classifyReason.trim()) {
      setError('دلیل «بدون نیاز به اقدام» الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.post(`/decisions/${selectedDecision.id}/follow-up/classify`, {
        classification,
        reason: classifyReason || undefined,
      })
      closeDialog()
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'خطا در ثبت طبقه‌بندی تصمیم.')
    } finally {
      setBusy(false)
    }
  }

  const handleCreateActionItem = async () => {
    if (!selectedDecision) return
    if (!actionForm.title.trim()) {
      setError('عنوان اقدام الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.post(`/decisions/${selectedDecision.id}/action-items`, {
        ...actionForm,
        responsible_unit_id: Number(actionForm.responsible_unit_id) || undefined,
        assigned_employee_id: actionForm.assigned_employee_id
          ? Number(actionForm.assigned_employee_id)
          : undefined,
      })
      closeDialog()
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'خطا در ایجاد اقدام اجرایی.')
    } finally {
      setBusy(false)
    }
  }

  const openClassify = (decision: FollowUpDecision) => {
    setSelectedDecision(decision)
    setDialogMode('classify')
    setClassification('ACTION_REQUIRED')
    setClassifyReason('')
  }

  const openAction = (decision: FollowUpDecision) => {
    setSelectedDecision(decision)
    setDialogMode('action')
    setActionForm({
      title: '',
      description: '',
      responsible_unit_id: decision.responsible_unit_id ? String(decision.responsible_unit_id) : '',
      assigned_employee_id: decision.responsible_employee_id
        ? String(decision.responsible_employee_id)
        : '',
      due_date: decision.target_date || '',
    })
  }

  const summary = data?.summary
  const decisions = data?.decisions || []

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={data?.meeting?.meeting_number || undefined}
        title="فضای پیگیری تصمیمات و مصوبات"
        description="پیگیری عملیاتی و تفکیک اقدامات تصمیمات مندرج در صورت‌جلسه رسمی مصوب"
        action={
          <div className="flex flex-wrap items-center gap-2">
            {data?.meeting?.status && (
              <Badge variant="outline" className="text-xs px-2.5 py-1">
                {MEETING_STATUS_LABELS[data.meeting.status] || data.meeting.status}
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
        <div className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-20 bg-card rounded-2xl animate-pulse" />
            ))}
          </div>
          <div className="h-64 bg-card rounded-2xl animate-pulse" />
        </div>
      )}

      {/* Summary KPI Strip */}
      {!loading && summary && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
            <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
              <FileCheck2 className="size-4" />
            </div>
            <div>
              <span className="text-[10px] text-muted-foreground block font-medium">کل مصوبات</span>
              <strong className="text-base font-bold text-foreground font-mono">
                {summary.total_decisions}
              </strong>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
            <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
              <ListTodo className="size-4" />
            </div>
            <div>
              <span className="text-[10px] text-muted-foreground block font-medium">
                در حال اجرا
              </span>
              <strong className="text-base font-bold text-foreground font-mono">
                {summary.in_progress}
              </strong>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
            <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
              <CheckCircle2 className="size-4" />
            </div>
            <div>
              <span className="text-[10px] text-muted-foreground block font-medium">
                تکمیل‌شده
              </span>
              <strong className="text-base font-bold text-foreground font-mono">
                {summary.completed}
              </strong>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
            <div className="size-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center font-bold text-xs shrink-0">
              <AlertTriangle className="size-4" />
            </div>
            <div>
              <span className="text-[10px] text-muted-foreground block font-medium">
                دارای تأخیر
              </span>
              <strong className="text-base font-bold text-foreground font-mono text-rose-600">
                {summary.overdue}
              </strong>
            </div>
          </div>
        </div>
      )}

      {/* Follow-up Initialization Banner */}
      {!loading && data && !data.followup_initialized && (
        <Card className="border-dashed border-border/80 bg-card/60 rounded-2xl">
          <CardContent className="flex flex-col sm:flex-row items-center justify-between gap-4 p-6 sm:p-8">
            <div className="space-y-1 text-center sm:text-right">
              <h3 className="text-sm font-bold text-foreground">
                پیگیری تصمیمات هنوز برای این جلسه فعال نشده است
              </h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                با فعال‌سازی پیگیری، تصمیمات مصوب صورت‌جلسه وارد چرخه پیگیری و تخصیص اقدامات می‌شوند.
              </p>
            </div>
            <Button
              disabled={busy}
              onClick={handleInitialize}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs shrink-0"
            >
              <CheckCircle2 className="size-4" />
              <span>{busy ? 'در حال فعال‌سازی…' : 'ایجاد و فعال‌سازی پیگیری تصمیمات'}</span>
            </Button>
          </CardContent>
        </Card>
      )}

      {/* Decisions List */}
      {!loading && data?.followup_initialized && (
        <Card className="border border-border/70 shadow-xs" dir="rtl">
          <CardHeader className="p-4 sm:p-5 border-b border-border/60">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              فهرست تصمیمات مصوب و اقدامات اجرایی
            </h3>
            <p className="text-[11px] text-muted-foreground">
              طبقه‌بندی هر مصوبه و صدور وظایف عملیاتی
            </p>
          </CardHeader>

          <CardContent className="p-4 sm:p-5 space-y-4">
            {decisions.length === 0 ? (
              <p className="text-xs text-muted-foreground text-center py-6">
                تصمیمی برای پیگیری یافت نشد.
              </p>
            ) : (
              <div className="space-y-4">
                {decisions.map((dec) => {
                  const classCfg = CLASSIFICATION_LABELS[dec.classification] || {
                    label: dec.classification,
                    color: 'text-slate-600 bg-slate-100',
                  }

                  return (
                    <div
                      key={dec.id}
                      className="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/60 space-y-3"
                    >
                      {/* Decision Header */}
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="font-mono text-[11px]">
                            {dec.decision_number}
                          </Badge>
                          <span
                            className={cn(
                              'text-[10px] font-bold px-2 py-0.5 rounded-md border',
                              classCfg.color
                            )}
                          >
                            {classCfg.label}
                          </span>
                        </div>

                        <div className="flex items-center gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => openClassify(dec)}
                            className="h-7.5 text-xs font-semibold rounded-lg gap-1"
                          >
                            <Tag className="size-3" />
                            <span>طبقه‌بندی</span>
                          </Button>

                          {dec.classification === 'ACTION_REQUIRED' && (
                            <Button
                              size="sm"
                              onClick={() => openAction(dec)}
                              className="h-7.5 text-xs font-semibold rounded-lg gap-1 bg-primary hover:bg-primary/90 text-white"
                            >
                              <Plus className="size-3" />
                              <span>تعریف اقدام جدید</span>
                            </Button>
                          )}
                        </div>
                      </div>

                      {/* Decision Text */}
                      <p className="text-xs font-medium text-foreground leading-relaxed">
                        {dec.decision_text}
                      </p>

                      {dec.target_date && (
                        <div className="text-[11px] text-muted-foreground flex items-center gap-1 font-mono">
                          <Calendar className="size-3 text-primary" />
                          <span>مهلت مصوب: {formatSolarDate(dec.target_date)} هـ.ش</span>
                        </div>
                      )}

                      {/* Attached Action Items */}
                      {dec.action_items && dec.action_items.length > 0 && (
                        <div className="mt-3 pt-3 border-t border-border/50 space-y-2">
                          <span className="text-[11px] font-bold text-muted-foreground block">
                            اقدامات صادرشده برای این تصمیم:
                          </span>
                          <div className="space-y-1.5">
                            {dec.action_items.map((ai) => (
                              <Link
                                key={ai.id}
                                to={`/action-items/${ai.id}`}
                                className="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-card border border-border/60 hover:border-primary transition-colors text-xs"
                              >
                                <div className="flex items-center gap-2 min-w-0">
                                  <Badge variant="outline" className="font-mono text-[10px]">
                                    {ai.action_number}
                                  </Badge>
                                  <span className="font-bold text-foreground truncate">
                                    {ai.title}
                                  </span>
                                </div>

                                <div className="flex items-center gap-2 shrink-0">
                                  <span className="text-[10px] font-mono text-muted-foreground">
                                    {ai.progress_percent || 0}٪
                                  </span>
                                  <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5">
                                    {ACTION_ITEM_STATUS_LABELS[ai.status] || ai.status}
                                  </Badge>
                                </div>
                              </Link>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  )
                })}
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Classify Dialog */}
      <Dialog open={dialogMode === 'classify'} onOpenChange={(open) => !open && closeDialog()}>
        <DialogContent dir="rtl" className="rounded-2xl sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="text-sm font-bold">
              طبقه‌بندی تصمیم ({selectedDecision?.decision_number})
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              تعیین نیاز به اقدام عملیاتی یا ماهیت صرفاً اطلاعی این مصوبه
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 py-2 text-xs">
            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">نوع تصمیم</label>
              <select
                value={classification}
                onChange={(e) => setClassification(e.target.value)}
                className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
              >
                <option value="ACTION_REQUIRED">نیازمند اقدام عملیاتی</option>
                <option value="NO_ACTION_REQUIRED">بدون نیاز به اقدام (صرفاً اطلاعی / تصویبی)</option>
              </select>
            </div>

            {classification === 'NO_ACTION_REQUIRED' && (
              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">دلیل عدم نیاز به اقدام</label>
                <Textarea
                  rows={2}
                  value={classifyReason}
                  onChange={(e) => setClassifyReason(e.target.value)}
                  placeholder="توضیح دهید چرا این مصوبه نیاز به اقدام ندارد..."
                  className="text-xs rounded-xl"
                />
              </div>
            )}
          </div>

          <DialogFooter className="gap-2 sm:justify-start">
            <Button
              disabled={busy}
              onClick={handleClassify}
              className="h-8.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white"
            >
              ثبت طبقه‌بندی
            </Button>
            <Button
              variant="outline"
              onClick={closeDialog}
              className="h-8.5 text-xs font-semibold rounded-xl"
            >
              انصراف
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Create Action Item Dialog */}
      <Dialog open={dialogMode === 'action'} onOpenChange={(open) => !open && closeDialog()}>
        <DialogContent dir="rtl" className="rounded-2xl sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-sm font-bold">
              تعریف اقدام جدید ({selectedDecision?.decision_number})
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              ایجاد اقدام عملیاتی متصل به این مصوبه رسمی
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 py-2 text-xs">
            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">عنوان اقدام</label>
              <Input
                value={actionForm.title}
                onChange={(e) => setActionForm({ ...actionForm, title: e.target.value })}
                placeholder="عنوان اقدام اجرایی..."
                className="h-9.5 text-xs rounded-xl"
              />
            </div>

            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">شرح تفصیلی اقدام</label>
              <Textarea
                rows={2}
                value={actionForm.description}
                onChange={(e) => setActionForm({ ...actionForm, description: e.target.value })}
                placeholder="شرح کارهایی که باید انجام شود..."
                className="text-xs rounded-xl"
              />
            </div>

            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">مهلت انجام</label>
              <MultiCalendarDatePicker
                value={actionForm.due_date}
                onChange={(iso) => setActionForm({ ...actionForm, due_date: iso })}
                placeholder="انتخاب تاریخ مهلت..."
                className="h-9.5 text-xs rounded-xl"
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:justify-start">
            <Button
              disabled={busy}
              onClick={handleCreateActionItem}
              className="h-8.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white"
            >
              ایجاد اقدام
            </Button>
            <Button
              variant="outline"
              onClick={closeDialog}
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
