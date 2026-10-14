import React, { useEffect, useState } from 'react'
import { Plus, Monitor, Clock } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'
import type { Terminal } from '../types'

type AssignTerminalCardProps = {
  availableTerminals: Terminal[]
  scheduleDate: string
  scheduleStartTime: string
  scheduleEndTime: string
  scheduleVersionId?: number
  busy: boolean
  onAssign: (data: {
    terminal_id: number
    valid_from: string
    valid_until: string
  }) => Promise<boolean>
}

function shiftDateTime(dateValue: string, timeValue: string, offsetMinutes: number) {
  const date = dateValue.slice(0, 10)
  const time = timeValue.slice(0, 5)
  const [year, month, day] = date.split('-').map(Number)
  const [hours, minutes] = time.split(':').map(Number)
  if (![year, month, day, hours, minutes].every(Number.isFinite)) return { date: '', time: '' }

  // Treat schedule values as Kabul wall-clock time without relying on the browser timezone.
  const shifted = new Date(Date.UTC(year, month - 1, day, hours, minutes + offsetMinutes))
  return {
    date: `${shifted.getUTCFullYear()}-${String(shifted.getUTCMonth() + 1).padStart(2, '0')}-${String(shifted.getUTCDate()).padStart(2, '0')}`,
    time: `${String(shifted.getUTCHours()).padStart(2, '0')}:${String(shifted.getUTCMinutes()).padStart(2, '0')}`,
  }
}

export function AssignTerminalCard({
  availableTerminals,
  scheduleDate,
  scheduleStartTime,
  scheduleEndTime,
  scheduleVersionId,
  busy,
  onAssign,
}: AssignTerminalCardProps) {
  const [terminalId, setTerminalId] = useState('')
  const [validFromDate, setValidFromDate] = useState('')
  const [validFromTime, setValidFromTime] = useState('')
  const [validUntilDate, setValidUntilDate] = useState('')
  const [validUntilTime, setValidUntilTime] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!scheduleDate || !scheduleStartTime || !scheduleEndTime) return
    const start = shiftDateTime(scheduleDate, scheduleStartTime, -30)
    const end = shiftDateTime(scheduleDate, scheduleEndTime, 30)
    if (!start.date || !end.date) return
    setValidFromDate(start.date)
    setValidFromTime(start.time)
    setValidUntilDate(end.date)
    setValidUntilTime(end.time)
  }, [scheduleVersionId, scheduleDate, scheduleStartTime, scheduleEndTime])

  const handleSubmit = async () => {
    if (!terminalId) {
      setError('انتخاب ترمینال الزامی است.')
      return
    }
    if (!validFromDate || !validFromTime || !validUntilDate || !validUntilTime) {
      setError('تعیین بازه زمانی مجاز فعالیت دستگاه الزامی است.')
      return
    }
    setError(null)
    const assigned = await onAssign({
      terminal_id: Number(terminalId),
      valid_from: `${validFromDate} ${validFromTime}:00`,
      valid_until: `${validUntilDate} ${validUntilTime}:00`,
    })
    if (assigned) setTerminalId('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              تخصیص دستگاه ترمینال به جلسه
            </h3>
            <p className="text-[11px] text-muted-foreground">
              تعیین ترمینال مجاز و پنجره زمانی مجاز ثبت حضور
            </p>
          </div>
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Monitor className="size-4" />
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 text-xs">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              انتخاب ترمینال مجاز
            </label>
            <select
              value={terminalId}
              onChange={(e) => setTerminalId(e.target.value)}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
            >
              <option value="">انتخاب دستگاه فعال...</option>
              {availableTerminals.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.code} • {t.name}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-2 rounded-xl border border-border/60 p-3">
            <label className="text-xs font-bold text-foreground block">شروع پنجرهٔ مجاز</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <MultiCalendarDatePicker
                value={validFromDate}
                onChange={setValidFromDate}
                placeholder="تاریخ آغاز"
                disabled={busy}
                showSolarPreview={false}
              />
              <TimePicker
                value={validFromTime}
                onChange={setValidFromTime}
                placeholder="ساعت آغاز"
                disabled={busy}
              />
            </div>
          </div>

          <div className="space-y-2 rounded-xl border border-border/60 p-3">
            <label className="text-xs font-bold text-foreground block">پایان پنجرهٔ مجاز</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <MultiCalendarDatePicker
                value={validUntilDate}
                onChange={setValidUntilDate}
                placeholder="تاریخ پایان"
                disabled={busy}
                showSolarPreview={false}
              />
              <TimePicker
                value={validUntilTime}
                onChange={setValidUntilTime}
                placeholder="ساعت پایان"
                disabled={busy}
              />
            </div>
          </div>
        </div>

        <p className="text-[11px] text-muted-foreground">
          بازه به‌صورت پیش‌فرض از ۳۰ دقیقه پیش از آغاز تا ۳۰ دقیقه پس از ختم برنامه‌ریزی‌شدهٔ جلسه تنظیم شده است و قابل تغییر می‌باشد.
        </p>

        <div className="pt-2 border-t border-border/50 flex justify-end">
          <Button
            disabled={busy || !terminalId || !validFromDate || !validFromTime || !validUntilDate || !validUntilTime}
            onClick={handleSubmit}
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Plus className="size-3.5" />
            <span>{busy ? 'در حال ثبت…' : 'ثبت تخصیص ترمینال'}</span>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
