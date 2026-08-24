import React from 'react'
import { Download, FileText, FileSpreadsheet, RefreshCw, Clock, CheckCircle2, AlertCircle } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import type { ExportJob } from '../types'

type ExportsListCardProps = {
  exports: ExportJob[]
  busy: boolean
  onRefresh: () => void
}

export function ExportsListCard({
  exports,
  busy,
  onRefresh,
}: ExportsListCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60 flex flex-row items-center justify-between">
        <div className="space-y-0.5">
          <h3 className="text-xs sm:text-sm font-bold text-foreground">
            فهرست فایل‌های خروجی و صادرات سازمانی
          </h3>
          <p className="text-[11px] text-muted-foreground">
            فایل‌های تولیدشده امن و منحصراً برای حساب کاربری شما قابل دریافت است
          </p>
        </div>

        <Button
          variant="outline"
          size="sm"
          disabled={busy}
          onClick={onRefresh}
          className="h-8 text-xs font-semibold rounded-xl gap-1.5"
        >
          <RefreshCw className={`size-3.5 ${busy ? 'animate-spin' : ''}`} />
          <span>تازه‌سازی فهرست</span>
        </Button>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3">
        {exports.length === 0 ? (
          <div className="text-center py-8 text-xs text-muted-foreground">
            هنوز فایلی برای صادرات تولید نشده است. برای تولید فایل به بخش «مرکز گزارش‌ها» مراجعه نمایید.
          </div>
        ) : (
          <div className="space-y-2.5">
            {exports.map((item) => {
              const isPdf = item.format === 'PDF'

              return (
                <div
                  key={item.uuid}
                  className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                >
                  <div className="flex items-center gap-3">
                    <div
                      className={`size-9 rounded-xl flex items-center justify-center shrink-0 ${
                        isPdf
                          ? 'bg-rose-500/10 text-rose-600'
                          : 'bg-emerald-500/10 text-emerald-600'
                      }`}
                    >
                      {isPdf ? <FileText className="size-4" /> : <FileSpreadsheet className="size-4" />}
                    </div>

                    <div className="space-y-0.5">
                      <strong className="text-xs font-bold text-foreground block">
                        {item.report_type}
                      </strong>
                      <span className="text-[10px] text-muted-foreground block font-mono">
                        درخواست‌شده در: {item.requested_at ? formatSolarDateTime(item.requested_at) : '—'}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <Badge variant="outline" className="text-[10px] font-mono">
                      {item.format}
                    </Badge>

                    <Badge
                      variant={item.status === 'COMPLETED' ? 'default' : 'secondary'}
                      className="text-[10px]"
                    >
                      {item.status === 'COMPLETED' ? 'آماده دریافت' : item.status}
                    </Badge>

                    {item.status === 'COMPLETED' && (
                      <Button
                        asChild
                        size="sm"
                        className="h-8 text-xs font-semibold rounded-xl gap-1.5 bg-primary hover:bg-primary/90 text-white shadow-xs"
                      >
                        <a href={`/api/reporting/exports/${item.uuid}/download`} download>
                          <Download className="size-3.5" />
                          <span>دانلود فایل</span>
                        </a>
                      </Button>
                    )}
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
