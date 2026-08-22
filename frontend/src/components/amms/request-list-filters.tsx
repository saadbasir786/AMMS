import { Filter, Search } from 'lucide-react'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export type FilterOption = {
  value: string
  label: string
}

type RequestListFiltersProps = {
  searchValue?: string
  searchPlaceholder?: string
  onSearchChange?: (value: string) => void
  onSearchSubmit?: () => void
  statusValue: string
  statusOptions: FilterOption[]
  onStatusChange: (value: string) => void
  resultCount?: number
}

const ALL = '__all__'

export function RequestListFilters({
  searchValue,
  searchPlaceholder = 'جستجو...',
  onSearchChange,
  onSearchSubmit,
  statusValue,
  statusOptions,
  onStatusChange,
  resultCount,
}: RequestListFiltersProps) {
  const hasSearch = searchValue !== undefined && Boolean(onSearchChange)

  return (
    <div className="mb-5 rounded-2xl border border-border/80 bg-card p-3 shadow-sm sm:p-4">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex min-w-0 flex-1 flex-col gap-3 md:flex-row md:items-center">
          {hasSearch ? (
            <div className="flex min-w-0 flex-1 items-center gap-2">
              <div className="relative min-w-0 flex-1">
                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={searchValue}
                  onChange={(event) => onSearchChange?.(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') onSearchSubmit?.()
                  }}
                  placeholder={searchPlaceholder}
                  className="h-10 ps-9"
                  aria-label="جستجو در درخواست‌ها"
                />
              </div>
              <Button type="button" variant="secondary" className="h-10 gap-2" onClick={onSearchSubmit}>
                <Search className="size-4" />
                <span className="hidden sm:inline">جستجو</span>
              </Button>
            </div>
          ) : null}

          <div className="flex items-center gap-2">
            <div className="hidden size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground md:flex">
              <Filter className="size-4" />
            </div>
            <Select
              value={statusValue || ALL}
              onValueChange={(value) => onStatusChange(value === ALL ? '' : value)}
            >
              <SelectTrigger className="h-10 min-w-52 bg-background">
                <SelectValue placeholder="همه وضعیت‌ها" />
              </SelectTrigger>
              <SelectContent position="popper" align="end">
                <SelectItem value={ALL}>همه وضعیت‌ها</SelectItem>
                {statusOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        {typeof resultCount === 'number' ? (
          <div className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
            <span>نتیجه</span>
            <Badge variant="secondary" className="min-w-8 justify-center tabular-nums">
              {resultCount.toLocaleString('fa-AF')}
            </Badge>
          </div>
        ) : null}
      </div>
    </div>
  )
}
