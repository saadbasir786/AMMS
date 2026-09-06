import { useCallback, useEffect, useMemo, useState } from 'react'
import { Plus, RotateCw, UserPlus, Users } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api, apiError, apiPage } from '@/lib/api-client'
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
  employee_number: '', first_name: '', last_name: '', father_name: '', organizational_unit_id: '',
  position_id: '', email: '', phone: '', status: 'ACTIVE',
}

type EmployeesPageProps = {
  isRolesView?: boolean
}

export function EmployeesPage({ isRolesView = false }: EmployeesPageProps) {
  const [employees, setEmployees] = useState<Employee[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [positions, setPositions] = useState<EmployeePosition[]>([])
  const [form, setForm] = useState<EmployeeForm>(emptyForm)
  const [editing, setEditing] = useState<Employee | null>(null)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [confirmDeactivation, setConfirmDeactivation] = useState(false)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [employeeResponse, unitResponse, positionResponse] = await Promise.all([
        api.get('/admin/employees'), api.get('/admin/organizational-units'), api.get('/admin/positions'),
      ])
      setEmployees(apiPage(employeeResponse)?.data || [])
      setUnits(apiPage(unitResponse)?.data || [])
      setPositions(apiPage(positionResponse)?.data || [])
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadData() }, [loadData])

  const availablePositions = useMemo(
    () => positions.filter((position) => String(position.organizational_unit_id || position.unit?.id) === form.organizational_unit_id && position.status !== 'INACTIVE'),
    [form.organizational_unit_id, positions]
  )

  const filteredEmployees = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) return employees
    return employees.filter((employee) => [employee.full_name, employee.employee_number, employee.unit?.name, employee.position?.title].filter(Boolean).some((value) => value!.toLowerCase().includes(query)))
  }, [employees, search])

  const resetForm = () => { setEditing(null); setForm(emptyForm) }

  const beginEdit = (employee: Employee) => {
    setEditing(employee)
    setForm({
      employee_number: employee.employee_number,
      first_name: employee.first_name || employee.full_name.split(' ')[0] || '',
      last_name: employee.last_name || employee.full_name.split(' ').slice(1).join(' ') || '',
      father_name: employee.father_name || '',
      organizational_unit_id: String(employee.organizational_unit_id || employee.unit?.id || ''),
      position_id: String(employee.position?.id || ''),
      email: employee.email || '', phone: employee.phone || '',
      status: employee.status === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
    })
  }

  const updateField = <Key extends keyof EmployeeForm>(field: Key, value: EmployeeForm[Key]) => setForm((current) => ({ ...current, [field]: value }))

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
      if (editing) await api.put(`/admin/employees/${editing.id}`, payload)
      else await api.post('/admin/employees', payload)
      resetForm()
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
      <PageHeader
        title={isRolesView ? 'کاربران و صلاحیت‌ها' : 'مدیریت کارمندان'}
        description={isRolesView ? 'مدیریت نقش‌ها در فاز بعدی به این بخش منتقل می‌شود.' : 'مشخصات پرسنلی، واحد سازمانی و سمت رسمی کارمندان را ثبت و به‌روزرسانی کنید.'}
        action={<Badge variant="outline" className="rounded-lg border-primary/30 px-2.5 py-1 text-xs text-primary"><Users className="ml-1 size-3.5" />{employees.filter((employee) => employee.status !== 'INACTIVE').length} کارمند فعال</Badge>}
      />

      {error && <Alert variant="destructive" className="rounded-2xl"><AlertDescription className="flex items-center justify-between gap-3 text-xs"><span>{error}</span><Button type="button" variant="outline" size="sm" className="h-8 rounded-xl text-xs" onClick={loadData}><RotateCw className="ml-1 size-3" /> تلاش مجدد</Button></AlertDescription></Alert>}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section className="space-y-4">
          <div className="flex flex-col gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
            <Input value={search} onChange={(event) => setSearch(event.target.value)} className="h-10 max-w-md rounded-xl text-xs" placeholder="جست‌وجو بر اساس نام، شماره، واحد یا سمت..." />
            <span className="text-xs text-muted-foreground">{filteredEmployees.length} نتیجه</span>
          </div>
          {!loading && <EmployeesTable employees={filteredEmployees} onEdit={beginEdit} />}
          {loading && <div className="space-y-3 rounded-2xl border border-border/70 bg-card p-5">{[1, 2, 3, 4].map((item) => <div key={item} className="h-14 animate-pulse rounded-xl bg-muted/60" />)}</div>}
        </section>

        <Card className="h-fit rounded-2xl border-border/70 shadow-xs xl:sticky xl:top-6">
          <CardHeader className="border-b border-border/60 p-4 sm:p-5"><CardTitle className="flex items-center gap-2 text-sm"><UserPlus className="size-4 text-primary" />{editing ? 'اصلاح کارمند' : 'ثبت کارمند'}</CardTitle></CardHeader>
          <CardContent className="p-4 sm:p-5">
            <form className="space-y-3.5" onSubmit={submit}>
              <div className="grid grid-cols-2 gap-3">
                <label className="space-y-1.5 text-xs font-bold">نام<Input required value={form.first_name} onChange={(event) => updateField('first_name', event.target.value)} className="h-10 rounded-xl text-xs" /></label>
                <label className="space-y-1.5 text-xs font-bold">تخلص<Input required value={form.last_name} onChange={(event) => updateField('last_name', event.target.value)} className="h-10 rounded-xl text-xs" /></label>
              </div>
              <label className="block space-y-1.5 text-xs font-bold">شماره کارمندی<Input required value={form.employee_number} onChange={(event) => updateField('employee_number', event.target.value)} className="h-10 rounded-xl font-mono text-xs" placeholder="EMP-TECH-001" /></label>
              <label className="block space-y-1.5 text-xs font-bold">نام پدر <span className="font-normal text-muted-foreground">(اختیاری)</span><Input value={form.father_name} onChange={(event) => updateField('father_name', event.target.value)} className="h-10 rounded-xl text-xs" /></label>
              <label className="block space-y-1.5 text-xs font-bold">واحد سازمانی<select required value={form.organizational_unit_id} onChange={(event) => setForm((current) => ({ ...current, organizational_unit_id: event.target.value, position_id: '' }))} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal"><option value="">انتخاب واحد</option>{units.filter((unit) => unit.status !== 'INACTIVE').map((unit) => <option key={unit.id} value={unit.id}>{unit.name}</option>)}</select></label>
              <label className="block space-y-1.5 text-xs font-bold">سمت سازمانی<select value={form.position_id} disabled={!form.organizational_unit_id} onChange={(event) => updateField('position_id', event.target.value)} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal disabled:cursor-not-allowed disabled:opacity-60"><option value="">بدون سمت تعیین‌شده</option>{availablePositions.map((position) => <option key={position.id} value={position.id}>{position.title}</option>)}</select></label>
              <div className="grid grid-cols-2 gap-3"><label className="space-y-1.5 text-xs font-bold">ایمیل <span className="font-normal text-muted-foreground">(اختیاری)</span><Input type="email" value={form.email} onChange={(event) => updateField('email', event.target.value)} className="h-10 rounded-xl text-xs" /></label><label className="space-y-1.5 text-xs font-bold">شماره تماس <span className="font-normal text-muted-foreground">(اختیاری)</span><Input value={form.phone} onChange={(event) => updateField('phone', event.target.value)} className="h-10 rounded-xl text-xs" /></label></div>
              {editing && <label className="block space-y-1.5 text-xs font-bold">وضعیت<select value={form.status} onChange={(event) => updateField('status', event.target.value as EmployeeForm['status'])} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal"><option value="ACTIVE">فعال</option><option value="INACTIVE">غیرفعال</option></select></label>}
              <div className="flex gap-2 pt-2"><Button type="submit" disabled={saving} className="h-10 flex-1 rounded-xl text-xs">{editing ? 'ثبت اصلاحات' : <><Plus className="ml-1 size-3.5" /> ثبت کارمند</>}</Button>{editing && <Button type="button" variant="outline" disabled={saving} className="h-10 rounded-xl text-xs" onClick={resetForm}>انصراف</Button>}</div>
            </form>
          </CardContent>
        </Card>
      </div>
      <ConfirmDialog
        open={confirmDeactivation}
        onOpenChange={setConfirmDeactivation}
        title="غیرفعال‌سازی کارمند تأیید شود؟"
        description="حساب وابسته به این کارمند فوراً دسترسی عملیاتی خود را از دست می‌دهد."
        confirmLabel="غیرفعال‌سازی"
        variant="destructive"
        onConfirm={save}
      />
    </div>
  )
}
