import React, { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Save,
  Building2,
  Users,
  CheckCircle2
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'
import { api, apiData } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'
import type { OrganizationalUnit, EmployeeItem } from '../types'

type EditRequestPageProps = {
  purposeLabels?: Record<string, string>
  priorityLabels?: Record<string, string>
}

export function EditRequestPage({
  purposeLabels = {
    COORDINATION: 'هماهنگی',
    DECISION: 'تصمیم‌گیری',
    CONSULTATION: 'مشورت',
    REPORTING: 'گزارش‌دهی',
    APPROVAL: 'تأیید',
    FOLLOW_UP: 'پیگیری',
    OTHER: 'سایر',
  },
  priorityLabels = {
    NORMAL: 'عادی',
    HIGH: 'بلند',
    URGENT: 'عاجل',
  },
}: EditRequestPageProps) {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [request, setRequest] = useState<any>(null)
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [employees, setEmployees] = useState<EmployeeItem[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [form, setForm] = useState<any>({
    subject: '',
    purpose: 'COORDINATION',
    description: '',
    priority: 'NORMAL',
    preferred_date: '',
    preferred_start_time: '09:00',
    estimated_duration_minutes: 60,
    proposed_location: '',
    participant_ids: [] as number[],
  })

  useEffect(() => {
    if (!id) return
    setLoading(true)
    setError(null)
    Promise.all([
      api.get(`/meeting-requests/${id}`),
      api.get('/organizational-units'),
      fetchAllPages<EmployeeItem>('/employees'),
    ])
      .then(([reqRes, unitsRes, empRes]) => {
        const r = apiData(reqRes)
        setRequest(r)
        setUnits(apiData(unitsRes) || [])
        setEmployees(empRes)
        setForm({
          subject: r.subject || '',
          purpose: r.purpose || 'COORDINATION',
          description: r.description || '',
          priority: r.priority || 'NORMAL',
          preferred_date: r.preferred_date ? r.preferred_date.slice(0, 10) : '',
          preferred_start_time: r.preferred_start_time ? r.preferred_start_time.slice(0, 5) : '09:00',
          estimated_duration_minutes: r.estimated_duration_minutes || 60,
          proposed_location: r.proposed_location || '',
          participant_ids: (r.participants || [])
            .filter((p: any) => p.participant_type === 'PROPOSED')
            .map((p: any) => p.employee?.id ?? p.employee_id),
        })
      })
      .catch((err: any) => {
        setError(err?.response?.data?.message || 'بارگذاری اطلاعات درخواست با خطا مواجه شد.')
      })
      .finally(() => setLoading(false))
  }, [id])

  const allowedUnitIds = useMemo(() => {
    const selected = Number(request?.requesting_unit_id)
    const ids = new Set<number>()
    if (!selected) return ids
    ids.add(selected)
    let changed = true
    while (changed) {
      changed = false
      for (const u of units) {
        if (u.parent_id && ids.has(Number(u.parent_id)) && !ids.has(Number(u.id))) {
          ids.add(Number(u.id))
          changed = true
        }
      }
    }
    return ids
  }, [request?.requesting_unit_id, units])

  const selectableEmployees = useMemo(() => {
    return employees.filter((e) =>
      allowedUnitIds.has(Number(e.organizational_unit_id ?? e.unit?.id))
    )
  }, [employees, allowedUnitIds])

  const toggleParticipant = (empId: number) => {
    setForm((prev: any) => {
      const exists = prev.participant_ids.includes(empId)
      return {
        ...prev,
        participant_ids: exists
          ? prev.participant_ids.filter((x: number) => x !== empId)
          : [...prev.participant_ids, empId],
      }
    })
  }

  const handleSave = async (submitForReview: boolean = false) => {
    if (!id) return
    if (!form.subject.trim()) {
      setError('عنوان یا موضوع جلسه الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.put(`/meeting-requests/${id}`, {
        ...form,
        requesting_unit_id: request.requesting_unit_id,
        target_unit_id: request.target_unit_id,
      })

      if (submitForReview) {
        await api.post(`/meeting-requests/${id}/submit`)
      }
      navigate(`/meeting-requests/${id}`)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ذخیره تغییرات با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={request?.request_number || undefined}
        title="ویرایش و اصلاح درخواست جلسه"
        description="اصلاح مشخصات اداری، زمان پیشنهادی، محل برگزاری و فهرست اعضای نامزدشده"
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={() => navigate(`/meeting-requests/${id}`)}
            className="text-xs font-semibold rounded-xl gap-1.5"
          >
            <ArrowRight className="size-3.5" />
            <span>بازگشت به درخواست</span>
          </Button>
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
              onClick={() => window.location.reload()}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="h-96 rounded-2xl bg-card border border-border/70 animate-pulse" />
          <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
        </div>
      )}

      {/* Form Content */}
      {!loading && request && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <h3 className="text-xs sm:text-sm font-bold text-foreground">
                  مشخصات اصلی جلسه
                </h3>
              </CardHeader>

              <CardContent className="p-4 sm:p-5 space-y-4 text-xs">
                <div className="space-y-1.5">
                  <label className="font-bold text-foreground block">
                    عنوان و موضوع جلسه <span className="text-rose-500">*</span>
                  </label>
                  <Input
                    value={form.subject}
                    onChange={(e) => setForm({ ...form, subject: e.target.value })}
                    className="h-9.5 text-xs rounded-xl"
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                  <div className="space-y-1.5">
                    <label className="font-bold text-foreground block">هدف جلسه</label>
                    <select
                      value={form.purpose}
                      onChange={(e) => setForm({ ...form, purpose: e.target.value })}
                      className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
                    >
                      {Object.entries(purposeLabels).map(([key, label]) => (
                        <option key={key} value={key}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <label className="font-bold text-foreground block">اولویت اداری</label>
                    <select
                      value={form.priority}
                      onChange={(e) => setForm({ ...form, priority: e.target.value })}
                      className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
                    >
                      {Object.entries(priorityLabels).map(([key, label]) => (
                        <option key={key} value={key}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="font-bold text-foreground block">
                    شرح و ضرورت برگزاری جلسه
                  </label>
                  <Textarea
                    rows={4}
                    value={form.description}
                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                    className="text-xs rounded-xl"
                  />
                </div>
              </CardContent>
            </Card>

            {/* Time & Place Card */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <h3 className="text-xs sm:text-sm font-bold text-foreground">
                  زمان و مکان پیشنهادی جلسه
                </h3>
              </CardHeader>

              <CardContent className="p-4 sm:p-5 space-y-4 text-xs">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                  <div className="space-y-1.5">
                    <label className="font-bold text-foreground block">تاریخ پیشنهادی</label>
                    <MultiCalendarDatePicker
                      value={form.preferred_date}
                      onChange={(iso) => setForm({ ...form, preferred_date: iso })}
                      className="h-9.5 text-xs rounded-xl"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="font-bold text-foreground block">ساعت پیشنهادی آغاز</label>
                    <TimePicker
                      value={form.preferred_start_time}
                      onChange={(time) => setForm({ ...form, preferred_start_time: time })}
                      className="h-9.5 text-xs rounded-xl"
                    />
                  </div>

                  <div className="space-y-1.5">
                    <label className="font-bold text-foreground block">مدت تخمینی (دقیقه)</label>
                    <Input
                      type="number"
                      min={15}
                      step={15}
                      value={form.estimated_duration_minutes}
                      onChange={(e) =>
                        setForm({ ...form, estimated_duration_minutes: Number(e.target.value) })
                      }
                      className="h-9.5 text-xs rounded-xl font-mono"
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="font-bold text-foreground block">محل پیشنهادی برگزاری</label>
                  <Input
                    value={form.proposed_location}
                    onChange={(e) => setForm({ ...form, proposed_location: e.target.value })}
                    placeholder="اتاق جلسات، تالار کنفرانس..."
                    className="h-9.5 text-xs rounded-xl"
                  />
                </div>
              </CardContent>
            </Card>

            {/* Participants Card */}
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <h3 className="text-xs sm:text-sm font-bold text-foreground">
                  اعضای نامزدشده از سوی ریاست درخواست‌کننده
                </h3>
              </CardHeader>

              <CardContent className="p-4 sm:p-5 space-y-3 text-xs">
                {selectableEmployees.length === 0 ? (
                  <p className="text-xs text-muted-foreground py-2">
                    کارمندی در این واحد سازمانی برای انتخاب یافت نشد.
                  </p>
                ) : (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {selectableEmployees.map((emp) => {
                      const isSelected = form.participant_ids.includes(emp.id)
                      return (
                        <div
                          key={emp.id}
                          onClick={() => toggleParticipant(emp.id)}
                          className={`flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all ${
                            isSelected
                              ? 'bg-primary/10 border-primary/40 text-primary shadow-xs'
                              : 'bg-card border-border/70 hover:border-border text-foreground'
                          }`}
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                              {emp.full_name?.charAt(0) || 'ک'}
                            </div>
                            <div className="min-w-0">
                              <strong className="text-xs font-semibold block truncate">
                                {emp.full_name}
                              </strong>
                              <span className="text-[10px] text-muted-foreground block truncate">
                                {emp.position?.title || 'کارمند'} • {emp.unit?.name || ''}
                              </span>
                            </div>
                          </div>

                          <div
                            className={`size-4 rounded-full border flex items-center justify-center ${
                              isSelected ? 'bg-primary border-primary text-white' : 'border-border'
                            }`}
                          >
                            {isSelected && <CheckCircle2 className="size-3" />}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                )}
              </CardContent>
            </Card>
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <Card className="border border-border/70 shadow-xs">
              <CardHeader className="p-4 sm:p-5 border-b border-border/60">
                <h3 className="text-xs sm:text-sm font-bold text-foreground">
                  اقدامات قابل انجام
                </h3>
                <p className="text-[11px] text-muted-foreground">
                  ذخیره پیش‌نویس اصلاح‌شده یا ارسال مجدد برای بررسی
                </p>
              </CardHeader>

              <CardContent className="p-4 sm:p-5 space-y-3">
                <Button
                  disabled={busy || !form.subject.trim()}
                  onClick={() => handleSave(false)}
                  className="w-full h-9.5 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white shadow-xs"
                >
                  <Save className="size-3.5 ml-1.5" />
                  <span>{busy ? 'در حال ذخیره…' : 'ذخیره تغییرات پیش‌نویس'}</span>
                </Button>

                <Button
                  variant="outline"
                  disabled={busy || !form.subject.trim()}
                  onClick={() => handleSave(true)}
                  className="w-full h-9.5 text-xs font-semibold rounded-xl text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-950/20"
                >
                  <span>ذخیره و ارسال برای بررسی</span>
                </Button>

                <Button
                  variant="ghost"
                  onClick={() => navigate(`/meeting-requests/${id}`)}
                  className="w-full h-9 text-xs font-semibold rounded-xl text-muted-foreground hover:text-foreground"
                >
                  انصراف و بازگشت
                </Button>
              </CardContent>
            </Card>
          </aside>
        </div>
      )}
    </div>
  )
}
