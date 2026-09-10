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
  UserCheck,
  BriefcaseBusiness,
  ShieldAlert,
  Info,
  UserPlus
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { StatusBadge } from '@/components/amms/status-badge'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiError } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import { useAmmsMetadata } from '../lib/amms-metadata'
import type {
  AdminUser,
  Employee,
  OrganizationalUnit,
  UserAccessAudit,
  UserRoleScope,
} from '../types'

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
  const metadata = useAmmsMetadata()
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
  const [accountType, setAccountType] = useState<'EMPLOYEE' | 'TECHNICAL'>('EMPLOYEE')
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
        fetchAllAdminPages<OrganizationalUnit>('/admin/organizational-units'),
      ])
      setUsers(loadedUsers)
      setEmployees(loadedEmployees)
      setUnits(unitsResponse)

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
      const loadedAudits = await fetchAllAdminPages<UserAccessAudit>(
        `/admin/users/${userId}/access-audits`
      )
      setAudits(loadedAudits)
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
      ...(positionRole && positionRole !== 'EMPLOYEE' && metadata.roles.includes(positionRole)
        ? [positionRole]
        : []),
    ]
  }, [metadata.roles, selectedUser])

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

  // Eligible employees for account assignment
  const eligibleEmployees = useMemo(() => {
    return employees.filter(
      (e) =>
        e.status !== 'INACTIVE' &&
        (!users.some((u) => u.employee_id === e.id && u.id !== selectedUser?.id) ||
          (selectedUser && selectedUser.employee_id === e.id))
    )
  }, [employees, users, selectedUser])

  const selectedEmployee = useMemo(() => {
    if (!form.employee_id) return null
    return employees.find((e) => String(e.id) === String(form.employee_id)) || null
  }, [employees, form.employee_id])

  // Dialog open handlers
  const openCreateAccountDialog = () => {
    setSelectedUser(null)
    setAccountType('EMPLOYEE')
    setForm(emptyForm)
    setAccountDialogOpen(true)
  }

  const openEditAccountDialog = (user: AdminUser) => {
    setSelectedUser(user)
    setAccountType(user.employee_id ? 'EMPLOYEE' : 'TECHNICAL')
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
    const found = employees.find((e) => String(e.id) === empId)
    setForm((prev) => ({
      ...prev,
      employee_id: empId,
      name: found ? found.full_name : prev.name,
      email: found?.email || prev.email,
    }))
  }

  // Account submit
  const saveAccount = async () => {
    if (accountType === 'EMPLOYEE' && !form.employee_id) {
      setError('لطفاً کارمند مورد نظر را جهت اتصال به حساب سازمانی انتخاب نمایید.')
      return
    }
    if (!selectedUser && (!form.password || form.password.length < 12)) {
      setError('رمز عبور باید حداقل ۱۲ کاراکتر باشد.')
      return
    }
    setSaving(true)
    setError(null)
    try {
      const payload = {
        ...form,
        employee_id: accountType === 'EMPLOYEE' && form.employee_id ? Number(form.employee_id) : null,
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
            {metadata.roles.map((key) => (
              <option key={key} value={key}>
                {metadata.labels.roles[key] || key}
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
                                <span>{metadata.labels.roles[scope.role] || scope.role}</span>
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
              {selectedUser ? 'اصلاح مشخصات حساب کاربری' : 'ایجاد حساب کاربری جدید'}
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              {selectedUser
                ? 'ویرایش هویت کاربر، ایمیل رسمی ورود یا وضعیت فعالیت حساب'
                : 'تعیین هویت کاربر بر اساس پرونده پرسنلی کارمند یا ایجاد حساب فنی'}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleAccountSubmit} className="space-y-4 pt-1">
            {/* Account Type Toggle */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                نوع حساب کاربری
              </label>
              <div className="grid grid-cols-2 gap-1.5 p-1 rounded-xl bg-muted/60 border border-border/70 text-xs">
                <button
                  type="button"
                  onClick={() => {
                    setAccountType('EMPLOYEE')
                  }}
                  className={cn(
                    'flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg font-medium transition-all',
                    accountType === 'EMPLOYEE'
                      ? 'bg-card text-primary font-bold shadow-xs border border-border/70'
                      : 'text-muted-foreground hover:text-foreground'
                  )}
                >
                  <UserCheck className="size-3.5 shrink-0" />
                  <span>حساب پرسنل اداری</span>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setAccountType('TECHNICAL')
                    setForm((prev) => ({ ...prev, employee_id: '' }))
                  }}
                  className={cn(
                    'flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg font-medium transition-all',
                    accountType === 'TECHNICAL'
                      ? 'bg-card text-primary font-bold shadow-xs border border-border/70'
                      : 'text-muted-foreground hover:text-foreground'
                  )}
                >
                  <ShieldAlert className="size-3.5 shrink-0" />
                  <span>حساب فنی سیستم</span>
                </button>
              </div>
            </div>

            {/* Employee Selection (if Employee Account) */}
            {accountType === 'EMPLOYEE' ? (
              <div className="space-y-2">
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-foreground block">
                      انتخاب کارمند سازمان <span className="text-destructive">*</span>
                    </label>
                    <a
                      href="/admin/employees"
                      target="_blank"
                      rel="noreferrer"
                      className="text-[11px] text-primary hover:underline flex items-center gap-1 font-medium transition-colors"
                      title="ثبت کارمند جدید در بخش تشکیلات و پرسنل"
                    >
                      <UserPlus className="size-3" />
                      <span>ثبت کارمند جدید در تشکیلات</span>
                    </a>
                  </div>
                  {eligibleEmployees.length === 0 ? (
                    <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-200 space-y-2">
                      <p className="text-[11px] leading-relaxed">
                        تمامی کارمندان فعلی سیستم دارای حساب کاربری هستند یا هنوز پرونده پرسنلی ثبت نشده است. برای تعریف کارمند جدید، به بخش مدیریت تشکیلات مراجعه نمایید.
                      </p>
                      <a
                        href="/admin/employees"
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-medium hover:bg-primary/90 transition-colors"
                      >
                        <UserPlus className="size-3.5" />
                        <span>ورود به بخش مدیریت پرسنل</span>
                      </a>
                    </div>
                  ) : (
                    <select
                      required
                      value={form.employee_id}
                      onChange={(e) => handleEmployeeChange(e.target.value)}
                      className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                      <option value="">-- انتخاب کارمند فاقد حساب کاربری --</option>
                      {eligibleEmployees.map((emp) => (
                        <option key={emp.id} value={emp.id}>
                          {emp.full_name} · {emp.position?.title || 'کارمند'} ({emp.employee_number})
                          {emp.unit?.name ? ` · ${emp.unit.name}` : ''}
                        </option>
                      ))}
                    </select>
                  )}
                </div>

                {/* Selected Employee Summary Card */}
                {selectedEmployee && (
                  <div className="p-3 rounded-xl bg-primary/5 border border-primary/20 space-y-1 text-xs">
                    <div className="flex items-center justify-between">
                      <strong className="font-bold text-foreground flex items-center gap-1.5">
                        <UserCheck className="size-3.5 text-primary" />
                        <span>{selectedEmployee.full_name}</span>
                      </strong>
                      <Badge variant="outline" className="font-mono text-[10px] rounded-md">
                        {selectedEmployee.employee_number}
                      </Badge>
                    </div>
                    <div className="text-[11px] text-muted-foreground flex flex-wrap gap-x-3 gap-y-0.5 pt-0.5">
                      <span className="flex items-center gap-1">
                        <Building2 className="size-3 text-muted-foreground/70" />
                        <span>{selectedEmployee.unit?.name || 'واحد نامشخص'}</span>
                      </span>
                      <span className="flex items-center gap-1">
                        <BriefcaseBusiness className="size-3 text-muted-foreground/70" />
                        <span>{selectedEmployee.position?.title || 'سمت نامشخص'}</span>
                      </span>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              /* Technical Account Notice */
              <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2">
                <Info className="size-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                <p className="text-[11px] leading-relaxed">
                  حساب فنی منحصراً برای مدیران سیستم (<span className="font-mono font-bold">SYSTEM_ADMIN</span>) بوده، به کارمند اداری متصل نمی‌شود و اختیارات جلسات سازمانی را دریافت نمی‌کند.
                </p>
              </div>
            )}

            {/* Name Field */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground flex items-center justify-between">
                <span>
                  {accountType === 'EMPLOYEE' ? 'نام و نام خانوادگی صاحب حساب' : 'عنوان حساب / نام مدیر فنی'}{' '}
                  <span className="text-destructive">*</span>
                </span>
                {accountType === 'EMPLOYEE' && selectedEmployee && (
                  <span className="text-[10px] text-muted-foreground font-normal">
                    (برگرفته از پرونده پرسنلی)
                  </span>
                )}
              </label>
              <Input
                required
                value={form.name}
                onChange={(e) =>
                  setForm((prev) => ({ ...prev, name: e.target.value }))
                }
                placeholder={
                  accountType === 'EMPLOYEE'
                    ? 'نام و تخلص کارمند...'
                    : 'مثال: مدیر فنی زیرساخت'
                }
                className="h-9.5 rounded-xl text-xs"
              />
            </div>

            {/* Email Field */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                ایمیل رسمی ورود (شناسه کاربری) <span className="text-destructive">*</span>
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

            {/* Password Field */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                رمز عبور ورود{' '}
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
                minLength={selectedUser ? undefined : 12}
                value={form.password}
                onChange={(e) =>
                  setForm((prev) => ({ ...prev, password: e.target.value }))
                }
                placeholder={selectedUser ? '••••••••••••' : 'حداقل ۱۲ کاراکتر...'}
                className="h-9.5 rounded-xl text-xs font-mono"
              />
              {!selectedUser && (
                <span className="text-[10px] text-muted-foreground block">
                  رمز عبور طبق ضوابط امنیتی باید حداقل ۱۲ کاراکتر باشد.
                </span>
              )}
            </div>

            {/* Status Field (if editing) */}
            {selectedUser && (
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  وضعیت حساب کاربری
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
                  <option value="INACTIVE">غیرفعال (مسدودشده)</option>
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
                          {metadata.labels.roles[scope.role] || scope.role}
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
                      {metadata.labels.roles[r] || r}
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
                      ? `${metadata.labels.roles[audit.role] || audit.role}${
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
