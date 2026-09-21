import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { AlertCircle, Building2, MapPin, Pencil, Plus, Power, RotateCw, Users } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'

type MeetingVenue = {
  id: number
  organization_id: number
  name: string
  description?: string | null
  location_details?: string | null
  capacity?: number | null
  status: 'ACTIVE' | 'INACTIVE' | string
}

type Organization = {
  id: number
  name: string
  short_name?: string | null
  status?: string
}

type VenueForm = {
  organization_id: string
  name: string
  description: string
  location_details: string
  capacity: string
}

const emptyForm: VenueForm = {
  organization_id: '',
  name: '',
  description: '',
  location_details: '',
  capacity: '',
}

export function MeetingVenuesPage() {
  const [venues, setVenues] = useState<MeetingVenue[]>([])
  const [organizations, setOrganizations] = useState<Organization[]>([])
  const [form, setForm] = useState<VenueForm>(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [venueItems, organizationItems] = await Promise.all([
        fetchAllPages<MeetingVenue>('/admin/meeting-venues'),
        fetchAllPages<Organization>('/admin/organizations'),
      ])
      setVenues(venueItems)
      setOrganizations(organizationItems)
      setForm((current) => ({
        ...current,
        organization_id: current.organization_id || String(organizationItems[0]?.id || ''),
      }))
    } catch (err: any) {
      setError(err?.response?.data?.message || 'بارگذاری محل‌های جلسات با خطا مواجه شد.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const organizationName = useCallback(
    (organizationId: number) => {
      const organization = organizations.find((item) => Number(item.id) === Number(organizationId))
      return organization?.short_name || organization?.name || 'سازمان'
    },
    [organizations]
  )

  const stats = useMemo(() => {
    const active = venues.filter((venue) => venue.status === 'ACTIVE').length
    const inactive = venues.length - active
    const capacity = venues
      .filter((venue) => venue.status === 'ACTIVE')
      .reduce((sum, venue) => sum + Number(venue.capacity || 0), 0)
    return { active, inactive, capacity }
  }, [venues])

  const resetForm = () => {
    setEditingId(null)
    setForm({
      ...emptyForm,
      organization_id: String(organizations[0]?.id || ''),
    })
  }

  const startEdit = (venue: MeetingVenue) => {
    setEditingId(venue.id)
    setForm({
      organization_id: String(venue.organization_id),
      name: venue.name || '',
      description: venue.description || '',
      location_details: venue.location_details || '',
      capacity: venue.capacity ? String(venue.capacity) : '',
    })
    setError(null)
  }

  const saveVenue = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!form.organization_id || !form.name.trim()) {
      setError('سازمان و نام محل الزامی است.')
      return
    }

    setBusy(true)
    setError(null)
    const payload = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      location_details: form.location_details.trim() || null,
      capacity: form.capacity ? Number(form.capacity) : null,
    }

    try {
      if (editingId) {
        await api.put(`/admin/meeting-venues/${editingId}`, payload)
      } else {
        await api.post('/admin/meeting-venues', {
          organization_id: Number(form.organization_id),
          ...payload,
        })
      }
      resetForm()
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت محل جلسه انجام نشد.')
    } finally {
      setBusy(false)
    }
  }

  const toggleStatus = async (venue: MeetingVenue) => {
    setBusy(true)
    setError(null)
    try {
      const action = venue.status === 'ACTIVE' ? 'deactivate' : 'activate'
      await api.post(`/admin/meeting-venues/${venue.id}/${action}`)
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'تغییر وضعیت محل جلسه انجام نشد.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      <PageHeader
        title="مدیریت محل‌های داخلی جلسات"
        description="تعریف و مدیریت تالارها و اتاق‌های قابل رزرو برای جلوگیری از تداخل محل جلسات"
        action={
          <Button variant="outline" size="sm" onClick={loadData} disabled={loading} className="h-8.5 text-xs rounded-xl gap-1.5">
            <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
            تازه‌سازی
          </Button>
        }
      />

      {error && (
        <Alert variant="destructive" className="rounded-2xl">
          <AlertCircle className="size-4" />
          <AlertDescription className="text-xs">{error}</AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <Card className="border-border/70 shadow-xs">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center"><MapPin className="size-4" /></div>
            <div><span className="text-[10px] text-muted-foreground block">محل‌های فعال</span><strong className="text-lg font-bold font-mono">{stats.active}</strong></div>
          </CardContent>
        </Card>
        <Card className="border-border/70 shadow-xs">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="size-9 rounded-xl bg-slate-500/10 text-slate-600 flex items-center justify-center"><Power className="size-4" /></div>
            <div><span className="text-[10px] text-muted-foreground block">غیرفعال</span><strong className="text-lg font-bold font-mono">{stats.inactive}</strong></div>
          </CardContent>
        </Card>
        <Card className="border-border/70 shadow-xs">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center"><Users className="size-4" /></div>
            <div><span className="text-[10px] text-muted-foreground block">ظرفیت مجموع محل‌های فعال</span><strong className="text-lg font-bold font-mono">{stats.capacity || '—'}</strong></div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[22rem_minmax(0,1fr)] gap-5 items-start">
        <Card className="border-border/70 shadow-xs xl:sticky xl:top-4">
          <CardHeader className="p-4 border-b border-border/60">
            <div className="flex items-center gap-2">
              <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                {editingId ? <Pencil className="size-4" /> : <Plus className="size-4" />}
              </div>
              <div>
                <h3 className="text-sm font-bold">{editingId ? 'اصلاح محل جلسه' : 'ثبت محل جدید'}</h3>
                <p className="text-[11px] text-muted-foreground">محل داخلی دارای شناسه مستقل برای رزرو است.</p>
              </div>
            </div>
          </CardHeader>
          <CardContent className="p-4">
            <form onSubmit={saveVenue} className="space-y-3.5">
              <div className="space-y-1.5">
                <label className="text-xs font-bold">سازمان <span className="text-destructive">*</span></label>
                <select
                  value={form.organization_id}
                  disabled={!!editingId}
                  onChange={(e) => setForm((current) => ({ ...current, organization_id: e.target.value }))}
                  className="w-full h-9.5 rounded-xl border border-border/70 bg-card px-2.5 text-xs disabled:opacity-60"
                >
                  <option value="">انتخاب سازمان</option>
                  {organizations.map((organization) => <option key={organization.id} value={organization.id}>{organization.name}</option>)}
                </select>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold">نام محل <span className="text-destructive">*</span></label>
                <Input value={form.name} onChange={(e) => setForm((current) => ({ ...current, name: e.target.value }))} placeholder="مثلاً تالار کنفرانس مرکزی" className="h-9.5 text-xs rounded-xl" />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold">مشخصات موقعیت</label>
                <Input value={form.location_details} onChange={(e) => setForm((current) => ({ ...current, location_details: e.target.value }))} placeholder="ساختمان، طبقه یا شماره اتاق" className="h-9.5 text-xs rounded-xl" />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold">ظرفیت</label>
                <Input type="number" min={1} value={form.capacity} onChange={(e) => setForm((current) => ({ ...current, capacity: e.target.value }))} placeholder="تعداد نفر" className="h-9.5 text-xs rounded-xl" />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold">توضیحات</label>
                <textarea value={form.description} onChange={(e) => setForm((current) => ({ ...current, description: e.target.value }))} rows={3} placeholder="توضیحات اختیاری درباره امکانات یا کاربرد محل" className="w-full rounded-xl border border-border/70 bg-card px-3 py-2 text-xs resize-none" />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-border/50">
                {editingId && <Button type="button" variant="outline" size="sm" onClick={resetForm} disabled={busy} className="h-8.5 text-xs rounded-xl">انصراف</Button>}
                <Button type="submit" size="sm" disabled={busy || !form.organization_id || !form.name.trim()} className="h-8.5 text-xs rounded-xl gap-1.5">
                  {editingId ? <Pencil className="size-3.5" /> : <Plus className="size-3.5" />}
                  {editingId ? 'ذخیره اصلاحات' : 'ثبت محل'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card className="border-border/70 shadow-xs overflow-hidden">
          <CardHeader className="p-4 border-b border-border/60">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h3 className="text-sm font-bold">فهرست محل‌های مدیریت‌شده</h3>
                <p className="text-[11px] text-muted-foreground">غیرفعال‌سازی فقط انتخاب برای رزرو جدید را می‌بندد؛ رزروهای قبلاً تأییدشده حفظ می‌شوند.</p>
              </div>
              <span className="text-[11px] text-muted-foreground">{venues.length} محل</span>
            </div>
          </CardHeader>
          <CardContent className="p-4">
            {loading ? (
              <div className="space-y-3">{[1, 2, 3].map((item) => <div key={item} className="h-24 rounded-2xl bg-muted/60 animate-pulse" />)}</div>
            ) : venues.length === 0 ? (
              <div className="py-14 text-center border border-dashed border-border/80 rounded-2xl">
                <MapPin className="size-9 mx-auto text-muted-foreground/40 mb-2" />
                <h4 className="text-sm font-bold">هنوز محل داخلی ثبت نشده است</h4>
                <p className="text-[11px] text-muted-foreground mt-1">اولین تالار یا اتاق قابل رزرو را از فورم مقابل ثبت کنید.</p>
              </div>
            ) : (
              <div className="space-y-3">
                {venues.map((venue) => {
                  const active = venue.status === 'ACTIVE'
                  return (
                    <div key={venue.id} className="p-4 rounded-2xl border border-border/70 bg-card flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                      <div className="flex items-start gap-3 min-w-0">
                        <div className={`size-9 rounded-xl flex items-center justify-center shrink-0 ${active ? 'bg-emerald-500/10 text-emerald-600' : 'bg-muted text-muted-foreground'}`}><MapPin className="size-4" /></div>
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <strong className="text-sm font-bold">{venue.name}</strong>
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>{active ? 'فعال' : 'غیرفعال'}</span>
                          </div>
                          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground mt-1">
                            <span className="inline-flex items-center gap-1"><Building2 className="size-3" />{organizationName(venue.organization_id)}</span>
                            {venue.location_details && <span>{venue.location_details}</span>}
                            {venue.capacity && <span>{venue.capacity} نفر</span>}
                          </div>
                          {venue.description && <p className="text-[11px] text-muted-foreground mt-1.5 line-clamp-2">{venue.description}</p>}
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0 self-end lg:self-center">
                        <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => startEdit(venue)} className="h-8 text-xs rounded-xl gap-1"><Pencil className="size-3" />اصلاح</Button>
                        <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => toggleStatus(venue)} className="h-8 text-xs rounded-xl gap-1"><Power className="size-3" />{active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}</Button>
                      </div>
                    </div>
                  )
                })}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
