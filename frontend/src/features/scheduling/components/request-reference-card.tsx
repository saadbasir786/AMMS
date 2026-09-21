import React from 'react'
import { Info } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDate } from '@/lib/calendar-utils'

type RequestReferenceCardProps = {
  request: any
}

export function RequestReferenceCard({ request }: RequestReferenceCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-sky-500/10 text-sky-600 flex items-center justify-center shrink-0">
            <Info className="size-4" />
          </div>
          <div className="min-w-0">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">خلاصه درخواست</h3>
            <p className="text-[11px] text-muted-foreground truncate">
              {request.request_number || 'پیش‌نویس'}
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3 text-xs">
        <div>
          <span className="text-[10px] text-muted-foreground block mb-1">موضوع جلسه</span>
          <strong className="font-bold text-foreground leading-5 block">
            {request.subject || 'بدون موضوع'}
          </strong>
        </div>

        <div className="grid grid-cols-2 gap-2">
          <div className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block mb-0.5">ریاست درخواست‌کننده</span>
            <strong className="font-bold text-foreground block truncate">
              {request.requesting_unit?.name || '—'}
            </strong>
          </div>
          <div className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block mb-0.5">پیشنهاد اولیه</span>
            <strong className="font-bold text-foreground block truncate">
              {request.preferred_date ? formatSolarDate(request.preferred_date) : 'تعیین نشده'}
            </strong>
          </div>
        </div>

        <p className="text-[10px] text-muted-foreground leading-relaxed pt-1">
          جزئیات نهایی را در همین فضای زمان‌بندی تنظیم و تأیید کنید.
        </p>
      </CardContent>
    </Card>
  )
}
