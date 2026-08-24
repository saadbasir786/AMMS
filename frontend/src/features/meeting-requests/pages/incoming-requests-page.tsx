import React, { useEffect, useState, useMemo } from 'react'
import {
  RotateCw,
  Inbox,
  Clock3,
  CheckCircle2,
  AlertCircle,
  FileCheck2
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage } from '@/lib/api-client'
import { RequestsFilterBar } from '../components/requests-filter-bar'
import { RequestsTable } from '../components/requests-table'
import type { RequestRowItem } from '../types'

type IncomingRequestsPageProps = {
  statusLabels: Record<string, string>
  priorityLabels?: Record<string, string>
}

export function IncomingRequestsPage({
  statusLabels,
  priorityLabels,
}: IncomingRequestsPageProps) {
  const [data, setData] = useState<{ data: RequestRowItem[] }>({ data: [] })
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const [resSingle, resMulti] = await Promise.all([
        api.get('/incoming-meeting-requests', { params: { status: status || undefined } }),
        api.get('/incoming-multi-directorate-meeting-requests', { params: { status: status || undefined } }),
      ])
      const left = apiPage(resSingle).data || []
      const right = apiPage(resMulti).data || []
      const combined = [...left, ...right].sort((x: any, y: any) =>
        String(y.created_at || '').localeCompare(String(x.created_at || ''))
      )
      setData({ data: combined })
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری درخواست‌های وارده با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [status])

  const rows = (data.data || []).filter((r) => {
    if (!search.trim()) return true
    const q = search.toLowerCase()
    return (
      (r.subject || '').toLowerCase().includes(q) ||
      (r.request_number || '').toLowerCase().includes(q) ||
      (r.requesting_unit?.name || '').toLowerCase().includes(q)
    )
  })

  const underReviewCount = useMemo(() => {
    return rows.filter((r) =>
      ['SUBMITTED_TO_TARGET', 'UNDER_TARGET_REVIEW', 'UNDER_MULTI_TARGET_REVIEW'].includes(
        r.status || ''
      )
    ).length
  }, [rows])

  const approvedCount = useMemo(() => {
    return rows.filter((r) =>
      ['TARGET_APPROVED', 'BILATERAL_APPROVED', 'MULTILATERAL_APPROVED'].includes(r.status || '')
    ).length
  }, [rows])

  const statusOptions = Object.entries(statusLabels)
    .filter(
      ([key]) =>
        key.includes('TARGET') ||
        key.includes('BILATERAL') ||
        key.includes('PARTICIPATING') ||
        key === 'SUBMITTED_TO_TARGET' ||
        key === 'UNDER_TARGET_REVIEW'
    )
    .map(([value, label]) => ({ value, label }))

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        title="درخواست‌های وارده"
        description="درخواست‌های جلسه ارسالی از سایر ریاست‌ها که نیازمند بررسی و تأیید حوزه شما هستند"
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={load}
            disabled={loading}
            className="gap-1.5 text-xs font-semibold rounded-xl"
          >
            <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
            به‌روزرسانی
          </Button>
        }
      />

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <Inbox className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل وارده‌ها</span>
            <strong className="text-base font-bold text-foreground font-mono">
              {rows.length}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Clock3 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              در حال بررسی
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {underReviewCount}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تأییدشده توسط این واحد
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {approvedCount}
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
              onClick={load}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Filters */}
      <RequestsFilterBar
        searchValue={search}
        onSearchChange={setSearch}
        statusValue={status}
        statusOptions={statusOptions}
        onStatusChange={setStatus}
        totalCount={rows.length}
        mode="incoming"
      />

      {/* Requests Table */}
      <RequestsTable
        rows={rows}
        mode="incoming"
        statusLabels={statusLabels}
        priorityLabels={priorityLabels}
        emptyText="درخواست وارده‌ای در این وضعیت وجود ندارد."
      />
    </div>
  )
}
