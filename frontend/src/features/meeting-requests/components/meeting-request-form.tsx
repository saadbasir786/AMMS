import React, { useMemo, useState } from 'react'
import {
  Building2,
  Calendar,
  Clock,
  MapPin,
  Save,
  Send,
  AlertCircle,
  FileText,
  Loader2,
  Users2,
  ShieldCheck
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MultiSearchableSelect } from '@/components/ui/multi-searchable-select'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'
import { ParticipantPicker } from './participant-picker'
import { cn } from '@/lib/utils'
import type {
  CreateMeetingMode,
  MeetingFormValues,
  OrganizationalUnit,
  EmployeeItem
} from '../types'

type MeetingRequestFormProps = {
  mode?: CreateMeetingMode
  units: OrganizationalUnit[]
  employees: EmployeeItem[]
  currentUser?: any
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  initialValues?: Partial<MeetingFormValues>
  onSubmit: (values: MeetingFormValues, submitForApproval: boolean) => Promise<void>
  onCancel?: () => void
}

export function MeetingRequestForm({
  mode = 'internal',
  units,
  employees,
  currentUser,
  purposeLabels,
  priorityLabels,
  initialValues,
  onSubmit,
  onCancel,
}: MeetingRequestFormProps) {
  const isInterDirectorate = mode === 'inter-directorate'
  const isMultiDirectorate = mode === 'multi-directorate' || mode === 'multi-high-authority'
  const isHighAuthority =
    mode === 'high-authority' ||
    mode === 'multi-high-authority' ||
    mode === 'authority-initiated'

  // Determine user's assigned directorate
  const userDirectorateId = useMemo(() => {
    if (!currentUser) return null
    const execOrDirectorRole = currentUser.roles?.find((r: any) =>
      ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'].includes(r.role)
    )
    if (execOrDirectorRole?.unit?.id) return Number(execOrDirectorRole.unit.id)
    if (execOrDirectorRole?.organizational_unit_id) return Number(execOrDirectorRole.organizational_unit_id)

    const empUnit = currentUser.employee?.unit
    if (empUnit) {
      if (empUnit.parent_id) {
        return Number(empUnit.parent_id)
      }
      return Number(empUnit.id)
    }
    return null
  }, [currentUser])

  const isSystemAdmin = useMemo(() => {
    return !!currentUser?.roles?.some((r: any) => r.role === 'SYSTEM_ADMIN')
  }, [currentUser])

  const canChangeRequestingUnit = isSystemAdmin || !userDirectorateId

  const [form, setForm] = useState<MeetingFormValues>({
    requesting_unit_id: initialValues?.requesting_unit_id || userDirectorateId || '',
    target_unit_id: initialValues?.target_unit_id || '',
    target_unit_ids: initialValues?.target_unit_ids || [],
    subject: initialValues?.subject || '',
    purpose: initialValues?.purpose || (isHighAuthority ? 'DECISION' : 'COORDINATION'),
    priority: initialValues?.priority || 'NORMAL',
    description: initialValues?.description || '',
    meeting_justification: initialValues?.meeting_justification || '',
    requested_outcome: initialValues?.requested_outcome || '',
    preferred_date: initialValues?.preferred_date || '',
    preferred_start_time: initialValues?.preferred_start_time || '',
    estimated_duration_minutes: initialValues?.estimated_duration_minutes || 60,
    proposed_location: initialValues?.proposed_location || '',
    participant_ids: initialValues?.participant_ids || [],
  })

  // Sync if userDirectorateId loads after mount
  React.useEffect(() => {
    if (userDirectorateId && !form.requesting_unit_id) {
      setForm((prev) => ({ ...prev, requesting_unit_id: userDirectorateId }))
    }
  }, [userDirectorateId])

  const [submittingAction, setSubmittingAction] = useState<'draft' | 'submit' | null>(null)
  const [errorMessage, setErrorMessage] = useState('')

  // Filter directorate units
  const directorates = useMemo(() => {
    return units.filter((u) => u.unit_type === 'DIRECTORATE' || !u.parent_id)
  }, [units])

  // Compute eligible units for the selected requesting directorate
  const allowedUnitIds = useMemo(() => {
    const selected = Number(form.requesting_unit_id)
    if (!selected) return new Set<number>()
    const ids = new Set<number>([selected])
    let changed = true
    while (changed) {
      changed = false
      units.forEach((unit) => {
        if (unit.parent_id && ids.has(Number(unit.parent_id)) && !ids.has(Number(unit.id))) {
          ids.add(Number(unit.id))
          changed = true
        }
      })
    }
    return ids
  }, [units, form.requesting_unit_id])

  // Filter employees belonging to the selected requesting directorate hierarchy
  const eligibleEmployees = useMemo(() => {
    if (!form.requesting_unit_id) return []
    return employees.filter((emp) => allowedUnitIds.has(Number(emp.organizational_unit_id)))
  }, [employees, allowedUnitIds, form.requesting_unit_id])

  const handleFieldChange = (field: keyof MeetingFormValues, value: any) => {
    setForm((prev) => {
      const updated = { ...prev, [field]: value }
      if (field === 'requesting_unit_id') {
        updated.participant_ids = []
        if (isMultiDirectorate) {
          updated.target_unit_ids = (updated.target_unit_ids || []).filter(
            (id) => Number(id) !== Number(value)
          )
        }
      }
      return updated
    })
    setErrorMessage('')
  }

  const validate = (): boolean => {
    if (!form.requesting_unit_id) {
      setErrorMessage('لطفاً ریاست درخواست‌کننده را انتخاب نمایید.')
      return false
    }
    if (isInterDirectorate && !form.target_unit_id) {
      setErrorMessage('لطفاً ریاست مقصد را انتخاب نمایید.')
      return false
    }
    if (isInterDirectorate && Number(form.requesting_unit_id) === Number(form.target_unit_id)) {
      setErrorMessage('ریاست درخواست‌کننده و ریاست مقصد نمی‌توانند یکسان باشند.')
      return false
    }
    if (isMultiDirectorate && (!form.target_unit_ids || form.target_unit_ids.length < 2)) {
      setErrorMessage('در جلسات چندریاستی حداقل دو ریاست مقصد باید انتخاب شود.')
      return false
    }
    if (!form.subject.trim()) {
      setErrorMessage('لطفاً موضوع رسمی جلسه را وارد نمایید.')
      return false
    }
    return true
  }

  const handleSubmit = async (submitForApproval: boolean) => {
    if (!validate()) return

    setSubmittingAction(submitForApproval ? 'submit' : 'draft')
    setErrorMessage('')

    try {
      await onSubmit(form, submitForApproval)
    } catch (err: any) {
      const serverMsg =
        err?.response?.data?.message || 'خطا در ثبت اطلاعات درخواست جلسه. لطفاً مجدداً تلاش نمایید.'
      setErrorMessage(serverMsg)
    } finally {
      setSubmittingAction(null)
    }
  }

  return (
    <div className="space-y-4">
      {errorMessage && (
        <div className="p-3.5 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs font-semibold flex items-start gap-2.5 animate-in fade-in">
          <AlertCircle className="size-4 shrink-0 mt-0.5" />
          <span className="leading-relaxed">{errorMessage}</span>
        </div>
      )}

      {/* Section 1: Basic Information */}
      <Card className="border border-border/70 shadow-xs">
        <CardHeader className="p-3.5 sm:p-4 border-b border-border/60">
          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <Building2 className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">اطلاعات اصلی جلسه</h3>
              <p className="text-[11px] text-muted-foreground">
                مشخصات ریاست‌ها، موضوع و هدف اصلی تشکیل جلسه
              </p>
            </div>
          </div>
        </CardHeader>
        <CardContent className="p-3.5 sm:p-4 space-y-3.5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            {/* Requesting Unit */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                ریاست درخواست‌کننده <span className="text-destructive">*</span>
              </label>
              {canChangeRequestingUnit ? (
                <SearchableSelect
                  options={directorates.map((u) => ({
                    value: u.id,
                    label: u.name,
                    badge: u.code,
                    icon: Building2,
                  }))}
                  value={form.requesting_unit_id}
                  onChange={(val) => handleFieldChange('requesting_unit_id', val)}
                  placeholder="انتخاب یا جستجوی ریاست درخواست‌کننده..."
                  searchPlaceholder="جستجوی نام یا کد ریاست..."
                  emptyMessage="ریاستی با این مشخصات یافت نشد."
                />
              ) : (
                <div className="flex items-center justify-between h-9.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-xs">
                  <div className="flex items-center gap-2 min-w-0">
                    <Building2 className="size-3.5 text-primary shrink-0" />
                    <span className="font-bold text-foreground truncate">
                      {directorates.find((u) => Number(u.id) === Number(form.requesting_unit_id))?.name ||
                        currentUser?.employee?.unit?.name ||
                        'ریاست شما'}
                    </span>
                  </div>
                  <span className="inline-flex items-center gap-1 text-[10px] text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 px-2 py-0.5 rounded-md shrink-0 font-medium">
                    <ShieldCheck className="size-3 text-emerald-600" />
                    ثبت خودکار
                  </span>
                </div>
              )}
            </div>

            {/* Target Unit (if inter-directorate) */}
            {isInterDirectorate && (
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  ریاست مقصد (طرف جلسه) <span className="text-destructive">*</span>
                </label>
                <SearchableSelect
                  options={directorates
                    .filter((u) => Number(u.id) !== Number(form.requesting_unit_id))
                    .map((u) => ({
                      value: u.id,
                      label: u.name,
                      badge: u.code,
                      icon: Building2,
                    }))}
                  value={form.target_unit_id}
                  onChange={(val) => handleFieldChange('target_unit_id', val)}
                  placeholder="انتخاب یا جستجوی ریاست مقصد..."
                  searchPlaceholder="جستجوی نام یا کد ریاست مقصد..."
                  emptyMessage="ریاست مقصدی با این مشخصات یافت نشد."
                />
              </div>
            )}

            {/* Subject */}
            <div className={cn('space-y-1.5', (!isInterDirectorate && !isMultiDirectorate) && 'sm:col-span-2')}>
              <label className="text-xs font-bold text-foreground block">
                موضوع و عنوان رسمی جلسه <span className="text-destructive">*</span>
              </label>
              <Input
                type="text"
                value={form.subject}
                onChange={(e) => handleFieldChange('subject', e.target.value)}
                placeholder="مثلاً: جلسه هماهنگی توسعه سیستم‌های فناوری اطلاعات"
                className="h-9.5 text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl"
              />
            </div>

            {/* Purpose */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                هدف از تشکیل جلسه
              </label>
              <Select
                value={form.purpose}
                onValueChange={(val) => handleFieldChange('purpose', val)}
              >
                <SelectTrigger className="h-9.5 text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl">
                  <SelectValue placeholder="انتخاب هدف جلسه" />
                </SelectTrigger>
                <SelectContent align="end">
                  {Object.entries(purposeLabels).map(([k, v]) => (
                    <SelectItem key={k} value={k} className="text-xs">
                      {v}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Priority */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                اولویت جلسه
              </label>
              <Select
                value={form.priority}
                onValueChange={(val) => handleFieldChange('priority', val)}
              >
                <SelectTrigger className="h-9.5 text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl">
                  <SelectValue placeholder="انتخاب اولویت" />
                </SelectTrigger>
                <SelectContent align="end">
                  {Object.entries(priorityLabels).map(([k, v]) => (
                    <SelectItem key={k} value={k} className="text-xs">
                      {v}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Multi-Directorate Target Picker */}
          {isMultiDirectorate && (
            <div className="space-y-2 pt-2 border-t border-border/50">
              <label className="text-xs font-bold text-foreground block">
                ریاست‌های مقصد مشارکت‌کننده <span className="text-destructive">*</span>
              </label>
              <span className="text-[11px] text-muted-foreground block">
                حداقل دو ریاست مقصد را جهت هماهنگی و ارسال درخواست جلسه انتخاب نمایید.
              </span>
              <MultiSearchableSelect
                options={directorates
                  .filter((u) => Number(u.id) !== Number(form.requesting_unit_id))
                  .map((u) => ({
                    value: u.id,
                    label: u.name,
                    badge: u.code,
                    icon: Building2,
                  }))}
                value={form.target_unit_ids || []}
                onChange={(ids) => handleFieldChange('target_unit_ids', ids)}
                placeholder="انتخاب یا جستجوی ریاست‌های مقصد..."
                searchPlaceholder="جستجوی نام یا کد ریاست مقصد..."
                emptyMessage="ریاست مقصدی با این مشخصات یافت نشد."
              />
            </div>
          )}

          {/* High Authority Fields */}
          {isHighAuthority && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2 border-t border-border/50">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  توجیه برگزاری جلسه با مقام عالی
                </label>
                <Textarea
                  rows={2}
                  value={form.meeting_justification}
                  onChange={(e) => handleFieldChange('meeting_justification', e.target.value)}
                  placeholder="ضرورت و دلایل لزوم برگزاری جلسه با حضور مقام عالی..."
                  className="text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl leading-relaxed"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  نتیجه و خروجی مورد انتظار
                </label>
                <Textarea
                  rows={2}
                  value={form.requested_outcome}
                  onChange={(e) => handleFieldChange('requested_outcome', e.target.value)}
                  placeholder="تصمیم، مصوبه یا هدایتی که از مقام عالی درخواست می‌شود..."
                  className="text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl leading-relaxed"
                />
              </div>
            </div>
          )}

          {/* Description */}
          <div className="space-y-1.5 pt-1">
            <label className="text-xs font-bold text-foreground block">
              شرح موضوع و جزئیات مباحث جلسه
            </label>
            <Textarea
              rows={3}
              value={form.description}
              onChange={(e) => handleFieldChange('description', e.target.value)}
              placeholder="توضیحات تکمیلی پیرامون ضرورت برگزاری، محورهای بحث و خروجی‌های مدنظر جلسه..."
              className="text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl leading-relaxed resize-y"
            />
          </div>
        </CardContent>
      </Card>

      {/* Section 2: Proposed Schedule & Venue */}
      <Card className="border border-border/70 shadow-xs">
        <CardHeader className="p-3.5 sm:p-4 border-b border-border/60">
          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <Calendar className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">زمان و مکان پیشنهادی</h3>
              <p className="text-[11px] text-muted-foreground">
                پیشنهاد اولیه برای تاریخ، ساعت و محل برگزاری
              </p>
            </div>
          </div>
        </CardHeader>
        <CardContent className="p-3.5 sm:p-4 space-y-3.5">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            {/* Preferred Date */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                تاریخ پیشنهادی
              </label>
              <MultiCalendarDatePicker
                value={form.preferred_date}
                onChange={(val) => handleFieldChange('preferred_date', val)}
                placeholder="انتخاب تاریخ پیشنهادی جلسه..."
              />
            </div>

            {/* Start Time */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                ساعت آغاز پیشنهادی
              </label>
              <TimePicker
                value={form.preferred_start_time}
                onChange={(val) => handleFieldChange('preferred_start_time', val)}
                placeholder="انتخاب ساعت آغاز..."
              />
            </div>

            {/* Duration */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                مدت تقریبی (دقیقه)
              </label>
              <Input
                type="number"
                min={15}
                max={480}
                step={15}
                value={form.estimated_duration_minutes}
                onChange={(e) =>
                  handleFieldChange('estimated_duration_minutes', Number(e.target.value) || 60)
                }
                className="h-9.5 text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl"
              />
            </div>
          </div>

          {/* Location */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              محل پیشنهادی برگزاری
            </label>
            <div className="relative">
              <MapPin className="size-4 text-muted-foreground absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                type="text"
                value={form.proposed_location}
                onChange={(e) => handleFieldChange('proposed_location', e.target.value)}
                placeholder="مثلاً: تالار جلسات ریاست عمومی یا دفتر ریاست تکنالوژی"
                className="h-9.5 ps-10 pe-3 text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Section 3: Participants */}
      <Card className="border border-border/70 shadow-xs">
        <CardHeader className="p-3.5 sm:p-4 border-b border-border/60">
          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <Users2 className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">اشتراک‌کنندگان پیشنهادی</h3>
              <p className="text-[11px] text-muted-foreground">
                انتخاب اعضای مدنظر ریاست از میان کارمندان فعال
              </p>
            </div>
          </div>
        </CardHeader>
        <CardContent className="p-3.5 sm:p-4">
          {form.requesting_unit_id ? (
            <ParticipantPicker
              employees={eligibleEmployees}
              selectedIds={form.participant_ids}
              onChange={(ids) => handleFieldChange('participant_ids', ids)}
              placeholder="انتخاب یا جستجوی کارمندان ریاست..."
              searchPlaceholder="جستجوی کارمند بر اساس نام، دیپارتمنت یا شماره کارمندی..."
            />
          ) : (
            <div className="p-6 text-center text-xs text-muted-foreground border border-dashed rounded-xl space-y-1">
              <Building2 className="size-5 mx-auto text-muted-foreground/60" />
              <p>برای انتخاب اشتراک‌کنندگان، ابتدا ریاست درخواست‌کننده را مشخص نمایید.</p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Footer Actions */}
      <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
        {onCancel ? (
          <Button type="button" variant="outline" size="sm" onClick={onCancel} className="text-xs h-9 rounded-xl">
            انصراف و بازگشت
          </Button>
        ) : (
          <div />
        )}

        <div className="flex items-center gap-2.5">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={submittingAction !== null}
            onClick={() => handleSubmit(false)}
            className="text-xs h-9 px-3 gap-1.5 rounded-xl"
          >
            {submittingAction === 'draft' ? (
              <Loader2 className="size-3.5 animate-spin" />
            ) : (
              <Save className="size-3.5 text-muted-foreground" />
            )}
            ذخیره پیش‌نویس
          </Button>

          <Button
            type="button"
            size="sm"
            disabled={submittingAction !== null}
            onClick={() => handleSubmit(true)}
            className="text-xs h-9 px-4 gap-1.5 text-white !text-white rounded-xl shadow-xs"
          >
            {submittingAction === 'submit' ? (
              <Loader2 className="size-3.5 animate-spin text-white" />
            ) : (
              <Send className="size-3.5 text-white rotate-180" />
            )}
            ارسال برای بررسی و تأیید
          </Button>
        </div>
      </div>
    </div>
  )
}
