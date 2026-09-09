import React, { useEffect, useState, useCallback, useMemo } from 'react'
import { Link } from 'react-router-dom'
import {
  ClipboardCheck,
  CheckCircle2,
  Clock3,
  AlertCircle,
  RotateCw,
  Search,
  Eye,
  Building2,
  Check,
  RotateCcw,
  XCircle,
  AlertTriangle,
  Send
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { StatusBadge } from '@/components/amms/status-badge'
import { formatSolarDate } from '@/lib/calendar-utils'
import { api } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'
import type { RequestRowItem } from '../types'

type ApprovalsPageProps = {
  priorityLabels?: Record<string, string>
  purposeLabels?: Record<string, string>
}

export function ApprovalsPage({
  priorityLabels = { NORMAL: 'عادی', HIGH: 'بلند', URGENT: 'عاجل' },
  purposeLabels = {
    COORDINATION: 'هماهنگی',
    DECISION: 'تصمیم‌گیری',
    CONSULTATION: 'مشورت',
    REPORTING: 'گزارش‌دهی',
    APPROVAL: 'تأیید',
    FOLLOW_UP: 'پیگیری',
    OTHER: 'سایر',
  },
}: ApprovalsPageProps) {
  const [rows, setRows] = useState<RequestRowItem[]>([])
  const [search, setSearch] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<string | number | null>(null)
  const [error, setError] = useState<string | null>(null)

  // Dialog state
  const [activeDialog, setActiveDialog] = useState<{
    request: RequestRowItem
    action: 'approve' | 'return' | 'reject'
  } | null>(null)
  const [dialogReason, setDialogReason] = useState('')

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [genericRes, pendingReqRes] = await Promise.all([
        fetchAllPages<RequestRowItem>('/approvals').catch(() => []),
        fetchAllPages<RequestRowItem>('/meeting-requests', { status: 'PENDING_REQUESTER_APPROVAL' }).catch(() => []),
      ])

      const listA = genericRes
      const listB = pendingReqRes
      const combined = Array.from(
        new Map([...listA, ...listB].map((item: any) => [item.id, item])).values()
      ).sort((a: any, b: any) =>
        String(b.created_at || '').localeCompare(String(a.created_at || ''))
      )

      setRows(combined)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری کارتابل تأییدات با خطا مواجه شد. لطفاً دوباره تلاش نمایید.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const filteredRows = useMemo(() => {
    return rows.filter((r) => {
      const q = search.toLowerCase()
      const matchSearch =
        (r.subject || '').toLowerCase().includes(q) ||
        (r.request_number || '').toLowerCase().includes(q) ||
        (r.requesting_unit?.name || '').toLowerCase().includes(q) ||
        (r.creator?.name || '').toLowerCase().includes(q)

      const matchPriority = priorityFilter ? r.priority === priorityFilter : true

      return matchSearch && matchPriority
    })
  }, [rows, search, priorityFilter])

  const urgentCount = useMemo(
    () => rows.filter((r) => r.priority === 'URGENT').length,
    [rows]
  )

  const handleAction = async () => {
    if (!activeDialog) return
    const { request, action } = activeDialog
    setBusyId(request.id)
    setError(null)

    try {
      let endpoint = ''
      let body: any = {}

      if (request.flow_type === 'INTER_DIRECTORATE') {
        if (action === 'approve') endpoint = `/meeting-requests/${request.id}/requester-approve`
        else if (action === 'return') {
          endpoint = `/meeting-requests/${request.id}/requester-return`
          body = { reason: dialogReason }
        } else if (action === 'reject') {
          endpoint = `/meeting-requests/${request.id}/requester-reject`
          body = { reason: dialogReason }
        }
      } else if (request.flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
        if (action === 'approve') endpoint = `/meeting-requests/${request.id}/high-authority/requester-approve`
        else if (action === 'return') {
          endpoint = `/meeting-requests/${request.id}/high-authority/requester-return`
          body = { reason: dialogReason }
        } else if (action === 'reject') {
          endpoint = `/meeting-requests/${request.id}/high-authority/requester-reject`
          body = { reason: dialogReason }
        }
      } else {
        if (action === 'approve') endpoint = `/meeting-requests/${request.id}/approve`
        else if (action === 'return') {
          endpoint = `/meeting-requests/${request.id}/return-for-revision`
          body = { reason: dialogReason }
        } else if (action === 'reject') {
          endpoint = `/meeting-requests/${request.id}/reject`
          body = { reason: dialogReason }
        }
      }

      await api.post(endpoint, body)
      setActiveDialog(null)
      setDialogReason('')
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت تصمیم با خطا مواجه شد.')
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="کارتابل تاییدات و صلاحیت ریاست"
        description="بررسی، موافقت، اعاده یا رد درخواست‌های تنظیم جلسات صادره از ریاست تحت مدیریت شما"
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={loadData}
            disabled={loading}
            className="text-xs font-semibold rounded-xl gap-1.5"
          >
            <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
            <span>تازه‌سازی کارتابل</span>
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

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <ClipboardCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل منتظر تصمیم</span>
            <strong className="text-lg font-bold text-foreground font-mono">{rows.length} درخواست</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center font-bold text-xs shrink-0">
            <AlertTriangle className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">درخواست‌های عاجل</span>
            <strong className="text-lg font-bold text-foreground font-mono">{urgentCount} مورد</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">آماده ارجاع به مقصد</span>
            <strong className="text-lg font-bold text-foreground font-mono">{rows.length} مورد</strong>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">جستجو در درخواست‌ها</label>
          <div className="relative">
            <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="جستجو در موضوع، شماره سند، نام درخواست‌کننده..."
              className="h-9.5 pr-8 text-xs rounded-xl"
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">فیلتر اولویت اداری</label>
          <select
            value={priorityFilter}
            onChange={(e) => setPriorityFilter(e.target.value)}
            className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3 text-foreground"
          >
            <option value="">همه اولویت‌ها</option>
            <option value="URGENT">عاجل</option>
            <option value="HIGH">بلند</option>
            <option value="NORMAL">عادی</option>
          </select>
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/3" />
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="h-14 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Approvals Table */}
      {!loading && (
        <div className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs">
          {filteredRows.length === 0 ? (
            <div className="text-center py-12 px-4">
              <CheckCircle2 className="size-9 mx-auto text-emerald-500/50 mb-2.5" />
              <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
                کارتابل تأییدات خالی است
              </h4>
              <p className="text-[11px] text-muted-foreground">
                هیچ درخواست منتظر تأییدی در حوزه صلاحیت سازمانی شما یافت نشد.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-right border-collapse">
                <thead>
                  <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                    <th className="py-3 px-4 font-bold">شماره</th>
                    <th className="py-3 px-4 font-bold">موضوع جلسه</th>
                    <th className="py-3 px-4 font-bold">واحد / ثبت‌کننده</th>
                    <th className="py-3 px-4 font-bold">هدف</th>
                    <th className="py-3 px-4 font-bold">اولویت</th>
                    <th className="py-3 px-4 font-bold">تاریخ ثبت</th>
                    <th className="py-3 px-4 font-bold text-center">اقدامات سریع ریاست</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/50">
                  {filteredRows.map((r) => {
                    const isUrgent = r.priority === 'URGENT'
                    const isHigh = r.priority === 'HIGH'

                    return (
                      <tr
                        key={r.id}
                        className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-3 px-4 font-mono font-bold text-foreground">
                          <Badge variant="outline" className="font-mono text-[10px]">
                            {r.request_number || `REQ-${r.id}`}
                          </Badge>
                        </td>

                        <td className="py-3 px-4 max-w-xs font-semibold text-foreground">
                          <Link
                            to={`/meeting-requests/${r.id}`}
                            className="hover:text-primary transition-colors block truncate"
                          >
                            {r.subject}
                          </Link>
                        </td>

                        <td className="py-3 px-4 text-muted-foreground whitespace-nowrap">
                          <span className="text-foreground font-medium block">
                            {r.requesting_unit?.name || '—'}
                          </span>
                          <span className="text-[10px] text-muted-foreground">
                            توسط: {r.creator?.name || 'کارشناس'}
                          </span>
                        </td>

                        <td className="py-3 px-4 text-muted-foreground whitespace-nowrap">
                          {purposeLabels[r.purpose || ''] || r.purpose || '—'}
                        </td>

                        <td className="py-3 px-4 whitespace-nowrap">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold border ${
                              isUrgent
                                ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200'
                                : isHigh
                                ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200'
                                : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200'
                            }`}
                          >
                            {priorityLabels[r.priority || ''] || r.priority}
                          </span>
                        </td>

                        <td className="py-3 px-4 font-mono text-[11px] text-muted-foreground whitespace-nowrap">
                          {r.created_at ? formatSolarDate(r.created_at) : '—'}
                        </td>

                        {/* Fast Actions */}
                        <td className="py-3 px-4 text-center whitespace-nowrap">
                          <div className="flex items-center justify-center gap-1.5">
                            {/* Fast Approve */}
                            <Button
                              size="sm"
                              disabled={busyId === r.id}
                              onClick={() =>
                                setActiveDialog({ request: r, action: 'approve' })
                              }
                              className="h-7.5 px-2.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs gap-1"
                            >
                              <Check className="size-3" />
                              <span>تأیید</span>
                            </Button>

                            {/* Fast Return */}
                            <Button
                              variant="outline"
                              size="sm"
                              disabled={busyId === r.id}
                              onClick={() =>
                                setActiveDialog({ request: r, action: 'return' })
                              }
                              className="h-7.5 px-2 text-xs font-semibold rounded-lg text-amber-700 dark:text-amber-300 border-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/40 gap-1"
                            >
                              <RotateCcw className="size-3" />
                              <span>اصلاح</span>
                            </Button>

                            {/* View Detail Link */}
                            <Button
                              asChild
                              variant="ghost"
                              size="sm"
                              className="h-7.5 px-2 text-xs font-semibold rounded-lg text-muted-foreground hover:text-foreground"
                            >
                              <Link to={`/meeting-requests/${r.id}`}>
                                <Eye className="size-3.5" />
                              </Link>
                            </Button>
                          </div>
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

      {/* Confirmation & Reason Dialog */}
      {activeDialog && (
        <ConfirmDialog
          open={true}
          onOpenChange={(open) => {
            if (!open) {
              setActiveDialog(null)
              setDialogReason('')
            }
          }}
          title={
            activeDialog.action === 'approve'
              ? 'تأیید نهایی درخواست جلسه'
              : activeDialog.action === 'return'
              ? 'اعاده درخواست جهت اصلاح'
              : 'رد درخواست جلسه'
          }
          description={
            activeDialog.action === 'approve'
              ? `آیا از تأیید رسمی درخواست «${activeDialog.request.subject}» اطمینان دارید؟ پس از تأیید، درخواست وارد مرحله زمان‌بندی یا بررسی ریاست مقصد خواهد شد.`
              : `لطفاً دلایل و توضیحات لازم جهت ${
                  activeDialog.action === 'return' ? 'اصلاح' : 'رد'
                } درخواست را وارد نمایید:`
          }
          confirmLabel={
            activeDialog.action === 'approve'
              ? 'تأیید و ارسال'
              : activeDialog.action === 'return'
              ? 'ثبت و اعاده جهت اصلاح'
              : 'رد قطعی'
          }
          variant={activeDialog.action === 'reject' ? 'destructive' : 'default'}
          onConfirm={handleAction}
        >
          {activeDialog.action !== 'approve' && (
            <div className="mt-3 space-y-1.5 text-xs text-right" dir="rtl">
              <label className="font-bold text-foreground block">
                توضیحات و دلایل <span className="text-rose-500">*</span>
              </label>
              <textarea
                rows={3}
                value={dialogReason}
                onChange={(e) => setDialogReason(e.target.value)}
                placeholder="دلیل برگشت یا توضیحات برای کارشناس..."
                className="w-full text-xs rounded-xl border border-border/70 bg-card p-2.5 text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary"
              />
            </div>
          )}
        </ConfirmDialog>
      )}
    </div>
  )
}
