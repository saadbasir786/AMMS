import { useCallback, useEffect, useState } from 'react'
import { History, KeyRound, Pencil, Plus, RotateCw, ShieldCheck, UserCog, X } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData, apiError, apiPage } from '@/lib/api-client'
import type { AdminUser, Employee, OrganizationalUnit, UserAccessAudit, UserRoleScope } from '../types'

const roleLabels: Record<string, string> = {
  SYSTEM_ADMIN: 'مدیر سیستم', EMPLOYEE: 'کارمند', DIRECTOR: 'رییس ریاست', DEPUTY_DIRECTOR: 'معاون ریاست',
  EXECUTIVE_MANAGEMENT: 'مدیر اجراییه', AUTHORITY_OFFICE: 'ریاست دفتر مقام', EXECUTIVE_SECRETARY: 'سکرتر اجرائیوی', HIGH_AUTHORITY: 'مقام عالی',
}
const auditLabels: Record<string, string> = {
  ACCOUNT_CREATED: 'حساب ایجاد شد', ACCOUNT_UPDATED: 'مشخصات حساب اصلاح شد', ACCOUNT_DEACTIVATED: 'حساب غیرفعال شد', ACCOUNT_ACTIVATED: 'حساب فعال شد',
  PASSWORD_CHANGED: 'رمز عبور تغییر یافت', ROLE_ASSIGNED: 'نقش واگذار شد', ROLE_REVOKED: 'نقش لغو شد',
  CAPABILITY_GRANTED: 'صلاحیت ویژه واگذار شد', CAPABILITY_REVOKED: 'صلاحیت ویژه لغو شد',
}
type AccountForm = { name: string; email: string; password: string; employee_id: string; status: 'ACTIVE' | 'INACTIVE' }
const emptyForm: AccountForm = { name: '', email: '', password: '', employee_id: '', status: 'ACTIVE' }

