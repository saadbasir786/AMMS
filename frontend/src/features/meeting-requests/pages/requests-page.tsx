import React, { useEffect, useState, useMemo } from 'react'
import {
  RotateCw,
  FilePlus2,
  Clock3,
  CheckCircle2,
  FileText,
  AlertCircle,
  FileSpreadsheet
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage } from '@/lib/api-client'
import { CreateRequestDropdown } from '../components/create-request-dropdown'
import { RequestsFilterBar } from '../components/requests-filter-bar'
import { RequestsTable } from '../components/requests-table'
import type { RequestRowItem, PaginationMeta } from '../types'

type RequestsPageProps = {
  statusLabels: Record<string, string>
  priorityLabels?: Record<string, string>
  purposeLabels?: Record<string, string>
}

export function RequestsPage({
  statusLabels,
  priorityLabels,
  purposeLabels,
}: RequestsPageProps) {
  const [data, setData] = useState<{ data: RequestRowItem[]; meta?: PaginationMeta | null }>({
    data: [],
    meta: null,
  })
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = async (pageNum = page) => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/meeting-requests', {
        params: {
          status: status || undefined,
          search: search || undefined,
          page: pageNum,
        },
      })
      setData(apiPage(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری فهرست درخواست‌های جلسه با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    setPage(1)
    load(1)
  }, [status])

  const handlePageChange = (newPage: number) => {
    setPage(newPage)
    load(newPage)
  }

  const handleSearchSubmit = () => {
    setPage(1)
    load(1)
  }

  const rows = data.data || []
  const total = data.meta?.total ?? rows.length

  const pendingCount = useMemo(() => {
    return rows.filter((r) =>
      ['PENDING_INTERNAL_APPROVAL', 'PENDING_REQUESTER_APPROVAL', 'UNDER_TARGET_REVIEW'].includes(
        r.status || ''
      )
    ).length
  }, [rows])

  const approvedCount = useMemo(() => {
    return rows.filter((r) =>
      ['APPROVED', 'SCHEDULING', 'SCHEDULE_CONFIRMED', 'CONVERTED_TO_MEETING'].includes(
        r.status || ''
      )
    ).length
  }, [rows])

  const draftCount = useMemo(() => {
    return rows.filter((r) => ['DRAFT', 'RETURNED_FOR_REVISION'].includes(r.status || '')).length
  }, [rows])

  const statusOptions = Object.entries(statusLabels).map(([value, label]) => ({
    value,
    label,
  }))

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        title="درخواست‌های جلسه"
        description="مشاهده، پیگیری و مدیریت درخواست‌های جلسه ثبت‌شده در حوزه دسترسی شما"
        action={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => load(page)}
              disabled={loading}
              className="gap-1.5 text-xs font-semibold rounded-xl"
            >
              <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
              به‌روزرسانی
            </Button>
            <CreateRequestDropdown />
          </div>
        }
      />

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <FileSpreadsheet className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل درخواست‌ها</span>
            <strong className="text-base font-bold text-foreground font-mono">{total}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Clock3 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              در انتظار تأیید
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {pendingCount}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تأییدشده / نهایی
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {approvedCount}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-slate-500/10 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
            <FileText className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              پیش‌نویس / نیازمند اصلاح
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {draftCount}
            </strong>
          </div>
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertCircle className="size-4" />
          <AlertDescription className="text-xs flex items-center justify-between gap-2">
            <span>{error}</span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => load(page)}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Filter and Search Bar */}
      <RequestsFilterBar
        searchValue={search}
        onSearchChange={setSearch}
        onSearchSubmit={handleSearchSubmit}
        statusValue={status}
        statusOptions={statusOptions}
        onStatusChange={setStatus}
        totalCount={total}
      />

      {/* Requests Table */}
      <RequestsTable
        rows={rows}
        mode="all"
        statusLabels={statusLabels}
        priorityLabels={priorityLabels}
        purposeLabels={purposeLabels}
        emptyText="درخواست جلسه‌ای با این مشخصات یافت نشد."
        pagination={data.meta}
        onPageChange={handlePageChange}
      />
    </div>
  )
}
