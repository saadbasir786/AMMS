import React, { useState } from 'react'
import { CalendarClock, AlertTriangle, Send } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import type { ExceptionCaseType } from '../types'

type MeetingExceptionFormCardProps = {
  busy: boolean
  canCreate?: boolean
  meetingStatus?: string
  onCreateCase: (caseType: ExceptionCaseType, reason: string) => Promise<boolean>
}

export function MeetingExceptionFormCard({
  busy,
  canCreate = false,
  meetingStatus,
  onCreateCase,
}: MeetingExceptionFormCardProps) {
  const [caseType, setCaseType] = useState<ExceptionCaseType>(meetingStatus === 'POSTPONED' ? 'RESUMPTION' : meetingStatus === 'CANCELLED' ? 'REOPENING' : 'POSTPONEMENT')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!reason.trim()) {
      setError('ثبت دلیل اداری و رسمی پرونده الزامی است.')
      return
    }
    setError(null)
    if (await onCreateCase(caseType, reason)) setReason('')
  }

  if (!canCreate || !['SCHEDULED', 'POSTPONED', 'CANCELLED'].includes(meetingStatus || '')) return null

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between gap-3">
          <div className="space-y-0.5">
            <span className="text-[11px] font-semibold text-muted-foreground block">
              ثبت پرونده جدید وضعیت استثنایی
            </span>
            <h3 className="text-sm sm:text-base font-bold text-foreground">
              درخواست اقدام استثنایی برای جلسه
            </h3>
          </div>
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <CalendarClock className="size-4" />
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="space-y-3 text-xs">
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              نوع اقدام یا پرونده استثنایی
            </label>
            <select
              value={caseType}
              onChange={(e) => setCaseType(e.target.value as ExceptionCaseType)}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
            >
              {meetingStatus === 'SCHEDULED' && <option value="POSTPONEMENT">تعویق جلسه (به دلیل شرایط پیش‌بینی‌نشده)</option>}
              {['SCHEDULED', 'POSTPONED'].includes(meetingStatus || '') && <option value="CANCELLATION">لغو کامل جلسه</option>}
              {meetingStatus === 'CANCELLED' && <option value="REOPENING">بازگشایی جلسه لغوشده</option>}
              {meetingStatus === 'POSTPONED' && <option value="RESUMPTION">تعیین زمان جدید و فعال‌سازی مجدد جلسه</option>}
            </select>
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              دلیل اداری و رسمی
            </label>
            <Textarea
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="شرح دقیق اداری و ضرورت اقدام استثنایی را درج نمایید..."
              className="text-xs rounded-xl"
              disabled={busy}
            />
          </div>

          {caseType === 'POSTPONEMENT' && (
            <Alert className="rounded-xl p-3">
              <AlertTriangle className="size-4" />
              <AlertDescription className="text-[11px] text-muted-foreground">
                چنانچه تاریخ جدید از قبل مشخص است، توصیه می‌شود از فضای «تغییر و مذاکره زمان جلسه» استفاده نمایید.
              </AlertDescription>
            </Alert>
          )}

          <div className="pt-2 border-t border-border/50">
            <Button
              disabled={busy || !reason.trim()}
              onClick={handleSubmit}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
            >
              <Send className="size-3.5" />
              <span>{busy ? 'در حال ایجاد…' : 'ایجاد پرونده اقدام استثنایی'}</span>
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
