import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { Activity, Check, ChevronDown, ChevronUp, FileText, History, KeyRound, LockKeyhole, Pencil, Plus, Power, RefreshCw, ShieldCheck, UsersRound } from 'lucide-react'

import { PageHeader } from '@/components/amms/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { api, apiData, apiError, apiPage } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type { AmmsPermissionMetadata } from '../lib/amms-metadata'
import type { AmmsRoleAudit, AmmsRoleDefinition } from '../types'

const categoryLabels: Record<string, string> = {
  meeting: 'جلسات', 'meeting-request': 'درخواست‌های جلسه', reporting: 'گزارش‌ها', notification: 'اعلان‌ها',
  attendance: 'حاضری', 'action-item': 'اقدامات قابل انجام', decision: 'پیگیری تصمیم‌ها',
}
const auditLabels: Record<string, string> = {
  CUSTOM_ROLE_CREATED: 'ایجاد نقش سفارشی', CUSTOM_ROLE_UPDATED: 'ویرایش نقش سفارشی',
  CUSTOM_ROLE_PERMISSIONS_CHANGED: 'تغییر دسترسی‌های نقش', CUSTOM_ROLE_ACTIVATED: 'فعال‌سازی نقش',
  CUSTOM_ROLE_DEACTIVATED: 'غیرفعال‌سازی نقش',
}
type RoleForm = { name: string; label: string; description: string }

