import { useCallback, useEffect, useMemo, useState } from 'react'
import { Check, LockKeyhole, Pencil, Plus, Power, RefreshCw, ShieldCheck } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { StatusBadge } from '@/components/amms/status-badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { api, apiData, apiError, apiPage } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type { AmmsPermissionMetadata } from '../lib/amms-metadata'
import type { AmmsRoleAudit, AmmsRoleDefinition } from '../types'

const categoryLabels: Record<string, string> = {
  meeting: 'جلسات',
  'meeting-request': 'درخواست جلسه',
  reporting: 'گزارش‌ها',
  notification: 'اطلاعیه‌ها',
  attendance: 'حاضری',
  'action-item': 'اقدامات قابل انجام',
  decision: 'پیگیری تصمیم‌ها',
}

const auditLabels: Record<string, string> = {
  CUSTOM_ROLE_CREATED: 'ایجاد نقش سفارشی',
  CUSTOM_ROLE_UPDATED: 'ویرایش نقش سفارشی',
  CUSTOM_ROLE_PERMISSIONS_CHANGED: 'تغییر permissionها',
  CUSTOM_ROLE_ACTIVATED: 'فعال‌سازی نقش',
  CUSTOM_ROLE_DEACTIVATED: 'غیرفعال‌سازی نقش',
}

