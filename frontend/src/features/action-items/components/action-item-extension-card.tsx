import React, { useState } from 'react'
import { CalendarClock, Send } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'

type ActionItemExtensionCardProps = {
  busy: boolean
  onExtend: (dueDate: string, reason: string) => Promise<void>
}

export function ActionItemExtensionCard({
  busy,
  onExtend,
}: ActionItemExtensionCardProps) {
  const [extensionDate, setExtensionDate] = useState('')
  const [extensionReason, setExtensionReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!extensionDate) {
      setError('انتخاب تاریخ جدید مهلت الزامی است.')
      return
    }
    if (!extensionReason.trim()) {
      setError('ثبت دلیل تمدید مهلت الزامی است.')
      return
    }
    setError(null)
    await onExtend(extensionDate, extensionReason)
    setExtensionDate('')
    setExtensionReason('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
            <CalendarClock className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              درخواست تمدید مهلت اقدام
            </h3>
            <p className="text-[11px] text-muted-foreground">
              ثبت تاریخ پیشنهادی جدید به همراه دلیل موجه
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3">
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-[14rem_1fr] gap-3 text-xs">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              تاریخ جدید پیشنهادی
            </label>
            <MultiCalendarDatePicker
              value={extensionDate}
              onChange={setExtensionDate}
              placeholder="انتخاب تاریخ تمدید..."
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              دلیل و ضرورت تمدید مهلت
            </label>
            <Textarea
              rows={2}
              value={extensionReason}
              onChange={(e) => setExtensionReason(e.target.value)}
              placeholder="علت نیاز به تمدید زمان انجام کار..."
              className="text-xs rounded-xl"
              disabled={busy}
            />
          </div>
        </div>

        <div className="pt-1">
          <Button
            variant="outline"
            disabled={busy}
            onClick={handleSubmit}
            className="h-9 text-xs font-semibold rounded-xl gap-2 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-900"
          >
            <Send className="size-3.5" />
            <span>ثبت درخواست تمدید</span>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
