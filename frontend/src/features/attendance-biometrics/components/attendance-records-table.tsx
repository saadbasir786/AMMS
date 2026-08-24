import React from 'react'
import { Fingerprint, UserRound, Clock, CheckCircle2, ShieldCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { AttendanceRecord } from '../types'

type AttendanceRecordsTableProps = {
  records: AttendanceRecord[]
}

export function AttendanceRecordsTable({ records }: AttendanceRecordsTableProps) {
  if (records.length === 0) {
    return (
      <div
        className="text-center py-10 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <ShieldCheck className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
        <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
          هنوز رکوردی برای حضور ثبت نشده است
        </h4>
        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto leading-relaxed">
          حضور اشتراک‌کنندگان می‌تواند از طریق ترمینال‌های بایومتریک یا ثبت دستی مجاز انجام پذیرد.
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
              <th className="py-3.5 px-4 font-bold">اشتراک‌کننده / کارمند</th>
              <th className="py-3.5 px-4 font-bold">روش احراز هویت</th>
              <th className="py-3.5 px-4 font-bold">نسخه فهرست اعضا</th>
              <th className="py-3.5 px-4 font-bold">زمان رسمی ثبت در سرور</th>
              <th className="py-3.5 px-4 font-bold">توضیحات و دلیل ثبت دستی</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/50">
            {records.map((record) => {
              const isBiometric = record.attendance_method === 'FINGERPRINT'
              const employeeName = record.employee?.full_name || `کارمند #${record.employee_id}`

              return (
                <tr
                  key={record.uuid}
                  className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                >
                  {/* Employee */}
                  <td className="py-3 px-4 whitespace-nowrap">
                    <div className="flex items-center gap-2.5">
                      <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                        {employeeName.charAt(0)}
                      </div>
                      <div className="min-w-0">
                        <strong className="text-xs font-semibold text-foreground block truncate">
                          {employeeName}
                        </strong>
                        <span className="text-[10px] text-muted-foreground block font-mono">
                          شناسه: {record.employee_id}
                          {record.employee?.unit?.name ? ` • ${record.employee.unit.name}` : ''}
                        </span>
                      </div>
                    </div>
                  </td>

                  {/* Method */}
                  <td className="py-3 px-4 whitespace-nowrap">
                    {isBiometric ? (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900">
                        <Fingerprint className="size-3.5" />
                        <span>اثر انگشت (بایومتریک)</span>
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        <UserRound className="size-3.5 text-muted-foreground" />
                        <span>ثبت دستی مجاز</span>
                      </span>
                    )}
                  </td>

                  {/* Version */}
                  <td className="py-3 px-4 whitespace-nowrap font-mono text-[11px]">
                    <Badge variant="outline" className="px-2 py-0.5 font-mono">
                      نسخه {record.participant_version_id || '۱'}
                    </Badge>
                  </td>

                  {/* Timestamp */}
                  <td className="py-3 px-4 whitespace-nowrap text-muted-foreground">
                    {record.recorded_at_server ? (
                      <span className="flex items-center gap-1 font-mono text-[11px] text-foreground">
                        <Clock className="size-3 text-primary shrink-0" />
                        <span>{formatSolarDateTime(record.recorded_at_server)}</span>
                      </span>
                    ) : (
                      '—'
                    )}
                  </td>

                  {/* Reason */}
                  <td className="py-3 px-4 text-muted-foreground max-w-xs truncate">
                    {record.manual_reason ? (
                      <span className="text-xs text-foreground font-medium">
                        {record.manual_reason}
                      </span>
                    ) : (
                      <span className="text-xs text-muted-foreground/60">—</span>
                    )}
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
