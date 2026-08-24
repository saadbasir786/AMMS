import React from 'react'
import { Search, Filter, RotateCcw } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { MeetingFilters } from '../types'

const STATUS_OPTIONS = [
  { value: 'ALL', label: 'همه جلسات' },
  { value: 'SCHEDULED', label: 'زمان‌بندی‌شده' },
  { value: 'IN_PROGRESS', label: 'در حال برگزاری' },
  { value: 'HELD', label: 'پایان‌یافته' },
  { value: 'COMPLETED', label: 'تکمیل‌شده' },
  { value: 'POSTPONED', label: 'به تعویق افتاده' },
  { value: 'CANCELLED', label: 'لغو شده' },
]

type MeetingsFilterBarProps = {
  filters: MeetingFilters
  onChange: (filters: MeetingFilters) => void
  onReset: () => void
}

export function MeetingsFilterBar({
  filters,
  onChange,
  onReset,
}: MeetingsFilterBarProps) {
  const hasActiveFilters = filters.search || (filters.status && filters.status !== 'ALL')

  return (
    <div className="space-y-3" dir="rtl">
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        {/* Search */}
        <div className="relative flex-1">
          <Search className="size-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
          <Input
            value={filters.search}
            onChange={(e) => onChange({ ...filters, search: e.target.value })}
            placeholder="جستجوی جلسه بر اساس عنوان، شماره جلسه یا محل برگزاری..."
            className="pr-10 h-9.5 text-xs bg-card border-border/70 rounded-xl"
          />
        </div>

        {/* Reset */}
        {hasActiveFilters && (
          <Button
            variant="ghost"
            size="sm"
            onClick={onReset}
            className="h-9.5 text-xs text-muted-foreground hover:text-foreground gap-1.5 rounded-xl shrink-0"
          >
            <RotateCcw className="size-3.5" />
            <span>پاک‌کردن فیلترها</span>
          </Button>
        )}
      </div>

      {/* Status Chips */}
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
        <span className="text-[11px] font-semibold text-muted-foreground shrink-0 ml-1.5 flex items-center gap-1">
          <Filter className="size-3" />
          وضعیت:
        </span>
        {STATUS_OPTIONS.map((opt) => {
          const isActive =
            filters.status === opt.value || (!filters.status && opt.value === 'ALL')
          return (
            <button
              key={opt.value}
              type="button"
              onClick={() => onChange({ ...filters, status: opt.value })}
              className={cn(
                'px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all border shrink-0',
                isActive
                  ? 'bg-primary text-white border-primary shadow-xs'
                  : 'bg-card text-muted-foreground border-border/70 hover:border-border hover:text-foreground'
              )}
            >
              {opt.label}
            </button>
          )
        })}
      </div>
    </div>
  )
}
