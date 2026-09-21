import React from 'react'
import { CalendarDays, Clock3, MapPin } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { formatSolarDate } from '@/lib/calendar-utils'
import type { MeetingItem } from '../types'

type MeetingWorkspaceSummaryProps = {
  meeting: MeetingItem
}

export function MeetingWorkspaceSummary({ meeting }: MeetingWorkspaceSummaryProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardContent className="p-3.5 sm:p-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
          <div className="flex items-center gap-2.5 min-w-0">
            <CalendarDays className="size-4 text-primary shrink-0" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">تاریخ جلسه</span>
              <strong className="font-bold text-foreground block truncate">
                {meeting.meeting_date ? formatSolarDate(meeting.meeting_date) : 'تعیین نشده'}
              </strong>
            </div>
          </div>
          <div className="flex items-center gap-2.5 min-w-0">
            <Clock3 className="size-4 text-primary shrink-0" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">ساعت جلسه</span>
              <strong className="font-bold text-foreground block font-mono truncate">
                {meeting.start_time?.slice(0, 5) || '—'} تا {meeting.end_time?.slice(0, 5) || '—'}
              </strong>
            </div>
          </div>
          <div className="flex items-center gap-2.5 min-w-0">
            <MapPin className="size-4 text-primary shrink-0" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">محل برگزاری</span>
              <strong className="font-bold text-foreground block truncate">{meeting.location || 'تعیین نشده'}</strong>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
