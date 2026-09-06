import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  Users,
  CheckCircle2,
  BriefcaseBusiness,
  AlertCircle,
  Plus,
  RotateCw,
  Search,
  UserPlus,
  Building2,
  Check
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
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
import { EmployeesTable } from '../components/employees-table'
import type { Employee, EmployeePosition, OrganizationalUnit } from '../types'

type EmployeeForm = {
  employee_number: string
  first_name: string
  last_name: string
  father_name: string
  organizational_unit_id: string
  position_id: string
  email: string
  phone: string
  status: 'ACTIVE' | 'INACTIVE'
}

const emptyForm: EmployeeForm = {
  employee_number: '',
  first_name: '',
  last_name: '',
  father_name: '',
  organizational_unit_id: '',
  position_id: '',
  email: '',
  phone: '',
  status: 'ACTIVE',
}

type EmployeesPageProps = {
  embedded?: boolean
}

export function EmployeesPage({ embedded = false }: EmployeesPageProps) {
  const [employees, setEmployees] = useState<Employee[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [positions, setPositions] = useState<EmployeePosition[]>([])
  const [form, setForm] = useState<EmployeeForm>(emptyForm)
  const [editing, setEditing] = useState<Employee | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [confirmDeactivation, setConfirmDeactivation] = useState(false)

  // Filters
  const [search, setSearch] = useState('')
  const [unitFilter, setUnitFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [loadedEmployees, unitResponse, loadedPositions] = await Promise.all([
        fetchAllAdminPages<Employee>('/admin/employees'),
        api.get('/admin/organizational-units'),
        fetchAllAdminPages<EmployeePosition>('/admin/positions'),
      ])
      setEmployees(loadedEmployees)
      setUnits(apiData(unitResponse) || [])
      setPositions(loadedPositions)
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
    () => employees.filter((e) => e.status !== 'INACTIVE').length,
    [employees]
  )
  const withPositionCount = useMemo(
    () => employees.filter((e) => !!e.position_id || !!e.position?.title).length,
    [employees]
  )
  const inactiveCount = useMemo(
    () => employees.filter((e) => e.status === 'INACTIVE').length,
    [employees]
  )

  // Positions available for the selected unit in the dialog
  const availablePositions = useMemo(() => {
    if (!form.organizational_unit_id) return []
    return positions.filter(
      (position) =>
        String(position.organizational_unit_id || position.unit?.id) ===
          form.organizational_unit_id && position.status !== 'INACTIVE'
    )
  }, [form.organizational_unit_id, positions])

  // Filtered employees
  const filteredEmployees = useMemo(() => {
    return employees.filter((employee) => {
      const q = search.trim().toLowerCase()
      const matchSearch =
        !q ||
        [
          employee.full_name,
          employee.employee_number,
          employee.unit?.name,
          employee.position?.title,
          employee.email,
        ]
          .filter(Boolean)
          .some((val) => val!.toLowerCase().includes(q))

      const matchUnit = unitFilter
        ? String(employee.organizational_unit_id || employee.unit?.id) === unitFilter
        : true

      const matchStatus = statusFilter
        ? (employee.status || 'ACTIVE') === statusFilter
        : true

      return matchSearch && matchUnit && matchStatus
    })
  }, [employees, search, unitFilter, statusFilter])

  const openCreateDialog = () => {
    setEditing(null)
    setForm(emptyForm)
    setDialogOpen(true)
  }

  const openEditDialog = (employee: Employee) => {
    setEditing(employee)
    setForm({
      employee_number: employee.employee_number,
      first_name: employee.first_name || employee.full_name.split(' ')[0] || '',
      last_name:
        employee.last_name || employee.full_name.split(' ').slice(1).join(' ') || '',
      father_name: employee.father_name || '',
      organizational_unit_id: String(
        employee.organizational_unit_id || employee.unit?.id || ''
      ),
      position_id: String(employee.position?.id || employee.position_id || ''),
      email: employee.email || '',
      phone: employee.phone || '',
      status: employee.status === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
    })
    setDialogOpen(true)
  }

  const handleCloseDialog = () => {
    setDialogOpen(false)
    setEditing(null)
    setForm(emptyForm)
  }

  const updateField = <Key extends keyof EmployeeForm>(
    field: Key,
    value: EmployeeForm[Key]
  ) => setForm((current) => ({ ...current, [field]: value }))

  const save = async () => {
    setSaving(true)
    setError(null)
    try {
      const payload = {
        ...form,
        organizational_unit_id: Number(form.organizational_unit_id),
        position_id: form.position_id ? Number(form.position_id) : null,
        father_name: form.father_name.trim() || null,
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
      }
      if (editing) {
        await api.put(`/admin/employees/${editing.id}`, payload)
      } else {
        await api.post('/admin/employees', payload)
      }
      handleCloseDialog()
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    if (editing?.status !== 'INACTIVE' && form.status === 'INACTIVE') {
      setConfirmDeactivation(true)
      return
    }
    void save()
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      {embedded ? (
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-border/50">
          <div>
            <h3 className="text-sm font-bold text-foreground">فهرست پرسنل و کارمندان اداری</h3>
            <p className="text-[11px] text-muted-foreground">
              سوابق پرسنلی، انتساب به بست رسمی و وضعیت فعالیت کارمندان سازمان
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
              <UserPlus className="size-3.5" />
              <span>ثبت کارمند جدید</span>
            </Button>
          </div>
        </div>
      ) : (
        <PageHeader
          title="مدیریت کارمندان و پرسنل"
          description="ثبت سوابق پرسنلی، انتساب واحد تشکیلاتی، بست اداری و نظارت بر فعال بودن کارمندان"
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
                <UserPlus className="size-3.5" />
                <span>ثبت کارمند جدید</span>
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
            <Users className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              کل کارمندان ثبت‌شده
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {employees.length} نفر
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
            <CheckCircle2 className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              کارمندان فعال
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {activeCount} نفر
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
            <BriefcaseBusiness className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              دارای سمت رسمی
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {withPositionCount} نفر
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-slate-500/10 text-slate-600 dark:text-slate-400 flex items-center justify-center shrink-0">
            <AlertCircle className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              غیرفعال / منفصل
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {inactiveCount} نفر
            </strong>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            جست‌وجوی پرسنل
          </label>
          <div className="relative">
            <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="جستجو در نام، شماره کارمندی، ایمیل، سمت..."
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
            فیلتر وضعیت کارمند
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

      {/* Table Component */}
      {!loading && (
        <EmployeesTable
          employees={filteredEmployees}
          onEdit={openEditDialog}
        />
      )}

      {/* Create / Edit Employee Dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-lg rounded-2xl" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="text-sm font-bold">
              {editing ? 'ویرایش مشخصات کارمند' : 'ثبت کارمند اداری جدید'}
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              اطلاعات پرسنلی، واحد تابعه و سمت اداری کارمند را در این فرم تکمیل نمایید.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={submit} className="space-y-4 pt-1">
            {/* Name fields */}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  نام <span className="text-destructive">*</span>
                </label>
                <Input
                  required
                  value={form.first_name}
                  onChange={(e) => updateField('first_name', e.target.value)}
                  className="h-9.5 rounded-xl text-xs"
                  placeholder="نام کارمند..."
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  تخلص <span className="text-destructive">*</span>
                </label>
                <Input
                  required
                  value={form.last_name}
                  onChange={(e) => updateField('last_name', e.target.value)}
                  className="h-9.5 rounded-xl text-xs"
                  placeholder="تخلص کارمند..."
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  شماره کارمندی <span className="text-destructive">*</span>
                </label>
                <Input
                  required
                  value={form.employee_number}
                  onChange={(e) => updateField('employee_number', e.target.value)}
                  placeholder="EMP-TECH-001"
                  className="h-9.5 rounded-xl font-mono text-xs"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  نام پدر <span className="text-[10px] text-muted-foreground">(اختیاری)</span>
                </label>
                <Input
                  value={form.father_name}
                  onChange={(e) => updateField('father_name', e.target.value)}
                  className="h-9.5 rounded-xl text-xs"
                  placeholder="نام پدر..."
                />
              </div>
            </div>

            {/* Unit and Position */}
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
                    position_id: '',
                  }))
                }
                className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
              >
                <option value="">انتخاب واحد سازمانی...</option>
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
                سمت سازمانی اداری
              </label>
              <select
                value={form.position_id}
                disabled={!form.organizational_unit_id}
                onChange={(e) => updateField('position_id', e.target.value)}
                className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <option value="">بدون سمت مصوب (تعیین بعداً)</option>
                {availablePositions.map((pos) => (
                  <option key={pos.id} value={pos.id}>
                    {pos.title} {pos.code ? `(${pos.code})` : ''}
                  </option>
                ))}
              </select>
            </div>

            {/* Contact info */}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  ایمیل رسمی <span className="text-[10px] text-muted-foreground">(اختیاری)</span>
                </label>
                <Input
                  type="email"
                  value={form.email}
                  onChange={(e) => updateField('email', e.target.value)}
                  placeholder="employee@domain.gov.af"
                  className="h-9.5 rounded-xl font-mono text-xs"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  شماره تماس <span className="text-[10px] text-muted-foreground">(اختیاری)</span>
                </label>
                <Input
                  value={form.phone}
                  onChange={(e) => updateField('phone', e.target.value)}
                  placeholder="07xxxxxxxx"
                  className="h-9.5 rounded-xl font-mono text-xs"
                />
              </div>
            </div>

            {editing && (
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  وضعیت کارمند
                </label>
                <select
                  value={form.status}
                  onChange={(e) =>
                    updateField('status', e.target.value as EmployeeForm['status'])
                  }
                  className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                >
                  <option value="ACTIVE">فعال</option>
                  <option value="INACTIVE">غیرفعال (منفصل)</option>
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
                <span>{editing ? 'ثبت تغییرات' : 'ثبت کارمند جدید'}</span>
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

      {/* Deactivation Confirmation Dialog */}
      <ConfirmDialog
        open={confirmDeactivation}
        onOpenChange={setConfirmDeactivation}
        title="تأیید غیرفعال‌سازی کارمند"
        description="با غیرفعال‌سازی این کارمند، هرگونه حساب کاربری وابسته به وی بلافاصله تعلیق خواهد شد و دسترسی‌های عملیاتی وی سلب می‌گردد."
        confirmLabel="غیرفعال‌سازی کارمند"
        variant="destructive"
        onConfirm={save}
      />
    </div>
  )
}
