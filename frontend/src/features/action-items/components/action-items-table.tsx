import React from 'react'
import { Link } from 'react-router-dom'
import {
  Calendar,
  Clock,
  Building2,
  User,
  Eye,
  CheckCircle2,
  AlertTriangle,
  ListTodo
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { ActionItem } from '../types'

const STATUS_CONFIG: Record<
  string,
  { label: string; color: string }
> = {
  OPEN: {
    label: 'باز',
    color: 'text-sky-700 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800',
  },
  IN_PROGRESS: {
    label: 'در حال اجرا',
    color: 'text-indigo-700 bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800',
  },
  BLOCKED: {
    label: 'دارای مانع',
    color: 'text-rose-700 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800',
  },
  PENDING_VERIFICATION: {
    label: 'منتظر تأیید',
    color: 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800',
  },
  COMPLETED: {
    label: 'تکمیل‌شده',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800',
  },
  CANCELLED: {
    label: 'لغوشده',
    color: 'text-slate-600 bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700',
  },
}

type ActionItemsTableProps = {
  items: ActionItem[]
  loading?: boolean
}

export function ActionItemsTable({ items, loading }: ActionItemsTableProps) {
  if (!loading && items.length === 0) {
    return (
      <div
        className="text-center py-12 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <ListTodo className="size-10 mx-auto text-muted-foreground/40 mb-3" />
        <h4 className="text-sm font-bold text-foreground mb-1">
          اقدامی یافت نشد
        </h4>
        <p className="text-xs text-muted-foreground max-w-sm mx-auto leading-relaxed">
          با فیلترهای انتخابی یا در حوزه صلاحیت شما هیچ اقدام یا مصوبه‌ای ثبت نشده است.
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
              <th className="py-3.5 px-4 font-bold">شماره اقدام</th>
              <th className="py-3.5 px-4 font-bold">عنوان اقدام و تصمیم مرجع</th>
              <th className="py-3.5 px-4 font-bold">واحد و کارمند مسئول</th>
              <th className="py-3.5 px-4 font-bold">مهلت انجام</th>
              <th className="py-3.5 px-4 font-bold">درصد پیشرفت</th>
              <th className="py-3.5 px-4 font-bold">وضعیت</th>
              <th className="py-3.5 px-4 font-bold text-center">عملیات</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/50">
            {items.map((item) => {
              const statusCfg = STATUS_CONFIG[item.status] || {
                label: item.status,
                color: 'text-slate-600 bg-slate-100 border-slate-200',
              }

              const assigneeName =
                item.assigned_employee?.full_name ||
                item.assigned_employee?.name ||
                'تعیین نشده'

              return (
                <tr
                  key={item.id}
                  className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors group"
                >
                  {/* Action Number */}
                  <td className="py-3.5 px-4 shrink-0 font-mono font-bold text-foreground">
                    <Badge variant="outline" className="font-mono text-[11px] px-2 py-0.5">
                      {item.action_number}
                    </Badge>
                  </td>

                  {/* Title & Decision */}
                  <td className="py-3.5 px-4 min-w-[200px]">
                    <Link
                      to={`/action-items/${item.id}`}
                      className="font-bold text-foreground hover:text-primary transition-colors block leading-snug line-clamp-1"
                    >
                      {item.title}
                    </Link>
                    {item.decision_number && (
                      <span className="text-[11px] text-muted-foreground block mt-0.5 truncate">
                        مصوبه شماره: {item.decision_number}
                      </span>
                    )}
                  </td>

                  {/* Assignee & Unit */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="flex items-center gap-2">
                      <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                        {item.responsible_unit?.name?.charAt(0) || 'و'}
                      </div>
                      <div className="min-w-0">
                        <strong className="text-xs font-semibold text-foreground block truncate">
                          {item.responsible_unit?.name || '—'}
                        </strong>
                        <span className="text-[10px] text-muted-foreground block truncate">
                          مسئول: {assigneeName}
                        </span>
                      </div>
                    </div>
                  </td>

                  {/* Due Date & Overdue */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <div className="flex items-center gap-1.5 text-foreground font-medium">
                      <Calendar className="size-3.5 text-primary shrink-0" />
                      <span>
                        {item.due_date ? `${formatSolarDate(item.due_date)} هـ.ش` : '—'}
                      </span>
                    </div>
                    {item.is_overdue && (
                      <span className="text-[10px] text-rose-600 dark:text-rose-400 font-bold flex items-center gap-1 mt-0.5">
                        <AlertTriangle className="size-3 shrink-0" />
                        {item.days_overdue ? `${item.days_overdue} روز تأخیر` : 'دارای تأخیر'}
                      </span>
                    )}
                  </td>

                  {/* Progress Bar */}
                  <td className="py-3.5 px-4 min-w-[120px]">
                    <div className="space-y-1">
                      <div className="flex items-center justify-between text-[11px] font-mono">
                        <span className="font-bold text-foreground">
                          {item.progress_percent || 0}٪
                        </span>
                      </div>
                      <div className="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
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
                  </td>

                  {/* Status */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <span
                      className={cn(
                        'inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold border w-fit',
                        statusCfg.color
                      )}
                    >
                      {statusCfg.label}
                    </span>
                  </td>

                  {/* Actions */}
                  <td className="py-3.5 px-4 text-center whitespace-nowrap">
                    <Button
                      asChild
                      variant="outline"
                      size="sm"
                      className="h-8 text-xs font-semibold rounded-xl gap-1.5 hover:bg-primary hover:text-white transition-all shadow-xs"
                    >
                      <Link to={`/action-items/${item.id}`}>
                        <Eye className="size-3.5" />
                        <span>مشاهده اقدام</span>
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
