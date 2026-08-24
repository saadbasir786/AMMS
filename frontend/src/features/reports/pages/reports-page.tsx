import React, { useEffect, useState, useMemo, useCallback } from 'react'
import {
  BarChart3,
  FileSpreadsheet,
  FileText,
  Download,
  ShieldCheck,
  RotateCw,
  AlertCircle,
  TrendingUp,
  Table as TableIcon,
  CalendarCheck,
  FileArchive,
  Building2,
  Clock
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { formatSolarDate, formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData, apiPage } from '@/lib/api-client'
import { ReportsFilterBar, REPORT_CONFIGS } from '../components/reports-filter-bar'
import { ReportsKpiGrid } from '../components/reports-kpi-grid'
import { ReportsChartCard } from '../components/reports-chart-card'
import { ReportsDataTable } from '../components/reports-data-table'
import { ExportsListCard } from '../components/exports-list-card'
import type { ReportType, DashboardReportData, ExportJob } from '../types'

const ROLE_DASHBOARD_MAP: Record<string, string> = {
  EXECUTIVE_MANAGEMENT: '/reporting/dashboard/executive',
  HIGH_AUTHORITY: '/reporting/dashboard/executive',
  AUTHORITY_OFFICE: '/reporting/dashboard/authority',
  EXECUTIVE_SECRETARY: '/reporting/dashboard/authority',
  DIRECTOR: '/reporting/dashboard/directorate',
  EMPLOYEE: '/reporting/dashboard/personal',
}

function defaultRange() {
  const now = new Date()
  const from = new Date(now.getFullYear(), now.getMonth() - 5, 1)
  return {
    from: from.toISOString().slice(0, 10),
    to: now.toISOString().slice(0, 10),
  }
}

type ReportsPageProps = {
  user?: any
  exportsOnly?: boolean
}

export function ReportsPage({ user, exportsOnly = false }: ReportsPageProps) {
  const [activeTab, setActiveTab] = useState<'dashboard' | 'report' | 'exports'>(
    exportsOnly ? 'exports' : 'dashboard'
  )
  const [range, setRange] = useState(defaultRange)
  const [reportType, setReportType] = useState<ReportType>('MEETING_REGISTER')
  const [search, setSearch] = useState('')
  const [meetingStatus, setMeetingStatus] = useState('')

  // Data States
  const [dashboardData, setDashboardData] = useState<DashboardReportData | null>(null)
  const [rows, setRows] = useState<any[]>([])
  const [meta, setMeta] = useState<any>({})
  const [exportsList, setExportsList] = useState<ExportJob[]>([])

  const [loading, setLoading] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const userRole = user?.roles?.[0]?.role || 'EMPLOYEE'
  const dashboardUrl = ROLE_DASHBOARD_MAP[userRole] || '/reporting/dashboard/personal'
  const currentReportConfig =
    REPORT_CONFIGS.find((r) => r.id === reportType) || REPORT_CONFIGS[0]

  const queryParams = useMemo(
    () => ({
      from: range.from,
      to: range.to,
      search: search.trim() || undefined,
      meeting_status: meetingStatus || undefined,
      per_page: 25,
    }),
    [range, search, meetingStatus]
  )

  const loadDashboard = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(dashboardUrl, { params: queryParams })
      setDashboardData(apiData(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات داشبورد مدیریتی با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [dashboardUrl, queryParams])

  const loadReport = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(currentReportConfig.endpoint, { params: queryParams })
      const pageInfo = apiPage(res)
      setRows(pageInfo.data || [])
      setMeta(pageInfo.meta || {})
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات دفتر ثبت و گزارش با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [currentReportConfig, queryParams])

  const loadExports = useCallback(async () => {
    try {
      const res = await api.get('/reporting/exports')
      setExportsList(apiPage(res).data || [])
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'دریافت فهرست فایل‌های صادرات ممکن نیست.'
      )
    }
  }, [])

  useEffect(() => {
    if (activeTab === 'dashboard') loadDashboard()
    else if (activeTab === 'report') loadReport()
    else if (activeTab === 'exports') loadExports()
  }, [activeTab, loadDashboard, loadReport, loadExports])

  const handleCreateExport = async (format: 'PDF' | 'XLSX') => {
    setBusy(true)
    setError(null)
    try {
      await api.post('/reporting/exports', {
        ...queryParams,
        report_type: reportType,
        format,
      })
      setActiveTab('exports')
      await loadExports()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت درخواست تولید فایل با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Master Header */}
      <PageHeader
        title={exportsOnly ? 'مرکز خروجی‌ها و صادرات رسمی' : 'مرکز آمار، تحلیل‌ها و گزارش‌های رسمی'}
        description="محاسبه دقیق و بی‌درنگ شاخص‌های جلسات، تصمیمات، پیگیری مصوبات و دفاتر ثبت رسمی"
        action={
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-xs px-2.5 py-1 text-primary border-primary/30">
              <ShieldCheck className="size-3.5 ml-1" />
              <span>داده‌های مصوب و تغییرناپذیر</span>
            </Badge>
          </div>
        }
      />

      {/* Navigation Tabs */}
      <div className="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-muted/60 border border-border/70 w-fit">
        <button
          onClick={() => setActiveTab('dashboard')}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            activeTab === 'dashboard'
              ? 'bg-card text-foreground shadow-xs'
              : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          <BarChart3 className="size-4 text-primary" />
          <span>داشبورد تحلیلی و شاخص‌ها</span>
        </button>

        <button
          onClick={() => setActiveTab('report')}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            activeTab === 'report'
              ? 'bg-card text-foreground shadow-xs'
              : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          <TableIcon className="size-4 text-emerald-600" />
          <span>دفاتر ثبت و گزارش‌های تفصیلی</span>
        </button>

        <button
          onClick={() => setActiveTab('exports')}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            activeTab === 'exports'
              ? 'bg-card text-foreground shadow-xs'
              : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          <FileArchive className="size-4 text-indigo-600" />
          <span>مرکز صادرات فایل‌ها</span>
          {exportsList.length > 0 && (
            <Badge variant="secondary" className="font-mono text-[10px] px-1.5 py-0 mr-1">
              {exportsList.length}
            </Badge>
          )}
        </button>
      </div>

      {/* Filter Bar */}
      <ReportsFilterBar
        activeTab={activeTab}
        range={range}
        onRangeChange={setRange}
        reportType={reportType}
        onReportTypeChange={setReportType}
        search={search}
        onSearchChange={setSearch}
        meetingStatus={meetingStatus}
        onMeetingStatusChange={setMeetingStatus}
        busy={loading || busy}
        onRefresh={() => {
          if (activeTab === 'dashboard') loadDashboard()
          else if (activeTab === 'report') loadReport()
          else loadExports()
        }}
      />

      {/* Scope & Computation Metadata Strip */}
      {dashboardData && activeTab === 'dashboard' && (
        <div className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-border/50 text-[11px] text-muted-foreground">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1">
              <Building2 className="size-3.5 text-primary" />
              <span>
                حوزه گزارش: <strong>{dashboardData.scope?.organization_wide ? 'سازمان کل' : dashboardData.attribution_mode || 'محدوده مجاز سازمانی'}</strong>
              </span>
            </span>

            <span className="text-border">|</span>

            <span>
              بازه زمانی: <strong className="font-mono">{formatSolarDate(range.from)}</strong> تا <strong className="font-mono">{formatSolarDate(range.to)}</strong>
            </span>
          </div>

          {dashboardData.as_of && (
            <div className="flex items-center gap-1 font-mono text-[10px]">
              <Clock className="size-3 text-muted-foreground" />
              <span>به‌روزرسانی: {formatSolarDateTime(dashboardData.as_of)}</span>
            </div>
          )}
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
              onClick={() => {
                if (activeTab === 'dashboard') loadDashboard()
                else if (activeTab === 'report') loadReport()
                else loadExports()
              }}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Loading Skeleton */}
      {loading && activeTab === 'dashboard' && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-28 bg-card border border-border/70 rounded-2xl animate-pulse" />
            ))}
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div className="h-64 bg-card border border-border/70 rounded-2xl animate-pulse" />
            <div className="h-64 bg-card border border-border/70 rounded-2xl animate-pulse" />
          </div>
        </div>
      )}

      {/* Tab 1: Dashboard View */}
      {!loading && activeTab === 'dashboard' && dashboardData && (
        <div className="space-y-6">
          {/* KPI Cards Grid */}
          <ReportsKpiGrid cards={dashboardData.cards || []} />

          {/* Charts Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {(dashboardData.charts || []).map((chart) => (
              <ReportsChartCard key={chart.metric_id} chart={chart} />
            ))}
          </div>
        </div>
      )}

      {/* Tab 2: Detailed Registers View */}
      {activeTab === 'report' && (
        <ReportsDataTable
          reportTitle={currentReportConfig.title}
          rows={rows}
          meta={meta}
          loading={loading}
          busy={busy}
          onExportPdf={() => handleCreateExport('PDF')}
          onExportXlsx={() => handleCreateExport('XLSX')}
        />
      )}

      {/* Tab 3: Exports Center View */}
      {activeTab === 'exports' && (
        <ExportsListCard
          exports={exportsList}
          busy={busy}
          onRefresh={loadExports}
        />
      )}
    </div>
  )
}