export function UsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([])
  const [employees, setEmployees] = useState<Employee[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [audits, setAudits] = useState<UserAccessAudit[]>([])
  const [selected, setSelected] = useState<AdminUser | null>(null)
  const [form, setForm] = useState<AccountForm>(emptyForm)
  const [role, setRole] = useState('EMPLOYEE')
  const [roleUnitId, setRoleUnitId] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [revokeTarget, setRevokeTarget] = useState<UserRoleScope | null>(null)
  const [confirmDeactivation, setConfirmDeactivation] = useState(false)

  const load = useCallback(async () => {
    setLoading(true); setError(null)
    try {
      const [usersResponse, employeesResponse, unitsResponse] = await Promise.all([api.get('/admin/users'), api.get('/admin/employees'), api.get('/admin/organizational-units')])
      const loadedUsers = apiPage(usersResponse)?.data || []
      setUsers(loadedUsers); setEmployees(apiPage(employeesResponse)?.data || []); setUnits(apiData(unitsResponse) || [])
      setSelected((current) => current ? loadedUsers.find((user: AdminUser) => user.id === current.id) || null : null)
    } catch (err) { setError(apiError(err)) } finally { setLoading(false) }
  }, [])

  const loadAudits = useCallback(async (userId: number) => {
    try { const response = await api.get(`/admin/users/${userId}/access-audits`); setAudits(apiPage(response)?.data || []) } catch (err) { setError(apiError(err)) }
  }, [])

  useEffect(() => { void load() }, [load])
  useEffect(() => { if (selected) void loadAudits(selected.id); else setAudits([]) }, [selected, loadAudits])

  const beginEdit = (user: AdminUser) => {
    setSelected(user)
    setForm({ name: user.name, email: user.email, password: '', employee_id: user.employee_id ? String(user.employee_id) : '', status: user.status || 'ACTIVE' })
  }
  const resetForm = () => { setSelected(null); setForm(emptyForm); setAudits([]) }
  const save = async () => {
    setSaving(true); setError(null)
    try {
      const payload = { ...form, employee_id: form.employee_id ? Number(form.employee_id) : null, password: form.password || undefined }
      const response = selected ? await api.put(`/admin/users/${selected.id}`, payload) : await api.post('/admin/users', payload)
      const saved = apiData(response) as AdminUser
      await load()
      if (saved?.id) beginEdit(saved)
    } catch (err) { setError(apiError(err)) } finally { setSaving(false) }
  }
  const submit = (event: React.FormEvent) => { event.preventDefault(); if (selected?.status !== 'INACTIVE' && form.status === 'INACTIVE') setConfirmDeactivation(true); else void save() }
  const assignRole = async () => {
    if (!selected) return
    setSaving(true); setError(null)
    try {
      await api.post(`/admin/users/${selected.id}/roles`, { role, organizational_unit_id: role === 'SYSTEM_ADMIN' ? null : Number(roleUnitId) })
      setRoleUnitId(''); await load(); await loadAudits(selected.id)
    } catch (err) { setError(apiError(err)) } finally { setSaving(false) }
  }
  const revokeRole = async () => {
    if (!selected || !revokeTarget) return
    setSaving(true); setError(null)
    try { await api.delete(`/admin/users/${selected.id}/roles/${revokeTarget.id}`); setRevokeTarget(null); await load(); await loadAudits(selected.id) } catch (err) { setError(apiError(err)) } finally { setSaving(false) }
  }

  return <div className="space-y-6" dir="rtl">
    <PageHeader title="کاربران و صلاحیت‌ها" description="حساب کاربری، نقش سازمانی و حوزهٔ دسترسی را با تاریخچهٔ قابل حسابرسی مدیریت کنید. تفویض‌های تجاری جلسات در صفحه‌های تخصصی خود باقی می‌مانند." action={<Badge variant="outline" className="rounded-lg border-primary/30 px-2.5 py-1 text-xs text-primary"><ShieldCheck className="ml-1 size-3.5" />{users.filter((user) => user.status !== 'INACTIVE').length} حساب فعال</Badge>} />
    {error && <Alert variant="destructive" className="rounded-2xl"><AlertDescription className="flex items-center justify-between gap-3 text-xs"><span>{error}</span><Button type="button" variant="outline" size="sm" className="h-8 rounded-xl text-xs" onClick={() => void load()}><RotateCw className="ml-1 size-3" />تلاش مجدد</Button></AlertDescription></Alert>}
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
      <section className="space-y-4">
        <Card className="rounded-2xl border-border/70 shadow-xs"><CardHeader className="p-4 sm:p-5"><CardTitle className="flex items-center gap-2 text-sm"><UserCog className="size-4 text-primary" />فهرست حساب‌های کاربری</CardTitle></CardHeader><CardContent className="p-0">
          {loading ? <div className="space-y-3 p-5">{[1,2,3,4].map((item) => <div key={item} className="h-16 animate-pulse rounded-xl bg-muted/60" />)}</div> : users.length === 0 ? <p className="p-8 text-center text-sm text-muted-foreground">هنوز حسابی ثبت نشده است.</p> : <div className="divide-y divide-border/50">{users.map((user) => <button type="button" key={user.id} onClick={() => beginEdit(user)} className={`flex w-full items-center justify-between gap-3 p-4 text-right transition-colors hover:bg-muted/30 ${selected?.id === user.id ? 'bg-primary/5' : ''}`}><span className="min-w-0"><strong className="block truncate text-sm">{user.name}</strong><span className="mt-1 block truncate text-xs text-muted-foreground">{user.email} · {user.employee?.full_name || 'حساب فنی/بدون کارمند'}</span></span><span className="flex shrink-0 flex-wrap justify-end gap-1"><Badge variant={user.status === 'INACTIVE' ? 'outline' : 'secondary'} className="rounded-lg text-[10px]">{user.status === 'INACTIVE' ? 'غیرفعال' : 'فعال'}</Badge>{(user.roles || []).map((scope) => <Badge key={scope.id} variant="outline" className="rounded-lg text-[10px]">{roleLabels[scope.role] || scope.role}</Badge>)}</span></button>)}</div>}
        </CardContent></Card>
        {selected && <Card className="rounded-2xl border-border/70 shadow-xs"><CardHeader className="p-4 sm:p-5"><CardTitle className="flex items-center gap-2 text-sm"><History className="size-4 text-primary" />تاریخچهٔ دسترسی</CardTitle></CardHeader><CardContent className="space-y-3 p-4 sm:p-5">{audits.length === 0 ? <p className="text-xs text-muted-foreground">رویدادی برای این حساب ثبت نشده است.</p> : audits.map((audit) => <div key={audit.id} className="rounded-xl border border-border/60 p-3 text-xs"><div className="flex justify-between gap-3"><strong>{auditLabels[audit.action] || audit.action}</strong><span className="text-muted-foreground">{formatSolarDateTime(audit.created_at)}</span></div><p className="mt-1 text-muted-foreground">{audit.role ? `${roleLabels[audit.role] || audit.role}${audit.unit ? ` · ${audit.unit.name}` : ''}` : audit.unit?.name || 'تغییر حساب'} · توسط {audit.actor?.name || 'سیستم'}</p></div>)}</CardContent></Card>}
      </section>
      <section className="space-y-6">
        <Card className="rounded-2xl border-border/70 shadow-xs"><CardHeader className="p-4 sm:p-5"><CardTitle className="text-sm">{selected ? 'اصلاح حساب کاربری' : 'ایجاد حساب کاربری'}</CardTitle></CardHeader><CardContent className="p-4 sm:p-5"><form className="space-y-3.5" onSubmit={submit}><label className="block space-y-1.5 text-xs font-bold">نام نمایشی<Input required value={form.name} onChange={(event) => setForm((value) => ({ ...value, name: event.target.value }))} className="h-10 rounded-xl text-xs" /></label><label className="block space-y-1.5 text-xs font-bold">ایمیل ورود<Input required type="email" value={form.email} onChange={(event) => setForm((value) => ({ ...value, email: event.target.value }))} className="h-10 rounded-xl text-xs" /></label><label className="block space-y-1.5 text-xs font-bold">کارمند مرتبط<select value={form.employee_id} onChange={(event) => setForm((value) => ({ ...value, employee_id: event.target.value }))} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal"><option value="">حساب فنی / بدون کارمند</option>{employees.filter((employee) => employee.status !== 'INACTIVE' && (!employee.email || !users.some((user) => user.employee_id === employee.id && user.id !== selected?.id))).map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name} · {employee.employee_number}</option>)}</select></label><label className="block space-y-1.5 text-xs font-bold">رمز عبور {selected && <span className="font-normal text-muted-foreground">(برای حفظ رمز فعلی خالی بگذارید)</span>}<Input required={!selected} type="password" value={form.password} onChange={(event) => setForm((value) => ({ ...value, password: event.target.value }))} className="h-10 rounded-xl text-xs" /></label>{selected && <label className="block space-y-1.5 text-xs font-bold">وضعیت<select value={form.status} onChange={(event) => setForm((value) => ({ ...value, status: event.target.value as AccountForm['status'] }))} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal"><option value="ACTIVE">فعال</option><option value="INACTIVE">غیرفعال</option></select></label>}<div className="flex gap-2 pt-2"><Button disabled={saving} type="submit" className="h-10 flex-1 rounded-xl text-xs">{selected ? 'ثبت اصلاحات' : <><Plus className="ml-1 size-3.5" />ایجاد حساب</>}</Button>{selected && <Button type="button" variant="outline" onClick={resetForm} className="h-10 rounded-xl text-xs">انصراف</Button>}</div></form></CardContent></Card>
        {selected && <Card className="rounded-2xl border-border/70 shadow-xs"><CardHeader className="p-4 sm:p-5"><CardTitle className="flex items-center gap-2 text-sm"><KeyRound className="size-4 text-primary" />نقش‌ها و حوزه‌ها</CardTitle></CardHeader><CardContent className="space-y-3 p-4 sm:p-5"><div className="space-y-2">{(selected.roles || []).map((scope) => <div key={scope.id} className="flex items-center justify-between gap-2 rounded-xl border border-border/60 p-2.5 text-xs"><span>{roleLabels[scope.role] || scope.role} {scope.unit ? `· ${scope.unit.name}` : '· سراسری'}</span><Button type="button" size="icon" variant="ghost" className="size-7 text-destructive" onClick={() => setRevokeTarget(scope)}><X className="size-3.5" /></Button></div>)}</div><select value={role} onChange={(event) => { setRole(event.target.value); setRoleUnitId('') }} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs">{Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{role !== 'SYSTEM_ADMIN' && <select required value={roleUnitId} onChange={(event) => setRoleUnitId(event.target.value)} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs"><option value="">حوزهٔ سازمانی را انتخاب کنید</option>{units.filter((unit) => unit.status !== 'INACTIVE').map((unit) => <option key={unit.id} value={unit.id}>{unit.name}</option>)}</select>}<Button type="button" disabled={saving || (role !== 'SYSTEM_ADMIN' && !roleUnitId)} onClick={() => void assignRole()} className="h-10 w-full rounded-xl text-xs"><Plus className="ml-1 size-3.5" />واگذاری نقش</Button></CardContent></Card>}
      </section>
    </div>
    <ConfirmDialog open={confirmDeactivation} onOpenChange={setConfirmDeactivation} title="غیرفعال‌سازی حساب تأیید شود؟" description="توکن‌های فعال این کاربر فوراً باطل می‌شوند و تا فعال‌سازی مجدد امکان ورود نخواهد داشت." confirmLabel="غیرفعال‌سازی" variant="destructive" onConfirm={save} />
    <ConfirmDialog open={!!revokeTarget} onOpenChange={(open) => { if (!open) setRevokeTarget(null) }} title="لغو نقش تأیید شود؟" description="دسترسی ناشی از این نقش و حوزه فوراً از حساب کاربر برداشته می‌شود." confirmLabel="لغو نقش" variant="destructive" onConfirm={revokeRole} />
  </div>
}
