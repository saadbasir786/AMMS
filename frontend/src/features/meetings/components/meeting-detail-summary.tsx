import React from 'react'
import {
  CalendarDays,
  Clock,
  MapPin,
  Building2,
  UserCheck,
  FileText,
  BookmarkCheck,
  Hash
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate, getTripleDateSummary } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { MeetingItem } from '../types'

type MeetingDetailSummaryProps = {
  meeting: MeetingItem
}

export function MeetingDetailSummary({ meeting }: MeetingDetailSummaryProps) {
  const tripleDate = getTripleDateSummary(meeting.meeting_date)

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="text-[11px] font-semibold text-muted-foreground">
                مشخصات جلسه رسمی
              </span>
              <Badge variant="outline" className="font-mono text-[10px] px-2 py-0.5">
                <Hash className="size-2.5 ml-1" />
                {meeting.meeting_number}
              </Badge>
            </div>
            <h2 className="text-base sm:text-lg font-bold text-foreground leading-snug">
              {meeting.title}
            </h2>
          </div>

          {tripleDate && (
            <div className="flex items-center gap-1.5 p-1.5 px-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-border/60 text-[10px] font-medium text-muted-foreground shrink-0">
              <span>{tripleDate.solar}</span>
              <span>·</span>
              <span>{tripleDate.lunar}</span>
            </div>
          )}
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Info Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs">
          {/* Directorate */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Building2 className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">ریاست برگزارکننده</span>
              <strong className="font-bold text-foreground block truncate">
                {meeting.request?.requesting_unit?.name || '—'}
              </strong>
            </div>
          </div>

          {/* Date */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <CalendarDays className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">تاریخ رسمی جلسه</span>
              <strong className="font-bold text-foreground block">
                {meeting.meeting_date ? `${formatSolarDate(meeting.meeting_date)} هـ.ش` : '—'}
              </strong>
            </div>
          </div>

          {/* Time */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Clock className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">ساعت برگزاری</span>
              <strong className="font-bold text-foreground block font-mono">
                {meeting.start_time?.slice(0, 5)} تا {meeting.end_time?.slice(0, 5)}
              </strong>
            </div>
          </div>

          {/* Location */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <MapPin className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">محل برگزاری</span>
              <strong className="font-bold text-foreground block truncate">
                {meeting.location || '—'}
              </strong>
            </div>
          </div>

          {/* Chairperson */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5 sm:col-span-2">
            <UserCheck className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">رئیس / مدیر جلسه</span>
              <strong className="font-bold text-foreground block">
                {meeting.chairperson?.full_name || '—'}
                {meeting.chairperson?.unit?.name && (
                  <span className="text-muted-foreground font-normal mr-1.5">
                    ({meeting.chairperson.unit.name})
                  </span>
                )}
              </strong>
            </div>
          </div>
        </div>

        {/* Agenda Section */}
        {meeting.agenda && (
          <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-border/60 space-y-1.5">
            <span className="text-[11px] font-bold text-foreground flex items-center gap-1.5">
              <BookmarkCheck className="size-3.5 text-primary" />
              آجندا و دستور کار رسمی جلسه:
            </span>
            <p className="text-xs text-muted-foreground leading-relaxed whitespace-pre-wrap">
              {meeting.agenda}
            </p>
          </div>
        )}

        {/* Description Section */}
        {meeting.description && (
          <div className="p-3.5 rounded-xl bg-slate-50/50 dark:bg-slate-900/30 border border-border/50 space-y-1.5">
            <span className="text-[11px] font-bold text-foreground flex items-center gap-1.5">
              <FileText className="size-3.5 text-primary" />
              توضیحات و پس‌زمینه موضوع:
            </span>
            <p className="text-xs text-muted-foreground leading-relaxed whitespace-pre-wrap">
              {meeting.description}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