export function RolesPermissionsPage() {
  const [roles, setRoles] = useState<AmmsRoleDefinition[]>([])
  const [permissions, setPermissions] = useState<AmmsPermissionMetadata[]>([])
  const [audits, setAudits] = useState<AmmsRoleAudit[]>([])
  const [selected, setSelected] = useState<AmmsRoleDefinition | null>(null)
  const [form, setForm] = useState({ name: '', label: '', description: '' })
  const [loading, setLoading] = useState(true)
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
      setSelected((current) => current ? loadedRoles.find((role) => role.id === current.id) || null : null)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { void load() }, [load])

  useEffect(() => {
    if (!selected || selected.role_kind !== 'CUSTOM') {
      setAudits([])
      return
    }
    void api.get(`/admin/roles/${selected.id}/audits`, { params: { per_page: 50 } })
      .then((response) => setAudits((apiPage(response)?.data || []) as AmmsRoleAudit[]))
      .catch(() => setAudits([]))
  }, [selected])

  const systemRoles = useMemo(() => roles.filter((role) => role.role_kind === 'SYSTEM'), [roles])
  const customRoles = useMemo(() => roles.filter((role) => role.role_kind === 'CUSTOM'), [roles])
  const assignablePermissions = useMemo(() => permissions.filter((permission) => permission.custom_role_assignable), [permissions])
  const assignablePermissionsByCategory = useMemo(() => assignablePermissions.reduce<Record<string, AmmsPermissionMetadata[]>>((groups, permission) => {
    (groups[permission.category] ||= []).push(permission)
    return groups
  }, {}), [assignablePermissions])

  const edit = (role: AmmsRoleDefinition) => {
    setSelected(role)
    setForm({ name: role.name, label: role.label, description: role.description || '' })
  }

  const create = () => {
    setSelected(null)
    setForm({ name: '', label: '', description: '' })
  }

  const save = async () => {
    setSaving(true)
    setError(null)
    try {
      if (selected) await api.put(`/admin/roles/${selected.id}`, form)
      else await api.post('/admin/roles', form)
      await load()
      setSelected(null)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const changePermissions = async (permission: string, checked: boolean) => {
    if (!selected) return
    const next = checked ? [...selected.permissions, permission] : selected.permissions.filter((item) => item !== permission)
    setSaving(true)
    try {
      await api.post(`/admin/roles/${selected.id}/permissions`, { permissions: next })
      await load()
      setSelected((current) => current ? { ...current, permissions: next } : current)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  const toggleStatus = async (role: AmmsRoleDefinition) => {
    setSaving(true)
    try {
      await api.post(`/admin/roles/${role.id}/${role.status === 'ACTIVE' ? 'deactivate' : 'activate'}`)
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
        eyebrow="مدیریت دسترسی"
        title="نقش‌ها و permissionها"
        description="نقش‌های سیستمی ثابت هستند. نقش‌های سفارشی فقط permissionهای خواندنی و گزارش‌دهیِ محدود به حوزه سازمانی را دریافت می‌کنند."
        action={<div className="flex gap-2"><Button variant="outline" onClick={() => void load()} disabled={loading} className="rounded-xl"><RefreshCw className="ms-2 size-4" /> تازه‌سازی</Button><Button onClick={create} className="rounded-xl"><Plus className="ms-2 size-4" /> نقش سفارشی</Button></div>}
      />
      {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}

      <section className="space-y-3">
        <div className="flex items-center gap-2"><LockKeyhole className="size-4 text-muted-foreground" /><h2 className="text-lg font-semibold">نقش‌های سیستمی</h2></div>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {systemRoles.map((role) => <RoleCard key={role.id} role={role} onEdit={edit} />)}
        </div>
      </section>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle>ماتریس permission نقش‌های سیستمی</CardTitle><CardDescription>این ماتریس فقط برای مشاهده است و از رجیستری canonical سیستم خوانده می‌شود.</CardDescription></CardHeader>
        <CardContent className="overflow-x-auto p-0"><table className="w-full min-w-[760px] text-right text-xs"><thead className="bg-muted/50"><tr><th className="sticky right-0 bg-muted/90 p-3">permission</th>{systemRoles.map((role) => <th key={role.id} className="p-3 font-medium">{role.label}</th>)}</tr></thead><tbody>{permissions.map((permission) => <tr key={permission.name} className="border-t"><td className="sticky right-0 bg-card p-3 font-mono">{permission.name}</td>{systemRoles.map((role) => <td key={role.id} className="p-3 text-center">{role.permissions.includes(permission.name) ? <Check className="mx-auto size-4 text-primary" /> : <span className="text-muted-foreground">—</span>}</td>)}</tr>)}</tbody></table></CardContent>
      </Card>

      <section className="space-y-3">
        <div className="flex items-center justify-between"><div className="flex items-center gap-2"><ShieldCheck className="size-4 text-primary" /><h2 className="text-lg font-semibold">نقش‌های سفارشی</h2></div><span className="text-xs text-muted-foreground">{customRoles.length} نقش</span></div>
        {customRoles.length === 0 && !loading ? <Card className="rounded-2xl"><CardContent className="p-8 text-center text-sm text-muted-foreground">هنوز نقش سفارشی ایجاد نشده است.</CardContent></Card> : null}
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{customRoles.map((role) => <RoleCard key={role.id} role={role} onEdit={edit} onToggleStatus={toggleStatus} />)}</div>
      </section>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle>{selected ? `ویرایش ${selected.label}` : 'ایجاد نقش سفارشی'}</CardTitle><CardDescription>نام باید با حروف بزرگ و قالب SNAKE_CASE باشد؛ permissionهای workflow و مدیریتی قابل انتخاب نیستند.</CardDescription></CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2"><label className="space-y-1 text-sm">نام فنی<Input value={form.name} disabled={!!selected} onChange={(event) => setForm({ ...form, name: event.target.value.toUpperCase() })} placeholder="REPORT_VIEWER" className="mt-1 rounded-xl" /></label><label className="space-y-1 text-sm">عنوان نمایشی<Input value={form.label} onChange={(event) => setForm({ ...form, label: event.target.value })} className="mt-1 rounded-xl" /></label></div>
          <label className="block space-y-1 text-sm">توضیحات<Textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} className="mt-1 rounded-xl" /></label>
          <div className="flex gap-2"><Button onClick={() => void save()} disabled={saving || !form.name || !form.label} className="rounded-xl">{selected ? 'ذخیره تغییرات' : 'ایجاد نقش'}</Button>{selected ? <Button variant="outline" onClick={create} className="rounded-xl">لغو</Button> : null}</div>
        </CardContent>
      </Card>

      {selected?.role_kind === 'CUSTOM' ? <Card className="rounded-2xl"><CardHeader><CardTitle>permissionهای قابل واگذاری</CardTitle><CardDescription>این فهرست whitelist امنیتی است؛ موارد نامطمئن عمداً protected باقی می‌مانند.</CardDescription></CardHeader><CardContent className="space-y-5">{Object.entries(assignablePermissionsByCategory).map(([category, categoryPermissions]) => <section key={category} className="space-y-2"><h3 className="text-sm font-semibold">{categoryLabels[category] || category}</h3><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{categoryPermissions.map((permission) => <label key={permission.name} className="flex items-center gap-2 rounded-xl border p-3 text-sm"><Checkbox checked={selected.permissions.includes(permission.name)} disabled={saving} onCheckedChange={(checked) => void changePermissions(permission.name, checked === true)} /><span>{permission.name}</span></label>)}</div></section>)}</CardContent></Card> : null}
      {selected?.role_kind === 'CUSTOM' ? <Card className="rounded-2xl"><CardHeader><CardTitle>تاریخچه تغییرات نقش</CardTitle><CardDescription>رویدادهای ایجاد، تغییر، فعال‌سازی و تغییر permission این نقش.</CardDescription></CardHeader><CardContent className="space-y-2">{audits.length === 0 ? <p className="text-sm text-muted-foreground">رویدادی ثبت نشده است.</p> : audits.map((audit) => <div key={audit.id} className="flex items-center justify-between rounded-xl border p-3 text-xs"><span className="font-medium">{auditLabels[audit.action] || 'تغییر دسترسی'}</span><span className="text-muted-foreground">{audit.actor?.name || 'سیستم'} · {new Date(audit.created_at).toLocaleString('fa-IR')}</span></div>)}</CardContent></Card> : null}
    </div>
  )
}

function RoleCard({ role, onEdit, onToggleStatus }: { role: AmmsRoleDefinition; onEdit: (role: AmmsRoleDefinition) => void; onToggleStatus?: (role: AmmsRoleDefinition) => void }) {
  return <Card className="rounded-2xl"><CardHeader className="border-b"><div className="flex items-start justify-between gap-2"><div><CardTitle>{role.label}</CardTitle><CardDescription className="mt-1 font-mono text-[11px]">{role.name}</CardDescription></div><StatusBadge status={role.status} /></div></CardHeader><CardContent className="space-y-4 p-4"><p className="min-h-10 text-sm text-muted-foreground">{role.description || (role.role_kind === 'SYSTEM' ? 'نقش canonical و محافظت‌شده سیستم.' : 'نقش سفارشی با دسترسی محدود.')}</p><div className="flex items-center justify-between text-xs text-muted-foreground"><span>{role.permissions.length} permission</span><span>{role.assigned_user_count} کاربر</span></div><div className="flex gap-2">{role.role_kind === 'CUSTOM' ? <><Button variant="outline" size="sm" onClick={() => onEdit(role)} className="rounded-xl"><Pencil className="ms-1 size-3" /> ویرایش</Button><Button variant="outline" size="sm" onClick={() => onToggleStatus?.(role)} className="rounded-xl"><Power className="ms-1 size-3" /> {role.status === 'ACTIVE' ? 'غیرفعال‌سازی' : 'فعال‌سازی'}</Button></> : <span className="inline-flex items-center gap-1 text-xs text-muted-foreground"><Check className="size-3" /> فقط خواندنی</span>}</div></CardContent></Card>
}
