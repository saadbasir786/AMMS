import React from 'react'
import { Link } from 'react-router-dom'
import {
  Calendar,
  Clock,
  MapPin,
  UserCheck,
  Eye,
  CalendarX2,
  Building2
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { MeetingItem } from '../types'

const STATUS_CONFIG: Record<
  string,
  { label: string; color: string; badgeVariant?: 'default' | 'outline' | 'destructive' | 'secondary' }
> = {
  SCHEDULED: {
    label: 'زمان‌بندی‌شده',
    color: 'text-sky-700 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800',
  },
  IN_PROGRESS: {
    label: 'در حال برگزاری',
    color: 'text-indigo-700 bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800',
  },
  HELD: {
    label: 'پایان‌یافته',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800',
  },
  COMPLETED: {
    label: 'تکمیل‌شده',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800',
  },
  POSTPONED: {
    label: 'به تعویق افتاده',
    color: 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800',
  },
  CANCELLED: {
    label: 'لغو شده',
    color: 'text-rose-700 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800',
  },
}

type MeetingsTableProps = {
  meetings: MeetingItem[]
  loading?: boolean
}

export function MeetingsTable({ meetings, loading }: MeetingsTableProps) {
  if (!loading && meetings.length === 0) {
    return (
      <div
        className="text-center py-12 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <CalendarX2 className="size-10 mx-auto text-muted-foreground/40 mb-3" />
        <h4 className="text-sm font-bold text-foreground mb-1">
          جلسه‌ای یافت نشد
        </h4>
        <p className="text-xs text-muted-foreground max-w-sm mx-auto leading-relaxed">
          با فیلترهای انتخابی یا در این بخش هیچ جلسه رسمی ثبت نشده است.
        </p>
      </div>
    )
  }

  return (
    <div
      className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs"
      dir="rtl"
    >
      <div className="overflow-x-auto">
        <table className="w-full text-xs text-right border-collapse">
          <thead>
            <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
              <th className="py-3.5 px-4 font-bold">شماره جلسه</th>
              <th className="py-3.5 px-4 font-bold">عنوان جلسه و ریاست برگزارکننده</th>
              <th className="py-3.5 px-4 font-bold">تاریخ و ساعت برگزاری</th>
              <th className="py-3.5 px-4 font-bold">محل جلسه</th>
              <th className="py-3.5 px-4 font-bold">رئیس جلسه</th>
              <th className="py-3.5 px-4 font-bold">وضعیت</th>
              <th className="py-3.5 px-4 font-bold text-center">عملیات</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/50">
            {meetings.map((m) => {
              const statusCfg = STATUS_CONFIG[m.status] || {
                label: m.status,
                color: 'text-slate-600 bg-slate-100 border-slate-200',
              }

              return (
                <tr
                  key={m.id}
                  className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors group"
                >
                  {/* Meeting Number */}
                  <td className="py-3.5 px-4 shrink-0 font-mono font-bold text-foreground">
                    <Badge variant="outline" className="font-mono text-[11px] px-2 py-0.5">
                      {m.meeting_number}
                    </Badge>
                  </td>

                  {/* Title & Directorate */}
                  <td className="py-3.5 px-4 min-w-[200px]">
                    <Link
                      to={`/meetings/${m.id}`}
                      className="font-bold text-foreground hover:text-primary transition-colors block leading-snug line-clamp-1"
                    >
                      {m.title}
                    </Link>
                    <span className="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                      <Building2 className="size-3 text-muted-foreground/70" />
                      {m.request?.requesting_unit?.name || 'واحد نامشخص'}
                    </span>
                  </td>

                  {/* Date & Time */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="flex items-center gap-1.5 text-foreground font-medium">
                      <Calendar className="size-3.5 text-primary shrink-0" />
                      <span>{formatSolarDate(m.meeting_date)} هـ.ش</span>
                    </div>
                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground mt-0.5 font-mono">
                      <Clock className="size-3 text-muted-foreground/70 shrink-0" />
                      <span>
                        {m.start_time?.slice(0, 5)} تا {m.end_time?.slice(0, 5)}
                      </span>
                    </div>
                  </td>

                  {/* Location */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="flex items-center gap-1.5 text-muted-foreground">
                      <MapPin className="size-3.5 text-muted-foreground/70 shrink-0" />
                      <span className="text-foreground">{m.location || '—'}</span>
                    </div>
                  </td>

                  {/* Chairperson */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="flex items-center gap-1.5">
                      <div className="size-6 rounded-md bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                        {m.chairperson?.full_name?.charAt(0) || 'ر'}
                      </div>
                      <span className="font-medium text-foreground">
                        {m.chairperson?.full_name || '—'}
                      </span>
                    </div>
                  </td>

                  {/* Status */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="flex flex-col gap-1">
                      <span
                        className={cn(
                          'inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold border w-fit',
                          statusCfg.color
                        )}
                      >
                        {statusCfg.label}
                      </span>
                      {m.reschedule_pending && (
                        <span className="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">
                          تغییر زمان در جریان است
                        </span>
                      )}
                    </div>
                  </td>

                  {/* Action */}
                  <td className="py-3.5 px-4 text-center whitespace-nowrap">
                    <Button
                      asChild
                      variant="outline"
                      size="sm"
                      className="h-8 text-xs font-semibold rounded-xl gap-1.5 hover:bg-primary hover:text-white transition-all shadow-xs"
                    >
                      <Link to={`/meetings/${m.id}`}>
                        <Eye className="size-3.5" />
                        <span>مشاهده جلسه</span>
                      </Link>
                    </Button>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </div>
  )
}
