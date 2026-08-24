import React, { useState } from 'react'
import { Plus, Monitor, Clock } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { Terminal } from '../types'

type AssignTerminalCardProps = {
  availableTerminals: Terminal[]
  busy: boolean
  onAssign: (data: {
    terminal_id: number
    valid_from: string
    valid_until: string
  }) => Promise<void>
}

export function AssignTerminalCard({
  availableTerminals,
  busy,
  onAssign,
}: AssignTerminalCardProps) {
  const [terminalId, setTerminalId] = useState('')
  const [validFrom, setValidFrom] = useState('')
  const [validUntil, setValidUntil] = useState('')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!terminalId) {
      setError('انتخاب ترمینال الزامی است.')
      return
    }
    if (!validFrom || !validUntil) {
      setError('تعیین بازه زمانی مجاز فعالیت دستگاه الزامی است.')
      return
    }
    setError(null)
    await onAssign({
      terminal_id: Number(terminalId),
      valid_from: validFrom,
      valid_until: validUntil,
    })
    setTerminalId('')
    setValidFrom('')
    setValidUntil('')
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

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
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

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              شروع پنجره مجوز (ساعت و تاریخ)
            </label>
            <Input
              type="datetime-local"
              value={validFrom}
              onChange={(e) => setValidFrom(e.target.value)}
              className="h-9.5 text-xs rounded-xl font-mono"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              پایان پنجره مجوز (ساعت و تاریخ)
            </label>
            <Input
              type="datetime-local"
              value={validUntil}
              onChange={(e) => setValidUntil(e.target.value)}
              className="h-9.5 text-xs rounded-xl font-mono"
            />
          </div>
        </div>

        <div className="pt-2 border-t border-border/50 flex justify-end">
          <Button
            disabled={busy || !terminalId || !validFrom || !validUntil}
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
