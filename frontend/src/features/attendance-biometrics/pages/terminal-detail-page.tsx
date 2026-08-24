import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Monitor,
  KeyRound,
  ShieldAlert,
  CalendarDays,
  Clock,
  Ban
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import type { Terminal } from '../types'

export function TerminalDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [terminal, setTerminal] = useState<Terminal | null>(null)
  const [issuedCredential, setIssuedCredential] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Security controls state
  const [revokeOpen, setRevokeOpen] = useState(false)
  const [revokeReason, setRevokeReason] = useState('')

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(`/terminals/${id}`)
      setTerminal(apiData(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری مشخصات ترمینال با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleRotateCredentials = async () => {
    if (!id) return
    setBusy(true)
    setError(null)
    setIssuedCredential(null)
    try {
      const res = await api.post(`/terminals/${id}/credentials/rotate`)
      setIssuedCredential(apiData(res)?.credential?.token || null)
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'تعویض اعتبارنامه ترمینال انجام نشد.')
    } finally {
      setBusy(false)
    }
  }

  const handleRevokeTerminal = async () => {
    if (!id) return
    if (!revokeReason.trim()) {
      setError('ثبت دلیل لغو ترمینال الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.post(`/terminals/${id}/revoke`, { reason: revokeReason })
      setRevokeReason('')
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'لغو ترمینال انجام نشد.')
    } finally {
      setBusy(false)
    }
  }

  const assignments = terminal?.assignments || []

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={terminal?.code || undefined}
        title={terminal?.name || 'مشخصات دستگاه ترمینال'}
        description={
          terminal
            ? `${terminal.manufacturer || 'DERMALOG'} • مدل ${terminal.model || 'SVT3000'} • سریال: ${
                terminal.serial_number || 'بدون سریال'
              }`
            : 'مدیریت و کنترل امنیتی ترمینال'
        }
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={busy || terminal?.status === 'REVOKED'}
              onClick={handleRotateCredentials}
              className="text-xs font-semibold rounded-xl gap-1.5 text-indigo-700 dark:text-indigo-300 border-indigo-200"
            >
              <KeyRound className="size-3.5" />
              <span>تعویض اعتبارنامه (Rotate)</span>
            </Button>

            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate('/admin/terminals')}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به فهرست</span>
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

      {/* Single-use Credential Alert */}
      {issuedCredential && (
        <Alert className="rounded-2xl p-4 bg-indigo-50/50 dark:bg-indigo-950/30 border-indigo-200 dark:border-indigo-900">
          <KeyRound className="size-4 text-indigo-600" />
          <AlertDescription className="text-xs space-y-2">
            <strong className="block font-bold text-foreground">
              اعتبارنامه امن جدید صادر شد (فقط یک‌بار نمایش داده می‌شود):
            </strong>
            <code
              dir="ltr"
              className="block overflow-x-auto rounded-xl border bg-card p-3 font-mono text-xs font-bold text-primary select-all"
            >
              {issuedCredential}
            </code>
            <Button
              size="sm"
              variant="outline"
              onClick={() => setIssuedCredential(null)}
              className="h-8 text-xs font-semibold"
            >
              بستن و تأیید دریافت
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
          <div className="space-y-6">
            <div className="h-52 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
        </div>
      )}

      {/* Content */}
      {!loading && terminal && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            {/* Terminal Specs Card */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="text-[11px] font-semibold text-muted-foreground block font-mono">
                      UUID: {terminal.uuid}
                    </span>
                    <h3 className="text-sm font-bold text-foreground">
                      مشخصات سخت‌افزاری و ارتباطی دستگاه
                    </h3>
                  </div>
                  <Badge variant={terminal.status === 'ACTIVE' ? 'default' : 'outline'} className="text-xs">
                    {terminal.status}
                  </Badge>
                </div>
              </CardHeader>

              <CardContent className="p-4 sm:p-5">
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">پلتفرم سخت‌افزاری</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5">
                      {terminal.platform || '—'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">نسخه سیستم‌عامل (OS)</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      {terminal.os_version || '—'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">آخرین ارتباط با سرور</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      {terminal.last_seen_at ? formatSolarDateTime(terminal.last_seen_at) : '—'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">شماره سریال</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      {terminal.serial_number || '—'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">جلسه فعال جاری</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      {terminal.current_session_id || 'ندارد'}
                    </strong>
                  </div>

                  <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
                    <span className="text-[10px] text-muted-foreground block">تعداد جلسات تخصیص‌یافته</span>
                    <strong className="text-xs font-bold text-foreground block mt-0.5 font-mono">
                      {assignments.length} جلسه
                    </strong>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Assignments History */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <h3 className="text-xs sm:text-sm font-bold text-foreground">
                  تاریخچه تخصیص به جلسات رسمی
                </h3>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-2.5">
                {assignments.length === 0 ? (
                  <p className="text-xs text-muted-foreground text-center py-4">
                    این دستگاه هنوز به جلسه‌ای تخصیص نیافته است.
                  </p>
                ) : (
                  assignments.map((a) => (
                    <div
                      key={a.id}
                      className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                    >
                      <div>
                        <strong className="font-bold text-foreground block">
                          {a.meeting?.title || `جلسه #${a.meeting_id}`}
                        </strong>
                        <span className="text-[10px] text-muted-foreground block mt-0.5">
                          از {a.valid_from ? formatSolarDateTime(a.valid_from) : '—'} تا{' '}
                          {a.valid_until ? formatSolarDateTime(a.valid_until) : '—'}
                        </span>
                      </div>
                      <Badge variant="outline" className="text-[10px]">
                        {a.status}
                      </Badge>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            {/* Security Controls */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <div className="flex items-center gap-2">
                  <div className="size-7 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                    <ShieldAlert className="size-4" />
                  </div>
                  <div>
                    <h3 className="text-xs sm:text-sm font-bold text-foreground">
                      کنترل‌های امنیتی دستگاه
                    </h3>
                    <p className="text-[11px] text-muted-foreground">
                      ابطال دائمی مجوز و لغو ترمینال
                    </p>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="p-4 sm:p-5 space-y-3">
                <Textarea
                  rows={3}
                  value={revokeReason}
                  onChange={(e) => setRevokeReason(e.target.value)}
                  placeholder="دلیل لغو رسمی و ابطال دستگاه..."
                  className="text-xs rounded-xl"
                  disabled={busy || terminal.status === 'REVOKED'}
                />

                <Button
                  className="w-full h-9.5 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs"
                  variant="destructive"
                  disabled={busy || terminal.status === 'REVOKED' || !revokeReason.trim()}
                  onClick={() => setRevokeOpen(true)}
                >
                  <Ban className="size-3.5" />
                  <span>ابطال و لغو ترمینال</span>
                </Button>
              </CardContent>
            </Card>
          </aside>
        </div>
      )}

      {/* Confirm Revoke Dialog */}
      <ConfirmDialog
        open={revokeOpen}
        onOpenChange={setRevokeOpen}
        title="لغو رسمی و ابطال دستگاه انجام شود؟"
        description="با لغو ترمینال، اعتبارنامه سخت‌افزار منقضی شده و دیگر امکان اتصال به سرور و ثبت حضور را نخواهد داشت."
        confirmLabel="ابطال و لغو دستگاه"
        variant="destructive"
        onConfirm={handleRevokeTerminal}
      />
    </div>
  )
}
