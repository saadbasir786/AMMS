import React, { useCallback, useEffect, useMemo, useState } from 'react'
import {
  Users,
  ShieldCheck,
  UserCog,
  AlertCircle,
  Plus,
  RotateCw,
  Search,
  KeyRound,
  History,
  Pencil,
  Trash2,
  Check,
  Building2,
  Mail,
  UserCheck
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { StatusBadge } from '@/components/amms/status-badge'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
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
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData, apiError, apiPage } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type {
  AdminUser,
  Employee,
  OrganizationalUnit,
  UserAccessAudit,
  UserRoleScope,
} from '../types'

const roleLabels: Record<string, string> = {
  SYSTEM_ADMIN: 'مدیر سیستم (فنی)',
  EMPLOYEE: 'کارمند',
  DIRECTOR: 'رئیس اداره',
  DEPUTY_DIRECTOR: 'معاون اداره',
  EXECUTIVE_MANAGEMENT: 'مدیریت اجرائیه',
  AUTHORITY_OFFICE: 'دفتر مقام عالی',
  EXECUTIVE_SECRETARY: 'سکرتریت مقام عالی',
  HIGH_AUTHORITY: 'مقام عالی',
}

const auditLabels: Record<string, string> = {
  ACCOUNT_CREATED: 'ایجاد حساب کاربری',
  ACCOUNT_UPDATED: 'ویرایش مشخصات حساب',
  ACCOUNT_DEACTIVATED: 'غیرفعال‌سازی حساب',
  ACCOUNT_ACTIVATED: 'فعال‌سازی مجدد حساب',
  PASSWORD_CHANGED: 'تغییر رمز عبور',
  ROLE_ASSIGNED: 'واگذاری نقش سازمانی',
  ROLE_REVOKED: 'لغو نقش سازمانی',
  CAPABILITY_GRANTED: 'اعطای صلاحیت تفویض‌شده',
  CAPABILITY_REVOKED: 'لغو صلاحیت تفویض‌شده',
}

type AccountForm = {
  name: string
  email: string
  password: string
  employee_id: string
  status: 'ACTIVE' | 'INACTIVE'
}

const emptyForm: AccountForm = {
  name: '',
  email: '',
  password: '',
  employee_id: '',
  status: 'ACTIVE',
}

