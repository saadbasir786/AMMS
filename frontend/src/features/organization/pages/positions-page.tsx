import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  BriefcaseBusiness,
  CheckCircle2,
  AlertCircle,
  Building2,
  Plus,
  RotateCw,
  Search,
  Pencil,
  Check
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { StatusBadge } from '@/components/amms/status-badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { api, apiData, apiError } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type { EmployeePosition, OrganizationalUnit } from '../types'

type PositionForm = {
  organizational_unit_id: string
  title: string
  code: string
  position_type: string
  status: 'ACTIVE' | 'INACTIVE'
}

const emptyForm: PositionForm = {
  organizational_unit_id: '',
  title: '',
  code: '',
  position_type: 'EMPLOYEE',
  status: 'ACTIVE',
}

const positionTypeLabels: Record<string, string> = {
  DIRECTOR: 'رئیس ریاست',
  DEPUTY_DIRECTOR: 'معاون ریاست',
  EXECUTIVE_MANAGEMENT: 'مدیر اجرائیه',
  EMPLOYEE: 'کارمند اداری / فنی',
  HIGH_AUTHORITY: 'مقام عالی',
  AUTHORITY_OFFICE: 'ریاست دفتر مقام',
  EXECUTIVE_SECRETARY: 'سکرتر اجرائیوی',
}

type PositionsPageProps = {
  embedded?: boolean
}

