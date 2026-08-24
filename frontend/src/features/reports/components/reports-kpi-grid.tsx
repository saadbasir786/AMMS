import React from 'react'
import {
  CalendarDays,
  CheckCircle2,
  Users,
  Clock3,
  CalendarCheck,
  TrendingUp,
  AlertTriangle,
  FileCheck2,
  Activity,
  Layers,
  Percent,
  CalendarClock
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { MetricCard } from '../types'

const METRIC_CONFIG: Record<
  string,
  { label: string; icon: any; color: string; bg: string }
> = {
  MEETINGS_TOTAL: {
    label: 'کل جلسات رسمی',
    icon: CalendarDays,
    color: 'text-primary',
    bg: 'bg-primary/10',
  },
  MEETINGS_COMPLETED: {
    label: 'جلسات برگزار و تکمیل‌شده',
    icon: CheckCircle2,
    color: 'text-emerald-600 dark:text-emerald-400',
    bg: 'bg-emerald-500/10',
  },
  ATTENDANCE_RATE: {
    label: 'نرخ میانگین حضور رسمی',
    icon: CalendarCheck,
    color: 'text-indigo-600 dark:text-indigo-400',
    bg: 'bg-indigo-500/10',
  },
  ACTION_ITEMS_COMPLETED: {
    label: 'مصوبات و اقدامات تکمیل‌شده',
    icon: FileCheck2,
    color: 'text-teal-600 dark:text-teal-400',
    bg: 'bg-teal-500/10',
  },
  POSTPONED_MEETINGS: {
    label: 'جلسات معوق یا تغییریافته',
    icon: CalendarClock,
    color: 'text-amber-600 dark:text-amber-400',
    bg: 'bg-amber-500/10',
  },
  DECISIONS_TOTAL: {
    label: 'کل تصمیمات و مصوبات ثبت‌شده',
    icon: Activity,
    color: 'text-sky-600 dark:text-sky-400',
    bg: 'bg-sky-500/10',
  },
  REQUESTS_TOTAL: {
    label: 'کل درخواست‌های ورودی',
    icon: Layers,
    color: 'text-violet-600 dark:text-violet-400',
    bg: 'bg-violet-500/10',
  },
}

type ReportsKpiGridProps = {
  cards: MetricCard[]
}

export function ReportsKpiGrid({ cards }: ReportsKpiGridProps) {
  if (!cards || cards.length === 0) return null

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3.5" dir="rtl">
      {cards.map((card) => {
        const config = METRIC_CONFIG[card.metric_id] || {
          label: card.label || card.metric_id,
          icon: TrendingUp,
          color: 'text-primary',
          bg: 'bg-primary/10',
        }
        const Icon = config.icon
        const isPercent = card.unit === '%'
        const formattedValue =
          card.value === null || card.value === undefined
            ? 'بدون داده'
            : `${card.value}${isPercent ? '٪' : ''}`

        return (
          <div
            key={card.metric_id}
            className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex flex-col justify-between space-y-3 hover:border-primary/40 transition-all group"
          >
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold text-muted-foreground truncate max-w-[78%]">
                {card.label || config.label}
              </span>
              <div
                className={cn(
                  'size-8 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105',
                  config.bg,
                  config.color
                )}
              >
                <Icon className="size-4" />
              </div>
            </div>

            <div className="space-y-0.5">
              <strong className="text-xl sm:text-2xl font-extrabold text-foreground font-mono block">
                {formattedValue}
              </strong>
              <div className="flex items-center justify-between text-[10px] text-muted-foreground/60 font-mono">
                <span className="truncate">{card.metric_id}</span>
                {isPercent && <span className="text-emerald-600 font-bold">شاخص درصدی</span>}
              </div>
            </div>
          </div>
        )
      })}
    </div>
  )
}
