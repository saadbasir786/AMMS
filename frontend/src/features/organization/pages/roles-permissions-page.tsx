import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import {
  Check,
  ChevronDown,
  ChevronUp,
  FileText,
  History,
  KeyRound,
  LockKeyhole,
  Pencil,
  Plus,
  Power,
  RefreshCw,
  ShieldCheck,
  UsersRound,
} from 'lucide-react'

import { PageHeader } from '@/components/amms/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import { api, apiData, apiError, apiPage } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type { AmmsPermissionMetadata } from '../lib/amms-metadata'
import type { AmmsRoleAudit, AmmsRoleDefinition } from '../types'

const categoryLabels: Record<string, string> = {
  meeting: 'جلسات',
  'meeting-request': 'درخواست‌های جلسه',
  reporting: 'گزارش‌ها',
  notification: 'اعلان‌ها',
  attendance: 'حاضری',
  'action-item': 'اقدامات قابل انجام',
  decision: 'پیگیری تصمیم‌ها',
}

const auditLabels: Record<string, string> = {
  CUSTOM_ROLE_CREATED: 'ایجاد نقش سفارشی',
  CUSTOM_ROLE_UPDATED: 'ویرایش نقش سفارشی',
  CUSTOM_ROLE_PERMISSIONS_CHANGED: 'تغییر دسترسی‌های نقش',
  CUSTOM_ROLE_ACTIVATED: 'فعال‌سازی نقش',
  CUSTOM_ROLE_DEACTIVATED: 'غیرفعال‌سازی نقش',
}

type RoleForm = {
  name: string
  label: string
  description: string
}

type TabType = 'custom' | 'system' | 'matrix'

