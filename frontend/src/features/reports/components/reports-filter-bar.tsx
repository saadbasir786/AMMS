import React from 'react'
import { Search, RefreshCw, Calendar, Sparkles, Filter, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import type { ReportType, ReportTypeConfig } from '../types'

export const REPORT_CONFIGS: ReportTypeConfig[] = [
  {
    id: 'MEETING_REGISTER',
    title: 'دفتر ثبت جلسات رسمی',
    subtitle: 'سوابق جلسات برگزارشده، لغوشده و معوق با جزئیات زمان و مکان',
    endpoint: '/reporting/meetings',
    icon: 'CalendarDays',
    category: 'meetings',
  },
  {
    id: 'MEETING_REQUEST_REGISTER',
    title: 'دفتر ثبت درخواست‌های جلسه',
    subtitle: 'رهگیری کلیه درخواست‌های ارسالی، وارده و تأییدشده',
    endpoint: '/reporting/meeting-requests',
    icon: 'FileText',
    category: 'requests',
  },
  {
    id: 'ATTENDANCE_REGISTER',
    title: 'دفتر ثبت حضور و غیاب رسمی',
    subtitle: 'آمار حضور اشتراک‌کنندگان، تفکیک بایومتریک و ثبت دستی',
    endpoint: '/reporting/attendance',
    icon: 'CalendarCheck',
    category: 'attendance',
  },
  {
    id: 'DECISION_REGISTER',
    title: 'دفتر ثبت تصمیم‌ها و مصوبات',
    subtitle: 'مجموعه مصوبات رسمی جلسات به همراه طبقه‌بندی و فوریت',
    endpoint: '/reporting/decisions',
    icon: 'CheckCircle2',
    category: 'followup',
  },
  {
    id: 'ACTION_ITEM_REGISTER',
    title: 'دفتر ثبت اقدامات و پیگیری‌ها',
    subtitle: 'وضعیت اجرای مصوبات، مسئولین پیگیری و درصد پیشرفت',
    endpoint: '/reporting/action-items',
    icon: 'FileCheck2',
    category: 'followup',
  },
  {
    id: 'EXCEPTION_LIFECYCLE_REGISTER',
    title: 'دفتر ثبت رویدادهای استثنایی',
    subtitle: 'پرونده‌های تعویق، لغو و تغییرات اضطراری جلسات',
    endpoint: '/reporting/exceptions',
    icon: 'AlertTriangle',
    category: 'governance',
  },
  {
    id: 'HIGH_AUTHORITY_MEETING_REGISTER',
    title: 'دفتر ثبت جلسات مقام عالی',
    subtitle: 'جلسات با حضور یا ریاست مستقیم دفاتر مقام عالی وزارت',
    endpoint: '/reporting/high-authority-meetings',
    icon: 'Crown',
    category: 'governance',
  },
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
  onReset?: () => void
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
  onReset,
}: ReportsFilterBarProps) {
  const setPreset = (daysBack: number) => {
    const now = new Date()
    const from = new Date(now)
    from.setDate(from.getDate() - daysBack)
    onRangeChange({
      from: from.toISOString().slice(0, 10),
      to: now.toISOString().slice(0, 10),
    })
  }

  const setYearPreset = () => {
    const now = new Date()
    const from = new Date(now.getFullYear(), 0, 1)
    onRangeChange({
      from: from.toISOString().slice(0, 10),
      to: now.toISOString().slice(0, 10),
    })
  }

  return (
    <div
      className="p-4 sm:p-5 rounded-2xl border border-border/70 bg-card shadow-xs space-y-4"
      dir="rtl"
    >
      {/* Top Filter Controls */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 text-xs">
        {/* Date From */}
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground block">
            از تاریخ مبدا (هجری خورشیدی)
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
            تا تاریخ مقصد (هجری خورشیدی)
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
          <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
            <label className="text-xs font-bold text-foreground block">
              انتخاب دفتر ثبت رسمی
            </label>
            <select
              value={reportType}
              onChange={(e) => onReportTypeChange(e.target.value as ReportType)}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3 font-semibold text-foreground"
            >
              {REPORT_CONFIGS.map((cfg) => (
                <option key={cfg.id} value={cfg.id}>
                  {cfg.title}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Search Input (only on report tab) */}
        {activeTab === 'report' && (
          <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
            <label className="text-xs font-bold text-foreground block">
              جستجوی موضوع یا شماره سند
            </label>
            <div className="relative">
              <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                placeholder="جستجو در عنوان، کد یا توضیحات..."
                className="h-9.5 pr-8 text-xs rounded-xl"
              />
            </div>
          </div>
        )}

        {/* Meeting Status Selector (only on report tab) */}
        {activeTab === 'report' && (
          <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
            <label className="text-xs font-bold text-foreground block">
              فیلتر وضعیت
            </label>
            <select
              value={meetingStatus}
              onChange={(e) => onMeetingStatusChange(e.target.value)}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3 text-foreground"
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

      {/* Quick Chips & Actions Strip */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-border/50 text-xs">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="text-[11px] text-muted-foreground ml-1">بازه سریع:</span>
          <button
            type="button"
            onClick={() => setPreset(30)}
            className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-foreground text-[11px] font-semibold transition-colors"
          >
            ۳۰ روز اخیر
          </button>
          <button
            type="button"
            onClick={() => setPreset(90)}
            className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-foreground text-[11px] font-semibold transition-colors"
          >
            ۳ ماه اخیر
          </button>
          <button
            type="button"
            onClick={() => setPreset(180)}
            className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-foreground text-[11px] font-semibold transition-colors"
          >
            ۶ ماه اخیر
          </button>
          <button
            type="button"
            onClick={setYearPreset}
            className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-foreground text-[11px] font-semibold transition-colors"
          >
            سال جاری
          </button>
        </div>

        <div className="flex items-center gap-2">
          {search && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => onSearchChange('')}
              className="h-8 text-xs text-muted-foreground hover:text-foreground gap-1"
            >
              <X className="size-3" />
              <span>پاک کردن جستجو</span>
            </Button>
          )}

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
    </div>
  )
}
