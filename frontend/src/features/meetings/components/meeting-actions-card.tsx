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
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import type { MeetingItem } from '../types'

type WorkspaceKey = 'execution' | 'minutes' | 'terminals' | 'reschedule' | 'participant_change' | 'exceptions' | 'follow_up'

type MeetingActionsCardProps = {
  meeting: MeetingItem
  activeWorkspace?: WorkspaceKey
}

type MeetingWorkspaceNavItemProps = {
  active: boolean
  primary: boolean
  next?: boolean
  to: string
  label: string
  icon: React.ReactNode
}

function MeetingWorkspaceNavItem({ active, primary, next = false, to, label, icon }: MeetingWorkspaceNavItemProps) {
  if (active) {
    return (
      <div
        role="status"
        aria-current="page"
        className="w-full min-h-9.5 px-3.5 py-2 rounded-xl border border-primary/40 bg-primary/5 text-primary ring-1 ring-primary/15 flex items-center gap-2 text-xs font-semibold"
      >
        {icon}
        <span className="flex-1 text-right">{label}</span>
        <span className="text-[10px] font-medium text-primary/80">بخش فعال</span>
      </div>
    )
  }

  return (
    <Button
      asChild
      variant={primary ? 'default' : 'outline'}
      className={`w-full h-9.5 text-xs font-semibold rounded-xl gap-2 justify-start px-3.5 ${
        primary ? 'bg-primary hover:bg-primary/90 text-white shadow-xs' : 'border-border/70 hover:bg-primary/5'
      }`}
    >
      <Link to={to}>
        {icon}
        <span className="flex-1 text-right">{label}</span>
        {next && (
          <span className={primary ? 'text-[10px] font-medium text-white/80' : 'text-[10px] font-medium text-primary'}>
            گام بعدی
          </span>
        )}
      </Link>
    </Button>
  )
}

export function MeetingActionsCard({ meeting, activeWorkspace }: MeetingActionsCardProps) {
  const isCompleted = ['HELD', 'COMPLETED'].includes(meeting.status)
  const capabilities = meeting.action_capabilities
  const primaryCandidates: WorkspaceKey[] = ['SCHEDULED', 'IN_PROGRESS'].includes(meeting.status)
    ? ['execution', 'minutes', 'reschedule', 'participant_change', 'exceptions', 'follow_up']
    : ['HELD', 'COMPLETED'].includes(meeting.status)
    ? ['minutes', 'follow_up', 'reschedule', 'participant_change', 'exceptions', 'execution']
    : meeting.status === 'POSTPONED'
    ? ['reschedule', 'exceptions', 'minutes', 'follow_up']
    : ['minutes', 'reschedule', 'participant_change', 'exceptions', 'follow_up', 'execution']

  const primaryAction = primaryCandidates.find((key) => {
    if (key === 'execution') return capabilities?.execution.view
    if (key === 'minutes') return capabilities?.minutes.view
    if (key === 'reschedule') return capabilities?.reschedule.view
    if (key === 'participant_change') return capabilities?.participant_change.view
    if (key === 'exceptions') return capabilities?.exceptions.view
    return isCompleted && capabilities?.follow_up.view
  })

  const isNextAction = (key: WorkspaceKey) => Boolean(
    activeWorkspace && activeWorkspace !== key && primaryAction === key
  )

  const isPrimaryAction = (key: WorkspaceKey) => Boolean(
    activeWorkspace && primaryAction === key
  )

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
          {activeWorkspace ? 'دسترسی سریع به بخش‌های جلسه' : 'اقدامات قابل انجام'}
        </h3>
        <p className="text-[11px] text-muted-foreground">
          {activeWorkspace
            ? 'بخش فعلی مشخص شده و سایر بخش‌ها برای ورود در دسترس هستند.'
            : 'عملیات مجاز متناسب با وضعیت فعلی جلسه'}
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-2.5">
        {capabilities?.execution.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'execution'}
            primary={isPrimaryAction('execution')}
            next={isNextAction('execution')}
            to={`/meetings/${meeting.id}/execution`}
            icon={<Play className="size-4 shrink-0" />}
            label="ورود به فضای اجرای جلسه"
          />
        )}

        {capabilities?.minutes.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'minutes'}
            primary={isPrimaryAction('minutes')}
            next={isNextAction('minutes')}
            to={`/meetings/${meeting.id}/minutes`}
            icon={<FileCheck2 className="size-4 shrink-0" />}
            label="ثبت و تنظیم صورت‌جلسه رسمی"
          />
        )}

        {capabilities?.terminals.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'terminals'}
            primary={isPrimaryAction('terminals')}
            next={isNextAction('terminals')}
            to={`/meetings/${meeting.id}/terminals`}
            icon={<MonitorCheck className="size-4 shrink-0" />}
            label="ترمینال‌ها و حاضری بایومتریک"
          />
        )}

        {capabilities?.reschedule.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'reschedule'}
            primary={isPrimaryAction('reschedule')}
            next={isNextAction('reschedule')}
            to={`/meetings/${meeting.id}/reschedule`}
            icon={<CalendarClock className="size-4 shrink-0" />}
            label="درخواست تغییر زمان‌بندی جلسه"
          />
        )}

        {capabilities?.participant_change.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'participant_change'}
            primary={isPrimaryAction('participant_change')}
            next={isNextAction('participant_change')}
            to={`/meetings/${meeting.id}/participants/change`}
            icon={<UserCog className="size-4 shrink-0" />}
            label="تغییر و جایگزینی اشتراک‌کنندگان"
          />
        )}

        {capabilities?.exceptions.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'exceptions'}
            primary={isPrimaryAction('exceptions')}
            next={isNextAction('exceptions')}
            to={`/meetings/${meeting.id}/exceptions`}
            icon={<AlertOctagon className="size-4 shrink-0" />}
            label="مدیریت وضعیت‌های استثنایی"
          />
        )}

        {isCompleted && capabilities?.follow_up.view && (
          <MeetingWorkspaceNavItem
            active={activeWorkspace === 'follow_up'}
            primary={isPrimaryAction('follow_up')}
            next={isNextAction('follow_up')}
            to={`/meetings/${meeting.id}/follow-up`}
            icon={<ListTodo className="size-4 shrink-0" />}
            label="پیگیری تصمیم‌ها و مصوبات جلسه"
          />
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
