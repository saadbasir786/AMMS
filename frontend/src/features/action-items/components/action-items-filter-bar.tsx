import React from 'react'
import { Search, Filter, RotateCcw, AlertTriangle, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { ActionItemFilters } from '../types'

const STATUS_OPTIONS = [
  { value: '', label: 'همه اقدامات' },
  { value: 'OPEN', label: 'باز' },
  { value: 'IN_PROGRESS', label: 'در حال اجرا' },
  { value: 'BLOCKED', label: 'دارای مانع' },
  { value: 'PENDING_VERIFICATION', label: 'منتظر تأیید' },
  { value: 'COMPLETED', label: 'تکمیل‌شده' },
  { value: 'CANCELLED', label: 'لغوشده' },
]

type ActionItemsFilterBarProps = {
  filters: ActionItemFilters
  onChange: (filters: ActionItemFilters) => void
  onReset: () => void
}

export function ActionItemsFilterBar({
  filters,
  onChange,
  onReset,
}: ActionItemsFilterBarProps) {
  const hasActiveFilters = !!filters.search || !!filters.status || filters.overdueOnly

  return (
    <div className="space-y-3" dir="rtl">
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        {/* Search */}
        <div className="relative flex-1">
          <Search className="size-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
          <Input
            value={filters.search}
            onChange={(e) => onChange({ ...filters, search: e.target.value })}
            placeholder="جستجوی اقدام بر اساس شماره اقدام، عنوان، تصمیم یا نام مسئول..."
            className="pr-10 h-9.5 text-xs bg-card border-border/70 rounded-xl"
          />
          {filters.search && (
            <button
              type="button"
              onClick={() => onChange({ ...filters, search: '' })}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1"
            >
              <X className="size-3.5" />
            </button>
          )}
        </div>

        {/* Overdue Toggle */}
        <button
          type="button"
          onClick={() => onChange({ ...filters, overdueOnly: !filters.overdueOnly })}
          className={cn(
            'h-9.5 px-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all border shrink-0',
            filters.overdueOnly
              ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-900 shadow-xs'
              : 'bg-card text-muted-foreground border-border/70 hover:border-border hover:text-foreground'
          )}
        >
          <AlertTriangle className={cn('size-3.5', filters.overdueOnly ? 'text-rose-600' : 'text-muted-foreground')} />
          <span>فقط موارد دارای تأخیر</span>
        </button>

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
            filters.status === opt.value || (!filters.status && opt.value === '')
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
