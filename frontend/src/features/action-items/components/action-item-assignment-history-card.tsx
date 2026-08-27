import React from 'react'
import { ArrowLeftRight, History } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import type { ActionItemAssignmentHistory } from '../types'

export function ActionItemAssignmentHistoryCard({ history = [] }: { history?: ActionItemAssignmentHistory[] }) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60"><div className="flex items-center gap-2"><div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0"><History className="size-4" /></div><div><h3 className="text-xs sm:text-sm font-bold text-foreground">تاریخچه تغییر مسئولیت</h3><p className="text-[11px] text-muted-foreground">سوابق انتقال واحد یا کارمند مسئول</p></div></div></CardHeader>
      <CardContent className="p-4 sm:p-5">
        {history.length === 0 ? <p className="text-xs text-muted-foreground text-center py-3">تغییری در مسئولیت ثبت نشده است.</p> : <div className="space-y-3">{history.map((entry) => <div key={entry.id} className="rounded-xl border border-border/60 bg-slate-50/60 p-3 space-y-2"><div className="flex items-center justify-between gap-2"><span className="inline-flex items-center gap-1 text-[10px] font-bold text-primary"><ArrowLeftRight className="size-3" />انتقال مسئولیت</span><span className="text-[10px] text-muted-foreground">{entry.changed_at ? formatSolarDateTime(entry.changed_at) : '—'}</span></div><div className="text-[11px] text-foreground">واحد قبلی: <strong>{entry.from_unit?.name || (entry.from_unit_id ? `شناسه ${entry.from_unit_id}` : '—')}</strong> ← واحد جدید: <strong>{entry.to_unit?.name || (entry.to_unit_id ? `شناسه ${entry.to_unit_id}` : '—')}</strong></div><div className="text-[11px] text-muted-foreground">کارمند جدید: {entry.to_employee?.full_name || (entry.to_employee_id ? `شناسه ${entry.to_employee_id}` : 'تعیین نشده')}</div>{entry.reason && <p className="border-t border-border/50 pt-2 text-[11px] text-muted-foreground">دلیل: {entry.reason}</p>}</div>)}</div>}
      </CardContent>
    </Card>
  )
}