export function RolesPermissionsPage() {
  const [roles, setRoles] = useState<AmmsRoleDefinition[]>([])
  const [permissions, setPermissions] = useState<AmmsPermissionMetadata[]>([])
  const [audits, setAudits] = useState<AmmsRoleAudit[]>([])
  const [selected, setSelected] = useState<AmmsRoleDefinition | null>(null)
  const [form, setForm] = useState<RoleForm>({ name: '', label: '', description: '' })
  const [editorOpen, setEditorOpen] = useState(false)
  const [expanded, setExpanded] = useState<Record<string, boolean>>({})
  const [loading, setLoading] = useState(true)
  const [auditsLoading, setAuditsLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true); setError(null)
    try {
      const [loadedRoles, permissionResponse] = await Promise.all([
        fetchAllAdminPages<AmmsRoleDefinition>('/admin/roles'), api.get('/admin/permissions'),
      ])
      setRoles(loadedRoles)
      setPermissions((apiData(permissionResponse) || []) as AmmsPermissionMetadata[])
      setSelected((current) => current ? loadedRoles.find((role) => role.id === current.id) || null : null)
    } catch (err) { setError(apiError(err)) } finally { setLoading(false) }
  }, [])

  useEffect(() => { void load() }, [load])
  useEffect(() => {
    if (!selected || selected.role_kind !== 'CUSTOM') { setAudits([]); return }
    let mounted = true
    setAuditsLoading(true)
    void api.get(`/admin/roles/${selected.id}/audits`, { params: { per_page: 50 } })
      .then((response) => { if (mounted) setAudits((apiPage(response)?.data || []) as AmmsRoleAudit[]) })
      .catch(() => { if (mounted) setAudits([]) })
      .finally(() => { if (mounted) setAuditsLoading(false) })
    return () => { mounted = false }
  }, [selected])

  const systemRoles = useMemo(() => roles.filter((role) => role.role_kind === 'SYSTEM'), [roles])
  const customRoles = useMemo(() => roles.filter((role) => role.role_kind === 'CUSTOM'), [roles])
  const permissionGroups = useMemo(() => permissions.filter((permission) => permission.custom_role_assignable).reduce<Record<string, AmmsPermissionMetadata[]>>((groups, permission) => {
    ;(groups[permission.category] ||= []).push(permission); return groups
  }, {}), [permissions])

  const openEditor = (role?: AmmsRoleDefinition) => {
    setSelected(role || null)
    setForm(role ? { name: role.name, label: role.label, description: role.description || '' } : { name: '', label: '', description: '' })
    setEditorOpen(true)
  }
  const save = async () => {
    setSaving(true); setError(null)
    try {
      if (selected) await api.put(`/admin/roles/${selected.id}`, form); else await api.post('/admin/roles', form)
      await load(); setEditorOpen(false); setSelected(null)
    } catch (err) { setError(apiError(err)) } finally { setSaving(false) }
  }
  const changePermissions = async (permission: string, checked: boolean) => {
    if (!selected || selected.role_kind !== 'CUSTOM') return
    const next = checked ? [...new Set([...selected.permissions, permission])] : selected.permissions.filter((item) => item !== permission)
    setSaving(true); setError(null)
    try {
      await api.post(`/admin/roles/${selected.id}/permissions`, { permissions: next }); await load()
      setSelected((current) => current ? { ...current, permissions: next } : current)
    } catch (err) { setError(apiError(err)) } finally { setSaving(false) }
  }
  const toggleStatus = async (role: AmmsRoleDefinition) => {
    setSaving(true); setError(null)
    try { await api.post(`/admin/roles/${role.id}/${role.status === 'ACTIVE' ? 'deactivate' : 'activate'}`); await load() }
    catch (err) { setError(apiError(err)) } finally { setSaving(false) }
  }

  return (
    <div className="mx-auto max-w-7xl space-y-6" dir="rtl">
      <PageHeader title="نقش‌ها و دسترسی‌ها" description="نقش‌های سیستمی را مشاهده کنید و نقش‌های سفارشی را با دسترسی‌های مورد نیاز سازمان مدیریت کنید." action={<><Button variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw className="size-4" /> تازه‌سازی</Button><Button onClick={() => openEditor()}><Plus className="size-4" /> نقش سفارشی جدید</Button></>} />
      {error ? <Alert variant="destructive" className="flex items-center justify-between gap-4 rounded-xl"><AlertDescription>{error}</AlertDescription><Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>تلاش دوباره</Button></Alert> : null}
      {loading ? <RolesPageSkeleton /> : <>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <MetricCard icon={ShieldCheck} label="مجموع نقش‌ها" value={roles.length} tone="blue" />
          <MetricCard icon={LockKeyhole} label="نقش‌های سیستمی" value={systemRoles.length} tone="slate" />
          <MetricCard icon={KeyRound} label="نقش‌های سفارشی" value={customRoles.length} tone="violet" />
          <MetricCard icon={UsersRound} label="کاربران دارای نقش سفارشی" value={customRoles.reduce((sum, role) => sum + role.assigned_user_count, 0)} tone="emerald" />
        </div>
        <Tabs defaultValue="custom" className="space-y-5">
          <TabsList className="grid h-auto w-full grid-cols-1 gap-1 rounded-xl p-1 sm:grid-cols-3"><TabsTrigger value="custom" className="h-10 rounded-lg">نقش‌های سفارشی</TabsTrigger><TabsTrigger value="system" className="h-10 rounded-lg">نقش‌های سیستمی</TabsTrigger><TabsTrigger value="matrix" className="h-10 rounded-lg">ماتریس دسترسی</TabsTrigger></TabsList>
          <TabsContent value="custom" className="space-y-5">
            <SectionHeading icon={KeyRound} title="نقش‌های سفارشی" description="نقش‌های قابل تنظیم برای نیازهای اختصاصی سازمان." action={<Button size="sm" onClick={() => openEditor()}><Plus className="size-4" /> نقش جدید</Button>} />
            {customRoles.length === 0 ? <EmptyState icon={KeyRound} title="هنوز نقش سفارشی ایجاد نشده است" description="برای ساختن نخستین نقش سفارشی و تعیین دسترسی‌های آن اقدام کنید." action={<Button onClick={() => openEditor()}><Plus className="size-4" /> ایجاد نقش سفارشی</Button>} /> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{customRoles.map((role) => <RoleCard key={role.id} role={role} selected={selected?.id === role.id} onSelect={() => setSelected(role)} onEdit={() => openEditor(role)} onToggleStatus={() => void toggleStatus(role)} disabled={saving} />)}</div>}
            {selected?.role_kind === 'CUSTOM' ? <RoleDetails role={selected} permissionsByCategory={permissionGroups} expandedCategories={expanded} onToggleCategory={(category) => setExpanded((current) => ({ ...current, [category]: !(current[category] ?? true) }))} onChangePermission={changePermissions} audits={audits} auditsLoading={auditsLoading} saving={saving} /> : null}
          </TabsContent>
          <TabsContent value="system" className="space-y-5"><SectionHeading icon={LockKeyhole} title="نقش‌های سیستمی" description="این نقش‌ها بخشی از ساختار اصلی سامانه هستند و فقط برای مشاهده نمایش داده می‌شوند." /><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">{systemRoles.map((role) => <RoleCard key={role.id} role={role} />)}</div></TabsContent>
          <TabsContent value="matrix"><PermissionMatrix permissions={permissions} systemRoles={systemRoles} /></TabsContent>
        </Tabs>
      </>}
      <Sheet open={editorOpen} onOpenChange={setEditorOpen}><SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg"><SheetHeader className="border-b px-6 py-5"><SheetTitle>{selected ? 'ویرایش نقش سفارشی' : 'ایجاد نقش سفارشی'}</SheetTitle><SheetDescription>مشخصات نقش را وارد کنید. دسترسی‌ها پس از ذخیره از بخش جزئیات نقش قابل تنظیم هستند.</SheetDescription></SheetHeader><div className="space-y-5 px-6 py-5"><label className="block space-y-2 text-sm font-medium">نام فنی نقش<Input value={form.name} disabled={!!selected} onChange={(event) => setForm({ ...form, name: event.target.value.toUpperCase() })} placeholder="REPORT_VIEWER" className="rounded-xl" /><span className="block text-xs font-normal text-muted-foreground">نام یکتا با حروف انگلیسی، عدد و زیرخط.</span></label><label className="block space-y-2 text-sm font-medium">عنوان نمایشی<Input value={form.label} onChange={(event) => setForm({ ...form, label: event.target.value })} placeholder="مشاهده‌گر گزارش‌ها" className="rounded-xl" /></label><label className="block space-y-2 text-sm font-medium">توضیحات<Textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="کاربرد این نقش را کوتاه توضیح دهید." className="min-h-28 rounded-xl" /></label></div><SheetFooter className="border-t px-6 py-5 sm:flex-row sm:justify-start"><Button onClick={() => void save()} disabled={saving || !form.name || !form.label}>{saving ? 'در حال ذخیره…' : selected ? 'ذخیره تغییرات' : 'ایجاد نقش'}</Button><Button variant="outline" onClick={() => setEditorOpen(false)} disabled={saving}>انصراف</Button></SheetFooter></SheetContent></Sheet>
    </div>
  )
}