export function PositionsPage({ embedded = false }: PositionsPageProps) {
  const [positions, setPositions] = useState<EmployeePosition[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [form, setForm] = useState<PositionForm>(emptyForm)
  const [editing, setEditing] = useState<EmployeePosition | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Filters
  const [search, setSearch] = useState('')
  const [unitFilter, setUnitFilter] = useState('')
  const [typeFilter, setTypeFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [loadedPositions, unitsResponse] = await Promise.all([
        fetchAllAdminPages<EmployeePosition>('/admin/positions'),
        api.get('/admin/organizational-units'),
      ])
      setPositions(loadedPositions)
      setUnits(apiData(unitsResponse) || [])
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  // KPIs
  const activeCount = useMemo(
    () => positions.filter((p) => p.status !== 'INACTIVE').length,
    [positions]
  )
  const inactiveCount = useMemo(
    () => positions.filter((p) => p.status === 'INACTIVE').length,
    [positions]
  )
  const coveredUnitsCount = useMemo(() => {
    const unitIds = new Set(
      positions
        .map((p) => p.organizational_unit_id || p.unit?.id)
        .filter(Boolean)
    )
    return unitIds.size
  }, [positions])

  // Filtered list
  const filteredPositions = useMemo(() => {
    return positions.filter((pos) => {
      const q = search.trim().toLowerCase()
      const matchSearch =
        !q ||
        (pos.title || '').toLowerCase().includes(q) ||
        (pos.code || '').toLowerCase().includes(q) ||
        (pos.unit?.name || '').toLowerCase().includes(q)

      const matchUnit = unitFilter
        ? String(pos.organizational_unit_id || pos.unit?.id) === unitFilter
        : true

      const matchType = typeFilter ? pos.position_type === typeFilter : true

      const matchStatus = statusFilter
        ? (pos.status || 'ACTIVE') === statusFilter
        : true

      return matchSearch && matchUnit && matchType && matchStatus
    })
  }, [positions, search, unitFilter, typeFilter, statusFilter])

  const openCreateDialog = () => {
    setEditing(null)
    setForm(emptyForm)
    setDialogOpen(true)
  }

  const openEditDialog = (position: EmployeePosition) => {
    setEditing(position)
    setForm({
      organizational_unit_id: String(
        position.organizational_unit_id || position.unit?.id || ''
      ),
      title: position.title,
      code: position.code || '',
      position_type: position.position_type || 'EMPLOYEE',
      status: position.status || 'ACTIVE',
    })
    setDialogOpen(true)
  }

  const handleCloseDialog = () => {
    setDialogOpen(false)
    setEditing(null)
    setForm(emptyForm)
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = {
        ...form,
        organizational_unit_id: Number(form.organizational_unit_id),
        code: form.code.trim() || null,
      }
      if (editing) {
        await api.put(`/admin/positions/${editing.id}`, payload)
      } else {
        await api.post('/admin/positions', payload)
      }
      handleCloseDialog()
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      {embedded ? (
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-border/50">
          <div>
            <h3 className="text-sm font-bold text-foreground">سمت‌ها و بست‌های مصوب سازمانی</h3>
            <p className="text-[11px] text-muted-foreground">
              تعریف، رده‌بندی و تنظیم بست‌های رسمی تفکیک‌شده بر اساس ساختار تشکیلاتی
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              disabled={loading}
              className="h-8.5 text-xs font-semibold rounded-xl gap-1.5"
            >
              <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
              <span>تازه‌سازی</span>
            </Button>
            <Button
              size="sm"
              onClick={openCreateDialog}
              className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 shadow-xs"
            >
              <Plus className="size-3.5" />
              <span>ثبت سمت جدید</span>
            </Button>
          </div>
        </div>
      ) : (
        <PageHeader
          title="مدیریت سمت‌های سازمانی"
          description="ثبت، دسته‌بندی و نظارت بر پست‌ها و سمت‌های رسمی اداری تفکیک‌شده بر اساس ساختار تشکیلاتی"
          action={
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={loadData}
                disabled={loading}
                className="h-9 text-xs font-semibold rounded-xl gap-1.5"
              >
                <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
                <span>تازه‌سازی</span>
              </Button>
              <Button
                size="sm"
                onClick={openCreateDialog}
                className="h-9 text-xs font-semibold rounded-xl gap-1.5 shadow-xs"
              >
                <Plus className="size-3.5" />
                <span>ثبت سمت جدید</span>
              </Button>
            </div>
          }
        />
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
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <BriefcaseBusiness className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              کل سمت‌های مصوب
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {positions.length}
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              سمت‌های فعال
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {activeCount}
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-slate-500/10 text-slate-600 dark:text-slate-400 flex items-center justify-center shrink-0">
            <AlertCircle className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              سمت‌های غیرفعال
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {inactiveCount}
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
            <Building2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              واحدهای دارای سمت
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {coveredUnitsCount} واحد
            </strong>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            جست‌وجو در سمت‌ها
          </label>
          <div className="relative">
            <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="عنوان سمت یا کد شناسه..."
              className="h-9.5 pr-8 text-xs rounded-xl"
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            فیلتر واحد سازمانی
          </label>
          <select
            value={unitFilter}
            onChange={(e) => setUnitFilter(e.target.value)}
            className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-background px-3 text-foreground"
          >
            <option value="">همه واحدهای سازمانی</option>
            {units.map((unit) => (
              <option key={unit.id} value={unit.id}>
                {unit.name}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            فیلتر رده و نوع سمت
          </label>
          <select
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
            className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-background px-3 text-foreground"
          >
            <option value="">همه رده‌های سازمانی</option>
            {Object.entries(positionTypeLabels).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            فیلتر وضعیت
          </label>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-background px-3 text-foreground"
          >
            <option value="">همه وضعیت‌ها</option>
            <option value="ACTIVE">فعال</option>
            <option value="INACTIVE">غیرفعال</option>
          </select>
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-3.5 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/4" />
          <div className="space-y-2.5">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="h-14 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Positions Data Table */}
      {!loading && (
        <div className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs">
          {filteredPositions.length === 0 ? (
            <div className="text-center py-12 px-4">
              <BriefcaseBusiness className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
              <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
                سمتی مطابق با فیلترها یافت نشد
              </h4>
              <p className="text-[11px] text-muted-foreground">
                عنوان جستجو یا فیلترهای واحد و وضعیت را بازنشانی نمایید.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-right border-collapse">
                <thead>
                  <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                    <th className="py-3.5 px-4 font-bold">کد بست</th>
                    <th className="py-3.5 px-4 font-bold">عنوان سمت سازمانی</th>
                    <th className="py-3.5 px-4 font-bold">واحد سازمانی مربوطه</th>
                    <th className="py-3.5 px-4 font-bold">رده اداری</th>
                    <th className="py-3.5 px-4 font-bold">وضعیت</th>
                    <th className="py-3.5 px-4 font-bold text-center">عملیات</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/50">
                  {filteredPositions.map((pos) => (
                    <tr
                      key={pos.id}
                      className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                    >
                      <td className="py-3.5 px-4 font-mono">
                        {pos.code ? (
                          <Badge variant="outline" className="font-mono text-[10px] rounded-lg">
                            {pos.code}
                          </Badge>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="flex items-center gap-2.5">
                          <div className="size-7.5 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                            <BriefcaseBusiness className="size-3.5" />
                          </div>
                          <strong className="text-xs font-semibold text-foreground block">
                            {pos.title}
                          </strong>
                        </div>
                      </td>
                      <td className="py-3.5 px-4 text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                          <Building2 className="size-3.5 text-muted-foreground/60 shrink-0" />
                          <span className="font-medium text-foreground">
                            {pos.unit?.name || 'واحد نامشخص'}
                          </span>
                        </div>
                      </td>
                      <td className="py-3.5 px-4">
                        <Badge variant="secondary" className="rounded-lg text-[10px] font-normal">
                          {positionTypeLabels[pos.position_type || 'EMPLOYEE'] ||
                            pos.position_type ||
                            'کارمند'}
                        </Badge>
                      </td>
                      <td className="py-3.5 px-4">
                        <StatusBadge status={pos.status || 'ACTIVE'} />
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openEditDialog(pos)}
                          className="h-8 rounded-xl text-xs gap-1.5 hover:bg-primary/5 hover:text-primary"
                        >
                          <Pencil className="size-3.5" />
                          <span>اصلاح</span>
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Create / Edit Modal Dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-md rounded-2xl" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="text-sm font-bold">
              {editing ? 'اصلاح سمت سازمانی' : 'ثبت سمت سازمانی جدید'}
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              مشخصات پست و واحد تابعه را وارد نمایید. سمت سازمانی به‌تنهایی صلاحیت کاربری ایجاد نمی‌کند.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={submit} className="space-y-4 pt-1">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                واحد سازمانی <span className="text-destructive">*</span>
              </label>
              <select
                required
                value={form.organizational_unit_id}
                onChange={(e) =>
                  setForm((prev) => ({
                    ...prev,
                    organizational_unit_id: e.target.value,
                  }))
                }
                className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
              >
                <option value="">انتخاب واحد سازمانی مربوطه...</option>
                {units
                  .filter((u) => u.status !== 'INACTIVE')
                  .map((unit) => (
                    <option key={unit.id} value={unit.id}>
                      {unit.name}
                    </option>
                  ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                عنوان سمت سازمانی <span className="text-destructive">*</span>
              </label>
              <Input
                required
                value={form.title}
                onChange={(e) =>
                  setForm((prev) => ({ ...prev, title: e.target.value }))
                }
                placeholder="مثلاً: معاون ریاست، کارشناس شبکه، مدیر اداری..."
                className="h-9.5 rounded-xl text-xs"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  کد بست <span className="text-[10px] text-muted-foreground">(اختیاری)</span>
                </label>
                <Input
                  value={form.code}
                  onChange={(e) =>
                    setForm((prev) => ({ ...prev, code: e.target.value }))
                  }
                  placeholder="مثلاً: TECH-01"
                  className="h-9.5 rounded-xl font-mono text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  رده سازمانی
                </label>
                <select
                  value={form.position_type}
                  onChange={(e) =>
                    setForm((prev) => ({
                      ...prev,
                      position_type: e.target.value,
                    }))
                  }
                  className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                >
                  {Object.entries(positionTypeLabels).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {editing && (
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  وضعیت سمت
                </label>
                <select
                  value={form.status}
                  onChange={(e) =>
                    setForm((prev) => ({
                      ...prev,
                      status: e.target.value as PositionForm['status'],
                    }))
                  }
                  className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                >
                  <option value="ACTIVE">فعال</option>
                  <option value="INACTIVE">غیرفعال</option>
                </select>
              </div>
            )}

            <DialogFooter className="gap-2 pt-2 sm:justify-start">
              <Button
                type="submit"
                disabled={saving}
                className="h-9 text-xs font-semibold rounded-xl gap-1.5 flex-1"
              >
                {saving ? (
                  <RotateCw className="size-3.5 animate-spin" />
                ) : (
                  <Check className="size-3.5" />
                )}
                <span>{editing ? 'ثبت تغییرات' : 'ثبت سمت سازمانی'}</span>
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={saving}
                onClick={handleCloseDialog}
                className="h-9 text-xs rounded-xl"
              >
                انصراف
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
