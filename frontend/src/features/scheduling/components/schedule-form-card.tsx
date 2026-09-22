import React, { useMemo } from 'react'
import {
  Calendar,
  Clock,
  Clock3,
  UserCheck,
  CheckCircle2,
  Users2,
  AlertCircle,
  Loader2
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'
import { ParticipantPicker } from '@/shared/components'
import { cn } from '@/lib/utils'
import { ScheduleVenueField } from './schedule-venue-field'
import type { SchedulingFormValues } from '../types'
import type { EmployeeItem } from '@/shared/types'

type ScheduleFormCardProps = {
  form: SchedulingFormValues
  onChange: (field: keyof SchedulingFormValues, value: any) => void
  employees: EmployeeItem[]
  eligibleEmployees: EmployeeItem[]
  endTime: string
  busy: boolean
  onSubmit: () => Promise<void>
  venues?: Array<{ id: number; name: string; location_details?: string | null }>
}

export function ScheduleFormCard({
  form,
  onChange,
  employees,
  eligibleEmployees,
  endTime,
  busy,
  onSubmit,
  venues = [],
}: ScheduleFormCardProps) {
  const chairpersonOptions = useMemo(() => {
    return eligibleEmployees.map((e) => ({
      value: e.id,
      label: e.full_name,
      badge: e.unit?.name || e.position?.title,
      icon: UserCheck,
    }))
  }, [eligibleEmployees])

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Calendar className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              تنظیم برنامه رسمی جلسه
            </h3>
            <p className="text-[11px] text-muted-foreground">
              مقادیر پیشنهادی اولیه درج شده‌اند و در صورت نیاز قابل تغییر می‌باشند
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Notice Alert */}
        <div className="flex items-center gap-2.5 p-3 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60 text-xs text-emerald-900 dark:text-emerald-300 font-medium">
          <CheckCircle2 className="size-4 text-emerald-600 shrink-0" />
          <span>
            درخواست تأیید شده است. مقادیر پیشنهادی اولیه در فیلدها قرار گرفته‌اند؛ در صورت نیاز به تغییر زمان، مکان یا افراد، آن‌ها را ویرایش نمایید.
          </span>
        </div>

        {/* Date, Time, Duration Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          {/* Official Date */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              تاریخ رسمی جلسه <span className="text-destructive">*</span>
            </label>
            <MultiCalendarDatePicker
              value={form.meeting_date}
              onChange={(val) => onChange('meeting_date', val)}
              placeholder="انتخاب تاریخ جلسه..."
            />
          </div>

          {/* Start Time */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              ساعت آغاز <span className="text-destructive">*</span>
            </label>
            <TimePicker
              value={form.start_time}
              onChange={(val) => onChange('start_time', val)}
              placeholder="انتخاب ساعت آغاز..."
            />
          </div>

          {/* Duration */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              مدت جلسه (دقیقه) <span className="text-destructive">*</span>
            </label>
            <Input
              type="number"
              min={15}
              max={480}
              step={15}
              value={form.duration_minutes}
              onChange={(e) => onChange('duration_minutes', Number(e.target.value) || 60)}
              className="h-9.5 text-xs bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 rounded-xl"
            />
          </div>
        </div>

        {/* Calculated End Time Indicator */}
        <div className="flex items-center justify-between p-3 rounded-xl bg-slate-100/80 dark:bg-slate-800/60 border border-border/50 text-xs">
          <div className="flex items-center gap-2 text-muted-foreground">
            <Clock3 className="size-4 text-primary" />
            <span>زمان ختم محاسبه‌شده:</span>
          </div>
          <strong className="font-bold text-foreground font-mono">
            {endTime || 'پس از انتخاب ساعت و مدت محاسبه می‌شود'}
          </strong>
        </div>

        {/* Location & Chairperson Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              محل برگزاری جلسه <span className="text-destructive">*</span>
            </label>
            <ScheduleVenueField form={form} venues={venues} onChange={onChange} />
          </div>

          {/* Chairperson */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              رئیس / مدیر جلسه <span className="text-destructive">*</span>
            </label>
            <SearchableSelect
              options={chairpersonOptions}
              value={form.chairperson_employee_id}
              onChange={(val) => onChange('chairperson_employee_id', val)}
              placeholder="انتخاب رئیس جلسه..."
              searchPlaceholder="جستجوی نام یا واحد سازمانی..."
              emptyMessage="کارمندی یافت نشد."
            />
          </div>
        </div>

        {/* Final Participants Picker */}
        <div className="space-y-2 pt-2 border-t border-border/50">
          <div className="flex items-center justify-between">
            <label className="text-xs font-bold text-foreground flex items-center gap-1.5">
              <Users2 className="size-4 text-primary" />
              اشتراک‌کنندگان نهایی جلسه <span className="text-destructive">*</span>
            </label>
            <span className="text-[11px] text-muted-foreground">
              {form.participant_ids.length} نفر انتخاب شده
            </span>
          </div>

          <ParticipantPicker
            employees={eligibleEmployees}
            selectedIds={form.participant_ids}
            onChange={(ids: number[]) => onChange('participant_ids', ids)}
            placeholder="جستجو و انتخاب اشتراک‌کنندگان نهایی..."
          />
        </div>

        {/* Submit Button */}
        <div className="pt-3 border-t border-border/50 flex justify-end">
          <Button
            type="button"
            className="h-9.5 px-5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
            disabled={
              busy ||
              !form.meeting_date ||
              !form.start_time ||
              !form.chairperson_employee_id ||
              form.participant_ids.length < 1 ||
              (form.venue_type === 'INTERNAL_VENUE' ? !form.venue_id : !form.location)
            }
            onClick={onSubmit}
          >
            {busy ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <CheckCircle2 className="size-4" />
            )}
            <span>تأیید برنامه جلسه</span>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
