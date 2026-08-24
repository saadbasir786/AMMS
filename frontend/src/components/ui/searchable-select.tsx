import React, { useState, useRef, useEffect, useMemo } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Check, ChevronDown, Search, X, Building, ChevronsUpDown } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

export type SearchableSelectOption = {
  value: string | number
  label: string
  description?: string
  badge?: string
  icon?: React.ComponentType<{ className?: string }>
}

type SearchableSelectProps = {
  options: SearchableSelectOption[]
  value?: string | number | null
  onChange: (value: string | number) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyMessage?: string
  disabled?: boolean
  clearable?: boolean
  className?: string
  triggerClassName?: string
}

export function SearchableSelect({
  options,
  value,
  onChange,
  placeholder = 'انتخاب کنید...',
  searchPlaceholder = 'جستجو در گزینه‌ها...',
  emptyMessage = 'موردی یافت نشد.',
  disabled = false,
  clearable = true,
  className,
  triggerClassName,
}: SearchableSelectProps) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const searchInputRef = useRef<HTMLInputElement>(null)

  const selectedOption = useMemo(() => {
    return options.find((opt) => String(opt.value) === String(value))
  }, [options, value])

  const filteredOptions = useMemo(() => {
    if (!query.trim()) return options
    const q = query.toLowerCase().trim()
    return options.filter(
      (opt) =>
        opt.label.toLowerCase().includes(q) ||
        (opt.description && opt.description.toLowerCase().includes(q)) ||
        (opt.badge && opt.badge.toLowerCase().includes(q))
    )
  }, [options, query])

  useEffect(() => {
    if (open) {
      setTimeout(() => {
        searchInputRef.current?.focus()
      }, 50)
    } else {
      setQuery('')
    }
  }, [open])

  const handleSelect = (val: string | number) => {
    onChange(val)
    setOpen(false)
  }

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange('')
    setQuery('')
  }

  const SelectedIcon = selectedOption?.icon

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      <div className={cn('relative w-full', className)} dir="rtl">
        <PopoverPrimitive.Trigger asChild disabled={disabled}>
          <button
            type="button"
            className={cn(
              'flex h-10 w-full items-center justify-between gap-2 rounded-xl border border-input bg-slate-50/50 dark:bg-slate-900/40 px-3 py-2 text-xs sm:text-sm text-right transition-colors outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 border-slate-200 dark:border-slate-700 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 group',
              triggerClassName
            )}
          >
            <div className="flex items-center gap-2 min-w-0 flex-1">
              {SelectedIcon && (
                <SelectedIcon className="size-4 text-primary shrink-0" />
              )}
              {selectedOption ? (
                <span className="truncate font-medium text-foreground block">
                  {selectedOption.label}
                </span>
              ) : (
                <span className="text-muted-foreground truncate block">
                  {placeholder}
                </span>
              )}
            </div>

            <div className="flex items-center gap-1 shrink-0">
              {clearable && selectedOption && !disabled && (
                <span
                  role="button"
                  tabIndex={0}
                  onClick={handleClear}
                  onKeyDown={(e) => e.key === 'Enter' && handleClear(e as any)}
                  className="p-1 text-muted-foreground hover:text-foreground rounded-md transition-colors"
                  title="پاک کردن انتخاب"
                >
                  <X className="size-3.5" />
                </span>
              )}
              <ChevronsUpDown className="size-4 text-muted-foreground opacity-70 group-hover:opacity-100 transition-opacity" />
            </div>
          </button>
        </PopoverPrimitive.Trigger>

        <PopoverPrimitive.Portal>
          <PopoverPrimitive.Content
            dir="rtl"
            align="end"
            sideOffset={4}
            className="z-50 w-[calc(var(--radix-popover-trigger-width))] min-w-[280px] max-w-[460px] rounded-xl border border-border/80 bg-popover p-2 text-popover-foreground shadow-xl outline-none text-right animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 duration-150"
          >
            {/* Search Input Box */}
            <div className="relative mb-2">
              <Search className="size-3.5 text-muted-foreground absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                ref={searchInputRef}
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder={searchPlaceholder}
                className="h-8.5 ps-8.5 pe-8 text-xs bg-slate-50/70 dark:bg-slate-900/60 border-slate-200 dark:border-slate-700 rounded-lg text-right"
              />
              {query && (
                <button
                  type="button"
                  onClick={() => setQuery('')}
                  className="absolute end-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5"
                >
                  <X className="size-3" />
                </button>
              )}
            </div>

            {/* Options List */}
            <div className="max-h-60 overflow-y-auto space-y-0.5 pe-1">
              {filteredOptions.map((opt) => {
                const isSelected = String(opt.value) === String(value)
                const Icon = opt.icon
                return (
                  <button
                    key={String(opt.value)}
                    type="button"
                    onClick={() => handleSelect(opt.value)}
                    className={cn(
                      'flex w-full items-center justify-between gap-2.5 rounded-lg px-2.5 py-2 text-xs sm:text-sm text-right transition-colors select-none cursor-pointer group',
                      isSelected
                        ? 'bg-primary/10 text-primary font-bold'
                        : 'text-foreground hover:bg-slate-100 dark:hover:bg-slate-800'
                    )}
                  >
                    <div className="flex items-center gap-2.5 min-w-0 flex-1">
                      {Icon && (
                        <Icon
                          className={cn(
                            'size-4 shrink-0 transition-colors',
                            isSelected
                              ? 'text-primary'
                              : 'text-muted-foreground group-hover:text-foreground'
                          )}
                        />
                      )}
                      <div className="min-w-0">
                        <span className="block truncate">{opt.label}</span>
                        {opt.description && (
                          <span className="block text-[11px] text-muted-foreground font-normal truncate mt-0.5">
                            {opt.description}
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      {opt.badge && (
                        <Badge
                          variant="secondary"
                          className="text-[10px] px-1.5 py-0.5 font-normal"
                        >
                          {opt.badge}
                        </Badge>
                      )}
                      <div
                        className={cn(
                          'size-4 flex items-center justify-center rounded transition-opacity',
                          isSelected
                            ? 'text-primary opacity-100'
                            : 'opacity-0 group-hover:opacity-30'
                        )}
                      >
                        <Check className="size-3.5 stroke-[2.5]" />
                      </div>
                    </div>
                  </button>
                )
              })}

              {filteredOptions.length === 0 && (
                <div className="py-6 text-center text-xs text-muted-foreground space-y-1">
                  <Search className="size-4 mx-auto text-muted-foreground/60" />
                  <p>{emptyMessage}</p>
                </div>
              )}
            </div>
          </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
      </div>
    </PopoverPrimitive.Root>
  )
}
