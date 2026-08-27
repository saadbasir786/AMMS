import React from 'react'
import { CalendarClock, CheckCircle2, Clock3, XCircle } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDateTime, formatSolarDate } from '@/lib/calendar-utils'
import type { ActionItemExtensionRequest } from '../types'

const STATUS: Record<string, { label: string; icon: typeof Clock3; color: string }> = {
  PENDING: { label: 'در انتظار بررسی', icon: Clock3, color: 'text-amber-700 bg-amber-50' },
  APPROVED: { label: 'تأییدشده', icon: CheckCircle2, color: 'text-emerald-700 bg-emerald-50' },
  REJECTED: { label: 'ردشده', icon: XCircle, color: 'text-rose-700 bg-rose-50' },
  WITHDRAWN: { label: 'مختومه', icon: XCircle, color: 'text-slate-600 bg-slate-100' },
}

export function ActionItemExtensionHistoryCard({ extensions = [] }: { extensions?: ActionItemExtensionRequest[] }) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0"><CalendarClock className="size-4" /></div>
          <div><h3 className="text-xs sm:text-sm font-bold text-foreground">تاریخچه تمدید مهلت</h3><p className="text-[11px] text-muted-foreground">درخواست‌ها و نتیجه بررسی تمدید</p></div>
        </div>
      </CardHeader>
      <CardContent className="p-4 sm:p-5">
        {extensions.length === 0 ? <p className="text-xs text-muted-foreground text-center py-3">درخواستی برای تمدید ثبت نشده است.</p> : (
          <div className="space-y-3">
            {extensions.map((extension) => {
              const status = STATUS[extension.status] || STATUS.PENDING
              const Icon = status.icon
              return <div key={extension.id} className="rounded-xl border border-border/60 bg-slate-50/60 p-3 space-y-2">
                <div className="flex items-center justify-between gap-2">
                  <span className={`inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[10px] font-bold ${status.color}`}><Icon className="size-3" />{status.label}</span>
                  <span className="text-[10px] text-muted-foreground">{extension.requested_at ? formatSolarDateTime(extension.requested_at) : '—'}</span>
                </div>
                <div className="grid grid-cols-2 gap-2 text-[11px]"><span className="text-muted-foreground">مهلت پیشنهادی</span><strong>{extension.requested_due_date ? `${formatSolarDate(extension.requested_due_date)} هـ.ش` : '—'}</strong></div>
                <p className="text-xs text-foreground leading-relaxed">{extension.reason}</p>
                {extension.review_reason && <p className="border-t border-border/50 pt-2 text-[11px] text-muted-foreground">نتیجه بررسی: {extension.review_reason}</p>}
              </div>
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
