import React from 'react'
import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

type SchedulingStageBarProps = {
  status: string
}

export function SchedulingStageBar({ status }: SchedulingStageBarProps) {
  const stageOneActive = ['DRAFT', 'PENDING_INTERNAL_APPROVAL', 'RETURNED_FOR_REVISION'].includes(status)
  const stageOneDone = ['APPROVED', 'SCHEDULING', 'SCHEDULE_CONFIRMED', 'CONVERTED_TO_MEETING'].includes(status)
  const stageTwoActive = ['APPROVED', 'SCHEDULING'].includes(status)
  const stageTwoDone = ['SCHEDULE_CONFIRMED', 'CONVERTED_TO_MEETING'].includes(status)
  const stageThreeActive = status === 'SCHEDULE_CONFIRMED'
  const stageThreeDone = status === 'CONVERTED_TO_MEETING'

  const stages = [
    {
      step: 1,
      title: 'درخواست و تأیید',
      desc: 'ثبت درخواست، بررسی و تأیید ریاست',
      status: stageOneActive ? ('active' as const) : stageOneDone ? ('done' as const) : ('pending' as const),
    },
    {
      step: 2,
      title: 'تنظیم زمان‌بندی رسمی',
      desc: 'تاریخ، زمان، محل و رئیس جلسه',
      status: stageTwoActive ? ('active' as const) : stageTwoDone ? ('done' as const) : ('pending' as const),
    },
    {
      step: 3,
      title: 'ایجاد جلسه رسمی',
      desc: 'بازبینی، ثبت آجندا و صدور جلسه',
      status: stageThreeDone ? ('done' as const) : stageThreeActive ? ('active' as const) : ('pending' as const),
    },
  ]

  return (
    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3" dir="rtl">
      {stages.map((st) => (
        <div
          key={st.step}
          className={cn(
            'flex items-center gap-3 p-3.5 rounded-2xl border transition-all',
            st.status === 'active'
              ? 'bg-primary/5 border-primary/40 shadow-xs ring-1 ring-primary/20'
              : st.status === 'done'
              ? 'bg-card border-border/70'
              : 'bg-slate-50/50 dark:bg-slate-900/30 border-border/40 opacity-60'
          )}
        >
          <div
            className={cn(
              'size-8 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 transition-colors',
              st.status === 'active'
                ? 'bg-primary text-white shadow-xs'
                : st.status === 'done'
                ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                : 'bg-muted text-muted-foreground'
            )}
          >
            {st.status === 'done' ? (
              <Check className="size-4 stroke-[3]" />
            ) : (
              st.step
            )}
          </div>

          <div className="min-w-0">
            <strong
              className={cn(
                'text-xs font-bold block truncate',
                st.status === 'active'
                  ? 'text-primary'
                  : st.status === 'done'
                  ? 'text-foreground'
                  : 'text-muted-foreground'
              )}
            >
              {st.title}
            </strong>
            <span className="text-[10px] text-muted-foreground block truncate">
              {st.desc}
            </span>
          </div>
        </div>
      ))}
    </div>
  )
}
