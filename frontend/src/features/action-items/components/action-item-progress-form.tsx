import React, { useState } from 'react'
import {
  Play,
  CheckCircle2,
  AlertOctagon,
  RotateCcw,
  Send,
  Ban,
  Clock,
  Sparkles,
  FileText
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import type { ActionItem } from '../types'

type ActionItemProgressFormProps = {
  item: ActionItem
  busy: boolean
  onAction: (endpoint: string, body?: any) => Promise<void>
}

export function ActionItemProgressForm({
  item,
  busy,
  onAction,
}: ActionItemProgressFormProps) {
  const [progress, setProgress] = useState(item.progress_percent || 0)
  const [note, setNote] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [confirmCancelOpen, setConfirmCancelOpen] = useState(false)

  const isCompleted = item.status === 'COMPLETED'
  const isCancelled = item.status === 'CANCELLED'

  const handleStart = async () => {
    setError(null)
    await onAction('start')
  }

  const handleUpdateProgress = async () => {
    setError(null)
    await onAction('progress', {
      progress_percent: Number(progress),
      note: note || undefined,
    })
    setNote('')
  }

  const handleBlock = async () => {
    if (!reason.trim()) {
      setError('ثبت دلیل مانع الزامی است.')
      return
    }
    setError(null)
    await onAction('block', { reason })
    setReason('')
  }

  const handleUnblock = async () => {
    if (!reason.trim()) {
      setError('ثبت یادداشت رفع مانع الزامی است.')
      return
    }
    setError(null)
    await onAction('unblock', { note: reason })
    setReason('')
  }

  const handleSubmitCompletion = async () => {
    if (!note.trim()) {
      setError('یادداشت نهایی تکمیل الزامی است.')
      return
    }
    setError(null)
    await onAction('completion/submit', { completion_note: note })
    setNote('')
  }

  const handleVerify = async () => {
    setError(null)
    await onAction('completion/verify')
  }

  const handleReturn = async () => {
    if (!reason.trim()) {
      setError('دلیل برگشت جهت اصلاح الزامی است.')
      return
    }
    setError(null)
    await onAction('completion/return', { reason })
    setReason('')
  }

  const handleCancel = async () => {
    if (!reason.trim()) {
      setError('دلیل لغو اقدام الزامی است.')
      return
    }
    setError(null)
    await onAction('cancel', { reason })
    setReason('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          اقدامات قابل انجام
        </h3>
        <p className="text-[11px] text-muted-foreground">
          عملیات مجاز متناسب با وضعیت فعلی اقدام
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {error && (
          <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        {!isCompleted && !isCancelled ? (
          <>
            {/* Progress Input */}
            <div className="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  درصد پیشرفت (۰ الی ۱۰۰)
                </label>
                <Input
                  type="number"
                  min={0}
                  max={100}
                  value={progress}
                  onChange={(e) => setProgress(Math.min(100, Math.max(0, Number(e.target.value))))}
                  className="h-9.5 text-xs font-mono rounded-xl"
                  disabled={busy}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-foreground block">
                  یادداشت پیشرفت یا گزارش عملیات
                </label>
                <Textarea
                  rows={2}
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder="توضیح کوتاه در مورد پیشرفت انجام شده..."
                  className="text-xs rounded-xl"
                  disabled={busy}
                />
              </div>
            </div>

            {/* Reason / Obstacle Box (if needed) */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                توضیحات مانع / دلیل لغو یا برگشت
              </label>
              <Textarea
                rows={2}
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="در صورت اعلام مانع، رفع مانع یا لغو، دلیل را اینجا بنویسید..."
                className="text-xs rounded-xl"
                disabled={busy}
              />
            </div>

            {/* Action Buttons Matrix */}
            <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
              {/* Start */}
              {item.status === 'OPEN' && (
                <Button
                  disabled={busy}
                  onClick={handleStart}
                  className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
                >
                  <Play className="size-3.5" />
                  <span>شروع اجرای اقدام</span>
                </Button>
              )}

              {/* Progress */}
              {['IN_PROGRESS', 'BLOCKED'].includes(item.status) && (
                <Button
                  variant="outline"
                  disabled={busy}
                  onClick={handleUpdateProgress}
                  className="h-9.5 text-xs font-semibold rounded-xl gap-2"
                >
                  <Sparkles className="size-3.5 text-primary" />
                  <span>ثبت پیشرفت</span>
                </Button>
              )}

              {/* Block */}
              {item.status === 'IN_PROGRESS' && (
                <Button
                  variant="outline"
                  disabled={busy}
                  onClick={handleBlock}
                  className="h-9.5 text-xs font-semibold rounded-xl gap-2 text-rose-600 hover:bg-rose-50"
                >
                  <AlertOctagon className="size-3.5" />
                  <span>اعلام مانع و توقف</span>
                </Button>
              )}

              {/* Unblock */}
              {item.status === 'BLOCKED' && (
                <Button
                  variant="outline"
                  disabled={busy}
                  onClick={handleUnblock}
                  className="h-9.5 text-xs font-semibold rounded-xl gap-2 text-emerald-600 hover:bg-emerald-50"
                >
                  <CheckCircle2 className="size-3.5" />
                  <span>اعلام رفع مانع</span>
                </Button>
              )}

              {/* Submit Completion (when 100%) */}
              {['IN_PROGRESS', 'BLOCKED'].includes(item.status) && Number(progress) === 100 && (
                <Button
                  disabled={busy}
                  onClick={handleSubmitCompletion}
                  className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                >
                  <Send className="size-3.5" />
                  <span>ارسال برای تأیید تکمیل</span>
                </Button>
              )}

              {/* Verification & Return */}
              {item.status === 'PENDING_VERIFICATION' && (
                <>
                  <Button
                    disabled={busy}
                    onClick={handleVerify}
                    className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                  >
                    <CheckCircle2 className="size-3.5" />
                    <span>تأیید و خاتمه اقدام</span>
                  </Button>

                  <Button
                    variant="outline"
                    disabled={busy}
                    onClick={handleReturn}
                    className="h-9.5 text-xs font-semibold rounded-xl gap-2 text-amber-600 hover:bg-amber-50"
                  >
                    <RotateCcw className="size-3.5" />
                    <span>برگشت جهت اصلاح</span>
                  </Button>
                </>
              )}

              {/* Cancel */}
              {['OPEN', 'IN_PROGRESS', 'BLOCKED'].includes(item.status) && (
                <Button
                  variant="ghost"
                  disabled={busy}
                  onClick={() => setConfirmCancelOpen(true)}
                  className="h-9.5 text-xs font-semibold rounded-xl gap-2 text-muted-foreground hover:text-rose-600 mr-auto"
                >
                  <Ban className="size-3.5" />
                  <span>لغو اقدام</span>
                </Button>
              )}
            </div>
          </>
        ) : (
          <div className="p-4 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs text-muted-foreground text-center">
            {isCompleted ? 'این اقدام با موفقیت تکمیل و مختومه شده است.' : 'این اقدام لغو گردیده است.'}
          </div>
        )}
      </CardContent>

      <ConfirmDialog
        open={confirmCancelOpen}
        onOpenChange={setConfirmCancelOpen}
        title="لغو رسمی اقدام تأیید شود؟"
        description="با ثبت لغو، این اقدام متوقف و سابقه لغو در خط زمانی ثبت می‌گردد. آیا مطمئن هستید؟"
        confirmLabel="تأیید و لغو اقدام"
        variant="destructive"
        onConfirm={handleCancel}
      />
    </Card>
  )
}
