import React, { useEffect, useState } from 'react'
import {
  LayoutDashboard,
  Plus,
  RotateCw,
  Sparkles
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { api, apiData, apiPage } from '@/lib/api-client'
import { DashboardKpis, type DashboardKpiData } from '../components/dashboard-kpis'
import { QuickActionsCard } from '../components/quick-actions-card'
import { RecentRequestsCard, type DashboardRequestItem } from '../components/recent-requests-card'
import { WorkflowGuideCard } from '../components/workflow-guide-card'
import type { DashboardUser } from '../types'

type DashboardPageProps = {
  user: DashboardUser | null
  statusLabels: Record<string, string>
}

export function DashboardPage({ user, statusLabels }: DashboardPageProps) {
  const isSystemAdmin = user?.roles?.some((r: any) => r.role === 'SYSTEM_ADMIN')
  const [data, setData] = useState<DashboardKpiData>({})
  const [requests, setRequests] = useState<DashboardRequestItem[]>([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const loadData = async (isManual = false) => {
    if (isManual) setRefreshing(true)
    try {
      const [dashRes, reqRes] = await Promise.all([
        api.get('/dashboard'),
        api.get('/meeting-requests'),
      ])
      setData(apiData(dashRes) || {})
      setRequests(apiPage(reqRes).data || [])
    } catch (err) {
      console.error('Error fetching dashboard data:', err)
    } finally {
      setLoading(false)
      if (isManual) setRefreshing(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  return (
    <div className="space-y-6">
      {/* Header */}
      <PageHeader
        title="داشبورد مدیریت جلسات"
        description="خلاصه آمار، کارتابل جاری و وضعیت درخواست‌ها در یک نگاه"
        action={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => loadData(true)}
              disabled={refreshing}
              className="gap-1.5 text-xs"
            >
              <RotateCw className={`size-3.5 ${refreshing ? 'animate-spin' : ''}`} />
              به‌روزرسانی
            </Button>
            {!isSystemAdmin && (
              <Button asChild size="sm" className="gap-1.5 text-xs text-white !text-white">
                <a href="/meeting-requests/create">
                  <Plus className="size-3.5 text-white" />
                  درخواست جلسه جدید
                </a>
              </Button>
            )}
          </div>
        }
      />

      {/* KPI Cards */}
      <DashboardKpis data={data} />

      {/* Main Grid: Recent Requests & Quick Actions */}
      <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <RecentRequestsCard
          requests={requests}
          statusLabels={statusLabels}
          canCreateRequest={!isSystemAdmin}
        />
        <QuickActionsCard canCreateRequest={!isSystemAdmin} />
      </div>

      {/* Workflow Guidance Card */}
      <WorkflowGuideCard />
    </div>
  )
}
