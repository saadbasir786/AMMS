import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  ShieldCheck,
  KeyRound,
  LockKeyhole,
  UsersRound,
  FileText,
  Plus,
  RefreshCw,
  Search,
  SlidersHorizontal,
  Layers,
  CheckCircle2,
  Table as TableIcon,
  LayoutGrid,
} from 'lucide-react'

import { PageHeader } from '@/components/amms/page-header'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Sheet,
  SheetContent,
  SheetTitle,
  SheetDescription,
} from '@/components/ui/sheet'
import { cn } from '@/lib/utils'
import { api, apiData, apiError, apiPage } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type { AmmsPermissionMetadata } from '../lib/amms-metadata'
import type { AmmsRoleAudit, AmmsRoleDefinition } from '../types'

import { RoleCard } from '../components/roles/role-card'
import { RolesTable } from '../components/roles/roles-table'
import { RolePermissionInspector } from '../components/roles/role-permission-inspector'
import { PermissionMatrixView } from '../components/roles/permission-matrix-view'
import { RoleFormDialog } from '../components/roles/role-form-dialog'

type RoleForm = {
  name: string
  label: string
  description: string
}

type MainViewTab = 'explorer' | 'matrix'
type DisplayMode = 'table' | 'grid'
type RoleKindFilter = 'ALL' | 'CUSTOM' | 'SYSTEM'
type StatusFilter = 'ALL' | 'ACTIVE' | 'INACTIVE'

