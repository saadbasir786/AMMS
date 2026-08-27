import React from 'react'
import { Link } from 'react-router-dom'
import {
  Play,
  FileCheck2,
  MonitorCheck,
  CalendarClock,
  UserCog,
  AlertOctagon,
  ListTodo,
  ArrowRight
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import type { MeetingItem } from '../types'

type MeetingActionsCardProps = {
  meeting: MeetingItem
}

export function MeetingActionsCard({ meeting }: MeetingActionsCardProps) {
  const isCompleted = ['HELD', 'COMPLETED'].includes(meeting.status)
  const capabilities = meeting.action_capabilities
  const hasWorkspace = Boolean(
    capabilities?.execution.view ||
      capabilities?.minutes.view ||
      capabilities?.terminals.view ||
      capabilities?.reschedule.view ||
      capabilities?.participant_change.view ||
      capabilities?.exceptions.view ||
      (isCompleted && capabilities?.follow_up.view)
  )

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          اقدامات قابل انجام
        </h3>
        <p className="text-[11px] text-muted-foreground">
          عملیات مجاز متناسب با وضعیت فعلی جلسه
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-2.5">
        {/* Execution Workspace */}
        {capabilities?.execution.view && <Button
          asChild
          className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs justify-start px-3.5"
        >
          <Link to={`/meetings/${meeting.id}/execution`}>
            <Play className="size-4 shrink-0" />
            <span>ورود به فضای اجرای جلسه</span>
          </Link>
        </Button>}

        {/* Minutes Workspace */}
        {capabilities?.minutes.view && <Button
          asChild
          variant="outline"
          className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 justify-start px-3.5"
        >
          <Link to={`/meetings/${meeting.id}/minutes`}>
            <FileCheck2 className="size-4 text-primary shrink-0" />
            <span>ثبت و تنظیم صورت‌جلسه رسمی</span>
          </Link>
        </Button>}

        {/* Attendance & Terminals Workspace */}
        {capabilities?.terminals.view && <Button
          asChild
          variant="outline"
          className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 justify-start px-3.5"
        >
          <Link to={`/meetings/${meeting.id}/terminals`}>
            <MonitorCheck className="size-4 text-primary shrink-0" />
            <span>ترمینال‌ها و حاضری بایومتریک</span>
          </Link>
        </Button>}

        {/* Reschedule */}
        {capabilities?.reschedule.view && <Button
          asChild
          variant="outline"
          className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 justify-start px-3.5"
        >
          <Link to={`/meetings/${meeting.id}/reschedule`}>
            <CalendarClock className="size-4 text-amber-600 shrink-0" />
            <span>درخواست تغییر زمان‌بندی جلسه</span>
          </Link>
        </Button>}

        {/* Change Participants */}
        {capabilities?.participant_change.view && <Button
          asChild
          variant="outline"
          className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 justify-start px-3.5"
        >
          <Link to={`/meetings/${meeting.id}/participants/change`}>
            <UserCog className="size-4 text-amber-600 shrink-0" />
            <span>تغییر و جایگزینی اشتراک‌کنندگان</span>
          </Link>
        </Button>}

        {/* Exceptions */}
        {capabilities?.exceptions.view && <Button
          asChild
          variant="outline"
          className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 justify-start px-3.5"
        >
          <Link to={`/meetings/${meeting.id}/exceptions`}>
            <AlertOctagon className="size-4 text-rose-600 shrink-0" />
            <span>مدیریت وضعیت‌های استثنایی</span>
          </Link>
        </Button>}

        {/* Follow Up (when completed) */}
        {isCompleted && capabilities?.follow_up.view && (
          <Button
            asChild
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs justify-start px-3.5"
          >
            <Link to={`/meetings/${meeting.id}/follow-up`}>
              <ListTodo className="size-4 shrink-0" />
              <span>پیگیری تصامیم و مصوبات جلسه</span>
            </Link>
          </Button>
        )}

        {!hasWorkspace && (
          <p className="rounded-xl border border-border/70 bg-muted/40 px-3 py-3 text-center text-[11px] leading-5 text-muted-foreground">
            در وضعیت فعلی، اقدامی برای شما وجود ندارد.
          </p>
        )}
      </CardContent>
    </Card>
  )
}
