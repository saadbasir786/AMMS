import React, { useEffect, useState, useCallback } from 'react'
import {
  Users,
  Search,
  RotateCw,
  AlertCircle,
  Building2,
  Fingerprint,
  UserCheck,
  ShieldCheck
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { EmployeesTable } from '../components/employees-table'
import type { Employee, OrganizationalUnit } from '../types'

type EmployeesPageProps = {
  isRolesView?: boolean
}

export function EmployeesPage({ isRolesView = false }: EmployeesPageProps) {
  const [employees, setEmployees] = useState<Employee[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [search, setSearch] = useState('')
  const [selectedUnit, setSelectedUnit] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [empRes, unitRes] = await Promise.all([
        api.get('/employees'),
        api.get('/organizational-units').catch(() => ({ data: [] })),
      ])
      setEmployees(apiData(empRes) || [])
      setUnits(apiData(unitRes) || [])
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات کارمندان با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const filteredEmployees = employees.filter((emp) => {
    const q = search.toLowerCase()
    const matchSearch =
      emp.full_name?.toLowerCase().includes(q) ||
      emp.employee_number?.toLowerCase().includes(q) ||
      emp.unit?.name?.toLowerCase().includes(q) ||
      emp.position?.title?.toLowerCase().includes(q)

    const matchUnit = selectedUnit
      ? String(emp.organizational_unit_id || emp.unit?.id) === selectedUnit
      : true

    return matchSearch && matchUnit
  })

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title={isRolesView ? 'مدیریت پرسنل و نقش‌های سازمانی' : 'فهرست کارمندان و اعضای سازمان'}
        description="مدیریت مشخصات پرسنلی، انتساب به ریاست‌ها، نقش‌ها و دسترسی‌های اداری"
        action={
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-xs px-2.5 py-1 text-primary border-primary/30">
              <Users className="size-3.5 ml-1" />
              <span>پرسنل فعال سازمان</span>
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

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <Users className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل کارمندان فعال</span>
            <strong className="text-lg font-bold text-foreground font-mono">{employees.length} نفر</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <ShieldCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">واحدهای سازمانی پوشش‌یافته</span>
            <strong className="text-lg font-bold text-foreground font-mono">{units.length} واحد</strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3 sm:col-span-1 col-span-2">
          <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <Fingerprint className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">آماده احراز هویت بایومتریک</span>
            <strong className="text-lg font-bold text-foreground font-mono">{employees.length} نفر</strong>
          </div>
        </div>
      </div>

      {/* Filters Bar */}
      <div className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            جستجو در مشخصات کارمند
          </label>
          <div className="relative">
            <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="جستجو بر اساس نام، شماره کارمندی، سمت یا ریاست..."
              className="h-9.5 pr-8 text-xs rounded-xl"
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            فیلتر بر اساس ریاست یا واحد
          </label>
          <select
            value={selectedUnit}
            onChange={(e) => setSelectedUnit(e.target.value)}
            className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
          >
            <option value="">همه ریاست‌ها و واحدها</option>
            {units.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name} ({u.code})
              </option>
            ))}
          </select>
        </div>
      </div>

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

      {/* Employees Table */}
      {!loading && <EmployeesTable employees={filteredEmployees} />}
    </div>
  )
}
