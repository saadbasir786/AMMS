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
  Activity
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { MetricCard } from '../types'

const METRIC_ICON_MAP: Record<string, any> = {
  MEETINGS_TOTAL: CalendarDays,
  MEETINGS_COMPLETED: CheckCircle2,
  ATTENDANCE_RATE: CalendarCheck,
  ACTION_ITEMS_COMPLETED: FileCheck2,
  POSTPONED_MEETINGS: AlertTriangle,
  DECISIONS_TOTAL: Activity,
}

type ReportsKpiGridProps = {
  cards: MetricCard[]
}

export function ReportsKpiGrid({ cards }: ReportsKpiGridProps) {
  if (!cards || cards.length === 0) return null

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3.5" dir="rtl">
      {cards.map((card) => {
        const Icon = METRIC_ICON_MAP[card.metric_id] || TrendingUp
        const isPercent = card.unit === '%'
        const formattedValue =
          card.value === null || card.value === undefined
            ? 'بدون داده'
            : `${card.value}${isPercent ? '٪' : ''}`

        return (
          <div
            key={card.metric_id}
            className="p-4 rounded-2xl bg-card border border-border/70 shadow-xs flex flex-col justify-between space-y-2 hover:border-primary/40 transition-colors"
          >
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold text-muted-foreground truncate max-w-[80%]">
                {card.label}
              </span>
              <div className="size-7 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <Icon className="size-3.5" />
              </div>
            </div>

            <div className="space-y-0.5">
              <strong className="text-lg sm:text-xl font-extrabold text-foreground font-mono block">
                {formattedValue}
              </strong>
              <span className="text-[10px] text-muted-foreground/60 font-mono block truncate">
                {card.metric_id}
              </span>
            </div>
          </div>
        )
      })}
    </div>
  )
}
