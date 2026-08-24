import React from 'react'
import {
  FilePlus2,
  Clock3,
  AlertCircle,
  CalendarDays
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'

export type DashboardKpiData = {
  my_requests?: number
  pending_approval?: number
  returned?: number
  upcoming_meetings?: number
}

type DashboardKpisProps = {
  data: DashboardKpiData
}

type KpiItem = {
  id: string
  label: string
  value: number
  icon: React.ElementType
  colorClasses: {
    bg: string
    iconBg: string
    text: string
    border: string
  }
  description: string
}

export function DashboardKpis({ data }: DashboardKpisProps) {
  const kpis: KpiItem[] = [
    {
      id: 'my_requests',
      label: 'درخواست‌های من',
      value: data.my_requests || 0,
      icon: FilePlus2,
      colorClasses: {
        bg: 'bg-sky-500/5 hover:bg-sky-500/10',
        iconBg: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        text: 'text-sky-950 dark:text-sky-100',
        border: 'border-sky-200/60 dark:border-sky-800/40',
      },
      description: 'مجموع درخواست‌های ثبت‌شده شما',
    },
    {
      id: 'pending_approval',
      label: 'منتظر بررسی و تأیید',
      value: data.pending_approval || 0,
      icon: Clock3,
      colorClasses: {
        bg: 'bg-amber-500/5 hover:bg-amber-500/10',
        iconBg: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        text: 'text-amber-950 dark:text-amber-100',
        border: 'border-amber-200/60 dark:border-amber-800/40',
      },
      description: 'درخواست‌های در انتظار بررسی ریاست',
    },
    {
      id: 'returned',
      label: 'نیازمند اقدام / برگشتی',
      value: data.returned || 0,
      icon: AlertCircle,
      colorClasses: {
        bg: 'bg-rose-500/5 hover:bg-rose-500/10',
        iconBg: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
        text: 'text-rose-950 dark:text-rose-100',
        border: 'border-rose-200/60 dark:border-rose-800/40',
      },
      description: 'درخواست‌های دارای ملاحظه یا رد شده',
    },
    {
      id: 'upcoming_meetings',
      label: 'جلسات آینده',
      value: data.upcoming_meetings || 0,
      icon: CalendarDays,
      colorClasses: {
        bg: 'bg-emerald-500/5 hover:bg-emerald-500/10',
        iconBg: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        text: 'text-emerald-950 dark:text-emerald-100',
        border: 'border-emerald-200/60 dark:border-emerald-800/40',
      },
      description: 'جلسات رسمی زمان‌بندی‌شده پیش‌رو',
    },
  ]

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {kpis.map((item) => {
        const Icon = item.icon
        return (
          <Card
            key={item.id}
            className={cn(
              'border transition-all duration-200 shadow-xs hover:shadow-md cursor-default',
              item.colorClasses.bg,
              item.colorClasses.border
            )}
          >
            <CardContent className="p-4 sm:p-5 flex items-start justify-between gap-3">
              <div className="space-y-1">
                <span className="text-xs font-semibold text-muted-foreground block">
                  {item.label}
                </span>
                <div className={cn('text-2xl sm:text-3xl font-black font-mono tracking-tight', item.colorClasses.text)}>
                  {item.value.toLocaleString('fa-AF')}
                </div>
                <span className="text-[11px] text-muted-foreground block leading-tight pt-1">
                  {item.description}
                </span>
              </div>
              <div
                className={cn(
                  'size-10 rounded-2xl flex items-center justify-center shrink-0 shadow-xs',
                  item.colorClasses.iconBg
                )}
              >
                <Icon className="size-5" />
              </div>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}
