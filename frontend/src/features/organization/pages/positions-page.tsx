import { useCallback, useEffect, useMemo, useState } from 'react'
import { BriefcaseBusiness, Pencil, Plus, RotateCw } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api, apiData, apiError } from '@/lib/api-client'
import { fetchAllAdminPages } from '../lib/fetch-all-admin-pages'
import type { EmployeePosition, OrganizationalUnit } from '../types'

type PositionForm = {
  organizational_unit_id: string
  title: string
  code: string
  position_type: string
  status: 'ACTIVE' | 'INACTIVE'
}

const emptyForm: PositionForm = {
  organizational_unit_id: '',
  title: '',
  code: '',
  position_type: 'EMPLOYEE',
  status: 'ACTIVE',
}

const positionTypeLabels: Record<string, string> = {
  DIRECTOR: 'رئیس ریاست',
  DEPUTY_DIRECTOR: 'معاون ریاست',
  EXECUTIVE_MANAGEMENT: 'مدیر اجرائیه',
  EMPLOYEE: 'کارمند',
  HIGH_AUTHORITY: 'مقام عالی',
  AUTHORITY_OFFICE: 'ریاست دفتر مقام',
  EXECUTIVE_SECRETARY: 'سکرتر اجرائیوی',
}

export function PositionsPage() {
  const [positions, setPositions] = useState<EmployeePosition[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [form, setForm] = useState<PositionForm>(emptyForm)
  const [editing, setEditing] = useState<EmployeePosition | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [loadedPositions, unitsResponse] = await Promise.all([
        fetchAllAdminPages<EmployeePosition>('/admin/positions'),
        api.get('/admin/organizational-units'),
      ])
      setPositions(loadedPositions)
      setUnits(apiData(unitsResponse) || [])
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const activeCount = useMemo(
    () => positions.filter((position) => position.status !== 'INACTIVE').length,
    [positions]
  )

  const resetForm = () => {
    setEditing(null)
    setForm(emptyForm)
  }

  const beginEdit = (position: EmployeePosition) => {
    setEditing(position)
    setForm({
      organizational_unit_id: String(position.organizational_unit_id || position.unit?.id || ''),
      title: position.title,
      code: position.code || '',
      position_type: position.position_type || 'EMPLOYEE',
      status: position.status || 'ACTIVE',
    })
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = {
        ...form,
        organizational_unit_id: Number(form.organizational_unit_id),
        code: form.code.trim() || null,
      }
      if (editing) {
        await api.put(`/admin/positions/${editing.id}`, payload)
      } else {
        await api.post('/admin/positions', payload)
      }
      resetForm()
      await loadData()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      <PageHeader
        title="مدیریت سمت‌های سازمانی"
        description="سمت‌های اداری هر واحد را ثبت و نگهداری کنید. سمت به‌تنهایی هیچ صلاحیت سیستمی ایجاد نمی‌کند."
        action={
          <Badge variant="outline" className="rounded-lg border-primary/30 px-2.5 py-1 text-xs text-primary">
            <BriefcaseBusiness className="ml-1 size-3.5" />
            {activeCount} سمت فعال
          </Badge>
        }
      />

      {error && (
        <Alert variant="destructive" className="rounded-2xl">
          <AlertDescription className="flex items-center justify-between gap-3 text-xs">
            <span>{error}</span>
            <Button type="button" variant="outline" size="sm" className="h-8 rounded-xl text-xs" onClick={loadData}>
              <RotateCw className="ml-1 size-3" /> تلاش مجدد
            </Button>
          </AlertDescription>
        </Alert>
      )}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
        <Card className="rounded-2xl border-border/70 shadow-xs">
          <CardHeader className="border-b border-border/60 p-4 sm:p-5">
            <CardTitle className="flex items-center gap-2 text-sm">
              <BriefcaseBusiness className="size-4 text-primary" /> فهرست سمت‌ها
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {loading ? (
              <div className="space-y-3 p-5">{[1, 2, 3, 4].map((item) => <div key={item} className="h-14 animate-pulse rounded-xl bg-muted/60" />)}</div>
            ) : positions.length === 0 ? (
              <p className="p-8 text-center text-sm text-muted-foreground">هنوز سمتی ثبت نشده است.</p>
            ) : (
              <div className="divide-y divide-border/50">
                {positions.map((position) => (
                  <div key={position.id} className="flex items-center justify-between gap-3 p-4 transition-colors hover:bg-muted/30">
                    <div className="min-w-0">
                      <strong className="block truncate text-sm text-foreground">{position.title}</strong>
                      <span className="mt-1 block truncate text-xs text-muted-foreground">
                        {position.unit?.name || 'واحد نامشخص'} · {positionTypeLabels[position.position_type || 'EMPLOYEE'] || position.position_type || 'کارمند'}
                      </span>
                      {position.code && <span className="mt-1 block font-mono text-[10px] text-muted-foreground">{position.code}</span>}
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                      <Badge variant={position.status === 'INACTIVE' ? 'outline' : 'secondary'} className="rounded-lg text-[10px]">
                        {position.status === 'INACTIVE' ? 'غیرفعال' : 'فعال'}
                      </Badge>
                      <Button type="button" variant="outline" size="sm" className="h-8 rounded-xl text-xs" onClick={() => beginEdit(position)}>
                        <Pencil className="ml-1 size-3" /> اصلاح
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="h-fit rounded-2xl border-border/70 shadow-xs xl:sticky xl:top-6">
          <CardHeader className="border-b border-border/60 p-4 sm:p-5">
            <CardTitle className="text-sm">{editing ? 'اصلاح سمت سازمانی' : 'ثبت سمت سازمانی'}</CardTitle>
          </CardHeader>
          <CardContent className="p-4 sm:p-5">
            <form className="space-y-4" onSubmit={submit}>
              <label className="block space-y-1.5 text-xs font-bold text-foreground">
                واحد سازمانی
                <select required value={form.organizational_unit_id} onChange={(event) => setForm((current) => ({ ...current, organizational_unit_id: event.target.value }))} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal">
                  <option value="">انتخاب واحد</option>
                  {units.filter((unit) => unit.status !== 'INACTIVE').map((unit) => <option key={unit.id} value={unit.id}>{unit.name}</option>)}
                </select>
              </label>
              <label className="block space-y-1.5 text-xs font-bold text-foreground">
                عنوان سمت
                <Input required value={form.title} onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))} className="h-10 rounded-xl text-xs" placeholder="مثلاً معاون ریاست" />
              </label>
              <label className="block space-y-1.5 text-xs font-bold text-foreground">
                کد سمت <span className="font-normal text-muted-foreground">(اختیاری)</span>
                <Input value={form.code} onChange={(event) => setForm((current) => ({ ...current, code: event.target.value }))} className="h-10 rounded-xl font-mono text-xs" placeholder="TECH-DEPUTY" />
              </label>
              <label className="block space-y-1.5 text-xs font-bold text-foreground">
                نوع سمت
                <select value={form.position_type} onChange={(event) => setForm((current) => ({ ...current, position_type: event.target.value }))} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal">
                  {Object.entries(positionTypeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
              </label>
              {editing && (
                <label className="block space-y-1.5 text-xs font-bold text-foreground">
                  وضعیت
                  <select value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value as PositionForm['status'] }))} className="h-10 w-full rounded-xl border border-input bg-background px-3 text-xs font-normal">
                    <option value="ACTIVE">فعال</option>
                    <option value="INACTIVE">غیرفعال</option>
                  </select>
                </label>
              )}
              <div className="flex gap-2 pt-2">
                <Button type="submit" disabled={saving} className="h-10 flex-1 rounded-xl text-xs">
                  {editing ? 'ثبت اصلاحات' : <><Plus className="ml-1 size-3.5" /> ثبت سمت</>}
                </Button>
                {editing && <Button type="button" variant="outline" disabled={saving} className="h-10 rounded-xl text-xs" onClick={resetForm}>انصراف</Button>}
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
