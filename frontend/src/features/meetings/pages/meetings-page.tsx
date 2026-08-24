import React, { useEffect, useState, useMemo, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarDays,
  CalendarCheck,
  CalendarClock,
  CheckCircle2,
  RotateCw,
  AlertCircle,
  FilePlus2,
  Search
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage } from '@/lib/api-client'
import { MeetingsFilterBar } from '../components/meetings-filter-bar'
import { MeetingsTable } from '../components/meetings-table'
import type { MeetingItem, MeetingFilters } from '../types'

export function MeetingsPage() {
  const [meetings, setMeetings] = useState<MeetingItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [filters, setFilters] = useState<MeetingFilters>({
    search: '',
    status: 'ALL',
    unit_id: '',
  })

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/meetings')
      const paged = apiPage(res)
      setMeetings(paged.data || [])
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری فهرست جلسات با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  // Filtered meetings
  const filteredMeetings = useMemo(() => {
    return meetings.filter((m) => {
      // Search
      if (filters.search.trim()) {
        const query = filters.search.toLowerCase().trim()
        const matchesTitle = m.title?.toLowerCase().includes(query)
        const matchesNumber = m.meeting_number?.toLowerCase().includes(query)
        const matchesLocation = m.location?.toLowerCase().includes(query)
        const matchesChairperson = m.chairperson?.full_name?.toLowerCase().includes(query)
        const matchesUnit = m.request?.requesting_unit?.name?.toLowerCase().includes(query)
        if (
          !matchesTitle &&
          !matchesNumber &&
          !matchesLocation &&
          !matchesChairperson &&
          !matchesUnit
        ) {
          return false
        }
      }

      // Status
      if (filters.status && filters.status !== 'ALL') {
        if (m.status !== filters.status) return false
      }

      return true
    })
  }, [meetings, filters])

  // Stat summary counters
  const scheduledCount = meetings.filter((m) => m.status === 'SCHEDULED').length
  const completedCount = meetings.filter((m) => ['HELD', 'COMPLETED'].includes(m.status)).length

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

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <CalendarDays className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل جلسات</span>
            <strong className="text-base font-bold text-foreground font-mono">
              {meetings.length}
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
              {scheduledCount}
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
              {completedCount}
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
              {meetings.length > 0
                ? `${Math.round((completedCount / (meetings.length || 1)) * 100)}%`
                : '۱۰۰٪'}
            </strong>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <MeetingsFilterBar
        filters={filters}
        onChange={setFilters}
        onReset={() => setFilters({ search: '', status: 'ALL', unit_id: '' })}
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

      {/* Table */}
      {!loading && !error && (
        <MeetingsTable meetings={filteredMeetings} loading={loading} />
      )}
    </div>
  )
}
