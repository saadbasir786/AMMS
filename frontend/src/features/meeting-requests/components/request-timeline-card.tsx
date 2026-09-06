import React from 'react'
import { Clock3 } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { meetingActionLabel } from '@/app/config/labels'

const ACTION_LABELS: Record<string, string> = {
  CREATED: 'درخواست ایجاد شد',
  SUBMITTED: 'ارسال برای بررسی و تأیید',
  RETURNED: 'جهت اصلاح برگشت داده شد',
  RESUBMITTED: 'درخواست پس از اصلاح مجدداً ارسال شد',
  APPROVED: 'درخواست تأیید شد',
  APPROVED_UNDER_DELEGATION: meetingActionLabel('APPROVED_UNDER_DELEGATION'),
  MINUTES_UNIT_ACKNOWLEDGED_UNDER_DELEGATION: meetingActionLabel('MINUTES_UNIT_ACKNOWLEDGED_UNDER_DELEGATION'),
  MINUTES_CORRECTION_REQUESTED_UNDER_DELEGATION: meetingActionLabel('MINUTES_CORRECTION_REQUESTED_UNDER_DELEGATION'),
  REJECTED: 'درخواست رد شد',
  SCHEDULING_STARTED: 'زمان‌بندی جلسه آغاز شد',
  SCHEDULE_CONFIRMED: 'زمان‌بندی نهایی و تأیید شد',
  MEETING_CREATED: 'جلسه رسمی تشکیل گردید',
}

type RequestTimelineCardProps = {
  actions?: any[]
}

export function RequestTimelineCard({ actions = [] }: RequestTimelineCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60 flex flex-row items-start justify-between gap-4">
        <div className="space-y-1">
          <h3 className="text-sm font-bold text-foreground">
            تاریخچه و گردش کار
          </h3>
          <p className="text-xs text-muted-foreground">
            ثبت غیرقابل تغییر رویدادهای اداری
          </p>
        </div>
        <Clock3 className="size-5 text-muted-foreground shrink-0" />
      </CardHeader>

      <CardContent className="p-4 sm:p-5">
        {actions.length === 0 ? (
          <p className="text-xs text-muted-foreground text-center py-4">
            رویدادی برای این درخواست ثبت نشده است.
          </p>
        ) : (
          <ol className="relative space-y-5 before:absolute before:bottom-2 before:top-2 before:right-[6px] before:w-px before:bg-border/70">
            {actions.map((act) => {
              const label =
                ACTION_LABELS[act.action] ||
                meetingActionLabel(act.action) ||
                act.action
              const formattedDate = act.created_at
                ? formatSolarDateTime(act.created_at)
                : '—'

              return (
                <li key={act.id} className="relative pr-6">
                  <span className="absolute right-0 top-1.5 size-3.5 rounded-full border-2 border-background bg-primary ring-1 ring-border/80 shadow-2xs" />

                  <div className="space-y-1">
                    <strong className="block text-sm font-medium text-foreground">
                      {label}
                    </strong>
                    <div className="text-xs text-muted-foreground">
                      {act.actor?.name || 'سیستم'} • {formattedDate}
                    </div>

                    {act.comment && (
                      <div className="mt-2 p-2.5 rounded-xl bg-muted/50 border border-border/70 text-xs text-foreground/90 leading-relaxed">
                        {act.comment}
                      </div>
                    )}
                  </div>
                </li>
              )
            })}
          </ol>
        )}
      </CardContent>
    </Card>
  )
}
