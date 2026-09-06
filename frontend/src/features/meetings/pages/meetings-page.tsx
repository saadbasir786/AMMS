import React, { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarDays,
  CalendarCheck,
  CalendarClock,
  CheckCircle2,
  RotateCw,
  AlertCircle,
  FilePlus2,
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage } from '@/lib/api-client'
import { MeetingsFilterBar } from '../components/meetings-filter-bar'
import { MeetingsTable } from '../components/meetings-table'
import type { MeetingItem, MeetingFilters, PaginationMeta, MeetingStats } from '../types'

export function MeetingsPage() {
  const [meetings, setMeetings] = useState<MeetingItem[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState<number>(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [stats, setStats] = useState<MeetingStats>({
    total: 0,
    upcoming: 0,
    completed: 0,
  })

  const [filters, setFilters] = useState<MeetingFilters>({
    search: '',
    status: 'ALL',
    unit_id: '',
  })

  const loadData = useCallback(async (targetPage = 1) => {
    setLoading(true)
    setError(null)
    try {
      const params: Record<string, any> = {
        page: targetPage,
        per_page: 15,
      }
      if (filters.search.trim()) {
        params.search = filters.search.trim()
      }
      if (filters.status && filters.status !== 'ALL') {
        params.status = filters.status
      }
      if (filters.unit_id) {
        params.unit_id = filters.unit_id
      }

      const res = await api.get('/meetings', { params })
      const pageInfo = apiPage(res)
      setMeetings(pageInfo.data || [])
      setPagination(pageInfo.meta || null)

      if (res?.data?.stats) {
        setStats(res.data.stats)
      }
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری فهرست جلسات با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => {
    setPage(1)
    loadData(1)
  }, [filters, loadData])

  const handlePageChange = (newPage: number) => {
    setPage(newPage)
    loadData(newPage)
  }

  const handleResetFilters = () => {
    setFilters({ search: '', status: 'ALL', unit_id: '' })
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="جلسات رسمی سازمان"
        description="مشاهده، مدیریت، پیگیری حاضری، ثبت صورت‌جلسه و اجرای جلسات"
        action={
          <Button
            asChild
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Link to="/meeting-requests/create">
              <FilePlus2 className="size-4" />
              <span>ثبت درخواست جلسه جدید</span>
            </Link>
          </Button>
        }
      />

      {/* Summary KPI Strip - Authoritative Backend Calculations */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <CalendarDays className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل جلسات</span>
            <strong className="text-base font-bold text-foreground font-mono">
              {stats.total}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-sky-500/10 text-sky-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CalendarClock className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              جلسات آینده
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {stats.upcoming}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              پایان‌یافته / تکمیل
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {stats.completed}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CalendarCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              تطبیق زمانی
            </span>
            <strong className="text-base font-bold text-foreground font-mono">
              {stats.total > 0
                ? `${Math.round((stats.completed / (stats.total || 1)) * 100)}%`
                : '۱۰۰٪'}
            </strong>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <MeetingsFilterBar
        filters={filters}
        onChange={setFilters}
        onReset={handleResetFilters}
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
              onClick={() => loadData(page)}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Skeleton Loading State */}
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

      {/* Table with Pure Server-Side Pagination */}
      {!loading && !error && (
        <MeetingsTable
          meetings={meetings}
          loading={loading}
          pagination={pagination}
          onPageChange={handlePageChange}
        />
      )}
    </div>
  )
}
