import React, { useState } from 'react'
import { CheckCircle2, RotateCcw, AlertTriangle, Building2 } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import type { RescheduleProposal } from '../types'

type RescheduleAcknowledgementsCardProps = {
  activeProposal: RescheduleProposal
  requiredUnits: any[]
  canAcknowledge: boolean
  canRequestAlternative: boolean
  busy: boolean
  onAcknowledge: () => Promise<void>
  onRequestAlternative: (reason: string) => Promise<void>
}

export function RescheduleAcknowledgementsCard({
  activeProposal,
  requiredUnits,
  canAcknowledge,
  canRequestAlternative,
  busy,
  onAcknowledge,
  onRequestAlternative,
}: RescheduleAcknowledgementsCardProps) {
  const [alternativeReason, setAlternativeReason] = useState('')
  const [showAlternativeBox, setShowAlternativeBox] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleAlternativeSubmit = async () => {
    if (!alternativeReason.trim()) {
      setError('ثبت دلیل درخواست زمان بدیل الزامی است.')
      return
    }
    setError(null)
    await onRequestAlternative(alternativeReason)
    setAlternativeReason('')
    setShowAlternativeBox(false)
  }

  const acknowledgements = activeProposal.acknowledgements || []

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          ماتریس تأییدات و هماهنگی ریاست‌ها
        </h3>
        <p className="text-[11px] text-muted-foreground">
          وضعیت اعلام نظر واحدهای سازمانی نسبت به پیشنهاد زمان جاری
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="space-y-2">
          {requiredUnits.map((u) => {
            const unitId = Number(u.organizational_unit_id || u.id)
            const isAck = acknowledgements.some(
              (a) => Number(a.organizational_unit_id) === unitId
            )

            return (
              <div
                key={u.id}
                className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
              >
                <div className="flex items-center gap-2">
                  <Building2 className="size-4 text-primary shrink-0" />
                  <strong className="font-semibold text-foreground">
                    {u.unit?.name || u.name || `واحد سازمانی #${unitId}`}
                  </strong>
                </div>

                <Badge
                  variant={isAck ? 'default' : 'outline'}
                  className="text-[10px] px-2 py-0.5"
                >
                  {isAck ? 'تأیید دریافت شد' : 'در انتظار اعلام نظر'}
                </Badge>
              </div>
            )
          })}
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
          {canAcknowledge && (
            <Button
              disabled={busy}
              onClick={onAcknowledge}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
            >
              <CheckCircle2 className="size-3.5" />
              <span>تأیید پیشنهاد برای حوزه من</span>
            </Button>
          )}

          {canRequestAlternative && !showAlternativeBox && (
            <Button
              variant="outline"
              disabled={busy}
              onClick={() => setShowAlternativeBox(true)}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-900"
            >
              <RotateCcw className="size-3.5" />
              <span>درخواست زمان یا تاریخ بدیل</span>
            </Button>
          )}
        </div>

        {/* Alternative Box */}
        {showAlternativeBox && (
          <div className="p-3.5 rounded-xl bg-amber-50/40 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 space-y-2.5">
            <label className="text-xs font-bold text-foreground block">
              دلیل عدم امکان حضور و پیشنهاد زمان بدیل:
            </label>
            <Textarea
              rows={2}
              value={alternativeReason}
              onChange={(e) => setAlternativeReason(e.target.value)}
              placeholder="توضیح دهید چرا این زمان مناسب نیست و چه زمانی مقدور است..."
              className="text-xs rounded-xl"
              disabled={busy}
            />
            <div className="flex gap-2">
              <Button
                size="sm"
                disabled={busy}
                onClick={handleAlternativeSubmit}
                className="h-8 text-xs font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white"
              >
                ارسال درخواست بدیل
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => setShowAlternativeBox(false)}
                className="h-8 text-xs font-semibold rounded-lg"
              >
                انصراف
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
