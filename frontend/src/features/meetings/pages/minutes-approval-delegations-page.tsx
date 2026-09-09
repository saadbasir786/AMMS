import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  FileCheck,
  CheckCircle2,
  Users,
  AlertCircle,
  Plus,
  RotateCw,
  Search,
  Building2,
  Calendar,
  XCircle,
  Check,
  UserCheck
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { StatusBadge } from '@/components/amms/status-badge'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
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

type Unit = { id: number; name: string }
type Delegate = { id: number; name: string; email?: string; employee?: { full_name: string } | null }
type Delegation = {
  id: number
  unit?: Unit
  user?: Delegate
  expires_at?: string | null
  grant_reason?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
  active: boolean
}

type MinutesApprovalDelegationsPageProps = {
  embedded?: boolean
}

export function MinutesApprovalDelegationsPage({ embedded = false }: MinutesApprovalDelegationsPageProps = {}) {
  const [delegations, setDelegations] = useState<Delegation[]>([])
  const [delegates, setDelegates] = useState<Delegate[]>([])
  const [units, setUnits] = useState<Unit[]>([])

  // Modal & Form
  const [dialogOpen, setDialogOpen] = useState(false)
  const [unitId, setUnitId] = useState('')
  const [delegateUserId, setDelegateUserId] = useState('')
  const [expiresAt, setExpiresAt] = useState('')
  const [reason, setReason] = useState('')

  // Revocation
  const [revokeTarget, setRevokeTarget] = useState<Delegation | null>(null)
  const [revocationReason, setRevocationReason] = useState('')

  // State
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Filters
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<'ALL' | 'ACTIVE' | 'EXPIRED' | 'REVOKED'>('ALL')
  const [delegationsPage, setDelegationsPage] = useState(1)
  const [delegationsMeta, setDelegationsMeta] = useState<any>(null)
  const [delegationsStats, setDelegationsStats] = useState<any>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = apiData(await api.get('/minutes-approval-delegations', {
        params: {
          page: delegationsPage,
          per_page: 15,
          search: search.trim() || undefined,
          status: statusFilter === 'ALL' ? undefined : statusFilter,
        },
      }))
      setDelegations(data?.delegations || [])
      setDelegationsMeta(data?.delegations_meta || null)
      setDelegationsStats(data?.stats || null)
      setDelegates(data?.delegates || [])
      setUnits(data?.units || [])
      setUnitId((current) => current || String(data?.units?.[0]?.id || ''))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات تفویض‌های صورت‌جلسه با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [delegationsPage, search, statusFilter])

  useEffect(() => {
    loadData()
  }, [loadData])

  useEffect(() => {
    setDelegateUserId('')
  }, [unitId])

  useEffect(() => {
    setDelegationsPage(1)
  }, [search, statusFilter])

  // KPIs
  const fallbackActiveCount = useMemo(
    () => delegations.filter((d) => d.active).length,
    [delegations]
  )
  const fallbackRevokedCount = useMemo(
    () => delegations.filter((d) => !!d.revoked_at).length,
    [delegations]
  )
  const fallbackExpiredCount = useMemo(
    () => delegations.filter((d) => !d.active && !d.revoked_at).length,
    [delegations]
  )
  const activeCount = delegationsStats?.active ?? fallbackActiveCount
  const revokedCount = delegationsStats?.revoked ?? fallbackRevokedCount
  const expiredCount = delegationsStats?.expired ?? fallbackExpiredCount

  // Filtered delegations
  const filteredDelegations = useMemo(() => {
    if (delegationsStats) return delegations
    return delegations.filter((d) => {
      const q = search.trim().toLowerCase()
      const matchSearch =
        !q ||
        [
          d.user?.employee?.full_name,
          d.user?.name,
          d.user?.email,
          d.unit?.name,
          d.grant_reason,
        ]
          .filter(Boolean)
          .some((val) => val!.toLowerCase().includes(q))

      let matchStatus = true
      if (statusFilter === 'ACTIVE') matchStatus = d.active
      else if (statusFilter === 'REVOKED') matchStatus = !!d.revoked_at
      else if (statusFilter === 'EXPIRED') matchStatus = !d.active && !d.revoked_at

      return matchSearch && matchStatus
    })
  }, [delegations, delegationsStats, search, statusFilter])

  const openCreateDialog = () => {
    setDelegateUserId('')
    setExpiresAt('')
    setReason('')
    setDialogOpen(true)
  }

  const grant = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!unitId || !delegateUserId || !expiresAt || !reason.trim()) {
      setError('انتخاب اداره، معاونت مربوطه، تاریخ پایان و استناد اداری الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.post('/minutes-approval-delegations', {
        organizational_unit_id: Number(unitId),
        delegate_user_id: Number(delegateUserId),
        expires_at: expiresAt,
        reason: reason.trim(),
      })
      setDialogOpen(false)
      setDelegateUserId('')
      setExpiresAt('')
      setReason('')
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت تفویض صلاحیت با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const revoke = async () => {
    if (!revokeTarget || !revocationReason.trim()) return
    setBusy(true)
    setError(null)
    try {
      await api.delete(`/minutes-approval-delegations/${revokeTarget.id}`, {
        data: { reason: revocationReason.trim() },
      })
      setRevokeTarget(null)
      setRevocationReason('')
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'لغو تفویض صلاحیت با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      {!embedded ? (
        <PageHeader
          title="تفویض بررسی صورت‌جلسات رسمی"
          description="تفویض محدود، زمان‌دار و قابل لغو صلاحیت بررسی، اعلام نظر یا درخواست اصلاح صورت‌جلسه به معاونت همان ریاست"
          action={
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={loadData}
                disabled={loading}
                className="h-9 text-xs font-semibold rounded-xl gap-1.5"
              >
                <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
                <span>تازه‌سازی</span>
              </Button>
              <Button
                size="sm"
                onClick={openCreateDialog}
                className="h-9 text-xs font-semibold rounded-xl gap-1.5 shadow-xs"
              >
                <Plus className="size-3.5" />
                <span>تفویض جدید به معاونت</span>
              </Button>
            </div>
          }
        />
      ) : (
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs">
          <div>
            <h3 className="text-sm font-bold text-foreground flex items-center gap-2">
              <FileCheck className="size-4 text-primary" />
              <span>تفویض بررسی صورت‌جلسات رسمی</span>
            </h3>
            <p className="text-xs text-muted-foreground mt-0.5">
              تفویض بررسی و اعلام نظر رسمی روی صورت‌جلسات ارسالی به معاونت متبوع
            </p>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              disabled={loading}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5"
            >
              <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
              <span>تازه‌سازی</span>
            </Button>
            <Button
              size="sm"
              onClick={openCreateDialog}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 shadow-xs"
            >
              <Plus className="size-3.5" />
              <span>تفویض جدید به معاونت</span>
            </Button>
          </div>
        </div>
      )}

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
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <FileCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              کل سوابق تفویض صورت‌جلسه
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {delegationsStats?.total ?? delegations.length} مورد
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تفویض‌های فعال و دارای اعتبار
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {activeCount} مورد
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
            <Users className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              معاونان واجد صلاحیت اداری
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {delegates.length} نفر
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-slate-500/10 text-slate-600 dark:text-slate-400 flex items-center justify-center shrink-0">
            <AlertCircle className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              لغوشده / منقضی
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {revokedCount + expiredCount} مورد
            </strong>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div className="relative flex-1 max-w-md">
          <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="جستجو در نام معاونت، اداره یا استناد تفویض..."
            className="h-9.5 pr-8 text-xs rounded-xl"
          />
        </div>

        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
          {(
            [
              { id: 'ALL', label: 'همه موارد' },
              { id: 'ACTIVE', label: 'فعال و جاری' },
              { id: 'EXPIRED', label: 'منقضی‌شده' },
              { id: 'REVOKED', label: 'لغوشده' },
            ] as const
          ).map((tab) => (
            <Button
              key={tab.id}
              variant={statusFilter === tab.id ? 'default' : 'outline'}
              size="sm"
              onClick={() => setStatusFilter(tab.id)}
              className="h-8 text-xs rounded-xl font-medium px-3"
            >
              {tab.label}
            </Button>
          ))}
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-3.5 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/4" />
          <div className="space-y-2.5">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-14 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Delegations Table */}
      {!loading && (
        <div className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs">
          {filteredDelegations.length === 0 ? (
            <div className="text-center py-12 px-4">
              <FileCheck className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
              <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
                تفویض صلاحیتی یافت نشد
              </h4>
              <p className="text-[11px] text-muted-foreground">
                هیچ تفویض صلاحیتی برای بررسی صورت‌جلسه مطابق با این فیلتر ثبت نگردیده است.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-right border-collapse">
                <thead>
                  <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                    <th className="py-3.5 px-4 font-bold">معاون ریاست (دارنده صلاحیت)</th>
                    <th className="py-3.5 px-4 font-bold">ریاست / اداره مربوطه</th>
                    <th className="py-3.5 px-4 font-bold">مهلت اعتبار</th>
                    <th className="py-3.5 px-4 font-bold">دلیل و استناد تفویض</th>
                    <th className="py-3.5 px-4 font-bold">وضعیت</th>
                    <th className="py-3.5 px-4 font-bold text-center">اقدامات اداری</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/50">
                  {filteredDelegations.map((del) => {
                    const statusKey = del.active
                      ? 'ACTIVE'
                      : del.revoked_at
                      ? 'REVOKED'
                      : 'EXPIRED'
                    return (
                      <tr
                        key={del.id}
                        className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-3.5 px-4 whitespace-nowrap">
                          <div className="flex items-center gap-2.5">
                            <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                              <UserCheck className="size-4" />
                            </div>
                            <div>
                              <strong className="text-xs font-semibold text-foreground block">
                                {del.user?.employee?.full_name ||
                                  del.user?.name ||
                                  'معاون ریاست'}
                              </strong>
                              {del.user?.email && (
                                <span className="text-[10px] text-muted-foreground font-mono block">
                                  {del.user.email}
                                </span>
                              )}
                            </div>
                          </div>
                        </td>

                        <td className="py-3.5 px-4 text-muted-foreground">
                          <div className="flex items-center gap-1.5">
                            <Building2 className="size-3.5 text-muted-foreground/60 shrink-0" />
                            <span className="font-medium text-foreground">
                              {del.unit?.name || 'واحد نامشخص'}
                            </span>
                          </div>
                        </td>

                        <td className="py-3.5 px-4 whitespace-nowrap">
                          <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Calendar className="size-3.5 text-muted-foreground/60 shrink-0" />
                            <span>
                              {del.expires_at
                                ? formatSolarDateTime(del.expires_at)
                                : 'نامحدود'}
                            </span>
                          </div>
                        </td>

                        <td className="py-3.5 px-4 max-w-xs">
                          <p className="truncate text-foreground text-xs leading-relaxed">
                            {del.grant_reason || 'بدون درج استناد'}
                          </p>
                          {del.revocation_reason && (
                            <span className="text-[10px] text-destructive block truncate mt-0.5">
                              علت لغو: {del.revocation_reason}
                            </span>
                          )}
                        </td>

                        <td className="py-3.5 px-4 whitespace-nowrap">
                          <StatusBadge status={statusKey} />
                        </td>

                        <td className="py-3.5 px-4 text-center whitespace-nowrap">
                          {del.active ? (
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busy}
                              onClick={() => setRevokeTarget(del)}
                              className="h-8 rounded-xl text-xs gap-1.5 text-destructive hover:bg-destructive/10"
                            >
                              <XCircle className="size-3.5" />
                              <span>لغو تفویض</span>
                            </Button>
                          ) : (
                            <span className="text-[10px] text-muted-foreground italic">
                              غیرقابل تغییر
                            </span>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {!loading && delegationsMeta && (delegationsMeta.last_page ?? 1) > 1 && (
        <div className="flex items-center justify-center gap-3 text-xs">
          <Button
            variant="outline"
            size="sm"
            disabled={!delegationsMeta.current_page || delegationsMeta.current_page <= 1}
            onClick={() => setDelegationsPage((page) => Math.max(1, page - 1))}
            className="h-8 rounded-xl"
          >
            قبلی
          </Button>
          <span className="text-muted-foreground">
            صفحه {delegationsMeta.current_page} از {delegationsMeta.last_page}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={delegationsMeta.current_page >= delegationsMeta.last_page}
            onClick={() => setDelegationsPage((page) => page + 1)}
            className="h-8 rounded-xl"
          >
            بعدی
          </Button>
        </div>
      )}

      {/* Grant Delegation Dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-md rounded-2xl" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="text-sm font-bold flex items-center gap-2">
              <FileCheck className="size-4 text-primary" />
              <span>تفویض بررسی صورت‌جلسه به معاونت</span>
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              صلاحیت بررسی، تأیید یا درخواست اصلاح صورت‌جلسه به معاون منصوب همان اداره تفویض می‌گردد.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={grant} className="space-y-4 pt-1">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                ریاست / اداره مربوطه <span className="text-destructive">*</span>
              </label>
              <select
                required
                value={unitId}
                onChange={(e) => setUnitId(e.target.value)}
                className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
              >
                {units.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                معاونت واجد صلاحیت <span className="text-destructive">*</span>
              </label>
              <select
                required
                value={delegateUserId}
                onChange={(e) => setDelegateUserId(e.target.value)}
                className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
              >
                <option value="">انتخاب معاون اداره...</option>
                {delegates.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.employee?.full_name || d.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                تاریخ پایان اعتبار تفویض <span className="text-destructive">*</span>
              </label>
              <MultiCalendarDatePicker
                value={expiresAt}
                onChange={setExpiresAt}
                placeholder="انتخاب تاریخ انقضا..."
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                دلیل و استناد اداری تفویض <span className="text-destructive">*</span>
              </label>
              <Textarea
                required
                rows={3}
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="دلیل و محدوده اداری تفویض بررسی صورت‌جلسه را درج فرمایید..."
                className="rounded-xl text-xs leading-relaxed"
              />
            </div>

            <DialogFooter className="gap-2 pt-2 sm:justify-start">
              <Button
                type="submit"
                disabled={busy}
                className="h-9 text-xs font-semibold rounded-xl gap-1.5 flex-1"
              >
                {busy ? (
                  <RotateCw className="size-3.5 animate-spin" />
                ) : (
                  <Check className="size-3.5" />
                )}
                <span>ثبت و ابلاغ تفویض صلاحیت</span>
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={busy}
                onClick={() => setDialogOpen(false)}
                className="h-9 text-xs rounded-xl"
              >
                انصراف
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Revocation Confirm Dialog */}
      <ConfirmDialog
        open={!!revokeTarget}
        onOpenChange={(open) => {
          if (!open) setRevokeTarget(null)
        }}
        title="لغو تفویض صلاحیت بررسی صورت‌جلسه"
        description="پس از لغو، معاونت دیگر مجاز به تأیید یا درخواست اصلاح صورت‌جلسه با این تفویض نخواهد بود."
        confirmLabel="لغو قطعی تفویض"
        variant="destructive"
        onConfirm={revoke}
      >
        <div className="space-y-1.5 pt-2" dir="rtl">
          <label className="text-xs font-bold text-foreground block">
            دلیل اداری لغو تفویض <span className="text-destructive">*</span>
          </label>
          <Input
            required
            value={revocationReason}
            onChange={(e) => setRevocationReason(e.target.value)}
            placeholder="دلیل لغو تفویض را درج فرمایید..."
            className="h-9.5 rounded-xl text-xs"
          />
        </div>
      </ConfirmDialog>
    </div>
  )
}
