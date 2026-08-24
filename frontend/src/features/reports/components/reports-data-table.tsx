import React from 'react'
import { FileText, FileSpreadsheet, Search, Download, Table as TableIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardHeader, CardContent } from '@/components/ui/card'

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
  attendance_method: 'روش حضور',
  decision_text: 'متن تصمیم',
  action_item_id: 'شناسه اقدام',
  deadline: 'مهلت اقدام',
  progress_percent: 'درصد پیشرفت',
  case_type: 'نوع پرونده',
}

type ReportsDataTableProps = {
  rows: any[]
  meta: any
  loading: boolean
  busy: boolean
  onExportPdf: () => void
  onExportXlsx: () => void
}

export function ReportsDataTable({
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
        <p className="text-xs text-muted-foreground">در حال پردازش و دریافت داده‌های ثبتی...</p>
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
          داده‌ای در بازه زمانی انتخابی یافت نشد
        </h4>
        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto leading-relaxed">
          بازه زمانی یا فیلترهای جستجو را تغییر دهید تا نتایج جدید محاسبه گردد.
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
            <span>نتایج رسمی دفتر ثبت و گزارش تفصیلی</span>
          </h3>
          <p className="text-[11px] text-muted-foreground">
            تعداد {meta.total ?? rows.length} ردیف داده در محدوده دسترسی سازمانی شما
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
            <span>خروجی PDF رسمی</span>
          </Button>

          <Button
            variant="outline"
            size="sm"
            disabled={busy}
            onClick={onExportXlsx}
            className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 text-emerald-700 dark:text-emerald-300 border-emerald-200"
          >
            <FileSpreadsheet className="size-3.5" />
            <span>خروجی Excel (XLSX)</span>
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
            <tbody className="divide-y divide-border/50 font-sans">
              {rows.map((row, idx) => (
                <tr key={idx} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                  {columns.map((col) => {
                    const val = row[col]
                    let rendered = String(val ?? '—')

                    if (typeof val === 'object' && val !== null) {
                      rendered = val.name || val.title || val.full_name || JSON.stringify(val)
                    }

                    return (
                      <td key={col} className="py-3 px-4 max-w-xs truncate text-foreground">
                        {rendered}
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
