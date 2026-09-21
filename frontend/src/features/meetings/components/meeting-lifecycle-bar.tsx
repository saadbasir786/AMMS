import React from 'react'
import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

type MeetingLifecycleBarProps = {
  status: string
}

export function MeetingLifecycleBar({ status }: MeetingLifecycleBarProps) {
  const planningDone = ['SCHEDULED', 'IN_PROGRESS', 'HELD', 'COMPLETED', 'POSTPONED', 'CANCELLED'].includes(status)
  const executionActive = ['SCHEDULED', 'IN_PROGRESS'].includes(status)
  const executionDone = ['HELD', 'COMPLETED'].includes(status)
  const resultsActive = ['HELD', 'COMPLETED'].includes(status)

  const stages = [
    {
      step: 1,
      title: 'برنامه‌ریزی جلسه',
      desc: 'زمان، محل و اشتراک‌کنندگان نهایی',
      status: planningDone ? ('done' as const) : ('active' as const),
    },
    {
      step: 2,
      title: 'برگزاری جلسه',
      desc: 'ثبت آغاز و پایان رسمی جلسه',
      status: executionDone ? ('done' as const) : executionActive ? ('active' as const) : ('pending' as const),
    },
    {
      step: 3,
      title: 'صورت‌جلسه و پیگیری',
      desc: 'ثبت نتایج، تصمیم‌ها و اقدامات بعدی',
      status: resultsActive ? ('active' as const) : ('pending' as const),
    },
  ]

  return (
    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3" dir="rtl">
      {stages.map((stage) => (
        <div
          key={stage.step}
          className={cn(
            'flex items-center gap-3 rounded-2xl border transition-all',
            'p-3.5',
            stage.status === 'active'
              ? 'bg-primary/5 border-primary/40 shadow-xs ring-1 ring-primary/20'
              : stage.status === 'done'
              ? 'bg-card border-border/70'
              : 'bg-slate-50/50 dark:bg-slate-900/30 border-border/40 opacity-60'
          )}
        >
          <div
            className={cn(
              'size-8 rounded-xl flex items-center justify-center font-bold text-xs shrink-0',
              stage.status === 'active'
                ? 'bg-primary text-white shadow-xs'
                : stage.status === 'done'
                ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                : 'bg-muted text-muted-foreground'
            )}
          >
            {stage.status === 'done' ? <Check className="size-4 stroke-[3]" /> : stage.step}
          </div>
          <div className="min-w-0">
            <strong
              className={cn(
                'text-xs font-bold block truncate',
                stage.status === 'active'
                  ? 'text-primary'
                  : stage.status === 'done'
                  ? 'text-foreground'
                  : 'text-muted-foreground'
              )}
            >
              {stage.title}
            </strong>
            <span className="text-[10px] text-muted-foreground block truncate">{stage.desc}</span>
          </div>
        </div>
      ))}
    </div>
  )
}
