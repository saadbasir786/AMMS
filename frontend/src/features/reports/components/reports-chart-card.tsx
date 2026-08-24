import React from 'react'
import { BarChart3, TrendingUp, PieChart } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import type { ChartSeries } from '../types'

type ReportsChartCardProps = {
  chart: ChartSeries
}

export function ReportsChartCard({ chart }: ReportsChartCardProps) {
  const labels = chart.labels || []
  const series = chart.series || []
  const maxVal = Math.max(...series, 1)

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <span className="text-[10px] font-bold text-muted-foreground block font-mono">
              {chart.metric_id}
            </span>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              {chart.title || chart.metric_id}
            </h3>
          </div>
          <div className="size-7 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <BarChart3 className="size-4" />
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3">
        {labels.length === 0 ? (
          <p className="text-xs text-muted-foreground py-4 text-center">
            داده‌ای برای ترسیم نمودار یافت نشد.
          </p>
        ) : (
          <div className="space-y-2.5">
            {labels.map((label, idx) => {
              const val = series[idx] || 0
              const percentage = Math.round((val / maxVal) * 100)

              return (
                <div key={label} className="space-y-1 text-xs">
                  <div className="flex items-center justify-between text-[11px]">
                    <span className="font-semibold text-foreground truncate max-w-[70%]">
                      {label}
                    </span>
                    <strong className="font-mono text-foreground font-bold">
                      {val}
                    </strong>
                  </div>

                  <div className="h-2.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                    <div
                      className="h-full rounded-full bg-linear-to-l from-primary to-indigo-600 transition-all duration-500"
                      style={{ width: `${Math.max(percentage, 4)}%` }}
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
