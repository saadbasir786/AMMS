import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  ShieldCheck,
  FileCheck,
  Plus,
  RotateCw,
  Search,
  Building2,
  Calendar,
  AlertCircle,
  CheckCircle2,
  Users,
  XCircle,
  Clock,
  Shield,
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'
import {
  UnifiedDelegationDialog,
  type DelegationCapabilityType,
} from '../components/unified-delegation-dialog'

export type UnifiedDelegation = {
  id: number
  capability: 'INTERNAL' | 'MINUTES'
  organizational_unit_id?: number
  unit?: { id: number; name: string; code?: string }
  user?: {
    id: number
    name: string
    email?: string
    role_title?: string
    roles?: string[]
    employee?: {
      full_name: string
      organizational_unit_id?: number
      unit?: { id: number; name: string } | null
    } | null
  }
  granted_at?: string | null
  expires_at?: string | null
  grant_reason?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
  active: boolean
  granted_by_user?: { id: number; name: string }
  revoked_by_user?: { id: number; name: string }
}

type DelegationsPageProps = {
  initialTab?: 'internal' | 'minutes'
}

export function DelegationsPage({ initialTab }: DelegationsPageProps = {}) {
  const [delegations, setDelegations] = useState<UnifiedDelegation[]>([])
  const [internalStats, setInternalStats] = useState<any>(null)
  const [minutesStats, setMinutesStats] = useState<any>(null)

  // Modals & Forms
  const [unifiedDialogOpen, setUnifiedDialogOpen] = useState(false)
  const [initialCapability, setInitialCapability] = useState<DelegationCapabilityType>(
    initialTab === 'minutes' ? 'MINUTES' : 'INTERNAL'
  )

  // Revocation
  const [revokeTarget, setRevokeTarget] = useState<UnifiedDelegation | null>(null)
  const [revocationReason, setRevocationReason] = useState('')

  // State
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Filters
  const [search, setSearch] = useState('')
  const [capabilityFilter, setCapabilityFilter] = useState<'ALL' | 'INTERNAL' | 'MINUTES'>('ALL')
  const [statusFilter, setStatusFilter] = useState<'ALL' | 'ACTIVE' | 'EXPIRED' | 'REVOKED'>('ALL')

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [internalRes, minutesRes] = await Promise.allSettled([
        api.get('/internal-meeting-approval-delegations', { params: { per_page: 50 } }),
        api.get('/minutes-approval-delegations', { params: { per_page: 50 } }),
      ])

      const list: UnifiedDelegation[] = []

      if (internalRes.status === 'fulfilled') {
        const d = apiData(internalRes.value)
        setInternalStats(d?.stats || null)
        const items = (d?.delegations || []).map((item: any) => ({
          ...item,
          capability: 'INTERNAL' as const,
        }))
        list.push(...items)
      }

      if (minutesRes.status === 'fulfilled') {
        const d = apiData(minutesRes.value)
        setMinutesStats(d?.stats || null)
        const items = (d?.delegations || []).map((item: any) => ({
          ...item,
          capability: 'MINUTES' as const,
        }))
        list.push(...items)
      }

      setDelegations(list)
    } catch (err: any) {
      setError(
        err?.response?.data?.message || 'بارگذاری سوابق تفویض صلاحیت‌ها با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  // Filtered & Sorted Delegations
  const filteredDelegations = useMemo(() => {
    return delegations.filter((d) => {
      // 1. Filter by capability
      if (capabilityFilter !== 'ALL' && d.capability !== capabilityFilter) {
        return false
      }

      // 2. Filter by status
      if (statusFilter === 'ACTIVE' && !d.active) return false
      if (statusFilter === 'REVOKED' && !d.revoked_at) return false
      if (statusFilter === 'EXPIRED' && (d.active || d.revoked_at)) return false

      // 3. Search query
      const q = search.trim().toLowerCase()
      if (!q) return true

      return [
        d.user?.employee?.full_name,
        d.user?.name,
        d.user?.email,
        d.user?.role_title,
        d.unit?.name,
        d.grant_reason,
        d.revocation_reason,
      ]
        .filter(Boolean)
        .some((val) => val!.toLowerCase().includes(q))
    })
  }, [delegations, capabilityFilter, statusFilter, search])

  const sortedDelegations = useMemo(() => {
    return [...filteredDelegations].sort((a, b) => {
      if (a.active !== b.active) return a.active ? -1 : 1
      return (b.id || 0) - (a.id || 0)
    })
  }, [filteredDelegations])

  // Aggregate Stats
  const totalCount =
    (internalStats?.total ?? 0) + (minutesStats?.total ?? 0) || delegations.length
  const activeCount =
    (internalStats?.active ?? 0) + (minutesStats?.active ?? 0) ||
    delegations.filter((d) => d.active).length
  const internalTotal =
    internalStats?.total ?? delegations.filter((d) => d.capability === 'INTERNAL').length
  const minutesTotal =
    minutesStats?.total ?? delegations.filter((d) => d.capability === 'MINUTES').length

  const handleRevoke = async () => {
    if (!revokeTarget || !revocationReason.trim()) return
    setBusy(true)
    setError(null)
    try {
      const endpoint =
        revokeTarget.capability === 'INTERNAL'
          ? `/internal-meeting-approval-delegations/${revokeTarget.id}`
          : `/minutes-approval-delegations/${revokeTarget.id}`
      await api.delete(endpoint, {
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
      {/* Central Unified Header */}
      <PageHeader
        title="مدیریت تفویض صلاحیت‌ها"
        description="مرکز یکپارچه تفویض اختیارات سازمانی ریاست به معاونت اداره یا مدیریت اجرائیه (جلسات داخلی و صورت‌جلسات رسمی)"
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
              onClick={() => {
                setInitialCapability(
                  capabilityFilter === 'MINUTES' ? 'MINUTES' : 'INTERNAL'
                )
                setUnifiedDialogOpen(true)
              }}
              className="h-9 rounded-xl text-xs font-semibold gap-1.5 shadow-xs"
            >
              <Plus className="size-3.5" />
              <span>ثبت تفویض صلاحیت جدید</span>
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

      {/* Unified KPI Summary Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <ShieldCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              کل سوابق تفویض
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {totalCount} مورد
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تفویض‌های فعال و معتبر
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {activeCount} مورد
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center shrink-0">
            <Shield className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تفویض جلسات داخلی
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {internalTotal} مورد
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
            <FileCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تفویض بررسی صورت‌جلسات
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {minutesTotal} مورد
            </strong>
          </div>
        </div>
      </div>

      {/* Unified Filters Bar */}
      <div className="p-3 rounded-2xl bg-card border border-border/70 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          {/* Capability Type Filter */}
          <div className="flex items-center gap-1 p-1 rounded-xl bg-muted/70 border border-border/70 text-xs">
            <button
              type="button"
              onClick={() => setCapabilityFilter('ALL')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all',
                capabilityFilter === 'ALL'
                  ? 'bg-background text-foreground shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              همه صلاحیت‌ها
            </button>
            <button
              type="button"
              onClick={() => setCapabilityFilter('INTERNAL')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5',
                capabilityFilter === 'INTERNAL'
                  ? 'bg-background text-blue-600 dark:text-blue-400 shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              <ShieldCheck className="size-3.5" />
              <span>جلسات داخلی</span>
            </button>
            <button
              type="button"
              onClick={() => setCapabilityFilter('MINUTES')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5',
                capabilityFilter === 'MINUTES'
                  ? 'bg-background text-purple-600 dark:text-purple-400 shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              <FileCheck className="size-3.5" />
              <span>صورت‌جلسات</span>
            </button>
          </div>

          {/* Status Filter */}
          <div className="flex items-center gap-1 p-1 rounded-xl bg-muted/70 border border-border/70 text-xs">
            <button
              type="button"
              onClick={() => setStatusFilter('ALL')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all',
                statusFilter === 'ALL'
                  ? 'bg-background text-foreground shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              همه
            </button>
            <button
              type="button"
              onClick={() => setStatusFilter('ACTIVE')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all',
                statusFilter === 'ACTIVE'
                  ? 'bg-background text-emerald-600 shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              فعال و جاری
            </button>
            <button
              type="button"
              onClick={() => setStatusFilter('EXPIRED')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all',
                statusFilter === 'EXPIRED'
                  ? 'bg-background text-foreground shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              منقضی‌شده
            </button>
            <button
              type="button"
              onClick={() => setStatusFilter('REVOKED')}
              className={cn(
                'px-3 py-1.5 rounded-lg font-bold transition-all',
                statusFilter === 'REVOKED'
                  ? 'bg-background text-destructive shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              لغوشده
            </button>
          </div>
        </div>

        {/* Search */}
        <div className="relative min-w-[240px] md:w-80">
          <Search className="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="جستجو در نام، سِمت، ایمیل، اداره یا دلیل..."
            className="pr-9 h-9.5 rounded-xl text-xs bg-background border-border/70"
          />
        </div>
      </div>

      {/* Unified Delegations Table */}
      <div className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-muted-foreground text-xs animate-pulse">
            در حال بارگذاری اطلاعات تفویض‌های صلاحیت...
          </div>
        ) : sortedDelegations.length === 0 ? (
          <div className="p-16 text-center space-y-3">
            <div className="size-12 rounded-2xl bg-muted/70 flex items-center justify-center mx-auto text-muted-foreground">
              <ShieldCheck className="size-6 stroke-[1.5]" />
            </div>
            <h4 className="text-sm font-bold text-foreground">تفویض صلاحیتی یافت نشد</h4>
            <p className="text-xs text-muted-foreground max-w-sm mx-auto">
              هیچ تفویض صلاحیتی متناسب با فیلترها و جستجوی شما ثبت نگردیده است.
            </p>
            <Button
              onClick={() => {
                setInitialCapability('INTERNAL')
                setUnifiedDialogOpen(true)
              }}
              size="sm"
              className="rounded-xl text-xs font-semibold gap-1.5 shadow-xs mt-2"
            >
              <Plus className="size-3.5" />
              <span>ثبت تفویض صلاحیت جدید</span>
            </Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-muted/40 border-b border-border/60 text-muted-foreground font-semibold">
                <tr>
                  <th className="py-3.5 px-4 font-bold w-12 text-center">#</th>
                  <th className="py-3.5 px-4 font-bold">نوع صلاحیت تفویضی</th>
                  <th className="py-3.5 px-4 font-bold">فرد تفویض‌شونده</th>
                  <th className="py-3.5 px-4 font-bold">ریاست / اداره</th>
                  <th className="py-3.5 px-4 font-bold">تاریخ پایان اعتبار</th>
                  <th className="py-3.5 px-4 font-bold">دلیل و استناد تفویض</th>
                  <th className="py-3.5 px-4 font-bold text-center">وضعیت</th>
                  <th className="py-3.5 px-4 font-bold text-center">عملیات اداری</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/60">
                {sortedDelegations.map((d, index) => {
                  const isInternal = d.capability === 'INTERNAL'
                  const delegateName =
                    d.user?.employee?.full_name || d.user?.name || 'نامشخص'
                  const roleTitle =
                    d.user?.role_title ||
                    (d.user?.roles?.includes('DEPUTY_DIRECTOR')
                      ? 'معاون ریاست'
                      : 'مدیر اجرائیه')

                  return (
                    <tr
                      key={`${d.capability}-${d.id}`}
                      className="hover:bg-muted/30 transition-colors"
                    >
                      {/* Index */}
                      <td className="py-3.5 px-4 text-center font-mono text-muted-foreground">
                        {index + 1}
                      </td>

                      {/* Capability Type */}
                      <td className="py-3.5 px-4">
                        {isInternal ? (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20">
                            <ShieldCheck className="size-3.5 shrink-0" />
                            <span>تأیید جلسات داخلی</span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20">
                            <FileCheck className="size-3.5 shrink-0" />
                            <span>بررسی صورت‌جلسات رسمی</span>
                          </span>
                        )}
                      </td>

                      {/* Delegate User */}
                      <td className="py-3.5 px-4">
                        <div className="font-bold text-foreground text-xs flex items-center gap-2">
                          <span>{delegateName}</span>
                          <span className="text-[10px] px-2 py-0.5 rounded-md font-semibold bg-primary/10 text-primary border border-primary/20 shrink-0">
                            {roleTitle}
                          </span>
                        </div>
                        {d.user?.email && (
                          <div className="text-[11px] text-muted-foreground font-mono mt-0.5">
                            {d.user.email}
                          </div>
                        )}
                      </td>

                      {/* Unit */}
                      <td className="py-3.5 px-4">
                        <span className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                          <Building2 className="size-3.5 text-muted-foreground" />
                          <span>{d.unit?.name || 'اداره متبوع'}</span>
                        </span>
                      </td>

                      {/* Expiration */}
                      <td className="py-3.5 px-4">
                        {d.expires_at ? (
                          <span className="text-xs font-mono font-medium text-foreground flex items-center gap-1.5">
                            <Calendar className="size-3.5 text-muted-foreground" />
                            <span>{formatSolarDateTime(d.expires_at)}</span>
                          </span>
                        ) : (
                          <span className="text-muted-foreground text-xs font-medium">
                            نامحدود
                          </span>
                        )}
                      </td>

                      {/* Reason */}
                      <td className="py-3.5 px-4 max-w-xs truncate text-muted-foreground text-xs">
                        <span title={d.grant_reason || undefined}>
                          {d.grant_reason || '—'}
                        </span>
                      </td>

                      {/* Status */}
                      <td className="py-3.5 px-4 text-center">
                        {d.active ? (
                          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <CheckCircle2 className="size-3.5" />
                            <span>فعال و معتبر</span>
                          </span>
                        ) : d.revoked_at ? (
                          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-destructive/10 text-destructive border border-destructive/20">
                            <XCircle className="size-3.5" />
                            <span>لغوشده</span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-muted text-muted-foreground border border-border">
                            <Clock className="size-3.5" />
                            <span>منقضی‌شده</span>
                          </span>
                        )}
                      </td>

                      {/* Action */}
                      <td className="py-3.5 px-4 text-center">
                        {d.active ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              setRevokeTarget(d)
                              setRevocationReason('')
                            }}
                            className="h-8 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/10 rounded-xl gap-1.5"
                          >
                            <XCircle className="size-3.5" />
                            <span>لغو تفویض</span>
                          </Button>
                        ) : (
                          <span className="text-[11px] text-muted-foreground font-medium">
                            {d.revoked_at ? 'لغو توسط مدیریت' : 'پایان اعتبار'}
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

      {/* Unified Delegation Dialog */}
      <UnifiedDelegationDialog
        open={unifiedDialogOpen}
        onOpenChange={setUnifiedDialogOpen}
        initialCapability={initialCapability}
        onSuccess={() => {
          loadData()
        }}
      />

      {/* Revocation Confirm Dialog */}
      <ConfirmDialog
        open={!!revokeTarget}
        onOpenChange={(open) => {
          if (!open) setRevokeTarget(null)
        }}
        title={`لغو تفویض صلاحیت ${
          revokeTarget?.capability === 'INTERNAL'
            ? 'تأیید جلسات داخلی'
            : 'بررسی صورت‌جلسات رسمی'
        }`}
        description="با لغو این تفویض، فرد منصوب دیگر مجاز به اقدام با استناد به این صلاحیت نخواهد بود و سوابق لغو در تاریخچه ممیزی ثبت می‌گردد."
        confirmLabel="لغو قطعی تفویض"
        variant="destructive"
        onConfirm={handleRevoke}
      >
        <div className="space-y-1.5 pt-2" dir="rtl">
          <label className="text-xs font-bold text-foreground block">
            دلیل اداری لغو تفویض <span className="text-destructive">*</span>
          </label>
          <Input
            required
            value={revocationReason}
            onChange={(e) => setRevocationReason(e.target.value)}
            placeholder="دلیل و مستند لغو صلاحیت را درج نمایید..."
            className="h-9.5 rounded-xl text-xs"
          />
        </div>
      </ConfirmDialog>
    </div>
  )
}
