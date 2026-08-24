import React from 'react'
import {
  FileText,
  Eye,
  Calendar,
  Building,
  CalendarDays,
  ChevronRight,
  ChevronLeft
} from 'lucide-react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { cn, formatPersianDate } from '@/lib/utils'
import type { RequestRowItem, PaginationMeta } from '../types'

type RequestsTableProps = {
  rows: RequestRowItem[]
  mode?: 'all' | 'incoming'
  statusLabels: Record<string, string>
  priorityLabels?: Record<string, string>
  purposeLabels?: Record<string, string>
  emptyText?: string
  pagination?: PaginationMeta | null
  onPageChange?: (page: number) => void
}

function getPriorityBadge(value?: string | null, labels?: Record<string, string>) {
  const norm = String(value || '').toUpperCase()
  const label = labels?.[norm] || (norm === 'URGENT' ? 'عاجل' : norm === 'HIGH' ? 'بلند' : 'عادی')

  if (norm === 'URGENT') {
    return (
      <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
        <span className="size-1.5 rounded-full bg-rose-500 animate-pulse shrink-0" />
        {label}
      </span>
    )
  }
  if (norm === 'HIGH') {
    return (
      <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
        <span className="size-1.5 rounded-full bg-amber-500 shrink-0" />
        {label}
      </span>
    )
  }
  return (
    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 dark:bg-slate-800/70 dark:text-slate-300 border border-slate-200 dark:border-slate-700/60">
      <span className="size-1.5 rounded-full bg-slate-400 shrink-0" />
      {label}
    </span>
  )
}

function getStatusBadge(status?: string | null, statusLabels?: Record<string, string>) {
  const s = String(status || '').toUpperCase()
  const label = (statusLabels && statusLabels[s]) || s || 'نامشخص'

  if (/REJECTED|CANCELLED/.test(s)) {
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-900/50">
        <span className="size-1.5 rounded-full bg-rose-500 shrink-0" />
        {label}
      </span>
    )
  }
  if (/RETURNED|ACTION_REQUIRED|CLARIFICATION_REQUIRED/.test(s)) {
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50">
        <span className="size-1.5 rounded-full bg-amber-500 shrink-0" />
        {label}
      </span>
    )
  }
  if (/APPROVED|ACKNOWLEDGED|ISSUED|CONFIRMED|COMPLETED|HELD|SCHEDULE_CONFIRMED|CONVERTED_TO_MEETING/.test(s)) {
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/50">
        <span className="size-1.5 rounded-full bg-emerald-500 shrink-0" />
        {label}
      </span>
    )
  }
  if (/SCHEDULING/.test(s)) {
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-teal-50 text-teal-800 dark:bg-teal-950/40 dark:text-teal-300 border border-teal-200 dark:border-teal-900/50">
        <span className="size-1.5 rounded-full bg-teal-500 shrink-0" />
        {label}
      </span>
    )
  }
  return (
    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300 border border-sky-200 dark:border-sky-900/50">
      <span className="size-1.5 rounded-full bg-sky-500 animate-pulse shrink-0" />
      {label}
    </span>
  )
}

function resolveUnitDisplay(row: RequestRowItem, mode: 'all' | 'incoming') {
  if (mode === 'incoming') {
    const requester = row.units?.find((u) => u.role === 'REQUESTER')?.unit?.name
    return requester || row.requesting_unit?.name || '—'
  }
  return row.requesting_unit?.name || '—'
}