export function UsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([])
  const [employees, setEmployees] = useState<Employee[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [audits, setAudits] = useState<UserAccessAudit[]>([])

  // Selection & Dialogs
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null)
  const [accountDialogOpen, setAccountDialogOpen] = useState(false)
  const [rolesDialogOpen, setRolesDialogOpen] = useState(false)
  const [auditDialogOpen, setAuditDialogOpen] = useState(false)

  // Forms
  const [form, setForm] = useState<AccountForm>(emptyForm)
  const [newRole, setNewRole] = useState('EMPLOYEE')
  const [newRoleUnitId, setNewRoleUnitId] = useState('')

  // State
  const [loading, setLoading] = useState(true)
  const [auditsLoading, setAuditsLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Confirmations
  const [revokeTarget, setRevokeTarget] = useState<UserRoleScope | null>(null)
  const [confirmDeactivation, setConfirmDeactivation] = useState(false)

  // Filters
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [loadedUsers, loadedEmployees, unitsResponse] = await Promise.all([
        fetchAllAdminPages<AdminUser>('/admin/users'),
        fetchAllAdminPages<Employee>('/admin/employees'),
        api.get('/admin/organizational-units'),
      ])
      setUsers(loadedUsers)
      setEmployees(loadedEmployees)
      setUnits(apiData(unitsResponse) || [])

      // Update selectedUser if currently open in any dialog
      setSelectedUser((current) =>
        current ? loadedUsers.find((u: AdminUser) => u.id === current.id) || null : null
      )
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  const loadAudits = useCallback(async (userId: number) => {
    setAuditsLoading(true)
    try {
      const res = await api.get(`/admin/users/${userId}/access-audits`)
      setAudits(apiPage(res)?.data || [])
    } catch (err) {
      setError(apiError(err))
    } finally {
      setAuditsLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadData()
  }, [loadData])

  // KPIs
  const activeCount = useMemo(
    () => users.filter((u) => u.status !== 'INACTIVE').length,
    [users]
  )
  const withEmployeeCount = useMemo(
    () => users.filter((u) => !!u.employee_id || !!u.employee).length,
    [users]
  )
  const inactiveCount = useMemo(
    () => users.filter((u) => u.status === 'INACTIVE').length,
    [users]
  )

  // Filtered users
  const filteredUsers = useMemo(() => {
    return users.filter((user) => {
      const q = search.trim().toLowerCase()
      const matchSearch =
        !q ||
        [user.name, user.email, user.employee?.full_name, user.employee?.employee_number]
          .filter(Boolean)
          .some((val) => val!.toLowerCase().includes(q))

      const matchRole = roleFilter
        ? (user.roles || []).some((r) => r.role === roleFilter)
        : true

      const matchStatus = statusFilter
        ? (user.status || 'ACTIVE') === statusFilter
        : true

      return matchSearch && matchRole && matchStatus
    })
  }, [users, search, roleFilter, statusFilter])

  // Role options when assigning role to selected user
  const selectableRoles = useMemo(() => {
    if (!selectedUser?.employee) return ['SYSTEM_ADMIN']
    if (selectedUser.roles?.some((s) => s.role === 'SYSTEM_ADMIN')) return ['SYSTEM_ADMIN']
    const positionRole = selectedUser.employee.position?.position_type
    return [
      'EMPLOYEE',
      ...(positionRole && positionRole !== 'EMPLOYEE' && roleLabels[positionRole]
        ? [positionRole]
        : []),
    ]
  }, [selectedUser])

  const selectableUnits = useMemo(() => {
    const scopeIds = new Set(selectedUser?.employee?.unit_scope_ids || [])
    return units.filter((unit) => unit.status !== 'INACTIVE' && scopeIds.has(unit.id))
  }, [selectedUser, units])

  useEffect(() => {
    if (!selectableRoles.includes(newRole)) {
      setNewRole(selectableRoles[0] || 'EMPLOYEE')
      setNewRoleUnitId('')
    }
  }, [newRole, selectableRoles])

  // Dialog open handlers
  const openCreateAccountDialog = () => {
    setSelectedUser(null)
    setForm(emptyForm)
    setAccountDialogOpen(true)
  }

  const openEditAccountDialog = (user: AdminUser) => {
    setSelectedUser(user)
    setForm({
      name: user.name,
      email: user.email,
      password: '',
      employee_id: user.employee_id ? String(user.employee_id) : '',
      status: user.status || 'ACTIVE',
    })
    setAccountDialogOpen(true)
  }

  const openManageRolesDialog = (user: AdminUser) => {
    setSelectedUser(user)
    setNewRole('EMPLOYEE')
    setNewRoleUnitId('')
    setRolesDialogOpen(true)
  }

  const openAuditDialog = (user: AdminUser) => {
    setSelectedUser(user)
    setAuditDialogOpen(true)
    void loadAudits(user.id)
  }

  // Handle employee selection in account creation
  const handleEmployeeChange = (empId: string) => {
    setForm((prev) => {
      const found = employees.find((e) => String(e.id) === empId)
      return {
        ...prev,
        employee_id: empId,
        name: prev.name || found?.full_name || '',
        email: prev.email || found?.email || '',
      }
    })
  }

  // Account submit
  const saveAccount = async () => {
    setSaving(true)
    setError(null)
    try {
      const payload = {
        ...form,
        employee_id: form.employee_id ? Number(form.employee_id) : null,
        password: form.password || undefined,
      }
      if (selectedUser) {
        await api.put(`/admin/users/${selectedUser.id}`, payload)
      } else {
        await api.post('/admin/users', payload)
      }
      setAccountDialogOpen(false)
      setSelectedUser(null)
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const handleAccountSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (selectedUser?.status !== 'INACTIVE' && form.status === 'INACTIVE') {
      setConfirmDeactivation(true)
      return
    }
    void saveAccount()
  }

  // Role assignment & revocation
  const assignRole = async () => {
    if (!selectedUser) return
    setSaving(true)
    setError(null)
    try {
      await api.post(`/admin/users/${selectedUser.id}/roles`, {
        role: newRole,
        organizational_unit_id:
          newRole === 'SYSTEM_ADMIN' ? null : Number(newRoleUnitId),
      })
      setNewRoleUnitId('')
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const revokeRole = async () => {
    if (!selectedUser || !revokeTarget) return
    setSaving(true)
    setError(null)
    try {
      await api.delete(`/admin/users/${selectedUser.id}/roles/${revokeTarget.id}`)
      setRevokeTarget(null)
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        title="حساب‌های کاربری و مدیریت صلاحیت‌ها"
        description="ایجاد حساب‌های کاربری سیستم، اتصال به پرونده پرسنلی، و انتساب نقش‌های مقید به حیطه سازمانی"
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
              onClick={openCreateAccountDialog}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 shadow-xs"
            >
              <Plus className="size-3.5" />
              <span>ایجاد حساب کاربری</span>
            </Button>
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
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Users className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              کل حساب‌های کاربری
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {users.length} حساب
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
            <ShieldCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              حساب‌های فعال
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {activeCount}
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
            <UserCog className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              متصل به کارمند سازمانی
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {withEmployeeCount}
            </strong>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-9 rounded-xl bg-slate-500/10 text-slate-600 dark:text-slate-400 flex items-center justify-center shrink-0">
            <AlertCircle className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">
              حساب‌های غیرفعال
            </span>
            <strong className="text-lg font-bold text-foreground font-mono">
              {inactiveCount}
            </strong>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            جست‌وجوی حساب
          </label>
          <div className="relative">
            <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="جستجو در نام، ایمیل ورود، کارمند..."
              className="h-9.5 pr-8 text-xs rounded-xl"
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            فیلتر بر اساس نقش
          </label>
          <select
            value={roleFilter}
            onChange={(e) => setRoleFilter(e.target.value)}
            className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-background px-3 text-foreground"
          >
            <option value="">همه نقش‌های سیستمی</option>
            {Object.entries(roleLabels).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            فیلتر وضعیت حساب
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

      {/* Accounts Table */}
      {!loading && (
        <div className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs">
          {filteredUsers.length === 0 ? (
            <div className="text-center py-12 px-4">
              <Users className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
              <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
                حساب کاربری یافت نشد
              </h4>
              <p className="text-[11px] text-muted-foreground">
                عبارت جستجو یا فیلترهای نقش و وضعیت را بازنشانی نمایید.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-right border-collapse">
                <thead>
                  <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                    <th className="py-3.5 px-4 font-bold">نام کاربر</th>
                    <th className="py-3.5 px-4 font-bold">ایمیل ورود</th>
                    <th className="py-3.5 px-4 font-bold">کارمند متصل سازمانی</th>
                    <th className="py-3.5 px-4 font-bold">نقش‌ها و حوزه‌های مقید</th>
                    <th className="py-3.5 px-4 font-bold">وضعیت</th>
                    <th className="py-3.5 px-4 font-bold text-center">عملیات اداری</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/50">
                  {filteredUsers.map((user) => (
                    <tr
                      key={user.id}
                      className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                    >
                      <td className="py-3.5 px-4 whitespace-nowrap">
                        <div className="flex items-center gap-2.5">
                          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                            {user.name.charAt(0)}
                          </div>
                          <div>
                            <strong className="text-xs font-semibold text-foreground block">
                              {user.name}
                            </strong>
                            <span className="text-[10px] text-muted-foreground font-mono">
                              شناسه #{user.id}
                            </span>
                          </div>
                        </div>
                      </td>

                      <td className="py-3.5 px-4 font-mono text-muted-foreground whitespace-nowrap">
                        <div className="flex items-center gap-1.5">
                          <Mail className="size-3 text-muted-foreground/60" />
                          <span>{user.email}</span>
                        </div>
                      </td>

                      <td className="py-3.5 px-4">
                        {user.employee ? (
                          <div className="space-y-0.5">
                            <strong className="text-xs text-foreground block">
                              {user.employee.full_name}
                            </strong>
                            <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                              <Building2 className="size-2.5" />
                              {user.employee.unit?.name || 'واحد نامشخص'} ·{' '}
                              <span className="font-mono">
                                {user.employee.employee_number}
                              </span>
                            </span>
                          </div>
                        ) : (
                          <Badge variant="outline" className="text-[10px] text-muted-foreground">
                            حساب فنی / بدون کارمند
                          </Badge>
                        )}
                      </td>

                      <td className="py-3.5 px-4">
                        <div className="flex flex-wrap gap-1.5 max-w-sm">
                          {(user.roles || []).length === 0 ? (
                            <span className="text-[10px] text-muted-foreground italic">
                              بدون نقش تعریف‌شده
                            </span>
                          ) : (
                            user.roles?.map((scope) => (
                              <Badge
                                key={scope.id}
                                variant="secondary"
                                className="rounded-lg text-[10px] font-normal py-0.5"
                              >
                                <span>{roleLabels[scope.role] || scope.role}</span>
                                {scope.unit && (
                                  <span className="text-muted-foreground mr-1 text-[9px]">
                                    ({scope.unit.name})
                                  </span>
                                )}
                              </Badge>
                            ))
                          )}
                        </div>
                      </td>

                      <td className="py-3.5 px-4 whitespace-nowrap">
                        <StatusBadge status={user.status || 'ACTIVE'} />
                      </td>

                      <td className="py-3.5 px-4 text-center whitespace-nowrap">
                        <div className="flex items-center justify-center gap-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openManageRolesDialog(user)}
                            className="h-8 rounded-xl text-xs gap-1 hover:bg-primary/5 hover:text-primary"
                            title="مدیریت نقش‌های مقید"
                          >
                            <KeyRound className="size-3.5" />
                            <span>نقش‌ها</span>
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEditAccountDialog(user)}
                            className="h-8 rounded-xl text-xs gap-1 hover:bg-primary/5 hover:text-primary"
                            title="اصلاح مشخصات"
                          >
                            <Pencil className="size-3.5" />
                            <span>اصلاح</span>
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openAuditDialog(user)}
                            className="h-8 rounded-xl text-xs gap-1 hover:bg-primary/5 hover:text-primary"
                            title="تاریخچه دسترسی"
                          >
                            <History className="size-3.5" />
                            <span>ممیزی</span>
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Account Dialog: Create / Edit */}
      <Dialog open={accountDialogOpen} onOpenChange={setAccountDialogOpen}>
        <DialogContent className="sm:max-w-md rounded-2xl" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="text-sm font-bold">
              {selectedUser ? 'اصلاح حساب کاربری' : 'ایجاد حساب کاربری جدید'}
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              حساب کاربری را به کارمند فعال متصل نموده یا به صورت اکانت فنی ایجاد نمایید.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleAccountSubmit} className="space-y-3.5 pt-1">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                کارمند مرتبط
              </label>
              <select
                value={form.employee_id}
                onChange={(e) => handleEmployeeChange(e.target.value)}
                className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
              >
                <option value="">حساب فنی / بدون کارمند سازمانی</option>
                {employees
                  .filter(
                    (e) =>
                      e.status !== 'INACTIVE' &&
                      (!e.email ||
                        !users.some(
                          (u) => u.employee_id === e.id && u.id !== selectedUser?.id
                        ))
                  )
                  .map((emp) => (
                    <option key={emp.id} value={emp.id}>
                      {emp.full_name} · {emp.unit?.name || 'واحد نامشخص'} (
                      {emp.employee_number})
                    </option>
                  ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                نام نمایشی <span className="text-destructive">*</span>
              </label>
              <Input
                required
                value={form.name}
                onChange={(e) =>
                  setForm((prev) => ({ ...prev, name: e.target.value }))
                }
                placeholder="نام کامل کاربر..."
                className="h-9.5 rounded-xl text-xs"
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                ایمیل رسمی ورود <span className="text-destructive">*</span>
              </label>
              <Input
                required
                type="email"
                value={form.email}
                onChange={(e) =>
                  setForm((prev) => ({ ...prev, email: e.target.value }))
                }
                placeholder="user@domain.gov.af"
                className="h-9.5 rounded-xl font-mono text-xs"
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                رمز عبور{' '}
                {selectedUser ? (
                  <span className="text-[10px] font-normal text-muted-foreground">
                    (جهت ابقای رمز فعلی خالی بگذارید)
                  </span>
                ) : (
                  <span className="text-destructive">*</span>
                )}
              </label>
              <Input
                required={!selectedUser}
                type="password"
                value={form.password}
                onChange={(e) =>
                  setForm((prev) => ({ ...prev, password: e.target.value }))
                }
                placeholder={selectedUser ? '••••••••' : 'رمز عبور قوی...'}
                className="h-9.5 rounded-xl text-xs"
              />
            </div>

            {selectedUser && (
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  وضعیت حساب
                </label>
                <select
                  value={form.status}
                  onChange={(e) =>
                    setForm((prev) => ({
                      ...prev,
                      status: e.target.value as AccountForm['status'],
                    }))
                  }
                  className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                >
                  <option value="ACTIVE">فعال</option>
                  <option value="INACTIVE">غیرفعال (مسدود)</option>
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
                <span>{selectedUser ? 'ثبت تغییرات' : 'ایجاد حساب کاربری'}</span>
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={saving}
                onClick={() => setAccountDialogOpen(false)}
                className="h-9 text-xs rounded-xl"
              >
                انصراف
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Manage Roles Dialog */}
      <Dialog open={rolesDialogOpen} onOpenChange={setRolesDialogOpen}>
        <DialogContent className="sm:max-w-lg rounded-2xl" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="text-sm font-bold flex items-center gap-2">
              <KeyRound className="size-4 text-primary" />
              <span>نقش‌ها و حوزه‌های دسترسی: {selectedUser?.name}</span>
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              نقش‌های سازمانی باید دقیقاً متناسب با بست پرسنلی و مقید به واحد سازمانی باشند.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 pt-2">
            {/* Existing roles */}
            <div className="space-y-2">
              <label className="text-xs font-bold text-foreground block">
                نقش‌های فعال کاربر
              </label>
              {(selectedUser?.roles || []).length === 0 ? (
                <p className="text-xs text-muted-foreground p-3 rounded-xl border border-dashed border-border/70 text-center">
                  هیچ نقشی برای این حساب ثبت نشده است.
                </p>
              ) : (
                <div className="space-y-2 max-h-48 overflow-y-auto">
                  {selectedUser?.roles?.map((scope) => (
                    <div
                      key={scope.id}
                      className="flex items-center justify-between p-2.5 rounded-xl border border-border/60 bg-muted/20 text-xs"
                    >
                      <div>
                        <strong className="text-foreground">
                          {roleLabels[scope.role] || scope.role}
                        </strong>
                        <span className="text-muted-foreground mr-1.5 text-[11px]">
                          {scope.unit ? `· حوزه: ${scope.unit.name}` : '· دسترسی سراسری'}
                        </span>
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        disabled={saving}
                        onClick={() => setRevokeTarget(scope)}
                        className="h-7 text-xs text-destructive hover:bg-destructive/10 rounded-lg gap-1"
                      >
                        <Trash2 className="size-3.5" />
                        <span>لغو نقش</span>
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Assign new role section */}
            <div className="p-3.5 rounded-xl border border-border/70 bg-card space-y-3">
              <strong className="text-xs font-bold text-foreground block">
                افزودن نقش سازمانی جدید
              </strong>

              <div className="space-y-1.5">
                <label className="text-xs text-muted-foreground block">
                  انتخاب نقش
                </label>
                <select
                  value={newRole}
                  onChange={(e) => {
                    setNewRole(e.target.value)
                    setNewRoleUnitId('')
                  }}
                  className="h-9 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                >
                  {selectableRoles.map((r) => (
                    <option key={r} value={r}>
                      {roleLabels[r] || r}
                    </option>
                  ))}
                </select>
              </div>

              {newRole !== 'SYSTEM_ADMIN' && (
                <div className="space-y-1.5">
                  <label className="text-xs text-muted-foreground block">
                    واحد سازمانی تحت صلاحیت
                  </label>
                  <select
                    required
                    value={newRoleUnitId}
                    onChange={(e) => setNewRoleUnitId(e.target.value)}
                    className="h-9 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                  >
                    <option value="">انتخاب واحد سازمانی...</option>
                    {selectableUnits.map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <Button
                type="button"
                disabled={
                  saving || (newRole !== 'SYSTEM_ADMIN' && !newRoleUnitId)
                }
                onClick={assignRole}
                className="h-9 w-full rounded-xl text-xs font-semibold gap-1.5"
              >
                {saving ? (
                  <RotateCw className="size-3.5 animate-spin" />
                ) : (
                  <Plus className="size-3.5" />
                )}
                <span>ثبت و واگذاری نقش</span>
              </Button>
            </div>

            <DialogFooter className="pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setRolesDialogOpen(false)}
                className="h-9 text-xs rounded-xl"
              >
                بستن پنجره
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>

      {/* Audit Trail Dialog */}
      <Dialog open={auditDialogOpen} onOpenChange={setAuditDialogOpen}>
        <DialogContent className="sm:max-w-lg rounded-2xl" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="text-sm font-bold flex items-center gap-2">
              <History className="size-4 text-primary" />
              <span>تاریخچه ممیزی دسترسی: {selectedUser?.name}</span>
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              سوابق تغییر مشخصات، واگذاری نقش‌ها و رویدادهای امنیتی این حساب کاربری
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 pt-2 max-h-96 overflow-y-auto">
            {auditsLoading ? (
              <div className="space-y-2 p-2">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="h-14 bg-muted/40 animate-pulse rounded-xl" />
                ))}
              </div>
            ) : audits.length === 0 ? (
              <p className="text-xs text-muted-foreground text-center py-8">
                هنوز رویداد ممیزی برای این حساب ثبت نشده است.
              </p>
            ) : (
              audits.map((audit) => (
                <div
                  key={audit.id}
                  className="p-3 rounded-xl border border-border/60 bg-muted/20 space-y-1 text-xs"
                >
                  <div className="flex items-center justify-between">
                    <strong className="font-semibold text-foreground">
                      {auditLabels[audit.action] || audit.action}
                    </strong>
                    <span className="text-[10px] text-muted-foreground font-mono">
                      {formatSolarDateTime(audit.created_at)}
                    </span>
                  </div>
                  <p className="text-[11px] text-muted-foreground">
                    {audit.role
                      ? `${roleLabels[audit.role] || audit.role}${
                          audit.unit ? ` · ${audit.unit.name}` : ''
                        }`
                      : audit.unit?.name || 'ویرایش مشخصات حساب'}{' '}
                    · توسط: <span className="text-foreground">{audit.actor?.name || 'سیستم'}</span>
                  </p>
                </div>
              ))
            )}
          </div>

          <DialogFooter className="pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setAuditDialogOpen(false)}
              className="h-9 text-xs rounded-xl"
            >
              بستن پنجره
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Confirmation Dialogs */}
      <ConfirmDialog
        open={confirmDeactivation}
        onOpenChange={setConfirmDeactivation}
        title="تأیید غیرفعال‌سازی حساب کاربری"
        description="با غیرفعال‌سازی این حساب، کلیه توکن‌های فعال ورود بلافاصله باطل شده و کاربر تا زمان فعال‌سازی مجدد امکان ورود نخواهد داشت."
        confirmLabel="غیرفعال‌سازی حساب"
        variant="destructive"
        onConfirm={saveAccount}
      />

      <ConfirmDialog
        open={!!revokeTarget}
        onOpenChange={(open) => {
          if (!open) setRevokeTarget(null)
        }}
        title="لغو نقش سازمانی"
        description="دسترسی و اختیارات ناشی از این نقش بلافاصله از حساب کاربر سلب می‌گردد."
        confirmLabel="لغو قطعی نقش"
        variant="destructive"
        onConfirm={revokeRole}
      />
    </div>
  )
}