export function RolesPermissionsPage() {
  const navigate = useNavigate()

  // Core Data
  const [roles, setRoles] = useState<AmmsRoleDefinition[]>([])
  const [permissions, setPermissions] = useState<AmmsPermissionMetadata[]>([])
  const [audits, setAudits] = useState<AmmsRoleAudit[]>([])
  const [selectedRole, setSelectedRole] = useState<AmmsRoleDefinition | null>(null)

  // Filters and Navigation
  const [currentView, setCurrentView] = useState<MainViewTab>('explorer')
  const [displayMode, setDisplayMode] = useState<DisplayMode>('table')
  const [searchQuery, setSearchQuery] = useState('')
  const [roleKindFilter, setRoleKindFilter] = useState<RoleKindFilter>('ALL')
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('ALL')

  // Drawer / Sheet for Table View
  const [inspectorSheetOpen, setInspectorSheetOpen] = useState(false)

  // UI state
  const [expandedCategories, setExpandedCategories] = useState<Record<string, boolean>>({})
  const [editorOpen, setEditorOpen] = useState(false)
  const [editingRole, setEditingRole] = useState<AmmsRoleDefinition | null>(null)
  const [form, setForm] = useState<RoleForm>({ name: '', label: '', description: '' })
  const [confirmToggleRole, setConfirmToggleRole] = useState<AmmsRoleDefinition | null>(null)

  // Async States
  const [loading, setLoading] = useState(true)
  const [auditsLoading, setAuditsLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Initial Data Fetching
  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [loadedRoles, permResponse] = await Promise.all([
        fetchAllAdminPages<AmmsRoleDefinition>('/admin/roles'),
        api.get('/admin/permissions'),
      ])
      setRoles(loadedRoles)
      const allPerms = (apiData(permResponse) || []) as AmmsPermissionMetadata[]
      setPermissions(allPerms)

      setSelectedRole((current) => {
        if (current) {
          return loadedRoles.find((r) => r.id === current.id) || loadedRoles[0] || null
        }
        // Default to first custom role, or first system role
        return loadedRoles.find((r) => r.role_kind === 'CUSTOM') || loadedRoles[0] || null
      })
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadData()
  }, [loadData])

  // Load Audits for selected custom role
  useEffect(() => {
    if (!selectedRole || selectedRole.role_kind !== 'CUSTOM') {
      setAudits([])
      return
    }

    let mounted = true
    setAuditsLoading(true)
    void api
      .get(`/admin/roles/${selectedRole.id}/audits`, { params: { per_page: 50 } })
      .then((response) => {
        if (mounted) {
          setAudits((apiPage(response)?.data || []) as AmmsRoleAudit[])
        }
      })
      .catch(() => {
        if (mounted) setAudits([])
      })
      .finally(() => {
        if (mounted) setAuditsLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [selectedRole?.id, selectedRole?.role_kind])

  // Computed Metrics
  const systemRoles = useMemo(() => roles.filter((r) => r.role_kind === 'SYSTEM'), [roles])
  const customRoles = useMemo(() => roles.filter((r) => r.role_kind === 'CUSTOM'), [roles])
  const activeCustomRoles = useMemo(
    () => customRoles.filter((r) => r.status === 'ACTIVE').length,
    [customRoles]
  )
  const totalAssignedUsers = useMemo(
    () => roles.reduce((sum, r) => sum + r.assigned_user_count, 0),
    [roles]
  )

  // Filtered Roles List
  const filteredRoles = useMemo(() => {
    const query = searchQuery.trim().toLowerCase()

    return roles.filter((role) => {
      // Kind Filter
      if (roleKindFilter === 'CUSTOM' && role.role_kind !== 'CUSTOM') return false
      if (roleKindFilter === 'SYSTEM' && role.role_kind !== 'SYSTEM') return false

      // Status Filter
      if (statusFilter === 'ACTIVE' && role.status !== 'ACTIVE') return false
      if (statusFilter === 'INACTIVE' && role.status !== 'INACTIVE') return false

      // Search Query
      if (!query) return true

      return (
        role.label.toLowerCase().includes(query) ||
        role.name.toLowerCase().includes(query) ||
        (role.description && role.description.toLowerCase().includes(query))
      )
    })
  }, [roles, roleKindFilter, statusFilter, searchQuery])

  // Group assignable permissions for custom role editor
  const assignablePermissions = useMemo(
    () => permissions.filter((p) => p.custom_role_assignable),
    [permissions]
  )

  const permissionsByCategory = useMemo(() => {
    return assignablePermissions.reduce<Record<string, AmmsPermissionMetadata[]>>((groups, perm) => {
      ;(groups[perm.category] ||= []).push(perm)
      return groups
    }, {})
  }, [assignablePermissions])

  // Category Expand Toggle
  const handleToggleCategory = (category: string) => {
    setExpandedCategories((curr) => ({
      ...curr,
      [category]: !(curr[category] ?? true),
    }))
  }

  // Open Create/Edit Dialog
  const handleOpenEditor = (role?: AmmsRoleDefinition) => {
    setEditingRole(role || null)
    setForm(
      role
        ? { name: role.name, label: role.label, description: role.description || '' }
        : { name: '', label: '', description: '' }
    )
    setError(null)
    setEditorOpen(true)
  }

  // Save Role Metadata
  const handleSaveRole = async () => {
    setSaving(true)
    setError(null)
    try {
      if (editingRole) {
        await api.put(`/admin/roles/${editingRole.id}`, form)
      } else {
        await api.post('/admin/roles', form)
      }

      await loadData()
      setEditorOpen(false)
      setEditingRole(null)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  // Single Permission Toggle
  const handleChangePermission = async (permission: string, checked: boolean) => {
    if (!selectedRole || selectedRole.role_kind !== 'CUSTOM') return

    const nextPermissions = checked
      ? Array.from(new Set([...selectedRole.permissions, permission]))
      : selectedRole.permissions.filter((p) => p !== permission)

    setSaving(true)
    setError(null)
    try {
      await api.post(`/admin/roles/${selectedRole.id}/permissions`, {
        permissions: nextPermissions,
      })

      // Update state locally for snappy UI
      setSelectedRole((curr) => (curr ? { ...curr, permissions: nextPermissions } : null))
      setRoles((curr) =>
        curr.map((r) => (r.id === selectedRole.id ? { ...r, permissions: nextPermissions } : r))
      )
    } catch (err) {
      setError(apiError(err))
      await loadData()
    } finally {
      setSaving(false)
    }
  }

  // Batch Permissions Change (Select/Deselect All in Category)
  const handleBatchChangePermissions = async (toAdd: string[], toRemove: string[]) => {
    if (!selectedRole || selectedRole.role_kind !== 'CUSTOM') return

    let next = [...selectedRole.permissions]
    if (toAdd.length > 0) {
      next = Array.from(new Set([...next, ...toAdd]))
    }
    if (toRemove.length > 0) {
      const removeSet = new Set(toRemove)
      next = next.filter((p) => !removeSet.has(p))
    }

    setSaving(true)
    setError(null)
    try {
      await api.post(`/admin/roles/${selectedRole.id}/permissions`, {
        permissions: next,
      })

      setSelectedRole((curr) => (curr ? { ...curr, permissions: next } : null))
      setRoles((curr) =>
        curr.map((r) => (r.id === selectedRole.id ? { ...r, permissions: next } : r))
      )
    } catch (err) {
      setError(apiError(err))
      await loadData()
    } finally {
      setSaving(false)
    }
  }

  // Status Activation/Deactivation Toggle
  const handleExecuteToggleStatus = async (role: AmmsRoleDefinition) => {
    setSaving(true)
    setError(null)
    try {
      const endpoint =
        role.status === 'ACTIVE'
          ? `/admin/roles/${role.id}/deactivate`
          : `/admin/roles/${role.id}/activate`
      await api.post(endpoint)
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
      setConfirmToggleRole(null)
    }
  }

  // Select role handler for table and grid
  const handleSelectRole = (role: AmmsRoleDefinition) => {
    setSelectedRole(role)
    if (displayMode === 'table') {
      setInspectorSheetOpen(true)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        title="نقش‌ها و سطوح دسترسی"
        description="مدیریت جامع نقش‌های سازمانی، تعریف نقش‌های سفارشی و ارزیابی ماتریس دسترسی‌های سامانه"
        action={
          <div className="flex items-center gap-2">
            <Button
              onClick={() => handleOpenEditor()}
              className="h-9 gap-1.5 rounded-xl text-xs font-semibold shadow-xs"
            >
              <Plus className="size-4" />
              <span>نقش سفارشی جدید</span>
            </Button>

            <Button
              variant="outline"
              onClick={() => void loadData()}
              disabled={loading}
              className="h-9 gap-1.5 rounded-xl text-xs font-semibold"
            >
              <RefreshCw className={cn('size-3.5', loading && 'animate-spin')} />
              <span>تازه‌سازی</span>
            </Button>
          </div>
        }
      />

      {/* Top Metric Cards */}
      <div className="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
        <div className="rounded-2xl border border-border/70 bg-card p-4 shadow-2xs">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-medium text-muted-foreground">کل نقش‌های سیستم</span>
            <div className="flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <ShieldCheck className="size-4" />
            </div>
          </div>
          <strong className="mt-2 block font-mono text-xl font-bold text-foreground">
            {roles.length} <span className="text-xs font-normal text-muted-foreground">نقش</span>
          </strong>
        </div>

        <div className="rounded-2xl border border-border/70 bg-card p-4 shadow-2xs">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-medium text-muted-foreground">نقش‌های سیستمی پایه</span>
            <div className="flex size-8 items-center justify-center rounded-xl bg-muted text-muted-foreground">
              <LockKeyhole className="size-4" />
            </div>
          </div>
          <strong className="mt-2 block font-mono text-xl font-bold text-foreground">
            {systemRoles.length}{' '}
            <span className="text-xs font-normal text-muted-foreground">محافظت‌شده</span>
          </strong>
        </div>

        <div className="rounded-2xl border border-border/70 bg-card p-4 shadow-2xs">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-medium text-muted-foreground">نقش‌های سفارشی فعال</span>
            <div className="flex size-8 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600">
              <KeyRound className="size-4" />
            </div>
          </div>
          <strong className="mt-2 block font-mono text-xl font-bold text-foreground">
            {activeCustomRoles}{' '}
            <span className="text-xs font-normal text-muted-foreground">
              از {customRoles.length}
            </span>
          </strong>
        </div>

        <div className="rounded-2xl border border-border/70 bg-card p-4 shadow-2xs">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-medium text-muted-foreground">کاربران دارای نقش</span>
            <div className="flex size-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
              <UsersRound className="size-4" />
            </div>
          </div>
          <strong className="mt-2 block font-mono text-xl font-bold text-foreground">
            {totalAssignedUsers}{' '}
            <span className="text-xs font-normal text-muted-foreground">کاربر فعال</span>
          </strong>
        </div>
      </div>

      {/* Global Error Banner */}
      {error && !editorOpen && (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertDescription className="flex items-center justify-between gap-3 text-xs">
            <span>{error}</span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => void loadData()}
              disabled={loading}
              className="h-8 gap-1.5 text-xs font-semibold"
            >
              <RefreshCw className="size-3" />
              تلاش مجدد
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* View Switcher Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border/60 pb-3">
        <div className="flex items-center gap-1.5 rounded-xl bg-muted/50 p-1 border border-border/70 w-fit">
          <button
            type="button"
            onClick={() => setCurrentView('explorer')}
            className={cn(
              'flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition-all',
              currentView === 'explorer'
                ? 'bg-card text-primary shadow-xs border border-border/60'
                : 'text-muted-foreground hover:text-foreground'
            )}
          >
            <Layers className="size-4" />
            <span>مدیریت و مرور نقش‌ها</span>
          </button>

          <button
            type="button"
            onClick={() => setCurrentView('matrix')}
            className={cn(
              'flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition-all',
              currentView === 'matrix'
                ? 'bg-card text-primary shadow-xs border border-border/60'
                : 'text-muted-foreground hover:text-foreground'
            )}
          >
            <FileText className="size-4" />
            <span>ماتریس مقایسه دسترسی‌ها</span>
          </button>
        </div>

        {currentView === 'explorer' && (
          <div className="text-xs text-muted-foreground flex items-center gap-1">
            <CheckCircle2 className="size-3.5 text-primary" />
            <span>
              {displayMode === 'table'
                ? 'روی ردیف هر نقش یا دکمه «دسترسی‌ها» کلیک کنید تا پنل کشویی باز شود.'
                : 'یک نقش را از فهرست انتخاب کنید تا مشخصات آن در پنل چپ بررسی شود.'}
            </span>
          </div>
        )}
      </div>

      {/* Loading Skeleton */}
      {loading ? (
        <RolesSkeleton />
      ) : currentView === 'matrix' ? (
        /* PERMISSION MATRIX VIEW */
        <PermissionMatrixView permissions={permissions} roles={roles} />
      ) : (
        /* EXPLORER VIEW (TABLE OR GRID) */
        <div className="space-y-4">
          {/* Filter Toolbar & Display Mode Switcher */}
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-card p-3.5 rounded-2xl border border-border/70 shadow-2xs">
            {/* Search Bar */}
            <div className="relative flex-1 max-w-sm">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="جستجو در عنوان یا کد نقش…"
                className="h-9 pr-9 pl-3 text-xs rounded-xl bg-background"
              />
            </div>

            {/* Filter Chips & View Switcher */}
            <div className="flex items-center justify-between gap-3 flex-wrap text-xs">
              <div className="flex items-center gap-1.5 flex-wrap">
                {/* Kind Filter */}
                <div className="flex items-center bg-muted/60 p-0.5 rounded-lg border border-border/60">
                  <button
                    type="button"
                    onClick={() => setRoleKindFilter('ALL')}
                    className={cn(
                      'px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all',
                      roleKindFilter === 'ALL'
                        ? 'bg-card text-primary shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                    )}
                  >
                    همه ({roles.length})
                  </button>
                  <button
                    type="button"
                    onClick={() => setRoleKindFilter('CUSTOM')}
                    className={cn(
                      'px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all',
                      roleKindFilter === 'CUSTOM'
                        ? 'bg-card text-primary shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                    )}
                  >
                    سفارشی ({customRoles.length})
                  </button>
                  <button
                    type="button"
                    onClick={() => setRoleKindFilter('SYSTEM')}
                    className={cn(
                      'px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all',
                      roleKindFilter === 'SYSTEM'
                        ? 'bg-card text-primary shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                    )}
                  >
                    سیستمی ({systemRoles.length})
                  </button>
                </div>

                {/* Status Filter */}
                {roleKindFilter !== 'SYSTEM' && (
                  <div className="flex items-center bg-muted/60 p-0.5 rounded-lg border border-border/60">
                    <button
                      type="button"
                      onClick={() => setStatusFilter('ALL')}
                      className={cn(
                        'px-2 py-1 rounded-md text-[11px] font-semibold transition-all',
                        statusFilter === 'ALL'
                          ? 'bg-card text-primary shadow-xs'
                          : 'text-muted-foreground hover:text-foreground'
                      )}
                    >
                      وضعیت: همه
                    </button>
                    <button
                      type="button"
                      onClick={() => setStatusFilter('ACTIVE')}
                      className={cn(
                        'px-2 py-1 rounded-md text-[11px] font-semibold transition-all',
                        statusFilter === 'ACTIVE'
                          ? 'bg-card text-primary shadow-xs'
                          : 'text-muted-foreground hover:text-foreground'
                      )}
                    >
                      فقط فعال
                    </button>
                  </div>
                )}
              </div>

              {/* View Switcher Toggle (Table vs Grid) */}
              <div className="flex items-center bg-muted/60 p-0.5 rounded-lg border border-border/60">
                <button
                  type="button"
                  onClick={() => setDisplayMode('table')}
                  className={cn(
                    'flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all',
                    displayMode === 'table'
                      ? 'bg-card text-primary shadow-xs'
                      : 'text-muted-foreground hover:text-foreground'
                  )}
                  title="نمای جدولی (پیش‌فرض)"
                >
                  <TableIcon className="size-3.5" />
                  <span>جدول</span>
                </button>

                <button
                  type="button"
                  onClick={() => setDisplayMode('grid')}
                  className={cn(
                    'flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all',
                    displayMode === 'grid'
                      ? 'bg-card text-primary shadow-xs'
                      : 'text-muted-foreground hover:text-foreground'
                  )}
                  title="نمای کارتی دو ستونه"
                >
                  <LayoutGrid className="size-3.5" />
                  <span>کارت</span>
                </button>
              </div>
            </div>
          </div>

          {/* TABLE DISPLAY MODE (Default, high density) */}
          {displayMode === 'table' ? (
            <RolesTable
              roles={filteredRoles}
              selectedRoleId={selectedRole?.id}
              onSelectRole={handleSelectRole}
              onEditRole={(r) => handleOpenEditor(r)}
              onToggleStatus={(r) => setConfirmToggleRole(r)}
              disabled={saving}
            />
          ) : (
            /* GRID / MASTER-DETAIL DISPLAY MODE */
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
              {/* Master Cards List (4 cols on large screens) */}
              <div className="lg:col-span-4 space-y-2.5 max-h-[780px] overflow-y-auto pl-1">
                {filteredRoles.length === 0 ? (
                  <div className="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-dashed border-border/70 bg-card">
                    <SlidersHorizontal className="size-6 text-muted-foreground/60 mb-2" />
                    <p className="text-xs font-semibold text-foreground">نقشی با این مشخصات یافت نشد</p>
                    <p className="text-[11px] text-muted-foreground mt-1">
                      عبارت جستجو یا فیلترها را بررسی کنید.
                    </p>
                  </div>
                ) : (
                  filteredRoles.map((role) => (
                    <RoleCard
                      key={role.id}
                      role={role}
                      isSelected={selectedRole?.id === role.id}
                      onSelect={() => setSelectedRole(role)}
                      onEdit={() => handleOpenEditor(role)}
                      onToggleStatus={() => setConfirmToggleRole(role)}
                      disabled={saving}
                    />
                  ))
                )}
              </div>

              {/* Detail Inspector Pane (8 cols on large screens) */}
              <div className="lg:col-span-8">
                {selectedRole ? (
                  <RolePermissionInspector
                    role={selectedRole}
                    permissionsByCategory={permissionsByCategory}
                    allPermissions={permissions}
                    expandedCategories={expandedCategories}
                    onToggleCategory={handleToggleCategory}
                    onChangePermission={handleChangePermission}
                    onBatchChangePermissions={handleBatchChangePermissions}
                    onEdit={() => handleOpenEditor(selectedRole)}
                    onToggleStatus={() => setConfirmToggleRole(selectedRole)}
                    audits={audits}
                    auditsLoading={auditsLoading}
                    saving={saving}
                    onNavigateToUsers={() => navigate('/admin/users')}
                  />
                ) : (
                  <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-border/70 bg-card min-h-[380px]">
                    <KeyRound className="size-8 text-muted-foreground/50 mb-2.5" />
                    <h4 className="text-sm font-bold text-foreground">نقشی انتخاب نشده است</h4>
                    <p className="text-xs text-muted-foreground max-w-sm mt-1 leading-relaxed">
                      برای بررسی دسترسی‌ها، چارچوب حاکمیتی و سوابق، لطفاً یک نقش را از فهرست سمت راست انتخاب فرمایید.
                    </p>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Slide-over Inspector Sheet for Table View with Spacious Width */}
      <Sheet open={inspectorSheetOpen} onOpenChange={setInspectorSheetOpen}>
        <SheetContent
          side="right"
          className="data-[side=right]:sm:max-w-none !max-w-[1100px] overflow-y-auto p-4 sm:p-6 sm:p-7 rounded-r-none rounded-l-2xl border-l border-r-0 border-border/80 shadow-2xl bg-card text-right"
          style={{ width: '92vw', maxWidth: '1100px' }}
          dir="rtl"
        >
          <div className="sr-only">
            <SheetTitle>{selectedRole?.label || 'جزئیات نقش'}</SheetTitle>
            <SheetDescription>بررسی دسترسی‌ها، اختیارات و سوابق نقش</SheetDescription>
          </div>

          {selectedRole && (
            <div className="pt-2">
              <RolePermissionInspector
                role={selectedRole}
                permissionsByCategory={permissionsByCategory}
                allPermissions={permissions}
                expandedCategories={expandedCategories}
                onToggleCategory={handleToggleCategory}
                onChangePermission={handleChangePermission}
                onBatchChangePermissions={handleBatchChangePermissions}
                onEdit={() => handleOpenEditor(selectedRole)}
                onToggleStatus={() => setConfirmToggleRole(selectedRole)}
                audits={audits}
                auditsLoading={auditsLoading}
                saving={saving}
                onNavigateToUsers={() => {
                  setInspectorSheetOpen(false)
                  navigate('/admin/users')
                }}
              />
            </div>
          )}
        </SheetContent>
      </Sheet>

      {/* Role Create / Edit Dialog */}
      <RoleFormDialog
        open={editorOpen}
        onOpenChange={setEditorOpen}
        selectedRole={editingRole}
        form={form}
        setForm={setForm}
        onSave={handleSaveRole}
        saving={saving}
        error={error}
      />

      {/* Status Toggle Confirmation Dialog */}
      {confirmToggleRole && (
        <ConfirmDialog
          open={!!confirmToggleRole}
          onOpenChange={(open) => {
            if (!open) setConfirmToggleRole(null)
          }}
          title={
            confirmToggleRole.status === 'ACTIVE'
              ? 'تأیید غیرفعال‌سازی نقش سفارشی'
              : 'تأیید فعال‌سازی مجدد نقش سفارشی'
          }
          description={
            confirmToggleRole.status === 'ACTIVE'
              ? `آیا از غیرفعال‌سازی نقش «${confirmToggleRole.label}» اطمینان دارید؟ با غیرفعال‌سازی، دسترسی‌های ناشی از این نقش برای تمامی ${confirmToggleRole.assigned_user_count} کاربر دارای این نقش موقتاً مسدود خواهد شد.`
              : `آیا از فعال‌سازی مجدد نقش «${confirmToggleRole.label}» اطمینان دارید؟ دسترسی‌های مرتبط برای کاربران این نقش بلافاصله برقرار خواهد گردید.`
          }
          confirmLabel={
            confirmToggleRole.status === 'ACTIVE' ? 'غیرفعال‌سازی نقش' : 'فعال‌سازی مجدد'
          }
          variant={confirmToggleRole.status === 'ACTIVE' ? 'destructive' : 'default'}
          onConfirm={() => handleExecuteToggleStatus(confirmToggleRole)}
        />
      )}
    </div>
  )
}

function RolesSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-14 rounded-2xl" />
      <Skeleton className="h-[460px] rounded-2xl" />
    </div>
  )
}
