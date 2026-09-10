import { useState, useMemo } from 'react'
import {
  ShieldCheck,
  KeyRound,
  History,
  LockKeyhole,
  Check,
  Search,
  Users,
  Pencil,
  Power,
  Info,
  ChevronDown,
  ChevronUp,
  CheckSquare,
  Square,
  AlertCircle,
  ExternalLink,
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { StatusBadge } from '@/components/amms/status-badge'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import {
  PERMISSION_CATEGORIES,
  SYSTEM_ROLE_GOVERNANCE,
  getPermissionDetails,
} from '../../lib/roles-metadata-helper'
import type { AmmsPermissionMetadata } from '../../lib/amms-metadata'
import type { AmmsRoleAudit, AmmsRoleDefinition } from '../../types'

type RolePermissionInspectorProps = {
  role: AmmsRoleDefinition
  permissionsByCategory: Record<string, AmmsPermissionMetadata[]>
  allPermissions: AmmsPermissionMetadata[]
  expandedCategories: Record<string, boolean>
  onToggleCategory: (category: string) => void
  onChangePermission: (permission: string, checked: boolean) => void
  onBatchChangePermissions?: (permissionsToAdd: string[], permissionsToRemove: string[]) => void
  onEdit: () => void
  onToggleStatus: () => void
  audits: AmmsRoleAudit[]
  auditsLoading: boolean
  saving: boolean
  onNavigateToUsers?: (roleName: string) => void
}

const auditLabels: Record<string, string> = {
  CUSTOM_ROLE_CREATED: 'ایجاد نقش سفارشی',
  CUSTOM_ROLE_UPDATED: 'ویرایش مشخصات نقش',
  CUSTOM_ROLE_PERMISSIONS_CHANGED: 'تغییر دسترسی‌های نقش',
  CUSTOM_ROLE_ACTIVATED: 'فعال‌سازی نقش',
  CUSTOM_ROLE_DEACTIVATED: 'غیرفعال‌سازی نقش',
}

export function RolePermissionInspector({
  role,
  permissionsByCategory,
  allPermissions,
  expandedCategories,
  onToggleCategory,
  onChangePermission,
  onBatchChangePermissions,
  onEdit,
  onToggleStatus,
  audits,
  auditsLoading,
  saving,
  onNavigateToUsers,
}: RolePermissionInspectorProps) {
  const isSystem = role.role_kind === 'SYSTEM'
  const [activeTab, setActiveTab] = useState<'permissions' | 'audits' | 'governance'>('permissions')
  const [permSearch, setPermSearch] = useState('')
  const [onlySelectedFilter, setOnlySelectedFilter] = useState(false)

  const governance = isSystem ? SYSTEM_ROLE_GOVERNANCE[role.name] : null

  // Filter permissions based on search and selected filter
  const filteredCategoryEntries = useMemo(() => {
    const searchLower = permSearch.trim().toLowerCase()

    if (isSystem) {
      // For system roles, group by all categories that the role has permissions for
      const systemGroups: Record<string, string[]> = {}
      role.permissions.forEach((permName) => {
        const details = getPermissionDetails(permName)
        const cat = details.category || 'other'
        if (!systemGroups[cat]) systemGroups[cat] = []
        systemGroups[cat].push(permName)
      })

      const entries = Object.entries(systemGroups).filter(([category, permList]) => {
        const matching = permList.filter((name) => {
          if (!searchLower) return true
          const details = getPermissionDetails(name)
          return (
            details.label.toLowerCase().includes(searchLower) ||
            details.description.toLowerCase().includes(searchLower) ||
            name.toLowerCase().includes(searchLower)
          )
        })
        return matching.length > 0
      })

      return entries.map(([category, permList]) => ({
        category,
        permissions: permList
          .filter((name) => {
            if (!searchLower) return true
            const details = getPermissionDetails(name)
            return (
              details.label.toLowerCase().includes(searchLower) ||
              details.description.toLowerCase().includes(searchLower) ||
              name.toLowerCase().includes(searchLower)
            )
          })
          .map((name) => {
            const meta = allPermissions.find((p) => p.name === name)
            return (
              meta || {
                name,
                category,
                custom_role_assignable: false,
                protected: true,
                delegatable: false,
              }
            )
          }),
      }))
    }

    // For Custom roles, use permissionsByCategory
    return Object.entries(permissionsByCategory)
      .map(([category, categoryPermissions]) => {
        const matched = categoryPermissions.filter((permission) => {
          const isAssigned = role.permissions.includes(permission.name)
          if (onlySelectedFilter && !isAssigned) return false

          if (!searchLower) return true
          const details = getPermissionDetails(permission.name)
          return (
            details.label.toLowerCase().includes(searchLower) ||
            details.description.toLowerCase().includes(searchLower) ||
            permission.name.toLowerCase().includes(searchLower)
          )
        })

        return {
          category,
          permissions: matched,
        }
      })
      .filter((group) => group.permissions.length > 0)
  }, [permissionsByCategory, allPermissions, role.permissions, isSystem, permSearch, onlySelectedFilter])

  // Count total and selected
  const totalCategoryPerms = useMemo(() => {
    return Object.values(permissionsByCategory).reduce((acc, curr) => acc + curr.length, 0)
  }, [permissionsByCategory])

  const selectedCustomPerms = useMemo(() => {
    return role.permissions.length
  }, [role.permissions])

  // Batch toggle for a category
  const handleSelectAllCategory = (categoryPerms: AmmsPermissionMetadata[]) => {
    if (!onBatchChangePermissions) {
      categoryPerms.forEach((p) => {
        if (!role.permissions.includes(p.name)) {
          onChangePermission(p.name, true)
        }
      })
      return
    }
    const toAdd = categoryPerms
      .filter((p) => !role.permissions.includes(p.name))
      .map((p) => p.name)
    if (toAdd.length > 0) {
      onBatchChangePermissions(toAdd, [])
    }
  }

  const handleDeselectAllCategory = (categoryPerms: AmmsPermissionMetadata[]) => {
    if (!onBatchChangePermissions) {
      categoryPerms.forEach((p) => {
        if (role.permissions.includes(p.name)) {
          onChangePermission(p.name, false)
        }
      })
      return
    }
    const toRemove = categoryPerms
      .filter((p) => role.permissions.includes(p.name))
      .map((p) => p.name)
    if (toRemove.length > 0) {
      onBatchChangePermissions([], toRemove)
    }
  }

  return (
    <div className="space-y-4 text-right" dir="rtl">
      {/* Role Profile Header */}
      <Card className="rounded-2xl border-border/70 bg-card shadow-xs overflow-hidden text-right" dir="rtl">
        <div className="p-5 sm:p-6 bg-gradient-to-r from-muted/30 via-background to-muted/10 border-b border-border/60">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="flex items-start gap-3.5">
              <div
                className={cn(
                  'flex size-12 shrink-0 items-center justify-center rounded-2xl shadow-xs',
                  isSystem
                    ? 'bg-primary/10 text-primary border border-primary/20'
                    : 'bg-indigo-500/10 text-indigo-600 border border-indigo-500/20'
                )}
              >
                {isSystem ? <ShieldCheck className="size-6" /> : <KeyRound className="size-6" />}
              </div>

              <div>
                <div className="flex items-center gap-2.5 flex-wrap">
                  <h3 className="text-base sm:text-lg font-bold text-foreground">{role.label}</h3>
                  <StatusBadge status={role.status} />
                  {isSystem ? (
                    <Badge
                      variant="secondary"
                      className="h-5 gap-1 rounded-md px-2 text-[10px] font-semibold bg-muted/90 text-muted-foreground border border-border/70"
                    >
                      <LockKeyhole className="size-2.5" />
                      نقش سیستمی محافظت‌شده
                    </Badge>
                  ) : (
                    <Badge
                      variant="outline"
                      className="h-5 gap-1 rounded-md px-2 text-[10px] font-semibold text-primary border-primary/30 bg-primary/5"
                    >
                      <KeyRound className="size-2.5" />
                      نقش سازمانی سفارشی
                    </Badge>
                  )}
                </div>

                <div className="flex items-center gap-2 mt-1 font-mono text-xs text-muted-foreground" dir="ltr">
                  <span>{role.name}</span>
                </div>
              </div>
            </div>

            {/* Quick Header Actions */}
            <div className="flex items-center gap-2 self-start sm:self-center">
              {!isSystem && (
                <>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={onEdit}
                    disabled={saving}
                    className="h-8.5 gap-1.5 rounded-xl text-xs font-semibold shadow-xs"
                  >
                    <Pencil className="size-3.5" />
                    ویرایش مشخصات
                  </Button>

                  <Button
                    variant={role.status === 'ACTIVE' ? 'outline' : 'default'}
                    size="sm"
                    onClick={onToggleStatus}
                    disabled={saving}
                    className={cn(
                      'h-8.5 gap-1.5 rounded-xl text-xs font-semibold shadow-xs',
                      role.status === 'ACTIVE'
                        ? 'text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/20 border-rose-200 dark:border-rose-900'
                        : 'bg-emerald-600 hover:bg-emerald-700 text-white'
                    )}
                  >
                    <Power className="size-3.5" />
                    {role.status === 'ACTIVE' ? 'غیرفعال‌سازی' : 'فعال‌سازی'}
                  </Button>
                </>
              )}

              {onNavigateToUsers && (
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => onNavigateToUsers(role.name)}
                  className="h-8.5 gap-1.5 rounded-xl text-xs font-semibold shadow-xs"
                >
                  <Users className="size-3.5" />
                  <span>کاربران ({role.assigned_user_count})</span>
                  <ExternalLink className="size-3 opacity-60" />
                </Button>
              )}
            </div>
          </div>

          {role.description && (
            <p className="mt-3.5 text-xs text-muted-foreground leading-relaxed max-w-3xl">
              {role.description}
            </p>
          )}

          {/* Quick Metrics Strip */}
          <div className="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 pt-3 border-t border-border/50">
            <div className="rounded-xl bg-background/60 p-2.5 border border-border/60">
              <span className="block text-[10px] text-muted-foreground">تعداد دسترسی‌های فعال</span>
              <strong className="font-mono text-sm font-bold text-foreground mt-0.5 block">
                {role.permissions.length} دسترسی
              </strong>
            </div>

            <div className="rounded-xl bg-background/60 p-2.5 border border-border/60">
              <span className="block text-[10px] text-muted-foreground">کاربران دارای این نقش</span>
              <strong className="font-mono text-sm font-bold text-foreground mt-0.5 block">
                {role.assigned_user_count} کاربر
              </strong>
            </div>

            <div className="rounded-xl bg-background/60 p-2.5 border border-border/60 col-span-2 sm:col-span-1">
              <span className="block text-[10px] text-muted-foreground">وضعیت مدیریت</span>
              <strong className="text-xs font-bold text-foreground mt-0.5 block">
                {isSystem ? 'تنظیمات مرکزی سامانه' : 'مدیران ارشد تشکیلات'}
              </strong>
            </div>
          </div>
        </div>

        {/* Tabs for details */}
        <Tabs
          value={activeTab}
          onValueChange={(val) => setActiveTab(val as any)}
          className="w-full text-right"
          dir="rtl"
        >
          <div className="px-5 border-b border-border/60 bg-muted/20">
            <TabsList className="h-11 bg-transparent p-0 gap-4 w-full justify-start text-right">
              <TabsTrigger
                value="permissions"
                className="h-11 rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-primary px-3 text-xs font-semibold gap-1.5"
              >
                <KeyRound className="size-3.5" />
                <span>دسترسی‌های نقش ({role.permissions.length})</span>
              </TabsTrigger>

              {isSystem && (
                <TabsTrigger
                  value="governance"
                  className="h-11 rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-primary px-3 text-xs font-semibold gap-1.5"
                >
                  <ShieldCheck className="size-3.5" />
                  <span>منشور حاکمیتی و اختیارات</span>
                </TabsTrigger>
              )}

              {!isSystem && (
                <TabsTrigger
                  value="audits"
                  className="h-11 rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-primary px-3 text-xs font-semibold gap-1.5"
                >
                  <History className="size-3.5" />
                  <span>تاریخچه تغییرات</span>
                  {audits.length > 0 && (
                    <Badge variant="secondary" className="h-4 px-1 text-[9px] mr-1 rounded-full">
                      {audits.length}
                    </Badge>
                  )}
                </TabsTrigger>
              )}
            </TabsList>
          </div>

          {/* PERMISSIONS TAB CONTENT */}
          <TabsContent value="permissions" className="p-5 sm:p-6 space-y-4 m-0">
            {/* Search and filter bar for permissions */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-muted/20 p-3.5 rounded-xl border border-border/60">
              <div className="relative flex-1 max-w-md">
                <Search className="absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                <Input
                  value={permSearch}
                  onChange={(e) => setPermSearch(e.target.value)}
                  placeholder="جستجو در نام یا شرح دسترسی…"
                  className="h-9 pr-9 pl-3 text-xs rounded-xl bg-background"
                />
              </div>

              <div className="flex items-center gap-2 flex-wrap text-xs">
                {!isSystem && (
                  <Button
                    type="button"
                    variant={onlySelectedFilter ? 'secondary' : 'outline'}
                    size="sm"
                    onClick={() => setOnlySelectedFilter(!onlySelectedFilter)}
                    className="h-8 gap-1.5 rounded-lg text-xs"
                  >
                    <Check className="size-3" />
                    <span>فقط دسترسی‌های انتخابی ({selectedCustomPerms})</span>
                  </Button>
                )}

                <span className="text-xs text-muted-foreground mr-auto sm:mr-0 font-mono">
                  {isSystem
                    ? `${role.permissions.length} دسترسی مصوب`
                    : `${selectedCustomPerms} از ${totalCategoryPerms} انتخاب‌شده`}
                </span>
              </div>
            </div>

            {isSystem && governance && (
              <div className="rounded-xl border border-border/70 bg-muted/15 p-4 space-y-2">
                <div className="flex items-center gap-2 text-xs font-bold text-foreground">
                  <Info className="size-4 text-primary" />
                  <span>حوزهٔ اختیارات سیستمی:</span>
                </div>
                <p className="text-xs text-muted-foreground leading-relaxed">
                  {governance.boundary}
                </p>
                <div className="text-[11px] text-muted-foreground/80 flex items-center gap-1 mt-1">
                  <LockKeyhole className="size-3" />
                  <span>دسترسی‌های این نقش به صورت مستقیم از طریق ماتریس مرکزی AMMS کنترل می‌شوند.</span>
                </div>
              </div>
            )}

            {/* List of categories */}
            <div className="space-y-3">
              {filteredCategoryEntries.length === 0 ? (
                <div className="flex flex-col items-center justify-center p-8 text-center rounded-xl border border-dashed border-border/70">
                  <AlertCircle className="size-6 text-muted-foreground mb-2" />
                  <p className="text-xs font-semibold text-foreground">دسترسی مورد نظر یافت نشد</p>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    عبارت جستجو را تغییر دهید یا فیلتر «فقط دسترسی‌های انتخابی» را غیرفعال کنید.
                  </p>
                </div>
              ) : (
                filteredCategoryEntries.map(({ category, permissions }) => {
                  const catMeta = PERMISSION_CATEGORIES[category] || {
                    label: category,
                    description: 'دسترسی‌های عملیاتی این بخش',
                    icon: KeyRound,
                    color: 'text-primary',
                    bg: 'bg-primary/10',
                  }
                  const CategoryIcon = catMeta.icon
                  const isExpanded = expandedCategories[category] ?? true
                  const selectedCount = permissions.filter((p) =>
                    role.permissions.includes(p.name)
                  ).length
                  const allSelected = selectedCount === permissions.length && permissions.length > 0

                  return (
                    <div
                      key={category}
                      className="overflow-hidden rounded-xl border border-border/70 bg-card shadow-2xs"
                    >
                      {/* Category Header */}
                      <div className="flex items-center justify-between gap-3 bg-muted/30 px-4 py-3 border-b border-border/50 text-right" dir="rtl">
                        <button
                          type="button"
                          className="flex min-w-0 items-center gap-2.5 text-right cursor-pointer flex-1"
                          onClick={() => onToggleCategory(category)}
                        >
                          <div
                            className={cn(
                              'flex size-8 shrink-0 items-center justify-center rounded-lg',
                              catMeta.bg,
                              catMeta.color
                            )}
                          >
                            <CategoryIcon className="size-4" />
                          </div>

                          <div className="min-w-0 text-right">
                            <span className="block truncate text-xs font-bold text-foreground">
                              {catMeta.label}
                            </span>
                            <span className="block truncate text-[10px] text-muted-foreground">
                              {catMeta.description}
                            </span>
                          </div>
                        </button>

                        <div className="flex items-center gap-2 shrink-0">
                          <Badge
                            variant={selectedCount > 0 ? 'secondary' : 'outline'}
                            className="h-5 px-2 text-[10px] font-medium"
                          >
                            {selectedCount} از {permissions.length}
                          </Badge>

                          {!isSystem && (
                            <div className="hidden sm:flex items-center gap-1 border-r border-border/60 pr-2 mr-1">
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={saving || allSelected}
                                onClick={() => handleSelectAllCategory(permissions)}
                                className="h-6 px-1.5 text-[10px] text-primary hover:text-primary gap-1"
                                title="انتخاب همه دسترسی‌های این بخش"
                              >
                                <CheckSquare className="size-3" />
                                <span>انتخاب همه</span>
                              </Button>

                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={saving || selectedCount === 0}
                                onClick={() => handleDeselectAllCategory(permissions)}
                                className="h-6 px-1.5 text-[10px] text-muted-foreground hover:text-destructive gap-1"
                                title="لغو همه دسترسی‌های این بخش"
                              >
                                <Square className="size-3" />
                                <span>لغو همه</span>
                              </Button>
                            </div>
                          )}

                          <button
                            type="button"
                            onClick={() => onToggleCategory(category)}
                            className="p-1 text-muted-foreground hover:text-foreground"
                          >
                            {isExpanded ? (
                              <ChevronUp className="size-4" />
                            ) : (
                              <ChevronDown className="size-4" />
                            )}
                          </button>
                        </div>
                      </div>

                      {/* Category Permission Items */}
                      {isExpanded && (
                        <div className="p-3 grid gap-2.5 sm:grid-cols-2 bg-background/50 text-right" dir="rtl">
                          {permissions.map((permission) => {
                            const isAssigned = role.permissions.includes(permission.name)
                            const details = getPermissionDetails(permission.name)

                            return (
                              <label
                                key={permission.name}
                                className={cn(
                                  'group flex items-start gap-3 rounded-xl border p-3.5 transition-all duration-150 text-right',
                                  !isSystem && 'cursor-pointer',
                                  isAssigned
                                    ? 'border-primary/30 bg-primary/[0.03] shadow-xs'
                                    : 'border-border/60 bg-card hover:border-border hover:bg-muted/20',
                                  saving && 'opacity-70 pointer-events-none'
                                )}
                                dir="rtl"
                              >
                                {isSystem ? (
                                  <div className="flex size-4 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-600 mt-0.5">
                                    <Check className="size-3" />
                                  </div>
                                ) : (
                                  <Checkbox
                                    checked={isAssigned}
                                    disabled={saving}
                                    onCheckedChange={(val) =>
                                      onChangePermission(permission.name, val === true)
                                    }
                                    className="mt-0.5"
                                  />
                                )}

                                <div className="min-w-0 flex-1 text-right">
                                  <div className="flex items-center justify-between gap-1.5">
                                    <span
                                      className={cn(
                                        'text-xs font-semibold truncate text-right',
                                        isAssigned ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'
                                      )}
                                    >
                                      {details.label}
                                    </span>
                                    {isAssigned && (
                                      <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium shrink-0">
                                        فعال
                                      </span>
                                    )}
                                  </div>

                                  <p className="mt-1 text-[11px] text-muted-foreground leading-relaxed text-right">
                                    {details.description}
                                  </p>

                                  <span
                                    className="mt-1.5 block font-mono text-[9px] text-muted-foreground/70 text-left"
                                    dir="ltr"
                                  >
                                    {permission.name}
                                  </span>
                                </div>
                              </label>
                            )
                          })}
                        </div>
                      )}
                    </div>
                  )
                })
              )}
            </div>
          </TabsContent>

          {/* GOVERNANCE TAB (For System Roles) */}
          {isSystem && governance && (
            <TabsContent value="governance" className="p-5 sm:p-6 space-y-5 m-0">
              <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                <h4 className="text-sm font-bold text-primary flex items-center gap-2">
                  <ShieldCheck className="size-4" />
                  <span>چارچوب حاکمیتی نقش سازمانی «{role.label}»</span>
                </h4>
                <p className="mt-1 text-xs text-muted-foreground leading-relaxed">
                  بر اساس منشور اختیارات و امنیت سامانه (AMMS Canonical Roles & Scope Charter)، این نقش دارای مرزهای مشخص و تفکیک‌شده در گردش‌کارهای تصمیم‌گیری اداری می‌باشد.
                </p>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl border border-border/70 bg-card p-4 space-y-1.5">
                  <span className="text-[11px] font-bold text-foreground">مرز مسئولیت اداری:</span>
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    {governance.boundary}
                  </p>
                </div>

                <div className="rounded-xl border border-border/70 bg-card p-4 space-y-1.5">
                  <span className="text-[11px] font-bold text-foreground">حوزهٔ تشکیلاتی (Organizational Scope):</span>
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    {governance.governanceScope}
                  </p>
                </div>

                <div className="rounded-xl border border-border/70 bg-card p-4 space-y-1.5 sm:col-span-2">
                  <span className="text-[11px] font-bold text-foreground">اختیارات در گردش‌کارها (FLOW-01 تا FLOW-14):</span>
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    {governance.flowAuthoritySummary}
                  </p>
                </div>
              </div>
            </TabsContent>
          )}

          {/* AUDIT TAB (For Custom Roles) */}
          {!isSystem && (
            <TabsContent value="audits" className="p-5 sm:p-6 space-y-4 m-0">
              {auditsLoading ? (
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="flex gap-3 p-3 rounded-xl border border-border/50">
                      <Skeleton className="size-8 rounded-lg" />
                      <div className="flex-1 space-y-1.5">
                        <Skeleton className="h-4 w-1/3" />
                        <Skeleton className="h-3 w-1/2" />
                      </div>
                    </div>
                  ))}
                </div>
              ) : audits.length === 0 ? (
                <div className="flex flex-col items-center justify-center p-8 text-center rounded-xl border border-dashed border-border/70">
                  <History className="size-6 text-muted-foreground mb-2" />
                  <p className="text-xs font-semibold text-foreground">سابقه‌ای ثبت نشده است</p>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    تغییرات دسترسی و ویرایش مشخصات این نقش در این بخش نگهداری خواهند شد.
                  </p>
                </div>
              ) : (
                <div className="space-y-2.5 max-h-[460px] overflow-y-auto pl-1">
                  {audits.map((audit) => (
                    <div
                      key={audit.id}
                      className="rounded-xl border border-border/60 bg-muted/15 p-3 text-xs"
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-start gap-2.5">
                          <div className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary mt-0.5">
                            <History className="size-3.5" />
                          </div>
                          <div>
                            <span className="font-semibold text-foreground block">
                              {auditLabels[audit.action] || audit.action}
                            </span>
                            <span className="text-[11px] text-muted-foreground mt-0.5 block">
                              اقدام‌کننده:{' '}
                              <strong className="text-foreground">
                                {audit.actor?.name || 'سیستم'}
                              </strong>
                            </span>
                          </div>
                        </div>

                        <span className="text-[10px] font-mono text-muted-foreground whitespace-nowrap">
                          {formatSolarDateTime(audit.created_at)}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </TabsContent>
          )}
        </Tabs>
      </Card>
    </div>
  )
}
