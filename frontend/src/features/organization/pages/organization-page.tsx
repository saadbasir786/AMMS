import React, { useEffect, useState, useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Building2,
  Network,
  RotateCw,
  AlertCircle,
  Search,
  Layers,
  BriefcaseBusiness,
  Users
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { cn } from '@/lib/utils'
import { api, apiData } from '@/lib/api-client'
import { OrganizationTreeCard } from '../components/organization-tree-card'
import { PositionsPage } from './positions-page'
import { EmployeesPage } from './employees-page'
import type { OrganizationalUnit } from '../types'

type TabType = 'units' | 'positions' | 'employees'

type OrganizationPageProps = {
  initialTab?: TabType
}

export function OrganizationPage({ initialTab }: OrganizationPageProps) {
  const [searchParams, setSearchParams] = useSearchParams()
  const urlTab = searchParams.get('tab') as TabType | null
  const activeTab: TabType = urlTab || initialTab || 'units'

  // Units tab data state
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/organizational-units')
      setUnits(apiData(res) || [])
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات ساختار تشکیلاتی با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    if (activeTab === 'units') {
      loadData()
    }
  }, [activeTab, loadData])

  const handleTabChange = (tab: TabType) => {
    setSearchParams({ tab })
  }

  // Hierarchy calculations for units tab
  const rootUnits = useMemo(() => units.filter((u) => !u.parent_id), [units])
  const totalSubUnits = useMemo(() => units.filter((u) => !!u.parent_id).length, [units])

  const filteredRoots = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return rootUnits
    return rootUnits.filter((root) => {
      const matchRoot =
        root.name?.toLowerCase().includes(q) || root.code?.toLowerCase().includes(q)
      const matchChildren = units.some(
        (c) =>
          c.parent_id === root.id &&
          (c.name?.toLowerCase().includes(q) || c.code?.toLowerCase().includes(q))
      )
      return matchRoot || matchChildren
    })
  }, [rootUnits, units, search])

  return (
    <div className="space-y-6" dir="rtl">
      {/* Main Hub Header */}
      <PageHeader
        title="تشکیلات اداری و مدیریت پرسنل"
        description="مرکز یکپارچه مدیریت چارت تشکیلاتی، بست‌های مصوب سازمانی و سوابق پرسنلی کارمندان"
      />

      {/* Hub Navigation Tabs */}
      <div className="flex items-center gap-1.5 p-1.5 rounded-2xl bg-muted/50 border border-border/70 overflow-x-auto w-full sm:w-fit shadow-xs">
        <button
          type="button"
          onClick={() => handleTabChange('units')}
          className={cn(
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
            activeTab === 'units'
              ? 'bg-card text-primary shadow-xs border border-border/70'
              : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
          )}
        >
          <Building2 className="size-4 shrink-0" />
          <span>چارت و ساختار سازمانی</span>
        </button>

        <button
          type="button"
          onClick={() => handleTabChange('positions')}
          className={cn(
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
            activeTab === 'positions'
              ? 'bg-card text-primary shadow-xs border border-border/70'
              : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
          )}
        >
          <BriefcaseBusiness className="size-4 shrink-0" />
          <span>سمت‌ها و بست‌های اداری</span>
        </button>

        <button
          type="button"
          onClick={() => handleTabChange('employees')}
          className={cn(
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
            activeTab === 'employees'
              ? 'bg-card text-primary shadow-xs border border-border/70'
              : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
          )}
        >
          <Users className="size-4 shrink-0" />
          <span>پرسنل و کارمندان</span>
        </button>
      </div>

      {/* Tab 1: Units Hierarchy */}
      {activeTab === 'units' && (
        <div className="space-y-6">
          {/* Sub Header & Actions */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-border/50">
            <div>
              <h3 className="text-sm font-bold text-foreground">
                چارت مصوب و سلسله‌مراتب واحدهای اداری
              </h3>
              <p className="text-[11px] text-muted-foreground">
                مشاهده و نظارت بر ساختار ریاست‌های اصلی، دیپارتمنت‌ها و مدیریت‌های تابعه
              </p>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              disabled={loading}
              className="h-8.5 text-xs font-semibold rounded-xl gap-1.5"
            >
              <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
              <span>تازه‌سازی چارت</span>
            </Button>
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
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
            <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
              <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <Building2 className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">
                  ریاست‌های اصلی
                </span>
                <strong className="text-lg font-bold text-foreground font-mono">
                  {rootUnits.length} ریاست
                </strong>
              </div>
            </div>

            <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
              <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
                <Network className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">
                  دیپارتمنت‌ها و مدیریت‌ها
                </span>
                <strong className="text-lg font-bold text-foreground font-mono">
                  {totalSubUnits} واحد تابعه
                </strong>
              </div>
            </div>

            <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3 sm:col-span-1 col-span-2">
              <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                <Layers className="size-4" />
              </div>
              <div>
                <span className="text-[10px] text-muted-foreground block font-medium">
                  کل ساختار فعال
                </span>
                <strong className="text-lg font-bold text-foreground font-mono">
                  {units.length} واحد اداری
                </strong>
              </div>
            </div>
          </div>

          {/* Search Bar */}
          <div className="flex items-center gap-3">
            <div className="relative flex-1 max-w-sm">
              <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="جستجو در عنوان یا کد واحد سازمانی..."
                className="h-9.5 pr-8 text-xs rounded-xl"
              />
            </div>
          </div>

          {/* Loading Skeleton */}
          {loading && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {[1, 2, 3, 4].map((i) => (
                <div
                  key={i}
                  className="h-44 bg-card border border-border/70 rounded-2xl animate-pulse"
                />
              ))}
            </div>
          )}

          {/* Hierarchy Grid */}
          {!loading && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {filteredRoots.length === 0 ? (
                <div className="col-span-2 text-center py-12 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50">
                  <Building2 className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
                  <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
                    واحدی با این مشخصات یافت نشد
                  </h4>
                  <p className="text-[11px] text-muted-foreground">
                    عبارت جستجو را تغییر دهید یا بررسی نمایید.
                  </p>
                </div>
              ) : (
                filteredRoots.map((root) => {
                  const children = units.filter((c) => c.parent_id === root.id)
                  return (
                    <OrganizationTreeCard
                      key={root.id}
                      rootUnit={root}
                      childUnits={children}
                    />
                  )
                })
              )}
            </div>
          )}
        </div>
      )}

      {/* Tab 2: Positions View */}
      {activeTab === 'positions' && <PositionsPage embedded={true} />}

      {/* Tab 3: Employees View */}
      {activeTab === 'employees' && <EmployeesPage embedded={true} />}
    </div>
  )
}
