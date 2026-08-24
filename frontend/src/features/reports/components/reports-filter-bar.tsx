import React from 'react'
import { Search, RefreshCw, Filter, Calendar } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import type { ReportType, ReportTypeOption } from '../types'

export const REPORT_TYPE_OPTIONS: ReportTypeOption[] = [
  { id: 'MEETING_REGISTER', label: 'دفتر ثبت جلسات رسمی', endpoint: '/reporting/meetings' },
  { id: 'MEETING_REQUEST_REGISTER', label: 'دفتر ثبت درخواست‌های جلسه', endpoint: '/reporting/meeting-requests' },
  { id: 'ATTENDANCE_REGISTER', label: 'دفتر ثبت حضور رسمی اعضا', endpoint: '/reporting/attendance' },
  { id: 'DECISION_REGISTER', label: 'دفتر ثبت تصمیم‌ها و مصوبات', endpoint: '/reporting/decisions' },
  { id: 'ACTION_ITEM_REGISTER', label: 'دفتر ثبت اقدامات و مصوبات پیگیری', endpoint: '/reporting/action-items' },
  { id: 'EXCEPTION_LIFECYCLE_REGISTER', label: 'دفتر ثبت رویدادهای استثنایی و تعویق', endpoint: '/reporting/exceptions' },
  { id: 'HIGH_AUTHORITY_MEETING_REGISTER', label: 'دفتر ثبت جلسات مقام عالی', endpoint: '/reporting/high-authority-meetings' },
]

type ReportsFilterBarProps = {
  activeTab: 'dashboard' | 'report' | 'exports'
  range: { from: string; to: string }
  onRangeChange: (range: { from: string; to: string }) => void
  reportType: ReportType
  onReportTypeChange: (type: ReportType) => void
  search: string
  onSearchChange: (val: string) => void
  meetingStatus: string
  onMeetingStatusChange: (val: string) => void
  busy: boolean
  onRefresh: () => void
}

export function ReportsFilterBar({
  activeTab,
  range,
  onRangeChange,
  reportType,
  onReportTypeChange,
  search,
  onSearchChange,
  meetingStatus,
  onMeetingStatusChange,
  busy,
  onRefresh,
}: ReportsFilterBarProps) {
  return (
    <div
      className="p-4 rounded-2xl border border-border/70 bg-card shadow-xs space-y-3"
      dir="rtl"
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
        {/* Date From */}
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            از تاریخ مبدا
          </label>
          <MultiCalendarDatePicker
            value={range.from}
            onChange={(iso) => onRangeChange({ ...range, from: iso })}
            placeholder="انتخاب تاریخ مبدا..."
            className="h-9.5 text-xs rounded-xl"
          />
        </div>

        {/* Date To */}
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            تا تاریخ مقصد
          </label>
          <MultiCalendarDatePicker
            value={range.to}
            onChange={(iso) => onRangeChange({ ...range, to: iso })}
            placeholder="انتخاب تاریخ مقصد..."
            className="h-9.5 text-xs rounded-xl"
          />
        </div>

        {/* Report Type Selector (only on report tab) */}
        {activeTab === 'report' && (
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              نوع دفتر ثبت و گزارش
            </label>
            <select
              value={reportType}
              onChange={(e) => onReportTypeChange(e.target.value as ReportType)}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
            >
              {REPORT_TYPE_OPTIONS.map((opt) => (
                <option key={opt.id} value={opt.id}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Search Input (only on report tab) */}
        {activeTab === 'report' && (
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              جستجو در عنوان یا شماره
            </label>
            <div className="relative">
              <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                placeholder="عنوان، کد، شناسه..."
                className="h-9.5 pr-8 text-xs rounded-xl"
              />
            </div>
          </div>
        )}

        {/* Status Selector (only on report tab) */}
        {activeTab === 'report' && (
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              وضعیت جلسه
            </label>
            <select
              value={meetingStatus}
              onChange={(e) => onMeetingStatusChange(e.target.value)}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
            >
              <option value="">همه وضعیت‌ها</option>
              <option value="SCHEDULED">زمان‌بندی‌شده</option>
              <option value="COMPLETED">تکمیل‌شده</option>
              <option value="CANCELLED">لغوشده</option>
              <option value="POSTPONED">به‌تعویق‌افتاده</option>
            </select>
          </div>
        )}
      </div>

      {/* Action Strip */}
      <div className="flex items-center justify-between pt-2 border-t border-border/50">
        <div className="text-[11px] text-muted-foreground">
          محاسبه آمار و شاخص‌ها بر مبنای تقویم هجری خورشیدی و بازه زمانی تعیین‌شده
        </div>

        <Button
          size="sm"
          disabled={busy}
          onClick={onRefresh}
          className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 bg-primary hover:bg-primary/90 text-white shadow-xs"
        >
          <RefreshCw className={`size-3.5 ${busy ? 'animate-spin' : ''}`} />
          <span>به‌روزرسانی داده‌ها</span>
        </Button>
      </div>
    </div>
  )
}
