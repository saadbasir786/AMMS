import React, { useState } from 'react'
import { UserRound, Plus, ShieldAlert, CheckCircle2 } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'

type ManualAttendanceCardProps = {
  busy: boolean
  onSubmitManual: (employeeId: number, reason: string) => Promise<void>
}

export function ManualAttendanceCard({
  busy,
  onSubmitManual,
}: ManualAttendanceCardProps) {
  const [employeeId, setEmployeeId] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!employeeId || isNaN(Number(employeeId))) {
      setError('شناسه یا شماره کارمند معتبر الزامی است.')
      return
    }
    if (!reason.trim()) {
      setError('ثبت دلیل رسمی برای ثبت دستی حضور الزامی است.')
      return
    }
    setError(null)
    await onSubmitManual(Number(employeeId), reason)
    setEmployeeId('')
    setReason('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              ثبت دستی حضور اشتراک‌کننده
            </h3>
            <p className="text-[11px] text-muted-foreground">
              روش پشتیبان مجاز در صورت عدم دسترسی به ترمینال یا مشکل در ثبت اثر انگشت
            </p>
          </div>
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <UserRound className="size-4" />
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-[14rem_1fr] gap-3 text-xs">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              شناسه کارمندی (ID)
            </label>
            <Input
              dir="ltr"
              value={employeeId}
              onChange={(e) => setEmployeeId(e.target.value)}
              placeholder="مثلاً: 104"
              className="h-9.5 text-xs font-mono rounded-xl"
              disabled={busy}
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              دلیل اداری ثبت دستی (اجباری)
            </label>
            <Input
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="مثلاً: نقص فنی موقت دستگاه، زخم انگشت، یا تأیید شفاهی مدیر..."
              className="h-9.5 text-xs rounded-xl"
              disabled={busy}
            />
          </div>
        </div>

        <div className="pt-2 border-t border-border/50 flex justify-end">
          <Button
            disabled={busy || !employeeId || !reason.trim()}
            onClick={handleSubmit}
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Plus className="size-3.5" />
            <span>{busy ? 'در حال ثبت…' : 'ثبت حضور دستی'}</span>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
