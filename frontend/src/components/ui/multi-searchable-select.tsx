import React, { useState, useRef, useEffect, useMemo } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Check, ChevronDown, Search, X, Building, ChevronsUpDown, CheckSquare, Square } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export type MultiSelectOption = {
  value: string | number
  label: string
  description?: string
  badge?: string
  icon?: React.ComponentType<{ className?: string }>
}

type MultiSearchableSelectProps = {
  options: MultiSelectOption[]
  value: (string | number)[]
  onChange: (value: number[]) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyMessage?: string
  disabled?: boolean
  className?: string
  triggerClassName?: string
}

export function MultiSearchableSelect({
  options,
  value = [],
  onChange,
  placeholder = 'انتخاب ریاست‌های مقصد...',
  searchPlaceholder = 'جستجوی نام یا کد ریاست...',
  emptyMessage = 'ریاستی یافت نشد.',
  disabled = false,
  className,
  triggerClassName,
}: MultiSearchableSelectProps) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const searchInputRef = useRef<HTMLInputElement>(null)

  const selectedValues = useMemo(() => {
    return new Set(value.map(String))
  }, [value])

  const selectedOptions = useMemo(() => {
    return options.filter((opt) => selectedValues.has(String(opt.value)))
  }, [options, selectedValues])

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

  const handleToggle = (val: string | number) => {
    const numVal = Number(val)
    const current = new Set(value.map(Number))
    if (current.has(numVal)) {
      current.delete(numVal)
    } else {
      current.add(numVal)
    }
    onChange(Array.from(current))
  }

  const handleRemove = (val: string | number, e: React.MouseEvent) => {
    e.stopPropagation()
    const numVal = Number(val)
    onChange(value.map(Number).filter((id) => id !== numVal))
  }

  const handleClearAll = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange([])
  }

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      <div className={cn('relative w-full space-y-2', className)} dir="rtl">
        {/* Trigger Button */}
        <PopoverPrimitive.Trigger asChild disabled={disabled}>
          <button
            type="button"
            className={cn(
              'flex min-h-10 w-full items-center justify-between gap-2 rounded-xl border border-input bg-slate-50/50 dark:bg-slate-900/40 px-3 py-2 text-xs sm:text-sm text-right transition-colors outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 border-slate-200 dark:border-slate-700 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 group',
              triggerClassName
            )}
          >
            <div className="flex items-center gap-2 min-w-0 flex-1">
              <Building className="size-4 text-primary shrink-0" />
              {selectedOptions.length > 0 ? (
                <span className="font-semibold text-foreground truncate">
                  {selectedOptions.length} ریاست مقصد انتخاب شده است
                </span>
              ) : (
                <span className="text-muted-foreground truncate block">
                  {placeholder}
                </span>
              )}
            </div>

            <div className="flex items-center gap-1.5 shrink-0">
              {selectedOptions.length > 0 && !disabled && (
                <span
                  role="button"
                  tabIndex={0}
                  onClick={handleClearAll}
                  className="p-1 text-muted-foreground hover:text-foreground rounded-md transition-colors"
                  title="پاک‌کردن همه"
                >
                  <X className="size-3.5" />
                </span>
              )}
              <ChevronsUpDown className="size-4 text-muted-foreground opacity-70 group-hover:opacity-100 transition-opacity" />
            </div>
          </button>
        </PopoverPrimitive.Trigger>

        {/* Selected Tags / Badges */}
        {selectedOptions.length > 0 && (
          <div className="flex flex-wrap gap-1.5 pt-0.5">
            {selectedOptions.map((opt) => (
              <Badge
                key={String(opt.value)}
                variant="secondary"
                className="text-xs py-1 pe-2 ps-1 gap-1.5 bg-primary/10 border border-primary/20 text-foreground font-medium rounded-lg"
              >
                <span>{opt.label}</span>
                <button
                  type="button"
                  onClick={(e) => handleRemove(opt.value, e)}
                  className="p-0.5 hover:bg-primary/20 rounded text-muted-foreground hover:text-foreground transition-colors"
                  title="حذف"
                >
                  <X className="size-3" />
                </button>
              </Badge>
            ))}
          </div>
        )}

        {/* Dropdown Menu Portal */}
        <PopoverPrimitive.Portal>
          <PopoverPrimitive.Content
            dir="rtl"
            align="end"
            sideOffset={4}
            className="z-50 w-[calc(var(--radix-popover-trigger-width))] min-w-[300px] max-w-[500px] rounded-xl border border-border/80 bg-popover p-2 text-popover-foreground shadow-xl outline-none text-right animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 duration-150"
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

            {/* List with Checkboxes */}
            <div className="max-h-60 overflow-y-auto space-y-0.5 pe-1">
              {filteredOptions.map((opt) => {
                const isSelected = selectedValues.has(String(opt.value))
                const Icon = opt.icon || Building
                return (
                  <button
                    key={String(opt.value)}
                    type="button"
                    onClick={() => handleToggle(opt.value)}
                    className={cn(
                      'flex w-full items-center justify-between gap-2.5 rounded-lg px-2.5 py-2 text-xs sm:text-sm text-right transition-colors select-none cursor-pointer group',
                      isSelected
                        ? 'bg-primary/10 text-primary font-bold'
                        : 'text-foreground hover:bg-slate-100 dark:hover:bg-slate-800'
                    )}
                  >
                    <div className="flex items-center gap-2.5 min-w-0 flex-1">
                      <div
                        className={cn(
                          'size-4 flex items-center justify-center rounded border transition-colors shrink-0',
                          isSelected
                            ? 'bg-primary border-primary text-white'
                            : 'border-muted-foreground/40 group-hover:border-primary/60'
                        )}
                      >
                        {isSelected && <Check className="size-3 stroke-[3]" />}
                      </div>
                      <div className="min-w-0">
                        <span className="block truncate">{opt.label}</span>
                        {opt.description && (
                          <span className="block text-[11px] text-muted-foreground font-normal truncate mt-0.5">
                            {opt.description}
                          </span>
                        )}
                      </div>
                    </div>

                    {opt.badge && (
                      <Badge
                        variant="secondary"
                        className="text-[10px] px-1.5 py-0.5 font-normal shrink-0"
                      >
                        {opt.badge}
                      </Badge>
                    )}
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
