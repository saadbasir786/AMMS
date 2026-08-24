import React, { useState, useRef, useEffect, useMemo } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Check, Search, X, Users, ChevronsUpDown, UserCheck } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { EmployeeItem } from '../types'

type ParticipantPickerProps = {
  employees: EmployeeItem[]
  selectedIds: number[]
  onChange: (ids: number[]) => void
  disabled?: boolean
  label?: string
  placeholder?: string
  searchPlaceholder?: string
  emptyMessage?: string
}

export function ParticipantPicker({
  employees,
  selectedIds,
  onChange,
  disabled = false,
  label = 'اشتراک‌کنندگان پیشنهادی ریاست',
  placeholder = 'انتخاب اشتراک‌کنندگان جلسه...',
  searchPlaceholder = 'جستجوی کارمند بر اساس نام، دیپارتمنت یا شماره کارمندی...',
  emptyMessage = 'کارمندی در این واحد یافت نشد.',
}: ParticipantPickerProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const searchInputRef = useRef<HTMLInputElement>(null)

  const selectedSet = useMemo(() => new Set(selectedIds), [selectedIds])

  const selectedEmployees = useMemo(() => {
    return employees.filter((emp) => selectedSet.has(emp.id))
  }, [employees, selectedSet])

  const filteredEmployees = useMemo(() => {
    if (!search.trim()) return employees
    const q = search.toLowerCase().trim()
    return employees.filter(
      (emp) =>
        emp.full_name.toLowerCase().includes(q) ||
        (emp.unit?.name || '').toLowerCase().includes(q) ||
        (emp.position?.title || '').toLowerCase().includes(q) ||
        (emp.employee_number || '').toLowerCase().includes(q)
    )
  }, [employees, search])

  useEffect(() => {
    if (open) {
      setTimeout(() => {
        searchInputRef.current?.focus()
      }, 50)
    } else {
      setSearch('')
    }
  }, [open])

  const toggleEmployee = (id: number) => {
    if (disabled) return
    if (selectedSet.has(id)) {
      onChange(selectedIds.filter((item) => item !== id))
    } else {
      onChange([...selectedIds, id])
    }
  }

  const handleRemove = (id: number, e: React.MouseEvent) => {
    e.stopPropagation()
    onChange(selectedIds.filter((item) => item !== id))
  }

  const selectAll = () => {
    if (disabled) return
    const allFilteredIds = filteredEmployees.map((e) => e.id)
    const combined = Array.from(new Set([...selectedIds, ...allFilteredIds]))
    onChange(combined)
  }

  const clearSelection = (e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (disabled) return
    onChange([])
  }

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      <div className="relative w-full space-y-2.5" dir="rtl">
        {/* Trigger Button */}
        <PopoverPrimitive.Trigger asChild disabled={disabled || employees.length === 0}>
          <button
            type="button"
            className={cn(
              'flex min-h-10 w-full items-center justify-between gap-2 rounded-xl border border-input bg-slate-50/50 dark:bg-slate-900/40 px-3 py-2 text-xs sm:text-sm text-right transition-colors outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 border-slate-200 dark:border-slate-700 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 group',
              employees.length === 0 && 'opacity-60 cursor-not-allowed'
            )}
          >
            <div className="flex items-center gap-2 min-w-0 flex-1">
              <Users className="size-4 text-primary shrink-0" />
              {selectedEmployees.length > 0 ? (
                <span className="font-semibold text-foreground truncate">
                  {selectedEmployees.length} اشتراک‌کننده انتخاب شده است
                </span>
              ) : (
                <span className="text-muted-foreground truncate block">
                  {employees.length === 0 ? 'هیچ کارمندی برای این ریاست یافت نشد' : placeholder}
                </span>
              )}
            </div>

            <div className="flex items-center gap-1.5 shrink-0">
              {selectedEmployees.length > 0 && !disabled && (
                <span
                  role="button"
                  tabIndex={0}
                  onClick={clearSelection}
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

        {/* Selected Participants Chips/Tags */}
        {selectedEmployees.length > 0 && (
          <div className="flex flex-wrap gap-1.5 pt-0.5">
            {selectedEmployees.map((emp) => (
              <Badge
                key={emp.id}
                variant="secondary"
                className="text-xs py-1 pe-2 ps-1 gap-1.5 bg-primary/10 border border-primary/20 text-foreground font-medium rounded-lg"
              >
                <div className="size-4.5 rounded-full bg-primary/20 text-primary text-[10px] flex items-center justify-center font-bold shrink-0">
                  {emp.full_name.trim().slice(0, 1)}
                </div>
                <span className="truncate max-w-44">{emp.full_name}</span>
                {emp.unit?.name && (
                  <span className="text-[10px] text-muted-foreground font-normal">
                    ({emp.unit.name})
                  </span>
                )}
                {!disabled && (
                  <button
                    type="button"
                    onClick={(e) => handleRemove(emp.id, e)}
                    className="p-0.5 hover:bg-primary/20 rounded text-muted-foreground hover:text-foreground transition-colors"
                    title="حذف"
                  >
                    <X className="size-3" />
                  </button>
                )}
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
            className="z-50 w-[calc(var(--radix-popover-trigger-width))] min-w-[320px] max-w-[540px] rounded-xl border border-border/80 bg-popover p-2 text-popover-foreground shadow-xl outline-none text-right animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 duration-150"
          >
            {/* Search Input Box */}
            <div className="relative mb-2">
              <Search className="size-3.5 text-muted-foreground absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                ref={searchInputRef}
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={searchPlaceholder}
                className="h-8.5 ps-8.5 pe-8 text-xs bg-slate-50/70 dark:bg-slate-900/60 border-slate-200 dark:border-slate-700 rounded-lg text-right"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch('')}
                  className="absolute end-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5"
                >
                  <X className="size-3" />
                </button>
              )}
            </div>

            {/* Quick Actions Header */}
            {filteredEmployees.length > 0 && (
              <div className="flex items-center justify-between px-2 py-1 mb-1 text-[11px] text-muted-foreground border-b border-border/40">
                <span>تعداد کارمندان: {filteredEmployees.length}</span>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={selectAll}
                    className="text-primary hover:underline font-medium"
                  >
                    انتخاب همه نتایج
                  </button>
                  <span>•</span>
                  <button
                    type="button"
                    onClick={clearSelection}
                    className="text-muted-foreground hover:text-destructive font-medium"
                  >
                    لغو همه
                  </button>
                </div>
              </div>
            )}

            {/* List with Checkboxes */}
            <div className="max-h-60 overflow-y-auto space-y-0.5 pe-1">
              {filteredEmployees.map((emp) => {
                const isSelected = selectedSet.has(emp.id)
                return (
                  <button
                    key={emp.id}
                    type="button"
                    onClick={() => toggleEmployee(emp.id)}
                    className={cn(
                      'flex w-full items-center justify-between gap-2.5 rounded-lg px-2.5 py-2 text-xs text-right transition-colors select-none cursor-pointer group',
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
                      <div className="size-6 rounded-md bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-[10px] font-bold text-muted-foreground shrink-0">
                        {emp.full_name.trim().slice(0, 1)}
                      </div>
                      <div className="min-w-0">
                        <span className="block truncate">{emp.full_name}</span>
                        {(emp.position?.title || emp.unit?.name) && (
                          <span className="block text-[10px] text-muted-foreground font-normal truncate mt-0.5">
                            {emp.position?.title ? `${emp.position.title} · ` : ''}
                            {emp.unit?.name || ''}
                          </span>
                        )}
                      </div>
                    </div>

                    {emp.employee_number && (
                      <Badge
                        variant="outline"
                        className="text-[10px] px-1.5 py-0 font-normal font-mono shrink-0"
                      >
                        {emp.employee_number}
                      </Badge>
                    )}
                  </button>
                )
              })}

              {filteredEmployees.length === 0 && (
                <div className="py-6 text-center text-xs text-muted-foreground space-y-1">
                  <Users className="size-4 mx-auto text-muted-foreground/60" />
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
