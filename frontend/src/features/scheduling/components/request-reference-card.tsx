import React from 'react'
import {
  Calendar,
  Clock,
  MapPin,
  HelpCircle,
  FileText,
  Building2,
  Info
} from 'lucide-react'
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
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              پیشنهاد اولیه (صرفاً جهت مرجع)
            </h3>
            <p className="text-[11px] text-muted-foreground">
              اطلاعات ثبت‌شده در زمان ثبت درخواست اولیه
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3 text-xs">
        {/* Requester Directorate */}
        <div className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
          <span className="text-[10px] text-muted-foreground block">ریاست درخواست‌کننده</span>
          <strong className="font-bold text-foreground block truncate">
            {request.requesting_unit?.name || '—'}
          </strong>
        </div>

        {/* Proposed Date */}
        <div className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
          <span className="text-[10px] text-muted-foreground block">تاریخ پیشنهادی اولیه</span>
          <strong className="font-bold text-foreground block">
            {request.preferred_date
              ? `${formatSolarDate(request.preferred_date)} هـ.ش`
              : 'تعیین نشده'}
          </strong>
        </div>

        {/* Proposed Time & Duration */}
        <div className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
          <span className="text-[10px] text-muted-foreground block">ساعت و مدت پیشنهادی</span>
          <strong className="font-bold text-foreground block font-mono">
            {request.preferred_start_time?.slice(0, 5) || '—'}
            {request.estimated_duration_minutes && (
              <span className="text-muted-foreground font-sans font-normal mr-1">
                ({request.estimated_duration_minutes} دقیقه)
              </span>
            )}
          </strong>
        </div>

        {/* Proposed Location */}
        <div className="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
          <span className="text-[10px] text-muted-foreground block">محل پیشنهادی اولیه</span>
          <strong className="font-bold text-foreground block truncate">
            {request.proposed_location || 'تعیین نشده'}
          </strong>
        </div>

        <p className="text-[10px] text-muted-foreground leading-relaxed pt-1">
          * این مقادیر صرفاً جهت راهنمایی شما نمایش داده شده و برنامه نهایی بر اساس فیلدهای صفحه
          تنظیم می‌گردد.
        </p>
      </CardContent>
    </Card>
  )
}