export function RolesPermissionsPage() {
  const [roles, setRoles] = useState<AmmsRoleDefinition[]>([])
  const [permissions, setPermissions] = useState<AmmsPermissionMetadata[]>([])
  const [audits, setAudits] = useState<AmmsRoleAudit[]>([])
  const [selected, setSelected] = useState<AmmsRoleDefinition | null>(null)
  const [activeTab, setActiveTab] = useState<TabType>('custom')
  const [form, setForm] = useState<RoleForm>({ name: '', label: '', description: '' })
  const [editorOpen, setEditorOpen] = useState(false)
  const [expanded, setExpanded] = useState<Record<string, boolean>>({})
  const [loading, setLoading] = useState(true)
  const [auditsLoading, setAuditsLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [loadedRoles, permissionResponse] = await Promise.all([
        fetchAllAdminPages<AmmsRoleDefinition>('/admin/roles'),
        api.get('/admin/permissions'),
      ])
      setRoles(loadedRoles)
      setPermissions((apiData(permissionResponse) || []) as AmmsPermissionMetadata[])
      setSelected((current) =>
        current ? loadedRoles.find((role) => role.id === current.id) || null : null
      )
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!selected || selected.role_kind !== 'CUSTOM') {
      setAudits([])
      return
    }

    let mounted = true
    setAuditsLoading(true)
    void api
      .get(`/admin/roles/${selected.id}/audits`, { params: { per_page: 50 } })
      .then((response) => {
        if (mounted) setAudits((apiPage(response)?.data || []) as AmmsRoleAudit[])
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
  }, [selected])

  const systemRoles = useMemo(
    () => roles.filter((role) => role.role_kind === 'SYSTEM'),
    [roles]
  )
  const customRoles = useMemo(
    () => roles.filter((role) => role.role_kind === 'CUSTOM'),
    [roles]
  )
  const activeCustomRoles = useMemo(
    () => customRoles.filter((role) => role.status === 'ACTIVE').length,
    [customRoles]
  )
  const assignedCustomUsers = useMemo(
    () => customRoles.reduce((sum, role) => sum + role.assigned_user_count, 0),
    [customRoles]
  )
  const assignablePermissions = useMemo(
    () => permissions.filter((permission) => permission.custom_role_assignable),
    [permissions]
  )
  const permissionGroups = useMemo(
    () =>
      assignablePermissions.reduce<Record<string, AmmsPermissionMetadata[]>>((groups, permission) => {
        ;(groups[permission.category] ||= []).push(permission)
        return groups
      }, {}),
    [assignablePermissions]
  )

  const openEditor = (role?: AmmsRoleDefinition) => {
    setSelected(role || null)
    setForm(
      role
        ? { name: role.name, label: role.label, description: role.description || '' }
        : { name: '', label: '', description: '' }
    )
    setError(null)
    setEditorOpen(true)
  }

  const save = async () => {
    setSaving(true)
    setError(null)
    try {
      if (selected) await api.put(`/admin/roles/${selected.id}`, form)
      else await api.post('/admin/roles', form)

      await load()
      setEditorOpen(false)
      setSelected(null)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const changePermissions = async (permission: string, checked: boolean) => {
    if (!selected || selected.role_kind !== 'CUSTOM') return

    const next = checked
      ? [...new Set([...selected.permissions, permission])]
      : selected.permissions.filter((item) => item !== permission)

    setSaving(true)
    setError(null)
    try {
      await api.post(`/admin/roles/${selected.id}/permissions`, { permissions: next })
      await load()
      setSelected((current) => (current ? { ...current, permissions: next } : current))
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const toggleStatus = async (role: AmmsRoleDefinition) => {
    setSaving(true)
    setError(null)
    try {
      await api.post(
        `/admin/roles/${role.id}/${role.status === 'ACTIVE' ? 'deactivate' : 'activate'}`
      )
      await load()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      <PageHeader
        title="نقش‌ها و دسترسی‌ها"
        description="مدیریت نقش‌های سیستمی، نقش‌های سفارشی و سطح دسترسی کاربران در سامانه"
      />

      <div className="flex items-center gap-1.5 overflow-x-auto rounded-2xl border border-border/70 bg-muted/50 p-1.5 shadow-xs sm:w-fit">
        <TabButton active={activeTab === 'custom'} icon={KeyRound} label="نقش‌های سفارشی" onClick={() => setActiveTab('custom')} />
        <TabButton active={activeTab === 'system'} icon={LockKeyhole} label="نقش‌های سیستمی" onClick={() => setActiveTab('system')} />
        <TabButton active={activeTab === 'matrix'} icon={FileText} label="ماتریس دسترسی" onClick={() => setActiveTab('matrix')} />
      </div>

      {error && !editorOpen ? (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertDescription className="flex items-center justify-between gap-3 text-xs">
            <span>{error}</span>
            <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading} className="h-8 shrink-0 gap-1.5 text-xs font-semibold">
              <RefreshCw className="size-3" />
              تلاش مجدد
            </Button>
          </AlertDescription>
        </Alert>
      ) : null}

      {loading ? (
        <RolesPageSkeleton />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-3.5 lg:grid-cols-4">
            <MetricCard icon={ShieldCheck} label="کل نقش‌ها" value={`${roles.length} نقش`} />
            <MetricCard icon={KeyRound} label="نقش‌های سفارشی فعال" value={`${activeCustomRoles} نقش`} tone="indigo" />
            <MetricCard icon={UsersRound} label="تخصیص نقش سفارشی" value={`${assignedCustomUsers} مورد`} tone="emerald" />
            <MetricCard icon={FileText} label="دسترسی قابل واگذاری" value={`${assignablePermissions.length} دسترسی`} tone="amber" />
          </div>

          {activeTab === 'custom' ? (
            <section className="space-y-5">
              <SectionBar
                title="نقش‌های سفارشی"
                description="نقش‌های قابل تنظیم برای نیازهای کاری سازمان و دسترسی‌های محدود به حوزهٔ تشکیلاتی"
                action={
                  <div className="flex items-center gap-2">
                    <Button size="sm" onClick={() => openEditor()} className="h-8.5 gap-1.5 rounded-xl text-xs font-semibold shadow-xs">
                      <Plus className="size-3.5" />
                      نقش سفارشی جدید
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading} className="h-8.5 gap-1.5 rounded-xl text-xs font-semibold">
                      <RefreshCw className="size-3.5" />
                      تازه‌سازی
                    </Button>
                  </div>
                }
              />

              {customRoles.length === 0 ? (
                <EmptyState
                  icon={KeyRound}
                  title="هنوز نقش سفارشی ایجاد نشده است"
                  description="برای ایجاد نخستین نقش سفارشی و تعیین دسترسی‌های آن از دکمهٔ بالا استفاده کنید."
                  action={
                    <Button size="sm" onClick={() => openEditor()} className="rounded-xl text-xs">
                      <Plus className="size-3.5" />
                      ایجاد نقش سفارشی
                    </Button>
                  }
                />
              ) : (
                <div className="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                  {customRoles.map((role) => (
                    <RoleCard
                      key={role.id}
                      role={role}
                      selected={selected?.id === role.id}
                      onSelect={() => setSelected(role)}
                      onEdit={() => openEditor(role)}
                      onToggleStatus={() => void toggleStatus(role)}
                      disabled={saving}
                    />
                  ))}
                </div>
              )}

              {selected?.role_kind === 'CUSTOM' ? (
                <RoleDetails
                  role={selected}
                  permissionsByCategory={permissionGroups}
                  expandedCategories={expanded}
                  onToggleCategory={(category) =>
                    setExpanded((current) => ({ ...current, [category]: !(current[category] ?? true) }))
                  }
                  onChangePermission={changePermissions}
                  audits={audits}
                  auditsLoading={auditsLoading}
                  saving={saving}
                />
              ) : null}
            </section>
          ) : null}

          {activeTab === 'system' ? (
            <section className="space-y-5">
              <SectionBar
                title="نقش‌های سیستمی"
                description="نقش‌های پایه و محافظت‌شدهٔ سامانه؛ این نقش‌ها از تنظیمات مرکزی AMMS مدیریت می‌شوند."
                action={
                  <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading} className="h-8.5 gap-1.5 rounded-xl text-xs font-semibold">
                    <RefreshCw className="size-3.5" />
                    تازه‌سازی
                  </Button>
                }
              />
              <div className="grid gap-3.5 md:grid-cols-2 xl:grid-cols-4">
                {systemRoles.map((role) => <RoleCard key={role.id} role={role} />)}
              </div>
            </section>
          ) : null}

          {activeTab === 'matrix' ? (
            <section className="space-y-5">
              <SectionBar title="ماتریس دسترسی نقش‌های سیستمی" description="مقایسهٔ دسترسی‌های تعریف‌شده برای نقش‌های اصلی سامانه" />
              <PermissionMatrix permissions={permissions} systemRoles={systemRoles} />
            </section>
          ) : null}
        </div>
      )}

      <Dialog open={editorOpen} onOpenChange={(open) => { if (!saving) setEditorOpen(open) }}>
        <DialogContent className="rounded-2xl sm:max-w-lg" dir="rtl">
          <DialogHeader className="space-y-1.5 text-right">
            <DialogTitle className="flex items-center gap-2 text-base font-bold text-foreground">
              <div className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <KeyRound className="size-4" />
              </div>
              <span>{selected ? 'ویرایش نقش سفارشی' : 'ایجاد نقش سفارشی جدید'}</span>
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              {selected
                ? `اصلاح مشخصات نقش «${selected.label}». دسترسی‌های نقش از بخش جزئیات مدیریت می‌شوند.`
                : 'مشخصات نقش را ثبت کنید؛ سپس دسترسی‌های مجاز آن را از بخش جزئیات نقش تعیین نمایید.'}
            </DialogDescription>
          </DialogHeader>

          {error ? (
            <div className="rounded-xl border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive">
              {error}
            </div>
          ) : null}

          <form
            onSubmit={(event) => {
              event.preventDefault()
              void save()
            }}
            className="space-y-4 pt-1 text-xs"
          >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label htmlFor="role-name" className="block font-bold text-foreground">
                  نام فنی نقش <span className="text-destructive">*</span>
                </label>
                <Input
                  id="role-name"
                  required
                  value={form.name}
                  disabled={!!selected || saving}
                  onChange={(event) => setForm({ ...form, name: event.target.value.toUpperCase() })}
                  placeholder="REPORT_VIEWER"
                  className="h-9.5 rounded-xl font-mono text-xs uppercase"
                  dir="ltr"
                />
                <p className="text-[10px] leading-relaxed text-muted-foreground">
                  حروف انگلیسی بزرگ، عدد و زیرخط؛ پس از ایجاد قابل تغییر نیست.
                </p>
              </div>

              <div className="space-y-1.5">
                <label htmlFor="role-label" className="block font-bold text-foreground">
                  عنوان نمایشی <span className="text-destructive">*</span>
                </label>
                <Input
                  id="role-label"
                  required
                  value={form.label}
                  disabled={saving}
                  onChange={(event) => setForm({ ...form, label: event.target.value })}
                  placeholder="مشاهده‌گر گزارش‌ها"
                  className="h-9.5 rounded-xl text-xs"
                />
                <p className="text-[10px] leading-relaxed text-muted-foreground">
                  عنوانی که برای مدیران سیستم و کاربران نمایش داده می‌شود.
                </p>
              </div>
            </div>

            <div className="space-y-1.5">
              <label htmlFor="role-description" className="block font-bold text-foreground">
                توضیحات نقش
              </label>
              <Textarea
                id="role-description"
                value={form.description}
                disabled={saving}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
                placeholder="کاربرد، مسئولیت یا هدف این نقش را کوتاه توضیح دهید."
                className="min-h-24 resize-none rounded-xl text-xs"
              />
              <p className="text-[10px] leading-relaxed text-muted-foreground">
                دسترسی‌های نقش در این مرحله تعیین نمی‌شوند و پس از ذخیره قابل تنظیم‌اند.
              </p>
            </div>

            <DialogFooter className="gap-2 pt-2 sm:justify-start">
              <Button
                type="submit"
                disabled={saving || !form.name.trim() || !form.label.trim()}
                className="h-9 flex-1 gap-1.5 rounded-xl text-xs font-semibold"
              >
                {saving ? <RefreshCw className="size-3.5 animate-spin" /> : <KeyRound className="size-3.5" />}
                <span>{saving ? 'در حال ذخیره…' : selected ? 'ذخیره تغییرات نقش' : 'ایجاد نقش سفارشی'}</span>
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={saving}
                onClick={() => setEditorOpen(false)}
                className="h-9 rounded-xl text-xs font-semibold"
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

function TabButton({ active, icon: Icon, label, onClick }: { active: boolean; icon: typeof ShieldCheck; label: string; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold transition-all',
        active
          ? 'border border-border/70 bg-card text-primary shadow-xs'
          : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
      )}
    >
      <Icon className="size-4 shrink-0" />
      {label}
    </button>
  )
}

