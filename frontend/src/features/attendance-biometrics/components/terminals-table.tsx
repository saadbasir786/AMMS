import React from 'react'
import { Link } from 'react-router-dom'
import { Monitor, KeyRound, Eye, Clock, CheckCircle2, AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { Terminal } from '../types'

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  ACTIVE: {
    label: 'فعال',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200',
  },
  INACTIVE: {
    label: 'غیرفعال',
    color: 'text-slate-600 bg-slate-100 border-slate-200',
  },
  MAINTENANCE: {
    label: 'در حال نگهداری',
    color: 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200',
  },
  REVOKED: {
    label: 'لغوشده',
    color: 'text-rose-700 bg-rose-50 border-rose-200',
  },
}

type TerminalsTableProps = {
  terminals: Terminal[]
  busy: boolean
  onIssuePairingCode: (terminal: Terminal) => Promise<void>
}

export function TerminalsTable({
  terminals,
  busy,
  onIssuePairingCode,
}: TerminalsTableProps) {
  if (terminals.length === 0) {
    return (
      <div
        className="text-center py-10 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <Monitor className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
        <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
          ترمینالی ثبت نشده است
        </h4>
        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto leading-relaxed">
          برای ثبت دستگاه‌های فیزیکی بایومتریک، از دکمه ثبت ترمینال جدید استفاده نمایید.
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
              <th className="py-3.5 px-4 font-bold">کد دستگاه</th>
              <th className="py-3.5 px-4 font-bold">نام و مدل ترمینال</th>
              <th className="py-3.5 px-4 font-bold">شماره سریال</th>
              <th className="py-3.5 px-4 font-bold">وضعیت دستگاه</th>
              <th className="py-3.5 px-4 font-bold">آخرین ارتباط با سرور</th>
              <th className="py-3.5 px-4 font-bold">جلسه فعال</th>
              <th className="py-3.5 px-4 font-bold text-center">عملیات</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/50">
            {terminals.map((terminal) => {
              const statusCfg = STATUS_CONFIG[terminal.status] || {
                label: terminal.status,
                color: 'text-slate-600 bg-slate-100 border-slate-200',
              }

              return (
                <tr
                  key={terminal.id}
                  className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                >
                  {/* Code */}
                  <td className="py-3.5 px-4 font-mono font-bold text-foreground">
                    <Badge variant="outline" className="font-mono text-[11px]">
                      {terminal.code}
                    </Badge>
                  </td>

                  {/* Name & Model */}
                  <td className="py-3.5 px-4">
                    <strong className="text-xs font-semibold text-foreground block">
                      {terminal.name}
                    </strong>
                    <span className="text-[10px] text-muted-foreground block">
                      {terminal.manufacturer || 'DERMALOG'} • {terminal.model || 'SVT3000'}
                    </span>
                  </td>

                  {/* Serial Number */}
                  <td className="py-3.5 px-4 font-mono text-muted-foreground">
                    {terminal.serial_number || '—'}
                  </td>

                  {/* Status */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    <span
                      className={cn(
                        'px-2.5 py-1 rounded-lg text-[11px] font-bold border',
                        statusCfg.color
                      )}
                    >
                      {statusCfg.label}
                    </span>
                  </td>

                  {/* Last Seen */}
                  <td className="py-3.5 px-4 whitespace-nowrap text-muted-foreground">
                    {terminal.last_seen_at ? (
                      <span className="flex items-center gap-1 text-[11px]">
                        <Clock className="size-3 text-primary shrink-0" />
                        <span>{formatSolarDateTime(terminal.last_seen_at)}</span>
                      </span>
                    ) : (
                      '—'
                    )}
                  </td>

                  {/* Current Active Session */}
                  <td className="py-3.5 px-4 whitespace-nowrap">
                    {terminal.current_session_id ? (
                      <Badge className="bg-emerald-600 text-white text-[10px]">
                        در حال برگزاری جلسه
                      </Badge>
                    ) : (
                      <span className="text-[11px] text-muted-foreground/60">آزاد</span>
                    )}
                  </td>

                  {/* Actions */}
                  <td className="py-3.5 px-4 whitespace-nowrap text-center">
                    <div className="flex items-center justify-center gap-1.5">
                      <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs font-semibold rounded-xl gap-1"
                      >
                        <Link to={`/admin/terminals/${terminal.id}`}>
                          <Eye className="size-3.5" />
                          <span>جزئیات</span>
                        </Link>
                      </Button>

                      <Button
                        variant="outline"
                        size="sm"
                        disabled={busy}
                        onClick={() => onIssuePairingCode(terminal)}
                        className="h-8 text-xs font-semibold rounded-xl gap-1 text-indigo-700 dark:text-indigo-300 border-indigo-200"
                      >
                        <KeyRound className="size-3.5" />
                          <span>کد اتصال</span>
                      </Button>
                    </div>
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
