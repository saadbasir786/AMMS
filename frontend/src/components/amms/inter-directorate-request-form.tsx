import { useMemo, useState } from 'react'
import { ArrowRight, Building2, CheckCircle2, Loader2, Save } from 'lucide-react'

import { FormField } from '@/components/amms/form-field'
import { FormSection } from '@/components/amms/form-section'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'

export type InterDirectorateFormValue = {
  requesting_unit_id: string
  target_unit_id: string
  subject: string
  purpose: string
  description: string
  priority: string
  preferred_date: string
  preferred_start_time: string
  estimated_duration_minutes: number
  proposed_location: string
  participant_ids: number[]
}

type Unit = { id: number | string; name: string; unit_type?: string }
type Employee = { id: number; full_name: string; organizational_unit_id?: number | string; unit?: { name?: string } }

export function InterDirectorateRequestForm({
  units,
  employees,
  purposeLabels,
  priorityLabels,
  initialValue,
  error,
  onSubmit,
}: {
  units: Unit[]
  employees: Employee[]
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  initialValue: InterDirectorateFormValue
  error?: string
  onSubmit: (value: InterDirectorateFormValue, submit: boolean) => Promise<void>
}) {
  const [form, setForm] = useState(initialValue)
  const [busyAction, setBusyAction] = useState<'draft' | 'submit' | null>(null)

  const directorates = useMemo(() => units.filter((unit) => unit.unit_type === 'DIRECTORATE'), [units])
  const allowedEmployees = useMemo(() => {
    const selected = [String(form.requesting_unit_id), String(form.target_unit_id)]
    return employees.filter((employee) => selected.includes(String(employee.organizational_unit_id)))
  }, [employees, form.requesting_unit_id, form.target_unit_id])

  const runSubmit = async (submit: boolean) => {
    if (busyAction) return
    setBusyAction(submit ? 'submit' : 'draft')
    try {
      await onSubmit(form, submit)
    } finally {
      setBusyAction(null)
    }
  }

  const setUnit = (key: 'requesting_unit_id' | 'target_unit_id', value: string) => {
    setForm((current) => ({ ...current, [key]: value, participant_ids: [] }))
  }

  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div className="grid gap-5">
        {error && (
          <Alert variant="destructive">
            <AlertTitle>ثبت درخواست انجام نشد</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <FormSection title="طرف‌های جلسه" description="ریاست درخواست‌کننده و ریاست مقصد باید متفاوت باشند.">
          <div className="grid gap-4 md:grid-cols-2">
            <FormField label="ریاست درخواست‌کننده" required>
              <Select value={String(form.requesting_unit_id)} onValueChange={(value) => setUnit('requesting_unit_id', value)}>
                <SelectTrigger className="w-full" aria-label="ریاست درخواست‌کننده"><SelectValue placeholder="انتخاب ریاست" /></SelectTrigger>
                <SelectContent>
                  {directorates.map((unit) => <SelectItem key={unit.id} value={String(unit.id)}>{unit.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </FormField>
            <FormField label="ریاست مقصد" required>
              <Select value={String(form.target_unit_id)} onValueChange={(value) => setUnit('target_unit_id', value)}>
                <SelectTrigger className="w-full" aria-label="ریاست مقصد"><SelectValue placeholder="انتخاب ریاست مقصد" /></SelectTrigger>
                <SelectContent>
                  {directorates.filter((unit) => String(unit.id) !== String(form.requesting_unit_id)).map((unit) => <SelectItem key={unit.id} value={String(unit.id)}>{unit.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </FormField>
          </div>
        </FormSection>

        <FormSection title="اطلاعات اصلی" description="موضوع، هدف و اولویت درخواست را مطابق محتوای رسمی جلسه درج کنید.">
          <div className="grid gap-4 lg:grid-cols-3">
            <FormField label="موضوع جلسه" required className="lg:col-span-3">
              <Input value={form.subject} onChange={(event) => setForm({ ...form, subject: event.target.value })} placeholder="عنوان رسمی جلسه" />
            </FormField>
            <FormField label="هدف جلسه" required>
              <Select value={form.purpose} onValueChange={(purpose) => setForm({ ...form, purpose })}>
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>{Object.entries(purposeLabels).map(([value, label]) => <SelectItem value={value} key={value}>{label}</SelectItem>)}</SelectContent>
              </Select>
            </FormField>
            <FormField label="اولویت" required>
              <Select value={form.priority} onValueChange={(priority) => setForm({ ...form, priority })}>
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>{Object.entries(priorityLabels).map(([value, label]) => <SelectItem value={value} key={value}>{label}</SelectItem>)}</SelectContent>
              </Select>
            </FormField>
          </div>
          <FormField label="شرح و توضیحات">
            <Textarea rows={5} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
          </FormField>
        </FormSection>

        <FormSection title="پیشنهاد اولیهٔ زمان‌بندی" description="این مقادیر پیشنهاد اولیه هستند و منطق زمان‌بندی موجود را تغییر نمی‌دهند.">
          <div className="grid gap-4 md:grid-cols-3">
            <FormField label="تاریخ"><Input type="date" value={form.preferred_date} onChange={(event) => setForm({ ...form, preferred_date: event.target.value })} /></FormField>
            <FormField label="زمان آغاز"><Input type="time" value={form.preferred_start_time} onChange={(event) => setForm({ ...form, preferred_start_time: event.target.value })} /></FormField>
            <FormField label="مدت (دقیقه)"><Input type="number" min={1} value={form.estimated_duration_minutes} onChange={(event) => setForm({ ...form, estimated_duration_minutes: Number(event.target.value) })} /></FormField>
          </div>
          <FormField label="محل پیشنهادی"><Input value={form.proposed_location} onChange={(event) => setForm({ ...form, proposed_location: event.target.value })} /></FormField>
        </FormSection>

        <FormSection title="اشتراک‌کنندگان پیشنهادی" description="هر ریاست فهرست نهایی خودش را طبق workflow موجود تأیید می‌کند.">
          <div className="grid gap-2 sm:grid-cols-2">
            {allowedEmployees.map((employee) => {
              const checked = form.participant_ids.includes(employee.id)
              return (
                <label key={employee.id} className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/70 p-3 transition-colors hover:bg-muted/40">
                  <Checkbox checked={checked} onCheckedChange={(next) => setForm({ ...form, participant_ids: next === true ? [...form.participant_ids, employee.id] : form.participant_ids.filter((id) => id !== employee.id) })} />
                  <span className="grid gap-0.5"><strong className="text-sm font-medium">{employee.full_name}</strong><span className="text-xs text-muted-foreground">{employee.unit?.name || '—'}</span></span>
                </label>
              )
            })}
            {allowedEmployees.length === 0 && <p className="text-sm text-muted-foreground">پس از انتخاب ریاست‌ها، کارمندان مرتبط در این بخش نمایش داده می‌شوند.</p>}
          </div>
        </FormSection>

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" disabled={Boolean(busyAction)} onClick={() => runSubmit(false)}>
            {busyAction === 'draft' ? <Loader2 className="animate-spin" /> : <Save />}
            ذخیرهٔ پیش‌نویس
          </Button>
          <Button type="button" disabled={Boolean(busyAction)} onClick={() => runSubmit(true)}>
            {busyAction === 'submit' ? <Loader2 className="animate-spin" /> : <ArrowRight />}
            ارسال برای تأیید داخلی
          </Button>
        </div>
      </div>

      <aside className="h-fit rounded-xl border border-primary/15 bg-primary/5 p-5 xl:sticky xl:top-24">
        <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><Building2 size={20} /></div>
        <p className="text-xs font-semibold tracking-wide text-primary">FLOW-02</p>
        <h3 className="mt-1 font-semibold">جلسه بین دو ریاست</h3>
        <p className="mt-2 text-sm leading-6 text-muted-foreground">این درخواست تا زمانی که ریاست درخواست‌کننده تأیید نکند به ریاست مقصد نمایش داده نمی‌شود.</p>
        <div className="mt-4 grid gap-2 text-sm">
          {['تأیید ریاست درخواست‌کننده', 'تأیید ریاست مقصد', 'زمان‌بندی مذاکره‌ای'].map((label) => <div key={label} className="flex items-center gap-2"><CheckCircle2 className="size-4 text-primary" />{label}</div>)}
        </div>
      </aside>
    </div>
  )
}
