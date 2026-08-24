import React from 'react'
import {
  Clock3,
  CheckCircle2,
  AlertOctagon,
  RotateCcw,
  Play,
  FileText,
  Sparkles,
  Ban
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { ActionItemUpdate } from '../types'

const UPDATE_LABELS: Record<string, { label: string; icon: any; color: string }> = {
  STARTED: {
    label: 'شروع اجرای اقدام',
    icon: Play,
    color: 'text-sky-600 bg-sky-50 dark:bg-sky-950/40',
  },
  PROGRESS: {
    label: 'ثبت درصد پیشرفت',
    icon: Sparkles,
    color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40',
  },
  BLOCKED_UPDATE: {
    label: 'اعلام مانع و توقف',
    icon: AlertOctagon,
    color: 'text-rose-600 bg-rose-50 dark:bg-rose-950/40',
  },
  UNBLOCKED: {
    label: 'رفع مانع و ادامه کار',
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
  COMPLETION_SUBMITTED: {
    label: 'ارسال برای تأیید تکمیل',
    icon: CheckCircle2,
    color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40',
  },
  COMPLETION_VERIFIED: {
    label: 'تأیید و خاتمه نهایی اقدام',
    icon: CheckCircle2,
    color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40',
  },
  COMPLETION_RETURNED: {
    label: 'برگشت جهت اصلاح گزارش',
    icon: RotateCcw,
    color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40',
  },
  CANCELLED: {
    label: 'لغو رسمی اقدام',
    icon: Ban,
    color: 'text-slate-600 bg-slate-100 dark:bg-slate-800',
  },
}

type ActionItemTimelineCardProps = {
  updates?: ActionItemUpdate[]
}

export function ActionItemTimelineCard({
  updates = [],
}: ActionItemTimelineCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Clock3 className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              تاریخچه و گزارش اقدامات
            </h3>
            <p className="text-[11px] text-muted-foreground">
              سوابق تغییر وضعیت، ثبت پیشرفت‌ها و گزارش‌ها
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5">
        {updates.length === 0 ? (
          <p className="text-xs text-muted-foreground text-center py-3">
            سابقه‌ای ثبت نشده است.
          </p>
        ) : (
          <div className="relative pr-4 space-y-4 before:absolute before:right-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-border/60">
            {updates.map((u) => {
              const uInfo = UPDATE_LABELS[u.update_type] || {
                label: u.update_type,
                icon: FileText,
                color: 'text-slate-600 bg-slate-100',
              }
              const Icon = uInfo.icon
              const formattedDate = u.created_at
                ? formatSolarDateTime(u.created_at)
                : '—'

              return (
                <div key={u.id} className="relative group">
                  <div
                    className={cn(
                      'absolute -right-[23px] top-1 size-4 rounded-full border-2 border-background flex items-center justify-center shadow-xs',
                      uInfo.color
                    )}
                  >
                    <Icon className="size-2.5" />
                  </div>

                  <div className="space-y-1">
                    <div className="flex items-center justify-between gap-2">
                      <strong className="text-xs font-bold text-foreground">
                        {uInfo.label}
                      </strong>
                      {u.progress_percent !== null && u.progress_percent !== undefined && (
                        <span className="text-[10px] font-mono font-bold text-primary bg-primary/10 px-1.5 py-0.5 rounded">
                          {u.progress_percent}٪ پیشرفت
                        </span>
                      )}
                    </div>

                    <div className="text-[10px] text-muted-foreground">
                      {u.actor?.name || 'کارمند'} • {formattedDate}
                    </div>

                    {u.note && (
                      <div className="mt-1.5 p-2 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-border/60 text-[11px] text-foreground leading-relaxed">
                        {u.note}
                      </div>
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
