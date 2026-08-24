import React, { useState } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Clock, Check, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

const QUICK_TIMES = [
  '08:00',
  '08:30',
  '09:00',
  '09:30',
  '10:00',
  '10:30',
  '11:00',
  '11:30',
  '12:00',
  '13:00',
  '13:30',
  '14:00',
  '14:30',
  '15:00',
  '15:30',
  '16:00',
]

type TimePickerProps = {
  value?: string | null // HH:mm format
  onChange: (time: string) => void
  placeholder?: string
  disabled?: boolean
  className?: string
}

export function TimePicker({
  value,
  onChange,
  placeholder = 'انتخاب ساعت آغاز...',
  disabled = false,
  className,
}: TimePickerProps) {
  const [open, setOpen] = useState(false)

  const handleSelectTime = (t: string) => {
    onChange(t)
    setOpen(false)
  }

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange('')
  }

  return (
    <div className={cn('relative w-full', className)} dir="rtl">
      <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
        <PopoverPrimitive.Trigger asChild disabled={disabled}>
          <button
            type="button"
            className={cn(
              'flex h-9.5 w-full items-center justify-between gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 px-3 text-xs text-right transition-colors outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 group',
              disabled && 'opacity-50 cursor-not-allowed'
            )}
          >
            <div className="flex items-center gap-2 min-w-0 flex-1">
              <Clock className="size-3.5 text-primary shrink-0" />
              {value ? (
                <span className="font-bold font-mono text-foreground tracking-wider">
                  {value}
                </span>
              ) : (
                <span className="text-muted-foreground truncate">{placeholder}</span>
              )}
            </div>

            {value && !disabled && (
              <span
                role="button"
                tabIndex={0}
                onClick={handleClear}
                className="p-1 text-muted-foreground hover:text-foreground rounded transition-colors shrink-0"
                title="پاک‌کردن ساعت"
              >
                <X className="size-3" />
              </span>
            )}
          </button>
        </PopoverPrimitive.Trigger>

        <PopoverPrimitive.Portal>
          <PopoverPrimitive.Content
            dir="rtl"
            align="start"
            sideOffset={4}
            className="z-50 w-[260px] sm:w-[280px] rounded-2xl border border-border/80 bg-card p-3 shadow-xl text-right animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 duration-150"
          >
            {/* Header & Custom Input */}
            <div className="space-y-2 pb-2.5 border-b border-border/60">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold text-foreground">ساعات کاری اداری</span>
                <span className="text-[10px] text-muted-foreground">گام‌های ۳۰ دقیقه‌ای</span>
              </div>
              <div className="flex items-center gap-2">
                <label className="text-[11px] text-muted-foreground shrink-0">ساعت دلخواه:</label>
                <Input
                  type="time"
                  value={value || ''}
                  onChange={(e) => onChange(e.target.value)}
                  className="h-8 text-xs font-mono bg-slate-50 dark:bg-slate-900 rounded-lg text-center"
                />
              </div>
            </div>

            {/* Quick Times Grid */}
            <div className="grid grid-cols-4 gap-1.5 pt-2.5 max-h-48 overflow-y-auto">
              {QUICK_TIMES.map((t) => {
                const isSelected = value === t
                return (
                  <button
                    key={t}
                    type="button"
                    onClick={() => handleSelectTime(t)}
                    className={cn(
                      'py-1.5 px-1 rounded-lg text-xs font-mono font-semibold transition-all text-center',
                      isSelected
                        ? 'bg-primary text-white shadow-xs font-bold'
                        : 'bg-slate-100/80 dark:bg-slate-800/80 text-foreground hover:bg-primary/10 hover:text-primary'
                    )}
                  >
                    {t}
                  </button>
                )
              })}
            </div>
          </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
      </PopoverPrimitive.Root>
    </div>
  )
}