function MetricCard({ icon: Icon, label, value, tone = 'primary' }: { icon: typeof ShieldCheck; label: string; value: string; tone?: 'primary' | 'indigo' | 'emerald' | 'amber' }) {
  const tones = {
    primary: 'bg-primary/10 text-primary',
    indigo: 'bg-indigo-500/10 text-indigo-600',
    emerald: 'bg-emerald-500/10 text-emerald-600',
    amber: 'bg-amber-500/10 text-amber-600',
  }

  return (
    <div className="flex items-center gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-xs">
      <div className={cn('flex size-9 shrink-0 items-center justify-center rounded-xl', tones[tone])}>
        <Icon className="size-4" />
      </div>
      <div className="min-w-0">
        <span className="block text-[10px] font-medium text-muted-foreground">{label}</span>
        <strong className="mt-0.5 block truncate font-mono text-lg font-bold text-foreground">{value}</strong>
      </div>
    </div>
  )
}

function SectionBar({ title, description, action }: { title: string; description: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col gap-3 border-b border-border/50 pb-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h3 className="text-sm font-bold text-foreground">{title}</h3>
        <p className="mt-0.5 text-[11px] text-muted-foreground">{description}</p>
      </div>
      {action}
    </div>
  )
}

function RoleCard({ role, selected = false, onSelect, onEdit, onToggleStatus, disabled = false }: { role: AmmsRoleDefinition; selected?: boolean; onSelect?: () => void; onEdit?: () => void; onToggleStatus?: () => void; disabled?: boolean }) {
  const isSystem = role.role_kind === 'SYSTEM'

  return (
    <Card className={cn('rounded-2xl border-border/70 shadow-xs transition-all hover:border-border hover:shadow-sm', selected && 'border-primary/60 ring-2 ring-primary/10')}>
      <CardHeader className="space-y-2.5 p-4 pb-3">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <CardTitle className="truncate text-sm font-bold">{role.label}</CardTitle>
            <CardDescription className="mt-1 truncate font-mono text-[10px]" dir="ltr">{role.name}</CardDescription>
          </div>
          <RoleStatusBadge role={role} />
        </div>
        <div className="flex flex-wrap gap-1.5">
          <Badge variant="outline" className="h-5 px-2 text-[10px] font-medium">{isSystem ? 'سیستمی' : 'سفارشی'}</Badge>
          {isSystem ? (
            <Badge variant="secondary" className="h-5 gap-1 px-2 text-[10px] font-medium">
              <LockKeyhole className="size-2.5" />
              محافظت‌شده
            </Badge>
          ) : null}
        </div>
      </CardHeader>

      <CardContent className="space-y-3 p-4 pt-0">
        <p className="line-clamp-2 min-h-9 text-[11px] leading-5 text-muted-foreground">
          {role.description || (isSystem ? 'نقش پایهٔ سامانه با دسترسی‌های تعریف‌شدهٔ مرکزی.' : 'نقش سفارشی با دسترسی محدود و قابل تنظیم.')}
        </p>

        <div className="grid grid-cols-2 divide-x divide-x-reverse divide-border/60 rounded-xl bg-muted/35 px-3 py-2.5">
          <div className="px-2">
            <span className="block text-[10px] text-muted-foreground">دسترسی‌ها</span>
            <strong className="mt-0.5 block font-mono text-sm">{role.permissions.length}</strong>
          </div>
          <div className="px-2">
            <span className="block text-[10px] text-muted-foreground">کاربران</span>
            <strong className="mt-0.5 block font-mono text-sm">{role.assigned_user_count}</strong>
          </div>
        </div>

        {isSystem ? (
          <div className="flex items-center gap-1.5 border-t border-border/50 pt-3 text-[10px] text-muted-foreground">
            <Check className="size-3 text-primary" />
            مدیریت‌شده توسط تنظیمات مرکزی سامانه
          </div>
        ) : (
          <div className="flex flex-wrap items-center gap-1.5 border-t border-border/50 pt-3">
            <Button variant={selected ? 'secondary' : 'outline'} size="sm" onClick={onSelect} disabled={disabled} className="h-7 rounded-lg px-2.5 text-[10px]">
              <KeyRound className="size-3" />
              {selected ? 'انتخاب‌شده' : 'دسترسی‌ها'}
            </Button>
            <Button variant="ghost" size="sm" onClick={onEdit} disabled={disabled} className="h-7 rounded-lg px-2.5 text-[10px]">
              <Pencil className="size-3" />
              ویرایش
            </Button>
            <Button variant="ghost" size="sm" onClick={onToggleStatus} disabled={disabled} className="h-7 rounded-lg px-2.5 text-[10px]">
              <Power className="size-3" />
              {role.status === 'ACTIVE' ? 'غیرفعال' : 'فعال'}
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function RoleStatusBadge({ role }: { role: AmmsRoleDefinition }) {
  return (
    <Badge
      variant={role.status === 'ACTIVE' ? 'outline' : 'secondary'}
      className={cn('h-5 px-2 text-[10px] font-medium', role.status === 'ACTIVE' && 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700')}
    >
      {role.status === 'ACTIVE' ? 'فعال' : 'غیرفعال'}
    </Badge>
  )
}

function RoleDetails({ role, permissionsByCategory, expandedCategories, onToggleCategory, onChangePermission, audits, auditsLoading, saving }: { role: AmmsRoleDefinition; permissionsByCategory: Record<string, AmmsPermissionMetadata[]>; expandedCategories: Record<string, boolean>; onToggleCategory: (category: string) => void; onChangePermission: (permission: string, checked: boolean) => void; audits: AmmsRoleAudit[]; auditsLoading: boolean; saving: boolean }) {
  return (
    <div className="space-y-3 border-t border-border/60 pt-5">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <span className="text-[10px] font-semibold text-primary">نقش انتخاب‌شده</span>
          <h3 className="mt-0.5 text-sm font-bold">{role.label}</h3>
        </div>
        <span className="font-mono text-[10px] text-muted-foreground" dir="ltr">{role.name}</span>
      </div>

      <div className="grid gap-3.5 xl:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
        <PermissionEditor role={role} permissionsByCategory={permissionsByCategory} expandedCategories={expandedCategories} onToggleCategory={onToggleCategory} onChangePermission={onChangePermission} saving={saving} />
        <AuditTimeline audits={audits} loading={auditsLoading} />
      </div>
    </div>
  )
}

function PermissionEditor({ role, permissionsByCategory, expandedCategories, onToggleCategory, onChangePermission, saving }: { role: AmmsRoleDefinition; permissionsByCategory: Record<string, AmmsPermissionMetadata[]>; expandedCategories: Record<string, boolean>; onToggleCategory: (category: string) => void; onChangePermission: (permission: string, checked: boolean) => void; saving: boolean }) {
  const entries = Object.entries(permissionsByCategory)
  const totalPermissions = entries.reduce((total, [, categoryPermissions]) => total + categoryPermissions.length, 0)
  const selectedPermissions = entries.reduce(
    (total, [, categoryPermissions]) =>
      total + categoryPermissions.filter((permission) => role.permissions.includes(permission.name)).length,
    0
  )

  return (
    <Card className="overflow-hidden rounded-2xl border-border/70 shadow-xs">
      <CardHeader className="border-b border-border/50 bg-muted/15 p-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2.5">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <KeyRound className="size-4" />
            </div>
            <div>
              <CardTitle className="text-sm font-bold">دسترسی‌های قابل واگذاری</CardTitle>
              <CardDescription className="mt-0.5 text-[10px] leading-4">
                دسترسی‌های مجاز این نقش را بر اساس حوزهٔ کاری انتخاب کنید؛ هر تغییر بلافاصله ذخیره می‌شود.
              </CardDescription>
            </div>
          </div>

          <div className="flex items-center gap-2 self-start sm:self-auto">
            <Badge variant="secondary" className="h-6 rounded-lg px-2.5 text-[10px] font-medium">
              {selectedPermissions} انتخاب‌شده
            </Badge>
            <Badge variant="outline" className="h-6 rounded-lg px-2.5 text-[10px] font-medium text-muted-foreground">
              از {totalPermissions}
            </Badge>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-3 p-4">
        {entries.length === 0 ? (
          <div className="flex min-h-28 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/70 bg-muted/10 px-4 text-center">
            <KeyRound className="size-5 text-muted-foreground/60" />
            <p className="text-xs font-medium text-muted-foreground">دسترسی قابل واگذاری یافت نشد.</p>
          </div>
        ) : (
          entries.map(([category, categoryPermissions]) => {
            const isExpanded = expandedCategories[category] ?? true
            const selectedCount = categoryPermissions.filter((permission) =>
              role.permissions.includes(permission.name)
            ).length

            return (
              <div key={category} className="overflow-hidden rounded-xl border border-border/70 bg-card">
                <button
                  type="button"
                  className="flex w-full items-center justify-between gap-3 bg-muted/25 px-3.5 py-3 text-start transition-colors hover:bg-muted/45"
                  onClick={() => onToggleCategory(category)}
                >
                  <span className="flex min-w-0 items-center gap-2.5">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-background text-primary">
                      <ShieldCheck className="size-3.5" />
                    </span>
                    <span className="min-w-0">
                      <span className="block truncate text-xs font-bold text-foreground">
                        {categoryLabels[category] || category}
                      </span>
                      <span className="mt-0.5 block text-[10px] text-muted-foreground">
                        {categoryPermissions.length} دسترسی در این بخش
                      </span>
                    </span>
                  </span>

                  <span className="flex shrink-0 items-center gap-2">
                    <Badge
                      variant={selectedCount > 0 ? 'secondary' : 'outline'}
                      className="h-5 rounded-md px-2 text-[9px] font-medium"
                    >
                      {selectedCount} از {categoryPermissions.length}
                    </Badge>
                    {isExpanded ? (
                      <ChevronUp className="size-3.5 text-muted-foreground" />
                    ) : (
                      <ChevronDown className="size-3.5 text-muted-foreground" />
                    )}
                  </span>
                </button>

                {isExpanded ? (
                  <div className="grid gap-2 border-t border-border/50 p-3 sm:grid-cols-2">
                    {categoryPermissions.map((permission) => {
                      const checked = role.permissions.includes(permission.name)
                      return (
                        <label
                          key={permission.name}
                          className={cn(
                            'group flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition-all',
                            checked
                              ? 'border-primary/25 bg-primary/[0.035] shadow-xs'
                              : 'border-border/60 bg-background hover:border-border hover:bg-muted/20',
                            saving && 'cursor-not-allowed opacity-70'
                          )}
                        >
                          <Checkbox
                            checked={checked}
                            disabled={saving}
                            onCheckedChange={(value) => onChangePermission(permission.name, value === true)}
                            className="mt-0.5"
                          />
                          <span className="min-w-0 flex-1">
                            <span className="flex items-center justify-between gap-2">
                              <span className="text-[11px] font-semibold text-foreground">
                                {checked ? 'دسترسی فعال' : 'دسترسی غیرفعال'}
                              </span>
                              {checked ? (
                                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                  <Check className="size-3" />
                                </span>
                              ) : null}
                            </span>
                            <span
                              className="mt-1 block break-all font-mono text-[10px] leading-4 text-muted-foreground"
                              dir="ltr"
                              title={permission.name}
                            >
                              {permission.name}
                            </span>
                          </span>
                        </label>
                      )
                    })}
                  </div>
                ) : null}
              </div>
            )
          })
        )}
      </CardContent>
    </Card>
  )
}

function AuditTimeline({ audits, loading }: { audits: AmmsRoleAudit[]; loading: boolean }) {
  return (
    <Card className="overflow-hidden rounded-2xl border-border/70 shadow-xs">
      <CardHeader className="border-b border-border/50 bg-muted/15 p-4">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <History className="size-4" />
            </div>
            <div>
              <CardTitle className="text-sm font-bold">تاریخچهٔ تغییرات</CardTitle>
              <CardDescription className="mt-0.5 text-[10px] leading-4">
                سوابق ایجاد، ویرایش و تغییر دسترسی‌های این نقش
              </CardDescription>
            </div>
          </div>
          {!loading ? (
            <Badge variant="outline" className="h-6 rounded-lg px-2.5 text-[10px] font-medium text-muted-foreground">
              {audits.length} رویداد
            </Badge>
          ) : null}
        </div>
      </CardHeader>

      <CardContent className="p-4">
        {loading ? (
          <div className="space-y-2.5">
            {[1, 2, 3].map((item) => (
              <div key={item} className="flex items-start gap-2.5 rounded-xl border border-border/50 p-3">
                <Skeleton className="size-8 shrink-0 rounded-xl" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-3.5 w-2/3" />
                  <Skeleton className="h-3 w-1/2" />
                </div>
              </div>
            ))}
          </div>
        ) : audits.length === 0 ? (
          <div className="flex min-h-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/70 bg-muted/10 px-4 text-center">
            <div className="flex size-9 items-center justify-center rounded-xl bg-muted text-muted-foreground">
              <History className="size-4" />
            </div>
            <p className="text-xs font-semibold text-foreground">هنوز تغییری ثبت نشده است</p>
            <p className="max-w-xs text-[10px] leading-4 text-muted-foreground">
              ایجاد نقش، ویرایش مشخصات و تغییر دسترسی‌ها به‌صورت خودکار در این بخش ثبت می‌شوند.
            </p>
          </div>
        ) : (
          <div className="max-h-[430px] space-y-2 overflow-y-auto pl-1">
            {audits.map((audit) => (
              <div
                key={audit.id}
                className="rounded-xl border border-border/60 bg-muted/15 p-3 transition-colors hover:bg-muted/25"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 items-start gap-2.5">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                      <History className="size-3.5" />
                    </div>
                    <div className="min-w-0">
                      <p className="truncate text-[11px] font-semibold text-foreground">
                        {auditLabels[audit.action] || audit.action}
                      </p>
                      <p className="mt-1 text-[10px] text-muted-foreground">
                        توسط <span className="font-medium text-foreground">{audit.actor?.name || 'سیستم'}</span>
                      </p>
                    </div>
                  </div>
                  <span className="shrink-0 whitespace-nowrap font-mono text-[9px] text-muted-foreground">
                    {formatSolarDateTime(audit.created_at)}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function PermissionMatrix({ permissions, systemRoles }: { permissions: AmmsPermissionMetadata[]; systemRoles: AmmsRoleDefinition[] }) {
  return (
    <Card className="overflow-hidden rounded-2xl border-border/70 shadow-xs">
      <CardContent className="p-0">
        {permissions.length === 0 || systemRoles.length === 0 ? (
          <p className="p-8 text-center text-xs text-muted-foreground">اطلاعات ماتریس در دسترس نیست.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-right text-[11px]">
              <thead className="bg-muted/50">
                <tr>
                  <th className="sticky right-0 z-10 bg-muted/95 p-3 text-xs font-semibold">دسترسی</th>
                  {systemRoles.map((role) => <th key={role.id} className="p-3 text-center text-[10px] font-semibold">{role.label}</th>)}
                </tr>
              </thead>
              <tbody>
                {permissions.map((permission) => (
                  <tr key={permission.name} className="border-t border-border/60 hover:bg-muted/25">
                    <td className="sticky right-0 z-10 bg-card p-3 font-mono text-[10px]" dir="ltr">{permission.name}</td>
                    {systemRoles.map((role) => (
                      <td key={role.id} className="p-3 text-center">
                        {role.permissions.includes(permission.name) ? <Check className="mx-auto size-3.5 text-emerald-600" /> : <span className="text-muted-foreground">—</span>}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function EmptyState({ icon: Icon, title, description, action }: { icon: typeof ShieldCheck; title: string; description: string; action: ReactNode }) {
  return (
    <Card className="rounded-2xl border-dashed shadow-none">
      <CardContent className="flex flex-col items-center gap-2.5 p-8 text-center">
        <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"><Icon className="size-4" /></div>
        <h3 className="text-sm font-semibold">{title}</h3>
        <p className="max-w-md text-[11px] leading-5 text-muted-foreground">{description}</p>
        {action}
      </CardContent>
    </Card>
  )
}

function RolesPageSkeleton() {
  return (
    <div className="space-y-5" aria-label="در حال بارگذاری">
      <div className="grid grid-cols-2 gap-3.5 lg:grid-cols-4">
        {[1, 2, 3, 4].map((item) => <Skeleton key={item} className="h-[78px] rounded-2xl" />)}
      </div>
      <Skeleton className="h-12 w-full rounded-xl" />
      <div className="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
        {[1, 2, 3].map((item) => <Skeleton key={item} className="h-52 rounded-2xl" />)}
      </div>
    </div>
  )
}
