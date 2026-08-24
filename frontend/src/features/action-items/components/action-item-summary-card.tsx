import React from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarDays,
  Clock,
  Building2,
  User,
  FileCheck2,
  AlertTriangle,
  Hash,
  ArrowUpRight
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate, getTripleDateSummary } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { ActionItem } from '../types'

type ActionItemSummaryCardProps = {
  item: ActionItem
}

export function ActionItemSummaryCard({ item }: ActionItemSummaryCardProps) {
  const tripleDate = getTripleDateSummary(item.due_date)
  const assigneeName =
    item.assigned_employee?.full_name ||
    item.assigned_employee?.name ||
    'تعیین نشده'

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="text-[11px] font-semibold text-muted-foreground">
                مشخصات اقدام اجرایی
              </span>
              <Badge variant="outline" className="font-mono text-[10px] px-2 py-0.5">
                <Hash className="size-2.5 ml-1" />
                {item.action_number}
              </Badge>
            </div>
            <h2 className="text-base sm:text-lg font-bold text-foreground leading-snug">
              {item.title}
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
          {/* Meeting Reference */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <FileCheck2 className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">جلسه مرجع</span>
              {item.meeting_id ? (
                <Link
                  to={`/meetings/${item.meeting_id}`}
                  className="font-bold text-primary hover:underline flex items-center gap-1 truncate"
                >
                  <span>{item.meeting_number || `جلسه #${item.meeting_id}`}</span>
                  <ArrowUpRight className="size-3" />
                </Link>
              ) : (
                <strong className="font-bold text-foreground block">
                  {item.meeting_number || '—'}
                </strong>
              )}
            </div>
          </div>

          {/* Responsible Unit */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <Building2 className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">واحد سازمانی مسئول</span>
              <strong className="font-bold text-foreground block truncate">
                {item.responsible_unit?.name || '—'}
              </strong>
            </div>
          </div>

          {/* Assigned Employee */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <User className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">کارمند مسئول</span>
              <strong className="font-bold text-foreground block truncate">
                {assigneeName}
              </strong>
            </div>
          </div>

          {/* Due Date */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5">
            <CalendarDays className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <span className="text-[10px] text-muted-foreground block">مهلت انجام</span>
              <strong className={cn('font-bold block', item.is_overdue ? 'text-rose-600' : 'text-foreground')}>
                {item.due_date ? `${formatSolarDate(item.due_date)} هـ.ش` : 'تعیین نشده'}
              </strong>
              {item.is_overdue && (
                <span className="text-[10px] text-rose-600 font-semibold block mt-0.5">
                  {item.days_overdue ? `${item.days_overdue} روز تأخیر` : 'مهلت سپری شده'}
                </span>
              )}
            </div>
          </div>

          {/* Progress Percent */}
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 flex items-start gap-2.5 sm:col-span-2">
            <Clock className="size-4 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between">
                <span className="text-[10px] text-muted-foreground">میزان پیشرفت اجرایی</span>
                <span className="text-xs font-bold font-mono text-foreground">
                  {item.progress_percent || 0}٪
                </span>
              </div>
              <div className="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden mt-1.5">
                <div
                  className={cn(
                    'h-full rounded-full transition-all',
                    item.progress_percent === 100
                      ? 'bg-emerald-500'
                      : item.status === 'BLOCKED'
                      ? 'bg-rose-500'
                      : 'bg-primary'
                  )}
                  style={{ width: `${Math.min(100, item.progress_percent || 0)}%` }}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Decision Section */}
        {item.decision_text && (
          <div className="p-3.5 rounded-xl bg-emerald-50/40 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40 space-y-1.5">
            <span className="text-[11px] font-bold text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
              <FileCheck2 className="size-3.5 text-emerald-600" />
              متن مصوبه رسمی جلسه {item.decision_number ? `(${item.decision_number})` : ''}:
            </span>
            <p className="text-xs text-foreground leading-relaxed whitespace-pre-wrap">
              {item.decision_text}
            </p>
          </div>
        )}

        {/* Description Section */}
        {item.description && (
          <div className="p-3.5 rounded-xl bg-slate-50/60 dark:bg-slate-900/30 border border-border/50 space-y-1.5">
            <span className="text-[11px] font-bold text-foreground">شرح تفصیلی اقدام:</span>
            <p className="text-xs text-muted-foreground leading-relaxed whitespace-pre-wrap">
              {item.description}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