function MetricCard({ icon: Icon, label, value, tone }: { icon: typeof ShieldCheck; label: string; value: number; tone: 'blue' | 'slate' | 'violet' | 'emerald' }) {
  const tones = { blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300', slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', violet: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300', emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }
  return <Card className="rounded-2xl border-border/70 shadow-sm"><CardContent className="flex items-center gap-4 p-5"><div className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${tones[tone]}`}><Icon className="size-5" /></div><div><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 text-2xl font-bold tracking-tight">{value}</p></div></CardContent></Card>
}

function SectionHeading({ icon: Icon, title, description, action }: { icon: typeof ShieldCheck; title: string; description: string; action?: ReactNode }) {
  return <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div className="flex items-start gap-3"><div className="mt-0.5 rounded-lg bg-primary/10 p-2 text-primary"><Icon className="size-4" /></div><div><h2 className="font-semibold">{title}</h2><p className="mt-1 text-sm text-muted-foreground">{description}</p></div></div>{action}</div>
}

function RoleCard({ role, selected = false, onSelect, onEdit, onToggleStatus, disabled = false }: { role: AmmsRoleDefinition; selected?: boolean; onSelect?: () => void; onEdit?: () => void; onToggleStatus?: () => void; disabled?: boolean }) {
  const isSystem = role.role_kind === 'SYSTEM'
  return <Card className={`rounded-2xl border-border/70 transition-shadow hover:shadow-md ${selected ? 'border-primary ring-2 ring-primary/15' : ''}`}><CardHeader className="gap-3 border-b border-border/60 pb-4"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><CardTitle className="truncate text-base">{role.label}</CardTitle><CardDescription className="mt-1 truncate font-mono text-[11px]" dir="ltr">{role.name}</CardDescription></div><RoleStatusBadge role={role} /></div><div className="flex flex-wrap gap-1.5"><Badge variant={isSystem ? 'secondary' : 'outline'}>{isSystem ? 'سیستمی' : 'سفارشی'}</Badge>{isSystem ? <Badge variant="outline"><LockKeyhole className="size-3" /> محافظت‌شده</Badge> : null}</div></CardHeader><CardContent className="space-y-4 p-4"><p className="min-h-10 text-sm leading-6 text-muted-foreground">{role.description || (isSystem ? 'نقش پایهٔ سامانه با دسترسی‌های تعریف‌شدهٔ مرکزی.' : 'نقش سفارشی با دسترسی محدود و قابل تنظیم.')}</p><div className="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3 text-xs"><div><p className="text-muted-foreground">دسترسی‌ها</p><p className="mt-1 font-semibold">{role.permissions.length}</p></div><div><p className="text-muted-foreground">کاربران</p><p className="mt-1 font-semibold">{role.assigned_user_count}</p></div></div>{isSystem ? <div className="flex items-center gap-2 text-xs text-muted-foreground"><Check className="size-3.5 text-primary" /> فقط برای مشاهده</div> : <div className="flex flex-wrap gap-2"><Button variant={selected ? 'default' : 'outline'} size="sm" onClick={onSelect} disabled={disabled}><Activity className="size-3.5" /> {selected ? 'جزئیات انتخاب‌شده' : 'مشاهده جزئیات'}</Button><Button variant="outline" size="sm" onClick={onEdit} disabled={disabled}><Pencil className="size-3.5" /> ویرایش</Button><Button variant="ghost" size="sm" onClick={onToggleStatus} disabled={disabled}><Power className="size-3.5" /> {role.status === 'ACTIVE' ? 'غیرفعال‌سازی' : 'فعال‌سازی'}</Button></div>}</CardContent></Card>
}

function RoleStatusBadge({ role }: { role: AmmsRoleDefinition }) {
  return <Badge variant={role.status === 'ACTIVE' ? 'default' : 'secondary'} className={role.status === 'ACTIVE' ? 'bg-emerald-600 hover:bg-emerald-600' : ''}>{role.status === 'ACTIVE' ? 'فعال' : 'غیرفعال'}</Badge>
}

function RoleDetails({ role, permissionsByCategory, expandedCategories, onToggleCategory, onChangePermission, audits, auditsLoading, saving }: { role: AmmsRoleDefinition; permissionsByCategory: Record<string, AmmsPermissionMetadata[]>; expandedCategories: Record<string, boolean>; onToggleCategory: (category: string) => void; onChangePermission: (permission: string, checked: boolean) => void; audits: AmmsRoleAudit[]; auditsLoading: boolean; saving: boolean }) {
  return <div className="space-y-4"><div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-semibold text-primary">جزئیات نقش انتخاب‌شده</p><h2 className="mt-1 text-xl font-bold">{role.label}</h2></div><p className="font-mono text-xs text-muted-foreground" dir="ltr">{role.name}</p></div><div className="grid gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]"><PermissionEditor role={role} permissionsByCategory={permissionsByCategory} expandedCategories={expandedCategories} onToggleCategory={onToggleCategory} onChangePermission={onChangePermission} saving={saving} /><AuditTimeline audits={audits} loading={auditsLoading} /></div></div>
}

function PermissionEditor({ role, permissionsByCategory, expandedCategories, onToggleCategory, onChangePermission, saving }: { role: AmmsRoleDefinition; permissionsByCategory: Record<string, AmmsPermissionMetadata[]>; expandedCategories: Record<string, boolean>; onToggleCategory: (category: string) => void; onChangePermission: (permission: string, checked: boolean) => void; saving: boolean }) {
  const entries = Object.entries(permissionsByCategory)
  return <Card className="rounded-2xl border-border/70"><CardHeader><div className="flex items-center gap-2"><KeyRound className="size-4 text-primary" /><CardTitle>دسترسی‌های قابل واگذاری</CardTitle></div><CardDescription>دسترسی‌های نقش را از میان گزینه‌های مجاز انتخاب کنید. تغییرات بلافاصله ذخیره می‌شوند.</CardDescription></CardHeader><CardContent className="space-y-3">{entries.length === 0 ? <p className="rounded-xl border border-dashed p-5 text-center text-sm text-muted-foreground">دسترسی قابل واگذاری یافت نشد.</p> : entries.map(([category, categoryPermissions]) => { const isExpanded = expandedCategories[category] ?? true; const selectedCount = categoryPermissions.filter((permission) => role.permissions.includes(permission.name)).length; return <div key={category} className="overflow-hidden rounded-xl border border-border/70"><button type="button" className="flex w-full items-center justify-between gap-3 bg-muted/35 p-3 text-start hover:bg-muted/60" onClick={() => onToggleCategory(category)}><span className="font-semibold">{categoryLabels[category] || category}</span><span className="flex items-center gap-2 text-xs text-muted-foreground"><span>{selectedCount} از {categoryPermissions.length}</span>{isExpanded ? <ChevronUp className="size-4" /> : <ChevronDown className="size-4" />}</span></button>{isExpanded ? <div className="grid gap-2 p-3 sm:grid-cols-2">{categoryPermissions.map((permission) => <label key={permission.name} className="flex cursor-pointer items-start gap-2 rounded-lg border border-transparent p-2.5 text-sm transition-colors hover:border-border hover:bg-muted/30"><Checkbox checked={role.permissions.includes(permission.name)} disabled={saving} onCheckedChange={(checked) => onChangePermission(permission.name, checked === true)} className="mt-0.5" /><span className="leading-5" title={permission.name}>{permission.name}</span></label>)}</div> : null}</div> })}</CardContent></Card>
}

function AuditTimeline({ audits, loading }: { audits: AmmsRoleAudit[]; loading: boolean }) {
  return <Card className="rounded-2xl border-border/70"><CardHeader><div className="flex items-center gap-2"><History className="size-4 text-primary" /><CardTitle>تاریخچهٔ تغییرات</CardTitle></div><CardDescription>رویدادهای ثبت‌شده برای این نقش.</CardDescription></CardHeader><CardContent>{loading ? <div className="space-y-4"><Skeleton className="h-12 w-full" /><Skeleton className="h-12 w-full" /><Skeleton className="h-12 w-4/5" /></div> : audits.length === 0 ? <p className="rounded-xl border border-dashed p-5 text-center text-sm text-muted-foreground">هنوز رویدادی ثبت نشده است.</p> : <div className="relative space-y-4 before:absolute before:inset-y-2 before:right-[7px] before:w-px before:bg-border">{audits.map((audit) => <div key={audit.id} className="relative flex gap-3"><span className="z-10 mt-1.5 size-3.5 shrink-0 rounded-full border-2 border-background bg-primary ring-1 ring-primary/25" /><div className="min-w-0 flex-1 rounded-xl bg-muted/40 p-3"><p className="text-sm font-semibold">{auditLabels[audit.action] || 'تغییر در نقش'}</p><p className="mt-1 text-xs text-muted-foreground">{audit.actor?.name || 'سامانه'} · {formatDate(audit.created_at)}</p></div></div>)}</div>}</CardContent></Card>
}

function PermissionMatrix({ permissions, systemRoles }: { permissions: AmmsPermissionMetadata[]; systemRoles: AmmsRoleDefinition[] }) {
  return <Card className="rounded-2xl border-border/70"><CardHeader><div className="flex items-center gap-2"><FileText className="size-4 text-primary" /><CardTitle>ماتریس دسترسی نقش‌های سیستمی</CardTitle></div><CardDescription>این جدول برای مقایسهٔ دسترسی‌های از پیش تعریف‌شدهٔ نقش‌های سیستمی است.</CardDescription></CardHeader><CardContent className="p-0">{permissions.length === 0 || systemRoles.length === 0 ? <p className="p-8 text-center text-sm text-muted-foreground">اطلاعات ماتریس در دسترس نیست.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-right text-xs"><thead className="bg-muted/50"><tr><th className="sticky right-0 bg-muted/90 p-3 font-semibold">دسترسی</th>{systemRoles.map((role) => <th key={role.id} className="p-3 font-semibold">{role.label}</th>)}</tr></thead><tbody>{permissions.map((permission) => <tr key={permission.name} className="border-t border-border/60 hover:bg-muted/30"><td className="sticky right-0 bg-card p-3 font-mono" dir="ltr">{permission.name}</td>{systemRoles.map((role) => <td key={role.id} className="p-3 text-center">{role.permissions.includes(permission.name) ? <Check className="mx-auto size-4 text-emerald-600" /> : <span className="text-muted-foreground">—</span>}</td>)}</tr>)}</tbody></table></div>}</CardContent></Card>
}

function EmptyState({ icon: Icon, title, description, action }: { icon: typeof ShieldCheck; title: string; description: string; action: ReactNode }) {
  return <Card className="rounded-2xl border-dashed"><CardContent className="flex flex-col items-center gap-3 p-10 text-center"><div className="rounded-2xl bg-primary/10 p-3 text-primary"><Icon className="size-6" /></div><h3 className="font-semibold">{title}</h3><p className="max-w-md text-sm text-muted-foreground">{description}</p>{action}</CardContent></Card>
}

function RolesPageSkeleton() {
  return <div className="space-y-5" aria-label="در حال بارگذاری"><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[1, 2, 3, 4].map((item) => <Skeleton key={item} className="h-24 rounded-2xl" />)}</div><Skeleton className="h-10 w-full rounded-xl" /><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{[1, 2, 3].map((item) => <Skeleton key={item} className="h-64 rounded-2xl" />)}</div></div>
}

function formatDate(value: string) { return new Date(value).toLocaleString('fa-IR') }