export function RequestsTable({
  rows,
  mode = 'all',
  statusLabels,
  priorityLabels,
  purposeLabels,
  emptyText = 'درخواستی با این مشخصات یافت نشد.',
  pagination,
  onPageChange,
}: RequestsTableProps) {
  return (
    <div className="rounded-2xl border border-border/70 bg-card shadow-xs overflow-hidden space-y-0">
      <div className="overflow-x-auto">
        <Table className="min-w-[860px]">
          <TableHeader className="bg-slate-50/90 dark:bg-slate-900/60 border-b border-border/60">
            <TableRow className="hover:bg-transparent">
              <TableHead className="ps-4 w-32 text-xs font-bold text-muted-foreground">شماره درخواست</TableHead>
              <TableHead className="min-w-60 text-xs font-bold text-muted-foreground">موضوع جلسه</TableHead>
              <TableHead className="w-44 text-xs font-bold text-muted-foreground">
                {mode === 'incoming' ? 'ریاست درخواست‌کننده' : 'ریاست مربوطه'}
              </TableHead>
              <TableHead className="w-24 text-xs font-bold text-muted-foreground">اولویت</TableHead>
              <TableHead className="w-44 text-xs font-bold text-muted-foreground">وضعیت گردش کار</TableHead>
              <TableHead className="w-36 text-xs font-bold text-muted-foreground">تاریخ ثبت</TableHead>
              <TableHead className="pe-4 text-center w-24 text-xs font-bold text-muted-foreground">عملیات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => {
              const canSchedule = ['SCHEDULING', 'SCHEDULE_CONFIRMED', 'BILATERAL_APPROVED'].includes(
                row.status || ''
              )
              const purposeText = row.purpose ? purposeLabels?.[row.purpose] || row.purpose : null

              return (
                <TableRow
                  key={row.id}
                  className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors group"
                >
                  {/* Request Number */}
                  <TableCell className="ps-4 py-3">
                    <span className="font-mono text-[11px] font-semibold px-2 py-0.5 rounded bg-muted/60 text-muted-foreground border border-border/40 inline-block">
                      {row.request_number || 'پیش‌نویس'}
                    </span>
                  </TableCell>

                  {/* Subject & Purpose */}
                  <TableCell className="py-3">
                    <a
                      href={`/meeting-requests/${row.id}`}
                      className="text-xs sm:text-sm font-bold text-foreground group-hover:text-primary transition-colors leading-snug line-clamp-1"
                    >
                      {row.subject || 'بدون موضوع'}
                    </a>
                    {purposeText && (
                      <span className="text-[11px] text-muted-foreground block mt-0.5">
                        هدف: {purposeText}
                      </span>
                    )}
                  </TableCell>

                  {/* Unit & Creator */}
                  <TableCell className="py-3 text-xs font-medium">
                    <div className="flex items-center gap-2 truncate max-w-48">
                      <div className="size-6 rounded-md bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                        {resolveUnitDisplay(row, mode).charAt(0)}
                      </div>
                      <div className="min-w-0">
                        <span className="text-foreground font-semibold block truncate">
                          {resolveUnitDisplay(row, mode)}
                        </span>
                        {row.creator?.name && (
                          <span className="text-[10px] text-muted-foreground block truncate">
                            ثبت: {row.creator.name}
                          </span>
                        )}
                      </div>
                    </div>
                  </TableCell>

                  {/* Priority */}
                  <TableCell className="py-3">
                    {getPriorityBadge(row.priority, priorityLabels)}
                  </TableCell>

                  {/* Status */}
                  <TableCell className="py-3">
                    {getStatusBadge(row.status, statusLabels)}
                  </TableCell>

                  {/* Date */}
                  <TableCell className="py-3 text-xs text-muted-foreground font-medium whitespace-nowrap">
                    {row.created_at ? (
                      <span className="flex items-center gap-1.5">
                        <Calendar className="size-3 text-muted-foreground" />
                        {formatPersianDate(row.created_at)}
                      </span>
                    ) : (
                      '—'
                    )}
                  </TableCell>

                  {/* Actions */}
                  <TableCell className="pe-4 py-3 text-center">
                    <div className="flex items-center justify-center gap-1">
                      <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="h-7.5 px-2 text-xs gap-1 hover:bg-primary/10 hover:text-primary rounded-lg"
                        aria-label="مشاهده درخواست"
                      >
                        <a href={`/meeting-requests/${row.id}`}>
                          <Eye className="size-3.5" />
                          <span className="hidden xl:inline">مشاهده</span>
                        </a>
                      </Button>
                      {canSchedule && (
                        <Button
                          asChild
                          size="sm"
                          className="h-7.5 px-2 text-xs gap-1 text-white !text-white rounded-lg shadow-xs"
                          title="فضای زمان‌بندی"
                        >
                          <a href={`/meeting-requests/${row.id}/scheduling`}>
                            <CalendarDays className="size-3.5 text-white" />
                            <span className="hidden xl:inline">زمان‌بندی</span>
                          </a>
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      {/* Empty State */}
      {rows.length === 0 && (
        <div className="flex flex-col items-center justify-center p-12 text-center space-y-3">
          <div className="size-12 rounded-2xl bg-muted/60 text-muted-foreground flex items-center justify-center">
            <FileText className="size-6" />
          </div>
          <div className="space-y-1">
            <strong className="text-sm font-bold text-foreground block">
              درخواستی یافت نشد
            </strong>
            <p className="text-xs text-muted-foreground max-w-sm">
              {emptyText}
            </p>
          </div>
        </div>
      )}

      {/* Pagination Footer */}
      {pagination && pagination.total > 0 && (
        <div className="p-3 sm:px-4 border-t border-border/60 bg-slate-50/50 dark:bg-slate-900/30 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
          <div>
            نمایش{' '}
            <strong className="font-semibold text-foreground">
              {pagination.from || 1}
            </strong>{' '}
            تا{' '}
            <strong className="font-semibold text-foreground">
              {pagination.to || rows.length}
            </strong>{' '}
            از مجموع{' '}
            <strong className="font-semibold text-foreground">
              {pagination.total}
            </strong>{' '}
            درخواست ثبت‌شده
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page <= 1}
              onClick={() => onPageChange?.(pagination.current_page - 1)}
              className="h-7.5 px-2.5 text-xs gap-1 rounded-lg"
            >
              <ChevronRight className="size-3.5" />
              <span>صفحه قبل</span>
            </Button>

            <span className="px-2 py-1 text-xs font-semibold text-foreground">
              صفحه {pagination.current_page} از {pagination.last_page || 1}
            </span>

            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => onPageChange?.(pagination.current_page + 1)}
              className="h-7.5 px-2.5 text-xs gap-1 rounded-lg"
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
