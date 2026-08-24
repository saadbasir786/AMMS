import React from 'react'
import {
  FileText,
  FileSpreadsheet,
  Search,
  Download,
  Table as TableIcon,
  Layers,
  ChevronLeft,
  ChevronRight
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/amms/status-badge'
import { formatSolarDate, formatSolarDateTime } from '@/lib/calendar-utils'

const FIELD_LABELS: Record<string, string> = {
  id: 'شناسه',
  uuid: 'کد یکتا',
  meeting_number: 'شماره جلسه',
  title: 'عنوان',
  subject: 'موضوع',
  status: 'وضعیت',
  meeting_date: 'تاریخ جلسه',
  start_time: 'ساعت آغاز',
  end_time: 'ساعت پایان',
  location: 'محل برگزاری',
  created_at: 'زمان ثبت',
  updated_at: 'زمان به‌روزرسانی',
  request_number: 'شماره درخواست',
  preferred_date: 'تاریخ پیشنهادی',
  organization_id: 'شناسه سازمان',
  employee_id: 'شناسه کارمند',
  employee_name: 'نام کارمند',
  attendance_method: 'روش حضور',
  decision_text: 'متن تصمیم و مصوبه',
  action_item_id: 'شناسه اقدام',
  deadline: 'مهلت اقدام',
  progress_percent: 'درصد پیشرفت',
  case_type: 'نوع رویداد استثنایی',
  requesting_unit: 'ریاست درخواست‌کننده',
  target_unit: 'ریاست مخاطب',
}

const STATUS_FIELDS = ['status', 'meeting_status', 'request_status', 'action_item_status']
const DATE_FIELDS = ['meeting_date', 'preferred_date', 'deadline', 'created_at', 'updated_at', 'recorded_at_server']

type ReportsDataTableProps = {
  reportTitle?: string
  rows: any[]
  meta: any
  loading: boolean
  busy: boolean
  onExportPdf: () => void
  onExportXlsx: () => void
}

export function ReportsDataTable({
  reportTitle,
  rows,
  meta,
  loading,
  busy,
  onExportPdf,
  onExportXlsx,
}: ReportsDataTableProps) {
  if (loading) {
    return (
      <div className="p-12 text-center rounded-2xl border border-border/70 bg-card shadow-xs">
        <div className="inline-block size-6 border-2 border-primary border-t-transparent rounded-full animate-spin mb-3" />
        <p className="text-xs text-muted-foreground">در حال دریافت و پردازش رکوردهای دفتر ثبت رسمی...</p>
      </div>
    )
  }

  if (rows.length === 0) {
    return (
      <div
        className="text-center py-12 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <Search className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
        <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
          رکوردی در این بازه زمانی یافت نشد
        </h4>
        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto leading-relaxed">
          بازه زمانی یا عبارت جستجو را در نوار فیلتر بالا تغییر دهید تا نتایج بازتولید شوند.
        </p>
      </div>
    )
  }

  const columns = Object.keys(rows[0])

  return (
    <Card className="border border-border/70 shadow-xs overflow-hidden" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="space-y-0.5">
          <h3 className="text-xs sm:text-sm font-bold text-foreground flex items-center gap-2">
            <TableIcon className="size-4 text-primary" />
            <span>{reportTitle || 'نتایج رسمی دفتر ثبت و گزارش تفصیلی'}</span>
          </h3>
          <p className="text-[11px] text-muted-foreground">
            تعداد {meta.total ?? rows.length} ردیف رسمی معتبر در محدوده دسترسی سازمانی شما
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={busy}
            onClick={onExportPdf}
            className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 text-rose-700 dark:text-rose-300 border-rose-200"
          >
            <FileText className="size-3.5" />
            <span>صادرات PDF</span>
          </Button>

          <Button
            variant="outline"
            size="sm"
            disabled={busy}
            onClick={onExportXlsx}
            className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 text-emerald-700 dark:text-emerald-300 border-emerald-200"
          >
            <FileSpreadsheet className="size-3.5" />
            <span>صادرات Excel</span>
          </Button>
        </div>
      </CardHeader>

      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-xs text-right border-collapse">
            <thead>
              <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                {columns.map((col) => (
                  <th key={col} className="py-3 px-4 font-bold whitespace-nowrap">
                    {FIELD_LABELS[col] || col}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-border/50">
              {rows.map((row, idx) => (
                <tr
                  key={idx}
                  className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                >
                  {columns.map((col) => {
                    const val = row[col]
                    let content: React.ReactNode = String(val ?? '—')

                    if (STATUS_FIELDS.includes(col) && typeof val === 'string') {
                      content = <StatusBadge status={val} />
                    } else if (DATE_FIELDS.includes(col) && typeof val === 'string' && val.includes('-')) {
                      content = (
                        <span className="font-mono text-[11px]">
                          {val.includes('T') ? formatSolarDateTime(val) : formatSolarDate(val)}
                        </span>
                      )
                    } else if (typeof val === 'object' && val !== null) {
                      content = val.name || val.title || val.full_name || JSON.stringify(val)
                    } else if (typeof val === 'number' && col.includes('percent')) {
                      content = <span className="font-bold font-mono">{val}٪</span>
                    }

                    return (
                      <td key={col} className="py-3 px-4 max-w-xs truncate text-foreground">
                        {content}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  )
}
