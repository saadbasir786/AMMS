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
  Building2
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import type { EmployeeBiometricData } from '../types'

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
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(`/employees/${id}/biometrics`)
      setData(apiData(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات بایومتریک کارمند با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const employee = data?.employee
  const templates = data?.templates || []
  const statusCfg = STATUS_CONFIG[data?.biometric_status || 'NOT_ENROLLED'] || {
    label: data?.biometric_status || '—',
    color: 'text-slate-600 bg-slate-100',
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
                      <th className="py-3.5 px-4 font-bold">تاریخ ثبت در سامانه</th>
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
        </div>
      )}
    </div>
  )
}
