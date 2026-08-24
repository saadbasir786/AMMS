import React from 'react'
import { Search, Filter, RotateCcw, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { SearchableSelect } from '@/components/ui/searchable-select'

export type StatusOption = {
  value: string
  label: string
}

type RequestsFilterBarProps = {
  searchValue: string
  onSearchChange: (value: string) => void
  onSearchSubmit?: () => void
  statusValue: string
  statusOptions: StatusOption[]
  onStatusChange: (status: string) => void
  totalCount: number
  mode?: 'all' | 'incoming'
}

const PRIMARY_TABS_ALL = [
  { value: '', label: 'همه درخواست‌ها' },
  { value: 'PENDING_INTERNAL_APPROVAL', label: 'منتظر تأیید' },
  { value: 'APPROVED', label: 'تأیید شده' },
  { value: 'SCHEDULING', label: 'در حال زمان‌بندی' },
  { value: 'RETURNED_FOR_REVISION', label: 'برگشت جهت اصلاح' },
  { value: 'DRAFT', label: 'پیش‌نویس' },
  { value: 'REJECTED', label: 'رد شده' },
]

const PRIMARY_TABS_INCOMING = [
  { value: '', label: 'همه وارده‌ها' },
  { value: 'SUBMITTED_TO_TARGET', label: 'در حال بررسی' },
  { value: 'TARGET_APPROVED', label: 'تأییدشده توسط این واحد' },
  { value: 'RETURNED_BY_TARGET', label: 'برگشت‌داده‌شده' },
]

export function RequestsFilterBar({
  searchValue,
  onSearchChange,
  onSearchSubmit,
  statusValue,
  statusOptions,
  onStatusChange,
  totalCount,
  mode = 'all',
}: RequestsFilterBarProps) {
  const quickTabs = mode === 'incoming' ? PRIMARY_TABS_INCOMING : PRIMARY_TABS_ALL
  const hasActiveFilters = !!searchValue || (!!statusValue && statusValue !== '')

  const handleReset = () => {
    onSearchChange('')
    onStatusChange('')
    onSearchSubmit?.()
  }

  return (
    <div className="space-y-3" dir="rtl">
      {/* Search and Secondary Dropdown */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        {/* Search */}
        <div className="relative flex-1">
          <Search className="size-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" />
          <Input
            value={searchValue}
            onChange={(e) => onSearchChange(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') onSearchSubmit?.()
            }}
            placeholder="جستجو بر اساس موضوع، شماره درخواست یا ریاست..."
            className="pr-10 h-9.5 text-xs bg-card border-border/70 rounded-xl"
          />
          {searchValue && (
            <button
              type="button"
              onClick={() => onSearchChange('')}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1"
            >
              <X className="size-3.5" />
            </button>
          )}
        </div>

        {/* Detailed Status Dropdown for exact workflow state */}
        {statusOptions.length > 0 && (
          <div className="w-full sm:w-48 shrink-0">
            <SearchableSelect
              options={[
                { value: '', label: 'همه وضعیت‌ها' },
                ...statusOptions.map((opt) => ({
                  value: opt.value,
                  label: opt.label,
                })),
              ]}
              value={statusValue || ''}
              onChange={(val) => onStatusChange(String(val))}
              placeholder="سایر وضعیت‌ها..."
              searchPlaceholder="جستجوی وضعیت..."
              emptyMessage="وضعیتی یافت نشد."
            />
          </div>
        )}

        {/* Reset Filters */}
        {hasActiveFilters && (
          <Button
            variant="ghost"
            size="sm"
            onClick={handleReset}
            className="h-9.5 text-xs text-muted-foreground hover:text-foreground gap-1.5 rounded-xl shrink-0"
          >
            <RotateCcw className="size-3.5" />
            <span>پاک‌کردن فیلترها</span>
          </Button>
        )}
      </div>

      {/* Horizontal Status Chips (Matching meetings feature standard) */}
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
        <span className="text-[11px] font-semibold text-muted-foreground shrink-0 ml-1.5 flex items-center gap-1">
          <Filter className="size-3" />
          وضعیت:
        </span>
        {quickTabs.map((tab) => {
          const isActive =
            statusValue === tab.value || (!statusValue && tab.value === '')
          return (
            <button
              key={tab.value}
              type="button"
              onClick={() => onStatusChange(tab.value)}
              className={cn(
                'px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all border shrink-0',
                isActive
                  ? 'bg-primary text-white border-primary shadow-xs'
                  : 'bg-card text-muted-foreground border-border/70 hover:border-border hover:text-foreground'
              )}
            >
              {tab.label}
            </button>
          )
        })}
      </div>
    </div>
  )
}
