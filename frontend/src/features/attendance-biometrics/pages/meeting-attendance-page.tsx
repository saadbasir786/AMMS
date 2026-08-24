import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Fingerprint,
  UserRound,
  ShieldCheck,
  CalendarCheck,
  Monitor
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { AttendanceRecordsTable } from '../components/attendance-records-table'
import { ManualAttendanceCard } from '../components/manual-attendance-card'
import type { MeetingAttendanceData } from '../types'

export function MeetingAttendancePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [data, setData] = useState<MeetingAttendanceData | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(`/meetings/${id}/attendance`)
      setData(apiData(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات حضور و غیاب جلسه با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleManualAttendance = async (employeeId: number, reason: string) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/attendance/manual`, {
        employee_id: employeeId,
        reason,
      })
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت دستی حضور با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const records = data?.records || []
  const meeting = data?.meeting

  const totalCount = records.length
  const biometricCount = records.filter((r) => r.attendance_method === 'FINGERPRINT').length
  const manualCount = records.filter((r) => r.attendance_method !== 'FINGERPRINT').length

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="حاضری و ثبت حضور اشتراک‌کنندگان جلسه"
        description={
          meeting
            ? `${meeting.title} • مشاهده وضعیت حضور بایومتریک و ثبت دستی مجاز`
            : 'مدیریت حضور و غیاب جلسه'
        }
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              asChild
              variant="outline"
              size="sm"
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <Link to={`/meetings/${id}/terminals`}>
                <Monitor className="size-3.5" />
                <span>ترمینال‌های جلسه</span>
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
              onClick={loadData}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <CalendarCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل حاضران ثبت‌شده</span>
            <strong className="text-base font-bold text-foreground font-mono">{totalCount}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Fingerprint className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">ثبت بایومتریک (اثر انگشت)</span>
            <strong className="text-base font-bold text-foreground font-mono text-emerald-600">
              {biometricCount}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-slate-500/10 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-xs shrink-0">
            <UserRound className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">ثبت دستی مجاز</span>
            <strong className="text-base font-bold text-foreground font-mono">{manualCount}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <ShieldCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">نسبت بایومتریک</span>
            <strong className="text-base font-bold text-foreground font-mono">
              {totalCount > 0 ? Math.round((biometricCount / totalCount) * 100) : 0}٪
            </strong>
          </div>
        </div>
      </div>

      {/* Manual Attendance Card */}
      <ManualAttendanceCard
        busy={busy}
        onSubmitManual={handleManualAttendance}
      />

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/3" />
          <div className="space-y-3">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-12 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Attendance Records Table */}
      {!loading && (
        <div className="space-y-3">
          <h3 className="text-sm font-bold text-foreground">
            فهرست رکوردهای رسمی حضور در جلسه
          </h3>
          <AttendanceRecordsTable records={records} />
        </div>
      )}
    </div>
  )
}
