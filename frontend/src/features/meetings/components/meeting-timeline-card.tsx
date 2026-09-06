import React from 'react'
import { Clock3, CheckCircle2, Send, RotateCcw, Calendar, FileText } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import { meetingActionLabel } from '@/app/config/labels'

const ACTION_LABELS: Record<string, { label: string; icon: any; color: string }> = {
  CREATED: {
    label: 'درخواست ایجاد شد',
    icon: FileText,
    color: 'text-slate-600 bg-slate-100 dark:bg-slate-800',
  },
  SUBMITTED: {
    label: 'ارسال برای تأیید داخلی',
    icon: Send,
    color: 'text-sky-600 bg-sky-50 dark:bg-sky-950/40',
  },
  APPROVED: {
    label: 'درخواست تأیید شد',
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
  APPROVED_UNDER_DELEGATION: {
    label: meetingActionLabel('APPROVED_UNDER_DELEGATION'),
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
  MINUTES_UNIT_ACKNOWLEDGED_UNDER_DELEGATION: {
    label: meetingActionLabel('MINUTES_UNIT_ACKNOWLEDGED_UNDER_DELEGATION'),
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
  MINUTES_CORRECTION_REQUESTED_UNDER_DELEGATION: {
    label: meetingActionLabel('MINUTES_CORRECTION_REQUESTED_UNDER_DELEGATION'),
    icon: RotateCcw,
    color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40',
  },
  SCHEDULING_STARTED: {
    label: 'زمان‌بندی آغاز شد',
    icon: Calendar,
    color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40',
  },
  SCHEDULE_CONFIRMED: {
    label: 'برنامه زمان‌بندی تأیید شد',
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
  MEETING_CREATED: {
    label: 'جلسه رسمی صادر و تشکیل گردید',
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
}

type MeetingTimelineCardProps = {
  actions?: any[]
}

export function MeetingTimelineCard({ actions = [] }: MeetingTimelineCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Clock3 className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              تاریخچه و گردش کار
            </h3>
            <p className="text-[11px] text-muted-foreground">
              سوابق اداری ثبت‌شده برای این جلسه
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5">
        {actions.length === 0 ? (
          <p className="text-xs text-muted-foreground text-center py-4">
            رویدادی ثبت نشده است.
          </p>
        ) : (
          <div className="relative pr-4 space-y-4 before:absolute before:right-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-border/60">
            {actions.map((act) => {
              const actInfo = ACTION_LABELS[act.action] || {
                label: act.action,
                icon: FileText,
                color: 'text-slate-600 bg-slate-100',
              }
              const Icon = actInfo.icon
              const formattedDate = act.created_at
                ? formatSolarDateTime(act.created_at)
                : '—'

              return (
                <div key={act.id} className="relative group">
                  <div
                    className={cn(
                      'absolute -right-[23px] top-1 size-4 rounded-full border-2 border-background flex items-center justify-center shadow-xs',
                      actInfo.color
                    )}
                  >
                    <Icon className="size-2.5" />
                  </div>

                  <div className="space-y-1">
                    <strong className="text-xs font-bold text-foreground block">
                      {actInfo.label}
                    </strong>
                    <div className="text-[10px] text-muted-foreground">
                      {act.actor?.name || 'سیستم'} • {formattedDate}
                    </div>

                    {act.comment && (
                      <div className="mt-1.5 p-2 rounded-lg bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 text-[11px] text-amber-900 dark:text-amber-300 leading-relaxed">
                        {act.comment}
                      </div>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
