import { useEffect, useMemo, useRef, useState, type MouseEvent } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Check, ChevronsUpDown, Search, Users, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import type { EmployeeItem } from '../types'

type ParticipantPickerProps = {
  employees: EmployeeItem[]
  selectedIds: number[]
  onChange: (ids: number[]) => void
  disabled?: boolean
  placeholder?: string
  searchPlaceholder?: string
  emptyMessage?: string
}

export function ParticipantPicker({
  employees,
  selectedIds,
  onChange,
  disabled = false,
  placeholder = 'انتخاب اشتراک‌کنندگان جلسه...',
  searchPlaceholder = 'جست‌وجوی کارمند بر اساس نام، دیپارتمنت یا شماره کارمندی...',
  emptyMessage = 'کارمندی در این واحد یافت نشد.',
}: ParticipantPickerProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const searchInputRef = useRef<HTMLInputElement>(null)
  const selectedSet = useMemo(() => new Set(selectedIds), [selectedIds])
  const selectedEmployees = useMemo(() => employees.filter((employee) => selectedSet.has(employee.id)), [employees, selectedSet])
  const filteredEmployees = useMemo(() => {
    const query = search.toLowerCase().trim()
    if (!query) return employees
    return employees.filter((employee) =>
      [employee.full_name, employee.unit?.name, employee.position?.title, employee.employee_number]
        .filter(Boolean)
        .some((value) => value!.toLowerCase().includes(query))
    )
  }, [employees, search])

  useEffect(() => {
    if (!open) {
      setSearch('')
      return
    }
    const timeout = window.setTimeout(() => searchInputRef.current?.focus(), 50)
    return () => window.clearTimeout(timeout)
  }, [open])

  const toggleEmployee = (id: number) => {
    if (disabled) return
    onChange(selectedSet.has(id) ? selectedIds.filter((item) => item !== id) : [...selectedIds, id])
  }

  const clearSelection = (event?: MouseEvent) => {
    event?.stopPropagation()
    if (!disabled) onChange([])
  }

  const selectAll = () => {
    if (!disabled) onChange(Array.from(new Set([...selectedIds, ...filteredEmployees.map((employee) => employee.id)])))
  }

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      <div className="relative w-full space-y-2.5" dir="rtl">
        <PopoverPrimitive.Trigger asChild disabled={disabled || employees.length === 0}>
          <button
            type="button"
            className={cn(
              'group flex min-h-10 w-full items-center justify-between gap-2 rounded-xl border border-input border-slate-200 bg-slate-50/50 px-3 py-2 text-right text-xs transition-colors outline-none hover:bg-slate-100/70 focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:bg-slate-800/60 sm:text-sm',
              employees.length === 0 && 'cursor-not-allowed opacity-60'
            )}
          >
            <div className="flex min-w-0 flex-1 items-center gap-2">
              <Users className="size-4 shrink-0 text-primary" />
              <span className={cn('truncate', selectedEmployees.length ? 'font-semibold text-foreground' : 'text-muted-foreground')}>
                {selectedEmployees.length ? `${selectedEmployees.length} اشتراک‌کننده انتخاب شده است` : employees.length ? placeholder : 'هیچ کارمندی برای این ریاست یافت نشد'}
              </span>
            </div>
            <div className="flex shrink-0 items-center gap-1.5">
              {selectedEmployees.length > 0 && !disabled && (
                <span role="button" tabIndex={0} onClick={clearSelection} className="rounded-md p-1 text-muted-foreground transition-colors hover:text-foreground" title="پاک‌کردن همه">
                  <X className="size-3.5" />
                </span>
              )}
              <ChevronsUpDown className="size-4 text-muted-foreground opacity-70 transition-opacity group-hover:opacity-100" />
            </div>
          </button>
        </PopoverPrimitive.Trigger>

        {selectedEmployees.length > 0 && (
          <div className="flex flex-wrap gap-1.5 pt-0.5">
            {selectedEmployees.map((employee) => (
              <Badge key={employee.id} variant="secondary" className="gap-1.5 rounded-lg border border-primary/20 bg-primary/10 py-1 pe-2 ps-1 text-xs font-medium text-foreground">
                <span className="flex size-4.5 shrink-0 items-center justify-center rounded-full bg-primary/20 text-[10px] font-bold text-primary">{employee.full_name.trim().slice(0, 1)}</span>
                <span className="max-w-44 truncate">{employee.full_name}</span>
                {employee.unit?.name && <span className="text-[10px] font-normal text-muted-foreground">({employee.unit.name})</span>}
                {!disabled && <button type="button" onClick={(event) => { event.stopPropagation(); onChange(selectedIds.filter((id) => id !== employee.id)) }} className="rounded p-0.5 text-muted-foreground transition-colors hover:bg-primary/20 hover:text-foreground" title="حذف"><X className="size-3" /></button>}
              </Badge>
            ))}
          </div>
        )}

        <PopoverPrimitive.Portal>
          <PopoverPrimitive.Content dir="rtl" align="end" sideOffset={4} className="z-50 w-[calc(var(--radix-popover-trigger-width))] min-w-[320px] max-w-[540px] rounded-xl border border-border/80 bg-popover p-2 text-right text-popover-foreground shadow-xl outline-none animate-in fade-in-0 zoom-in-95 duration-150">
            <div className="relative mb-2">
              <Search className="pointer-events-none absolute start-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
              <Input ref={searchInputRef} value={search} onChange={(event) => setSearch(event.target.value)} placeholder={searchPlaceholder} className="h-8.5 rounded-lg border-slate-200 bg-slate-50/70 ps-8.5 pe-8 text-right text-xs dark:border-slate-700 dark:bg-slate-900/60" />
              {search && <button type="button" onClick={() => setSearch('')} className="absolute end-2.5 top-1/2 -translate-y-1/2 p-0.5 text-muted-foreground hover:text-foreground"><X className="size-3" /></button>}
            </div>
            {filteredEmployees.length > 0 && (
              <div className="mb-1 flex items-center justify-between border-b border-border/40 px-2 py-1 text-[11px] text-muted-foreground">
                <span>تعداد کارمندان: {filteredEmployees.length}</span>
                <div className="flex items-center gap-2"><button type="button" onClick={selectAll} className="font-medium text-primary hover:underline">انتخاب همه نتایج</button><span>•</span><button type="button" onClick={() => clearSelection()} className="font-medium text-muted-foreground hover:text-destructive">لغو همه</button></div>
              </div>
            )}
            <div className="max-h-60 space-y-0.5 overflow-y-auto pe-1">
              {filteredEmployees.map((employee) => {
                const isSelected = selectedSet.has(employee.id)
                return <button key={employee.id} type="button" onClick={() => toggleEmployee(employee.id)} className={cn('group flex w-full items-center justify-between gap-2.5 rounded-lg px-2.5 py-2 text-right text-xs transition-colors', isSelected ? 'bg-primary/10 font-bold text-primary' : 'text-foreground hover:bg-slate-100 dark:hover:bg-slate-800')}>
                  <div className="flex min-w-0 flex-1 items-center gap-2.5"><span className={cn('flex size-4 shrink-0 items-center justify-center rounded border transition-colors', isSelected ? 'border-primary bg-primary text-white' : 'border-muted-foreground/40 group-hover:border-primary/60')}>{isSelected && <Check className="size-3 stroke-[3]" />}</span><span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[10px] font-bold text-muted-foreground dark:bg-slate-800">{employee.full_name.trim().slice(0, 1)}</span><span className="min-w-0"><span className="block truncate">{employee.full_name}</span>{(employee.position?.title || employee.unit?.name) && <span className="mt-0.5 block truncate text-[10px] font-normal text-muted-foreground">{employee.position?.title ? `${employee.position.title} · ` : ''}{employee.unit?.name || ''}</span>}</span></div>
                  {employee.employee_number && <Badge variant="outline" className="shrink-0 px-1.5 py-0 font-mono text-[10px] font-normal">{employee.employee_number}</Badge>}
                </button>
              })}
              {!filteredEmployees.length && <div className="space-y-1 py-6 text-center text-xs text-muted-foreground"><Users className="mx-auto size-4 text-muted-foreground/60" /><p>{emptyMessage}</p></div>}
            </div>
          </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
      </div>
    </PopoverPrimitive.Root>
  )
}
