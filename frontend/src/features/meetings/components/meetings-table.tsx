import React from 'react'
import { Link } from 'react-router-dom'
import {
  Calendar,
  Clock,
  MapPin,
  UserCheck,
  Eye,
  CalendarX2,
  Building2,
  ChevronRight,
  ChevronLeft,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { MeetingItem, PaginationMeta } from '../types'

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
  pagination?: PaginationMeta | null
  onPageChange?: (page: number) => void
}

export function MeetingsTable({
  meetings,
  loading,
  pagination,
  onPageChange,
}: MeetingsTableProps) {
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
    <div className="rounded-2xl border border-border/70 bg-card shadow-xs overflow-hidden" dir="rtl">
      <div className="overflow-x-auto">
        <table className="w-full text-xs text-right border-collapse">
          <thead>
            <tr className="border-b border-border/70 bg-muted/40 text-muted-foreground font-semibold">
              <th className="py-3 px-4 text-right">شماره جلسه</th>
              <th className="py-3 px-4 text-right">عنوان و واحد برگزارکننده</th>
              <th className="py-3 px-4 text-right">تاریخ و زمان</th>
              <th className="py-3 px-4 text-right">محل برگزاری</th>
              <th className="py-3 px-4 text-right">رئیس جلسه</th>
              <th className="py-3 px-4 text-right">وضعیت</th>
              <th className="py-3 px-4 text-center">عملیات</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/60">
            {meetings.map((m) => {
              const statusCfg = STATUS_CONFIG[m.status] || {
                label: m.status,
                color: 'text-muted-foreground bg-muted',
              }

              return (
                <tr
                  key={m.id}
                  className="hover:bg-muted/30 transition-colors group"
                >
                  {/* Meeting Number */}
                  <td className="py-3.5 px-4 font-mono font-bold text-primary whitespace-nowrap">
                    <Link
                      to={`/meetings/${m.id}`}
                      className="hover:underline flex items-center gap-1.5"
                    >
                      <span>{m.meeting_number}</span>
                    </Link>
                  </td>

                  {/* Title & Unit */}
                  <td className="py-3.5 px-4 max-w-xs">
                    <div className="space-y-0.5">
                      <p className="font-bold text-foreground truncate group-hover:text-primary transition-colors">
                        {m.title}
                      </p>
                      {m.request?.requesting_unit && (
                        <span className="text-[11px] text-muted-foreground flex items-center gap-1">
                          <Building2 className="size-3 shrink-0" />
                          <span>{m.request.requesting_unit.name}</span>
                        </span>
                      )}
                    </div>
                  </td>

                  {/* Date & Time */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="space-y-1">
                      <div className="flex items-center gap-1.5 font-medium text-foreground">
                        <Calendar className="size-3.5 text-primary shrink-0" />
                        <span>{formatSolarDate(m.meeting_date)}</span>
                      </div>
                      <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground font-mono">
                        <Clock className="size-3 shrink-0" />
                        <span>
                          {m.start_time?.slice(0, 5)} - {m.end_time?.slice(0, 5)}
                        </span>
                      </div>
                    </div>
                  </td>

                  {/* Location */}
                  <td className="py-3.5 px-4 max-w-[160px]">
                    <div className="flex items-center gap-1.5 text-muted-foreground truncate">
                      <MapPin className="size-3.5 shrink-0 text-muted-foreground/70" />
                      <span className="truncate">{m.location || '—'}</span>
                    </div>
                  </td>

                  {/* Chairperson */}
                  <td className="py-3.5 px-4 max-w-[140px]">
                    <div className="flex items-center gap-1.5 text-muted-foreground truncate">
                      <UserCheck className="size-3.5 shrink-0 text-muted-foreground/70" />
                      <span className="font-medium text-foreground truncate">
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

      {/* Pagination Footer */}
      {pagination && pagination.total > 0 && pagination.last_page > 1 && (
        <div className="p-3 sm:px-4 border-t border-border/70 bg-muted/20 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
          <div>
            نمایش{' '}
            <strong className="font-semibold text-foreground">
              {pagination.from || 1}
            </strong>{' '}
            تا{' '}
            <strong className="font-semibold text-foreground">
              {pagination.to || meetings.length}
            </strong>{' '}
            از مجموع{' '}
            <strong className="font-semibold text-foreground">
              {pagination.total}
            </strong>{' '}
            جلسه ثبت‌شده
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page <= 1 || loading}
              onClick={() => onPageChange?.(pagination.current_page - 1)}
              className="h-8 px-3 text-xs font-semibold gap-1 rounded-xl"
            >
              <ChevronRight className="size-3.5" />
              <span>صفحه قبل</span>
            </Button>

            <span className="px-2.5 py-1 text-xs font-bold text-foreground">
              صفحه {pagination.current_page} از {pagination.last_page || 1}
            </span>

            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page || loading}
              onClick={() => onPageChange?.(pagination.current_page + 1)}
              className="h-8 px-3 text-xs font-semibold gap-1 rounded-xl"
            >
              <span>صفحه بعد</span>
              <ChevronLeft className="size-3.5" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
