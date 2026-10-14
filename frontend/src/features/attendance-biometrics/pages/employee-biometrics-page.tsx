import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Fingerprint,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  Building2,
  Loader2
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import type { BiometricEnrollmentContext, EmployeeBiometricData } from '../types'

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  ENROLLED: {
    label: 'ثبت کامل و معتبر',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200',
  },
  INCOMPLETE: {
    label: 'ثبت ناقص',
    color: 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200',
  },
  NOT_ENROLLED: {
    label: 'ثبت‌نشده',
    color: 'text-rose-700 bg-rose-50 border-rose-200',
  },
}

export function EmployeeBiometricsPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [data, setData] = useState<EmployeeBiometricData | null>(null)
  const [templatesPage, setTemplatesPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [enrollmentContext, setEnrollmentContext] = useState<BiometricEnrollmentContext | null>(null)
  const [selectedTerminalId, setSelectedTerminalId] = useState('')
  const [enrollmentBusy, setEnrollmentBusy] = useState(false)
  const [enrollmentError, setEnrollmentError] = useState<string | null>(null)
  const [enrollmentNotice, setEnrollmentNotice] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [res, contextRes] = await Promise.all([
        api.get(`/employees/${id}/biometrics`, { params: { page: templatesPage, per_page: 25 } }),
        api.get(`/employees/${id}/biometric-enrollment-context`).catch(() => null),
      ])
      setData(apiData(res))
      const nextContext = contextRes ? (apiData(contextRes) as BiometricEnrollmentContext) : null
      setEnrollmentContext(nextContext)
      setSelectedTerminalId((current) => {
        if (nextContext?.terminals.some((terminal) => String(terminal.id) === current)) return current
        return nextContext?.terminals[0] ? String(nextContext.terminals[0].id) : ''
      })
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات بایومتریک کارمند با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [id, templatesPage])

  useEffect(() => {
    loadData()
  }, [loadData])

  const employee = data?.employee
  const templates = data?.templates || []
  const templatesMeta = data?.templates_meta
  const statusCfg = STATUS_CONFIG[data?.biometric_status || 'NOT_ENROLLED'] || {
    label: data?.biometric_status || '—',
    color: 'text-slate-600 bg-slate-100',
  }

  const startEnrollment = async () => {
    if (!id || !selectedTerminalId) return
    setEnrollmentBusy(true)
    setEnrollmentError(null)
    setEnrollmentNotice(null)
    try {
      await api.post('/biometric/enrollment-sessions', {
        employee_id: Number(id),
        terminal_id: Number(selectedTerminalId),
      })
      setEnrollmentNotice('نشست ثبت برای همین کارمند و ترمینال آغاز شد. اکنون در اپ ترمینال ثبت اثر انگشت را انجام دهید.')
      await loadData()
    } catch (err: any) {
      setEnrollmentError(err?.response?.data?.message || 'آغاز نشست ثبت بایومتریک انجام نشد؛ صلاحیت، ترمینال و نشست‌های فعال را بررسی کنید.')
    } finally {
      setEnrollmentBusy(false)
    }
  }

  const completeEnrollment = async () => {
    const enrollment = enrollmentContext?.enrollment
    if (!enrollment) return
    setEnrollmentBusy(true)
    setEnrollmentError(null)
    setEnrollmentNotice(null)
    try {
      await api.post(`/biometric/enrollment-sessions/${enrollment.uuid}/complete`)
      setEnrollmentNotice('نشست ثبت بایومتریک تکمیل شد.')
      await loadData()
    } catch (err: any) {
      setEnrollmentError(err?.response?.data?.message || 'تکمیل نشست انجام نشد؛ شمار انگشت‌های ثبت‌شده و صلاحیت خود را بررسی کنید.')
    } finally {
      setEnrollmentBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={employee?.employee_number || undefined}
        title={employee?.full_name ? `پروفایل بایومتریک: ${employee.full_name}` : 'وضعیت بایومتریک کارمند'}
        description={`ریاست: ${employee?.unit?.name || '—'} • بررسی متادیتای امن اثر انگشت‌های ثبت‌شده`}
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={() => navigate('/admin/biometrics')}
            className="text-xs font-semibold rounded-xl gap-1.5"
          >
            <ArrowRight className="size-3.5" />
            <span>بازگشت به رجیستری</span>
          </Button>
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
              <div key={i} className="h-20 bg-card border border-border/70 rounded-2xl animate-pulse" />
            ))}
      </div>

          <div className="h-64 bg-card border border-border/70 rounded-2xl animate-pulse" />
        </div>
      )}

      {/* Main Content */}
      {!loading && data && (
        <div className="space-y-6">
          {enrollmentContext && (
            <Card className="rounded-2xl border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center gap-2">
                  <Fingerprint className="size-4 text-primary" />
                  <h3 className="text-sm font-bold text-foreground">نشست ثبت اثر انگشت برای ترمینال</h3>
                </div>
                <p className="text-xs text-muted-foreground leading-6">
                  نشست را برای کارمند و ترمینال مشخص آغاز کنید؛ اپ ترمینال فقط در همین نشست قالب را می‌پذیرد.
                </p>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-4">
                {enrollmentError && (
                  <Alert variant="destructive" className="rounded-xl">
                    <AlertCircle className="size-4" />
                    <AlertDescription>{enrollmentError}</AlertDescription>
                  </Alert>
                )}
                {enrollmentNotice && (
                  <Alert className="rounded-xl border-emerald-200 bg-emerald-50 text-emerald-900">
                    <CheckCircle2 className="size-4" />
                    <AlertDescription>{enrollmentNotice}</AlertDescription>
                  </Alert>
                )}

                {enrollmentContext.enrollment ? (
                  <div className="flex flex-col gap-3 rounded-xl border border-border/70 bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1 text-sm">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{enrollmentContext.enrollment.status === 'IN_PROGRESS' ? 'در حال ثبت' : 'آماده ثبت'}</Badge>
                        <span className="font-semibold">{enrollmentContext.enrollment.terminal?.name || `ترمینال ${enrollmentContext.enrollment.terminal_id ?? 'تعیین‌نشده'}`}</span>
                      </div>
                      <p className="text-xs text-muted-foreground">
                        انگشت ثبت‌شده: {enrollmentContext.enrollment.active_finger_count} از {enrollmentContext.enrollment.required_finger_count}
                        {enrollmentContext.enrollment.expires_at ? ` · اعتبار تا ${formatSolarDateTime(enrollmentContext.enrollment.expires_at)}` : ''}
                      </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button variant="outline" size="sm" onClick={loadData} disabled={enrollmentBusy}>
                        <RotateCw className="size-3.5" /> تازه‌سازی وضعیت
                      </Button>
                      <Button
                        size="sm"
                        onClick={completeEnrollment}
                        disabled={enrollmentBusy || enrollmentContext.enrollment.active_finger_count < enrollmentContext.enrollment.required_finger_count}
                      >
                        {enrollmentBusy && <Loader2 className="size-3.5 animate-spin" />}
                        تکمیل نشست
                      </Button>
                    </div>
                  </div>
                ) : (
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label className="flex-1 space-y-1.5 text-xs font-medium">
                      <span>ترمینال ثبت</span>
                      <select
                        value={selectedTerminalId}
                        onChange={(event) => setSelectedTerminalId(event.target.value)}
                        disabled={enrollmentBusy || enrollmentContext.terminals.length === 0}
                        className="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      >
                        <option value="">انتخاب ترمینال فعال</option>
                        {enrollmentContext.terminals.map((terminal) => (
                          <option key={terminal.id} value={terminal.id}>
                            {terminal.name} · {terminal.code}{terminal.model ? ` · ${terminal.model}` : ''}
                          </option>
                        ))}
                      </select>
                    </label>
                    <Button onClick={startEnrollment} disabled={enrollmentBusy || !selectedTerminalId}>
                      {enrollmentBusy ? <Loader2 className="size-4 animate-spin" /> : <Fingerprint className="size-4" />}
                      آغاز نشست ثبت
                    </Button>
                    {enrollmentContext.terminals.length === 0 && (
                      <p className="text-xs text-amber-700">در ادارهٔ این کارمند ترمینال فعال ثبت نشده است.</p>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* KPI Cards */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
              <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                <ShieldCheck className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">وضعیت ثبت</span>
                <strong className="text-xs font-bold text-foreground block mt-0.5">
                  {statusCfg.label}
                </strong>
              </div>
            </div>

            <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
              <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
                <Fingerprint className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">انگشت‌های فعال</span>
                <strong className="text-base font-bold text-foreground font-mono">
                  {data.active_finger_count} از {data.required_active_fingers}
                </strong>
              </div>
            </div>

            <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
              <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
                <Building2 className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">ریاست / واحد</span>
                <strong className="text-xs font-bold text-foreground block mt-0.5 truncate">
                  {employee?.unit?.name || '—'}
                </strong>
              </div>
            </div>

            <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
              <div className="size-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
                <CheckCircle2 className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">تعداد نسخه‌های ثبت‌شده</span>
                <strong className="text-base font-bold text-foreground font-mono">
                  {templates.length} نسخه
                </strong>
              </div>
            </div>
          </div>

          {/* Templates Table */}
          <Card className="border border-border/70 shadow-xs" dir="rtl">
            <CardHeader className="p-4 sm:p-5 border-b border-border/60">
              <h3 className="text-xs sm:text-sm font-bold text-foreground">
                نسخه‌های متادیتای اثر انگشت ثبت‌شده
              </h3>
              <p className="text-[11px] text-muted-foreground">
                شناسه، موقعیت انگشت، کیفیت اسکن و تاریخ ثبت در پایگاه داده امن
              </p>
            </CardHeader>

            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-right border-collapse">
                  <thead>
                    <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                      <th className="py-3.5 px-4 font-bold">موقعیت انگشت</th>
                      <th className="py-3.5 px-4 font-bold">شماره نسخه</th>
                      <th className="py-3.5 px-4 font-bold">وضعیت نسخه</th>
                      <th className="py-3.5 px-4 font-bold">امتیاز کیفیت اسکن</th>
                      <th className="py-3.5 px-4 font-bold">تاریخ ثبت در سیستم</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border/50">
                    {templates.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="py-8 text-center text-xs text-muted-foreground">
                          هیچ نسخه بایومتریکی برای این کارمند ثبت نشده است.
                        </td>
                      </tr>
                    ) : (
                      templates.map((tpl) => (
                        <tr key={tpl.uuid} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30">
                          {/* Position */}
                          <td className="py-3.5 px-4">
                            <div className="flex items-center gap-2">
                              <Fingerprint className="size-3.5 text-primary" />
                              <strong className="font-semibold text-foreground">
                                {tpl.finger_position}
                              </strong>
                            </div>
                          </td>

                          {/* Version */}
                          <td className="py-3.5 px-4 font-mono">
                            <Badge variant="outline" className="text-[10px] px-2 py-0.5">
                              نسخه {tpl.version_number}
                            </Badge>
                          </td>

                          {/* Status */}
                          <td className="py-3.5 px-4">
                            <Badge
                              variant={tpl.status === 'ACTIVE' ? 'default' : 'secondary'}
                              className="text-[10px]"
                            >
                              {tpl.status === 'ACTIVE' ? 'فعال' : tpl.status}
                            </Badge>
                          </td>

                          {/* Quality Score */}
                          <td className="py-3.5 px-4 font-mono font-bold text-foreground">
                            {tpl.quality_score ? (
                              <span className="text-emerald-600">{tpl.quality_score}٪</span>
                            ) : (
                              '—'
                            )}
                          </td>

                          {/* Enrolled At */}
                          <td className="py-3.5 px-4 whitespace-nowrap text-muted-foreground">
                            {tpl.enrolled_at ? formatSolarDateTime(tpl.enrolled_at) : '—'}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>

          {templatesMeta && templatesMeta.last_page > 1 && (
            <div className="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-card px-4 py-3 text-xs" dir="rtl">
              <Button variant="outline" size="sm" disabled={templatesMeta.current_page <= 1} onClick={() => setTemplatesPage((page) => page - 1)}>قبلی</Button>
              <span>صفحهٔ {templatesMeta.current_page} از {templatesMeta.last_page} · {templatesMeta.total} template</span>
              <Button variant="outline" size="sm" disabled={templatesMeta.current_page >= templatesMeta.last_page} onClick={() => setTemplatesPage((page) => page + 1)}>بعدی</Button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
