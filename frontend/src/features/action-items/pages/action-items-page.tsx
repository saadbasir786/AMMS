import React, { useEffect, useState, useMemo, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  ListTodo,
  Clock3,
  CheckCircle2,
  AlertTriangle,
  RotateCw,
  AlertCircle,
  CalendarDays
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api } from '@/lib/api-client'
import { ActionItemsFilterBar } from '../components/action-items-filter-bar'
import { ActionItemsTable } from '../components/action-items-table'
import type { ActionItem, ActionItemFilters } from '../types'

export function ActionItemsPage() {
  const [items, setItems] = useState<ActionItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [filters, setFilters] = useState<ActionItemFilters>({
    search: '',
    status: '',
    overdueOnly: false,
  })

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/action-items', {
        params: {
          status: filters.status || undefined,
          overdue: filters.overdueOnly || undefined,
        },
      })
      const payload = res.data?.data ?? res.data
      setItems(Array.isArray(payload) ? payload : payload?.data || [])
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری کارتابل اقدامات با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [filters.status, filters.overdueOnly])

  useEffect(() => {
    loadData()
  }, [loadData])

  // Filtered items by search query
  const filteredItems = useMemo(() => {
    if (!filters.search.trim()) return items
    const query = filters.search.toLowerCase().trim()
    return items.filter((item) => {
      const matchesNumber = item.action_number?.toLowerCase().includes(query)
      const matchesTitle = item.title?.toLowerCase().includes(query)
      const matchesDecision = item.decision_number?.toLowerCase().includes(query)
      const matchesUnit = item.responsible_unit?.name?.toLowerCase().includes(query)
      const matchesAssignee =
        item.assigned_employee?.full_name?.toLowerCase().includes(query) ||
        item.assigned_employee?.name?.toLowerCase().includes(query)

      return (
        matchesNumber ||
        matchesTitle ||
        matchesDecision ||
        matchesUnit ||
        matchesAssignee
      )
    })
  }, [items, filters.search])

  // KPI counters
  const total = items.length
  const inProgressCount = items.filter((i) => ['OPEN', 'IN_PROGRESS'].includes(i.status)).length
  const pendingVerificationCount = items.filter((i) => i.status === 'PENDING_VERIFICATION').length
  const completedCount = items.filter((i) => i.status === 'COMPLETED').length
  const overdueCount = items.filter((i) => i.is_overdue).length

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        title="اقدامات و مصوبات من"
        description="مشاهده، ثبت گزارش پیشرفت، اعلام موانع و تکمیل وظایف محوله از جلسات رسمی"
        action={
          <Button
            asChild
            variant="outline"
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 shadow-xs"
          >
            <Link to="/meetings">
              <CalendarDays className="size-4" />
              <span>تقویم جلسات رسمی</span>
            </Link>
          </Button>
        }
      />

      {/* KPI Summary Strip */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <ListTodo className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل اقدامات</span>
            <strong className="text-base font-bold text-foreground font-mono">{total}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Clock3 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              در حال اجرا
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {inProgressCount}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              منتظر تأیید خاتمه
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {pendingVerificationCount}
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
              {overdueCount}
            </strong>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <ActionItemsFilterBar
        filters={filters}
        onChange={setFilters}
        onReset={() => setFilters({ search: '', status: '', overdueOnly: false })}
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
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/3" />
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="h-12 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Table */}
      {!loading && !error && (
        <ActionItemsTable items={filteredItems} loading={loading} />
      )}
    </div>
  )
}
