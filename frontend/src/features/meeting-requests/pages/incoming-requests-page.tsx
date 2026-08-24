import React, { useEffect, useState, useMemo } from 'react'
import {
  RotateCw,
  Inbox,
  Clock3,
  CheckCircle2,
  AlertCircle,
  FileCheck2,
  Crown,
  Building2,
  ShieldCheck
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage, apiData } from '@/lib/api-client'
import { RequestsFilterBar } from '../components/requests-filter-bar'
import { RequestsTable } from '../components/requests-table'
import type { RequestRowItem } from '../types'

export type InboxType =
  | 'standard'
  | 'high-authority'
  | 'multi-high-authority'
  | 'authority-initiated'

type IncomingRequestsPageProps = {
  inboxType?: InboxType
  statusLabels: Record<string, string>
  priorityLabels?: Record<string, string>
}

export function IncomingRequestsPage({
  inboxType = 'standard',
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
      if (inboxType === 'high-authority') {
        const meRes = await api.get('/me').catch(() => ({ data: { data: null } }))
        const me = apiData(meRes)
        const isAuthority = me?.roles?.some((r: any) => r.role === 'HIGH_AUTHORITY')
        const endpoint = isAuthority
          ? '/incoming-high-authority-decisions'
          : '/incoming-high-authority-office-meeting-requests'
        const res = await api.get(endpoint, { params: { status: status || undefined } })
        setData({ data: apiPage(res).data || [] })
      } else if (inboxType === 'multi-high-authority') {
        const res = await api.get('/incoming-high-authority-office-meeting-requests', {
          params: { status: status || undefined },
        }).catch(() => api.get('/incoming-meeting-requests'))
        setData({ data: apiPage(res).data || [] })
      } else if (inboxType === 'authority-initiated') {
        const res = await api.get('/incoming-authority-initiated-meeting-requests', {
          params: { status: status || undefined },
        })
        setData({ data: apiPage(res).data || [] })
      } else {
        const [resSingle, resMulti] = await Promise.all([
          api.get('/incoming-meeting-requests', { params: { status: status || undefined } }),
          api.get('/incoming-multi-directorate-meeting-requests', { params: { status: status || undefined } }).catch(() => ({ data: { data: [] } })),
        ])
        const left = apiPage(resSingle).data || []
        const right = apiPage(resMulti).data || []
        const combined = [...left, ...right].sort((x: any, y: any) =>
          String(y.created_at || '').localeCompare(String(x.created_at || ''))
        )
        setData({ data: combined })
      }
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
  }, [inboxType, status])

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
      [
        'SUBMITTED_TO_TARGET',
        'UNDER_TARGET_REVIEW',
        'UNDER_MULTI_TARGET_REVIEW',
        'SUBMITTED_TO_AUTHORITY_OFFICE',
        'UNDER_AUTHORITY_OFFICE_REVIEW',
        'PENDING_HIGH_AUTHORITY_DECISION',
      ].includes(r.status || '')
    ).length
  }, [rows])

  const approvedCount = useMemo(() => {
    return rows.filter((r) =>
      [
        'TARGET_APPROVED',
        'BILATERAL_APPROVED',
        'MULTILATERAL_APPROVED',
        'HIGH_AUTHORITY_APPROVED',
        'ISSUED_BY_HIGH_AUTHORITY',
      ].includes(r.status || '')
    ).length
  }, [rows])

  const getPageMeta = () => {
    switch (inboxType) {
      case 'high-authority':
        return {
          title: 'صندوق درخواست‌های مقام عالی',
          description: 'بررسی و اتخاذ تصمیم در خصوص درخواست‌های جلسه با مقام عالی',
          icon: Crown,
        }
      case 'multi-high-authority':
        return {
          title: 'صندوق جلسات چندریاستی با مقام عالی',
          description: 'هماهنگی و بررسی درخواست‌های چندریاستی با حضور مقام عالی',
          icon: ShieldCheck,
        }
      case 'authority-initiated':
        return {
          title: 'صندوق جلسات ابتکاری مقام عالی',
          description: 'درخواست‌ها و دستورات جلسات صادرشده توسط مقام عالی',
          icon: Building2,
        }
      default:
        return {
          title: 'درخواست‌های وارده',
          description: 'درخواست‌های دریافتی از سایر ریاست‌ها و واحدهای سازمانی جهت بررسی و پاسخ‌دهی',
          icon: Inbox,
        }
    }
  }

  const meta = getPageMeta()

  const statusOptions = Object.entries(statusLabels)
    .filter(
      ([key]) =>
        key.includes('TARGET') ||
        key.includes('BILATERAL') ||
        key.includes('PARTICIPATING') ||
        key.includes('AUTHORITY') ||
        key === 'SUBMITTED_TO_TARGET' ||
        key === 'UNDER_TARGET_REVIEW'
    )
    .map(([value, label]) => ({ value, label }))

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        title={meta.title}
        description={meta.description}
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={load}
            disabled={loading}
            className="text-xs font-semibold rounded-xl gap-1.5"
          >
            <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
            <span>به‌روزرسانی صندوق</span>
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
              onClick={load}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <Inbox className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل دریافتی‌ها</span>
            <strong className="text-lg font-bold text-foreground font-mono">{rows.length} درخواست</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Clock3 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">در حال بررسی</span>
            <strong className="text-lg font-bold text-foreground font-mono">{underReviewCount} مورد</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">تأیید و مصوب</span>
            <strong className="text-lg font-bold text-foreground font-mono">{approvedCount} مورد</strong>
          </div>
        </div>
      </div>

      {/* Filters Bar */}
      <RequestsFilterBar
        searchValue={search}
        onSearchChange={setSearch}
        statusValue={status}
        onStatusChange={setStatus}
        statusOptions={statusOptions}
        totalCount={rows.length}
        mode="incoming"
      />

      {/* Requests Table */}
      <RequestsTable
        rows={rows}
        mode="incoming"
        statusLabels={statusLabels}
        priorityLabels={priorityLabels}
        emptyText="هیچ درخواستی در این صندوق وارده یافت نشد."
      />
    </div>
  )
}
