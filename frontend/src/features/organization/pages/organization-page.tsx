import React, { useEffect, useState, useCallback } from 'react'
import {
  Building2,
  Network,
  RotateCw,
  AlertCircle,
  Search,
  Layers,
  ShieldCheck,
  Users
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { OrganizationTreeCard } from '../components/organization-tree-card'
import type { OrganizationalUnit } from '../types'

export function OrganizationPage() {
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
    loadData()
  }, [loadData])

  const rootUnits = units.filter((u) => !u.parent_id)
  const totalSubUnits = units.filter((u) => !!u.parent_id).length

  const filteredRoots = rootUnits.filter((root) => {
    const q = search.toLowerCase()
    const matchRoot = root.name?.toLowerCase().includes(q) || root.code?.toLowerCase().includes(q)
    const matchChildren = units.some(
      (c) =>
        c.parent_id === root.id &&
        (c.name?.toLowerCase().includes(q) || c.code?.toLowerCase().includes(q))
    )
    return matchRoot || matchChildren
  })

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="ساختار تشکیلاتی و سلسله‌مراتبی سازمان"
        description="مدیریت و مشاهده ریاست‌ها، مدیریت‌های اجرائیه و دیپارتمنت‌های فعال سازمانی"
        action={
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-xs px-2.5 py-1 text-primary border-primary/30">
              <Building2 className="size-3.5 ml-1" />
              <span>چارت مصوب اداری</span>
            </Badge>
          </div>
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
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <Building2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">ریاست‌های اصلی</span>
            <strong className="text-lg font-bold text-foreground font-mono">{rootUnits.length} ریاست</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Network className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">دیپارتمنت‌ها و مدیریت‌ها</span>
            <strong className="text-lg font-bold text-foreground font-mono">{totalSubUnits} واحد</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3 sm:col-span-1 col-span-2">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Layers className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل واحدهای فعال</span>
            <strong className="text-lg font-bold text-foreground font-mono">{units.length} واحد</strong>
          </div>
        </div>
      </div>

      {/* Search Bar */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-sm">
          <Search className="size-4 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="جستجو در عنوان یا کد ریاست/دیپارتمنت..."
            className="h-9.5 pr-9 text-xs rounded-xl"
          />
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-44 bg-card border border-border/70 rounded-2xl animate-pulse" />
          ))}
        </div>
      )}

      {/* Organization Grid */}
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
  )
}
