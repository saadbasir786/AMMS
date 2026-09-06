import React, { useState, useMemo } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import {
  Calendar as CalendarIcon,
  ChevronRight,
  ChevronLeft,
  X,
  Sparkles,
  Sun,
  Moon,
  Globe
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import {
  AFGHAN_SOLAR_MONTHS,
  GREGORIAN_MONTHS,
  PERSIAN_WEEK_DAYS,
  gregorianToJalaali,
  jalaaliToGregorian,
  getJalaaliMonthDays,
  toIsoDate,
  parseIsoDate,
  formatSolarDate,
  formatLunarDate,
  getTripleDateSummary
} from '@/lib/calendar-utils'

type CalendarMode = 'solar' | 'gregorian'

type MultiCalendarDatePickerProps = {
  value?: string | null // ISO string YYYY-MM-DD
  onChange: (value: string) => void
  placeholder?: string
  disabled?: boolean
  className?: string
}

export function MultiCalendarDatePicker({
  value,
  onChange,
  placeholder = 'انتخاب تاریخ جلسه...',
  disabled = false,
  className,
}: MultiCalendarDatePickerProps) {
  const [open, setOpen] = useState(false)
  const [calendarMode, setCalendarMode] = useState<CalendarMode>('solar')

  // Initial view year and month based on selected value or today
  const today = useMemo(() => new Date(), [])
  const todayJalaali = useMemo(
    () => gregorianToJalaali(today.getFullYear(), today.getMonth() + 1, today.getDate()),
    [today]
  )

  const selectedDateObj = useMemo(() => parseIsoDate(value), [value])
  const selectedJalaali = useMemo(() => {
    if (!selectedDateObj) return null
    return gregorianToJalaali(
      selectedDateObj.getFullYear(),
      selectedDateObj.getMonth() + 1,
      selectedDateObj.getDate()
    )
  }, [selectedDateObj])

  // Navigation state (for Solar Hijri)
  const [viewYear, setViewYear] = useState<number>(() => {
    return selectedJalaali ? selectedJalaali.jy : todayJalaali.jy
  })
  const [viewMonth, setViewMonth] = useState<number>(() => {
    return selectedJalaali ? selectedJalaali.jm : todayJalaali.jm
  })

  // Navigation state (for Gregorian)
  const [gregViewYear, setGregViewYear] = useState<number>(() => {
    return selectedDateObj ? selectedDateObj.getFullYear() : today.getFullYear()
  })
  const [gregViewMonth, setGregViewMonth] = useState<number>(() => {
    return selectedDateObj ? selectedDateObj.getMonth() + 1 : today.getMonth() + 1
  })

  // Navigate Prev / Next Month
  const handlePrevMonth = () => {
    if (calendarMode === 'solar') {
      if (viewMonth === 1) {
        setViewMonth(12)
        setViewYear((y) => y - 1)
      } else {
        setViewMonth((m) => m - 1)
      }
    } else {
      if (gregViewMonth === 1) {
        setGregViewMonth(12)
        setGregViewYear((y) => y - 1)
      } else {
        setGregViewMonth((m) => m - 1)
      }
    }
  }

  const handleNextMonth = () => {
    if (calendarMode === 'solar') {
      if (viewMonth === 12) {
        setViewMonth(1)
        setViewYear((y) => y + 1)
      } else {
        setViewMonth((m) => m + 1)
      }
    } else {
      if (gregViewMonth === 12) {
        setGregViewMonth(1)
        setGregViewYear((y) => y + 1)
      } else {
        setGregViewMonth((m) => m + 1)
      }
    }
  }

  // Calculate day cells for Solar Hijri
  const solarDays = useMemo(() => {
    const totalDays = getJalaaliMonthDays(viewYear, viewMonth)
    // Find day of week for 1st of this Jalaali month
    const { gy, gm, gd } = jalaaliToGregorian(viewYear, viewMonth, 1)
    const firstDate = new Date(gy, gm - 1, gd)
    // In JS: 0=Sunday, 1=Monday, ..., 6=Saturday
    // Persian week starts on Saturday (شنبه): Sat=0, Sun=1, Mon=2, Tue=3, Wed=4, Thu=5, Fri=6
    const jsDay = firstDate.getDay()
    const persianStartCol = (jsDay + 1) % 7

    const cells: Array<{ day: number | null; isToday: boolean; isSelected: boolean; isoDate: string }> = []

    // Empty offset cells
    for (let i = 0; i < persianStartCol; i++) {
      cells.push({ day: null, isToday: false, isSelected: false, isoDate: '' })
    }

    // Month days
    for (let day = 1; day <= totalDays; day++) {
      const g = jalaaliToGregorian(viewYear, viewMonth, day)
      const iso = toIsoDate(g.gy, g.gm, g.gd)
      const isToday =
        viewYear === todayJalaali.jy && viewMonth === todayJalaali.jm && day === todayJalaali.jd
      const isSelected =
        selectedJalaali !== null &&
        viewYear === selectedJalaali.jy &&
        viewMonth === selectedJalaali.jm &&
        day === selectedJalaali.jd

      cells.push({ day, isToday, isSelected, isoDate: iso })
    }

    return cells
  }, [viewYear, viewMonth, todayJalaali, selectedJalaali])

  // Select a day
  const handleSelectIso = (iso: string) => {
    onChange(iso)
    setOpen(false)
  }

  // Quick Shortcuts
  const setQuickToday = () => {
    const iso = toIsoDate(today.getFullYear(), today.getMonth() + 1, today.getDate())
    handleSelectIso(iso)
  }

  const setQuickTomorrow = () => {
    const tm = new Date(today)
    tm.setDate(tm.getDate() + 1)
    const iso = toIsoDate(tm.getFullYear(), tm.getMonth() + 1, tm.getDate())
    handleSelectIso(iso)
  }

  const setQuickNextSaturday = () => {
    const target = new Date(today)
    const jsDay = target.getDay()
    // Days until next Saturday (Saturday is 6 in JS)
    const daysUntilSat = (6 - jsDay + 7) % 7 || 7
    target.setDate(target.getDate() + daysUntilSat)
    const iso = toIsoDate(target.getFullYear(), target.getMonth() + 1, target.getDate())
    handleSelectIso(iso)
  }

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange('')
  }

  // Triple summary
  const tripleSummary = useMemo(() => getTripleDateSummary(value), [value])

  // Sanitize wrapper className to prevent height clipping (e.g. h-9.5 passed by callers)
  const wrapperClassName = useMemo(() => {
    if (!className) return ''
    return className
      .replace(/\b(h-\S+|min-h-\S+|max-h-\S+)\b/g, '')
      .trim()
  }, [className])

  return (
    <div className={cn('w-full space-y-1.5', wrapperClassName)} dir="rtl">
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
              <CalendarIcon className="size-3.5 text-primary shrink-0" />
              {selectedDateObj ? (
                <span className="font-bold text-foreground truncate">
                  {formatSolarDate(selectedDateObj)} هـ.ش
                </span>
              ) : (
                <span className="text-muted-foreground truncate">{placeholder}</span>
              )}
            </div>

            <div className="flex items-center gap-1 shrink-0">
              {value && !disabled && (
                <span
                  role="button"
                  tabIndex={0}
                  onClick={handleClear}
                  className="p-1 text-muted-foreground hover:text-foreground rounded transition-colors"
                  title="پاک‌کردن تاریخ"
                >
                  <X className="size-3" />
                </span>
              )}
            </div>
          </button>
        </PopoverPrimitive.Trigger>

        {/* Popover Calendar Grid */}
        <PopoverPrimitive.Portal>
          <PopoverPrimitive.Content
            dir="rtl"
            align="start"
            sideOffset={4}
            className="z-50 w-[300px] sm:w-[320px] rounded-2xl border border-border/80 bg-card p-3 shadow-xl text-right animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 duration-150"
          >
            {/* Calendar Mode Switcher */}
            <div className="flex items-center justify-between p-1 bg-slate-100 dark:bg-slate-900 rounded-xl mb-2.5 text-[11px] font-semibold">
              <button
                type="button"
                onClick={() => setCalendarMode('solar')}
                className={cn(
                  'flex-1 py-1 rounded-lg transition-all flex items-center justify-center gap-1',
                  calendarMode === 'solar'
                    ? 'bg-card text-primary shadow-xs font-bold'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                <Sun className="size-3 text-amber-500" />
                <span>هجری خورشیدی</span>
              </button>

              <button
                type="button"
                onClick={() => setCalendarMode('gregorian')}
                className={cn(
                  'flex-1 py-1 rounded-lg transition-all flex items-center justify-center gap-1',
                  calendarMode === 'gregorian'
                    ? 'bg-card text-primary shadow-xs font-bold'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                <Globe className="size-3 text-sky-500" />
                <span>میلادی</span>
              </button>
            </div>

            {/* Navigation Header */}
            <div className="flex items-center justify-between px-1 mb-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handlePrevMonth}
                className="size-7 p-0 rounded-lg text-muted-foreground hover:text-foreground"
                aria-label="ماه قبل"
              >
                <ChevronRight className="size-4" />
              </Button>

              <div className="text-xs font-bold text-foreground">
                {calendarMode === 'solar'
                  ? `${AFGHAN_SOLAR_MONTHS[viewMonth - 1]} ${viewYear}`
                  : `${GREGORIAN_MONTHS[gregViewMonth - 1]} ${gregViewYear}`}
              </div>

              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handleNextMonth}
                className="size-7 p-0 rounded-lg text-muted-foreground hover:text-foreground"
                aria-label="ماه بعد"
              >
                <ChevronLeft className="size-4" />
              </Button>
            </div>

            {/* Weekdays Header */}
            <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-muted-foreground mb-1">
              {PERSIAN_WEEK_DAYS.map((w, idx) => (
                <div
                  key={w.key}
                  className={cn(
                    'py-1',
                    idx === 6 && 'text-rose-500 font-bold' // Friday in Afghan calendar
                  )}
                >
                  {w.short}
                </div>
              ))}
            </div>

            {/* Day Cells Grid */}
            <div className="grid grid-cols-7 gap-1 text-center">
              {solarDays.map((cell, idx) => {
                if (!cell.day) {
                  return <div key={`empty-${idx}`} className="size-8" />
                }
                const isFriday = idx % 7 === 6
                return (
                  <button
                    key={cell.isoDate}
                    type="button"
                    onClick={() => handleSelectIso(cell.isoDate)}
                    className={cn(
                      'size-8 rounded-lg text-xs font-medium transition-all flex items-center justify-center select-none cursor-pointer',
                      cell.isSelected
                        ? 'bg-primary text-white font-bold shadow-xs'
                        : cell.isToday
                        ? 'border border-primary/60 text-primary font-bold bg-primary/5 hover:bg-primary/10'
                        : isFriday
                        ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30'
                        : 'text-foreground hover:bg-slate-100 dark:hover:bg-slate-800'
                    )}
                  >
                    {cell.day.toLocaleString('fa-AF')}
                  </button>
                )
              })}
            </div>

            {/* Quick Shortcuts Bar */}
            <div className="flex items-center justify-between gap-1 pt-2.5 mt-2 border-t border-border/60 text-[10px]">
              <button
                type="button"
                onClick={setQuickToday}
                className="px-2 py-1 rounded-md text-primary hover:bg-primary/10 font-medium"
              >
                امروز
              </button>
              <button
                type="button"
                onClick={setQuickTomorrow}
                className="px-2 py-1 rounded-md text-primary hover:bg-primary/10 font-medium"
              >
                فردا
              </button>
              <button
                type="button"
                onClick={setQuickNextSaturday}
                className="px-2 py-1 rounded-md text-primary hover:bg-primary/10 font-medium"
              >
                شنبه آینده
              </button>
            </div>
          </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
      </PopoverPrimitive.Root>

      {/* Triple Conversion Badge when Date is Selected */}
      {tripleSummary && (
        <div className="p-2 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-border/70 text-[11px] text-muted-foreground animate-in fade-in duration-150">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
            <div className="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-background border border-amber-500/20 text-foreground font-medium shadow-2xs">
              <Sun className="size-3 text-amber-500 shrink-0" />
              <span className="truncate">{tripleSummary.solar}</span>
            </div>
            <div className="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-background border border-indigo-500/20 text-foreground font-medium shadow-2xs">
              <Moon className="size-3 text-indigo-400 shrink-0" />
              <span className="truncate">{tripleSummary.lunar}</span>
            </div>
            <div className="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-background border border-sky-500/20 text-muted-foreground font-mono shadow-2xs">
              <Globe className="size-3 text-sky-400 shrink-0" />
              <span className="truncate">{tripleSummary.gregorian}</span>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
