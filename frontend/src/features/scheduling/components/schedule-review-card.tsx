import React from 'react'
import {
  CalendarCheck2,
  Calendar,
  Clock,
  MapPin,
  UserCheck,
  Users,
  FileText,
  ArrowLeft,
  Loader2
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate } from '@/lib/calendar-utils'
import type { EmployeeItem } from '@/shared/types'

type ScheduleReviewCardProps = {
  request: any
  employees: EmployeeItem[]
  finalParticipants: any[]
  agenda: string
  onAgendaChange: (agenda: string) => void
  endTime: string
  busy: boolean
  onFinalize: () => Promise<void>
}

export function ScheduleReviewCard({
  request,
  employees,
  finalParticipants,
  agenda,
  onAgendaChange,
  endTime,
  busy,
  onFinalize,
}: ScheduleReviewCardProps) {
  const chairperson = employees.find(
    (e) => Number(e.id) === Number(request.scheduled_chairperson_employee_id)
  )

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
            <CalendarCheck2 className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              بازبینی مشخصات و ایجاد جلسه رسمی
            </h3>
            <p className="text-[11px] text-muted-foreground">
              برنامه زمان‌بندی تأیید شده است؛ درج دستورکار نهایی و صدور جلسه
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Approved Summary Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
          {/* Date */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block mb-0.5">تاریخ رسمی جلسه</span>
            <strong className="font-bold text-foreground block">
              {request.scheduled_date ? formatSolarDate(request.scheduled_date) + ' هـ.ش' : '—'}
            </strong>
          </div>

          {/* Time & Duration */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block mb-0.5">ساعت و مدت زمان</span>
            <strong className="font-bold text-foreground block font-mono">
              {request.scheduled_start_time?.slice(0, 5) || '—'} تا {endTime || '—'}{' '}
              <span className="text-muted-foreground font-sans">
                ({request.scheduled_duration_minutes} دقیقه)
              </span>
            </strong>
          </div>

          {/* Location */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block mb-0.5">محل برگزاری</span>
            <strong className="font-bold text-foreground block truncate">
              {request.scheduled_location || '—'}
            </strong>
          </div>

          {/* Chairperson */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 sm:col-span-2 lg:col-span-3">
            <span className="text-[10px] text-muted-foreground block mb-0.5">رئیس جلسه</span>
            <strong className="font-bold text-foreground block">
              {chairperson ? `${chairperson.full_name} (${chairperson.unit?.name || 'واحد سازمانی'})` : '—'}
            </strong>
          </div>
        </div>

        {/* Final Participants */}
        <div className="space-y-2 pt-2 border-t border-border/50">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-foreground flex items-center gap-1.5">
              <Users className="size-4 text-primary" />
              اشتراک‌کنندگان نهایی تأییدشده
            </span>
            <Badge variant="secondary" className="text-[10px] font-mono">
              {finalParticipants.length} نفر
            </Badge>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {finalParticipants.map((p) => (
              <div
                key={p.id}
                className="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-border/50 text-xs"
              >
                <span className="font-semibold text-foreground truncate">
                  {p.employee?.full_name}
                </span>
                <span className="text-[10px] text-muted-foreground truncate">
                  {p.employee?.unit?.name}
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* Agenda Input */}
        <div className="space-y-1.5 pt-2 border-t border-border/50">
          <label className="text-xs font-bold text-foreground flex items-center gap-1.5">
            <FileText className="size-4 text-primary" />
            آجندا و دستور کار رسمی جلسه
          </label>
          <Textarea
            rows={4}
            value={agenda}
            onChange={(e) => onAgendaChange(e.target.value)}
            placeholder="عناوین، مواد آجندا و محورهای اصلی جلسه رسمی را درج نمایید..."
            className="text-xs rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 resize-y leading-relaxed"
          />
        </div>

        {/* Finalize Button */}
        <div className="pt-3 border-t border-border/50 flex justify-end">
          <Button
            type="button"
            className="h-9.5 px-5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
            disabled={busy}
            onClick={onFinalize}
          >
            {busy ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <CalendarCheck2 className="size-4" />
            )}
            <span>ایجاد و صدور جلسه رسمی</span>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
