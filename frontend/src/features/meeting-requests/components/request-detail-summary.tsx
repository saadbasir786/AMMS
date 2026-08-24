import React from 'react'
import {
  FileText,
  Building2,
  Calendar,
  Clock,
  MapPin,
  Tag,
  AlertCircle,
  Hash,
  UserCheck
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate, getTripleDateSummary } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'

type RequestDetailSummaryProps = {
  request: any
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
}

export function RequestDetailSummary({
  request,
  purposeLabels,
  priorityLabels,
}: RequestDetailSummaryProps) {
  const priority = request.priority || 'NORMAL'
  const tripleDate = getTripleDateSummary(request.preferred_date)

  const priorityColor =
    priority === 'URGENT'
      ? 'text-rose-600 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-900'
      : priority === 'HIGH'
      ? 'text-amber-600 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900'
      : 'text-slate-600 bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700'

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="text-[11px] font-semibold text-muted-foreground">
                مشخصات درخواست جلسه
              </span>
              {request.request_number && (
                <Badge variant="outline" className="font-mono text-[10px] px-2 py-0.5">
                  <Hash className="size-2.5 ml-1" />
                  {request.request_number}
                </Badge>
              )}
            </div>
            <h2 className="text-base sm:text-lg font-bold text-foreground leading-snug">
              {request.subject}
            </h2>
          </div>

          <div className="flex flex-wrap items-center gap-2 shrink-0">
            {tripleDate && (
              <div className="flex items-center gap-1.5 p-1.5 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-border/60 text-[10px] font-medium text-muted-foreground shrink-0">
                <span>{tripleDate.solar}</span>
                <span>·</span>
                <span>{tripleDate.lunar}</span>
              </div>
            )}

            <span
              className={cn(
                'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border',
                priorityColor
              )}
            >
              اولویت: {priorityLabels[priority] || priority}
            </span>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Info Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs">
          {/* Requesting Unit */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Building2 className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">ریاست درخواست‌کننده</span>
              <strong className="font-bold text-foreground block truncate">
                {request.requesting_unit?.name || '—'}
              </strong>
            </div>
          </div>

          {/* Purpose */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Tag className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">هدف از برگزاری</span>
              <strong className="font-bold text-foreground block truncate">
                {purposeLabels[request.purpose] || request.purpose || '—'}
              </strong>
            </div>
          </div>

          {/* Creator */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <UserCheck className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">ثبت‌کننده درخواست</span>
              <strong className="font-bold text-foreground block truncate">
                {request.creator?.name || '—'}
              </strong>
            </div>
          </div>

          {/* Preferred Date & Time */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Calendar className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">تاریخ و ساعت پیشنهادی</span>
              <strong className="font-bold text-foreground block">
                {request.preferred_date ? (
                  <>
                    {formatSolarDate(request.preferred_date)} هـ.ش
                    {request.preferred_start_time && (
                      <span className="text-muted-foreground mr-1.5 font-mono">
                        (ساعت {request.preferred_start_time.slice(0, 5)})
                      </span>
                    )}
                  </>
                ) : (
                  'تعیین نشده'
                )}
              </strong>
            </div>
          </div>

          {/* Duration */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Clock className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">مدت تخمینی جلسه</span>
              <strong className="font-bold text-foreground block">
                {request.estimated_duration_minutes
                  ? `${request.estimated_duration_minutes} دقیقه`
                  : 'تعیین نشده'}
              </strong>
            </div>
          </div>

          {/* Proposed Location */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <MapPin className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">محل پیشنهادی برگزاری</span>
              <strong className="font-bold text-foreground block truncate">
                {request.proposed_location || 'تعیین نشده'}
              </strong>
            </div>
          </div>
        </div>

        {/* Description Block */}
        {request.description && (
          <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-border/60 space-y-1.5">
            <span className="text-[11px] font-bold text-foreground flex items-center gap-1.5">
              <FileText className="size-3.5 text-primary" />
              شرح موضوع و جزئیات مباحث:
            </span>
            <p className="text-xs text-muted-foreground leading-relaxed whitespace-pre-wrap">
              {request.description}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
