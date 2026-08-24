import React from 'react'
import { BarChart3, TrendingUp, Activity } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { ChartSeries } from '../types'

const CHART_TITLES: Record<string, { title: string; subtitle: string }> = {
  MEETINGS_BY_STATUS: {
    title: 'توزیع جلسات بر حسب وضعیت رسمی',
    subtitle: 'نسبت جلسات برگزارشده، زمان‌بندی‌شده، لغوشده و معوق',
  },
  MEETINGS_BY_DIRECTORATE: {
    title: 'فراوانی جلسات بر حسب ریاست‌ها',
    subtitle: 'حجم فعالیت و جلسات برگزارشده در سطح واحدهای سازمانی',
  },
  DECISIONS_BY_CATEGORY: {
    title: 'دسته‌بندی تصمیمات و مصوبات',
    subtitle: 'تفکیک موضوعی مصوبات ثبت‌شده جلسات',
  },
  ACTION_ITEMS_STATUS: {
    title: 'وضعیت پیشرفت اقدامات و پیگیری‌ها',
    subtitle: 'سهم اقدامات تکمیل‌شده، در دست اقدام و دارای تاخیر',
  },
}

type ReportsChartCardProps = {
  chart: ChartSeries
}

export function ReportsChartCard({ chart }: ReportsChartCardProps) {
  const labels = chart.labels || []
  const series = chart.series || []
  const maxVal = Math.max(...series, 1)
  const totalSum = series.reduce((acc, curr) => acc + (Number(curr) || 0), 0)

  const meta = CHART_TITLES[chart.metric_id] || {
    title: chart.title || chart.metric_id,
    subtitle: 'تحلیل آماری و نموداری توزیع داده‌ها',
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between gap-3">
          <div className="space-y-0.5">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              {meta.title}
            </h3>
            <p className="text-[11px] text-muted-foreground">
              {meta.subtitle}
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-[10px] font-mono">
              مجموع: {totalSum}
            </Badge>
            <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <BarChart3 className="size-4" />
            </div>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3.5">
        {labels.length === 0 ? (
          <div className="text-xs text-muted-foreground py-6 text-center">
            داده‌ای در این بازه زمانی برای ترسیم نمودار یافت نشد.
          </div>
        ) : (
          <div className="space-y-3">
            {labels.map((label, idx) => {
              const val = series[idx] || 0
              const percentage = Math.round((val / maxVal) * 100)
              const sharePercent = totalSum > 0 ? Math.round((val / totalSum) * 100) : 0

              return (
                <div key={label} className="space-y-1.5 text-xs">
                  <div className="flex items-center justify-between text-[11px]">
                    <span className="font-semibold text-foreground truncate max-w-[65%]">
                      {label}
                    </span>
                    <div className="flex items-center gap-2 font-mono">
                      <span className="text-[10px] text-muted-foreground">({sharePercent}٪)</span>
                      <strong className="text-foreground font-bold">{val}</strong>
                    </div>
                  </div>

                  <div className="h-2.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                    <div
                      className="h-full rounded-full bg-linear-to-l from-primary via-indigo-500 to-sky-500 transition-all duration-500"
                      style={{ width: `${Math.max(percentage, 3)}%` }}
                    />
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
