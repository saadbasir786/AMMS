import React, { useState } from 'react'
import {
  Send,
  CheckCircle2,
  RotateCcw,
  XCircle,
  CalendarCheck,
  Eye,
  AlertCircle,
  Loader2,
  Edit3
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import type { MeetingRequestActionCapabilities } from '../lib/request-capabilities'

type RequestActionsCardProps = {
  request: any
  capabilities: MeetingRequestActionCapabilities
  onAction: (url: string, body?: any) => Promise<void>
  onEdit?: () => void
  onScheduling?: () => void
}

export function RequestActionsCard({
  request,
  capabilities,
  onAction,
  onEdit,
  onScheduling,
}: RequestActionsCardProps) {
  const [reason, setReason] = useState('')
  const [busyAction, setBusyAction] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [confirmModal, setConfirmModal] = useState<{
    url: string
    label: string
    destructive?: boolean
    body?: any
  } | null>(null)

  const run = async (url: string, body?: any) => {
    setBusyAction(url)
    setError('')
    try {
      await onAction(url, body)
      setReason('')
    } catch (err: any) {
      setError(err?.response?.data?.message || 'انجام عملیات با خطا مواجه شد.')
      throw err
    } finally {
      setBusyAction(null)
    }
  }

  const hasMeeting = !!request.meeting
  const hasAction = Object.values(capabilities).some(Boolean) || hasMeeting

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          اقدامات قابل انجام
        </h3>
        <p className="text-[11px] text-muted-foreground">
          عملیات مجاز متناسب با وضعیت فعلی درخواست
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3.5">
        {error && (
          <Alert variant="destructive" className="text-xs p-3 rounded-xl">
            <AlertCircle className="size-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {/* Draft State */}
        {capabilities.submit && (
          <Button
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2"
            disabled={!!busyAction}
            onClick={() => run('submit')}
          >
            {busyAction === 'submit' ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <Send className="size-4" />
            )}
            <span>ارسال برای تأیید</span>
          </Button>
        )}

        {/* Returned for Revision */}
        {capabilities.edit && onEdit && (
          <Button
            variant="outline"
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 border-primary text-primary hover:bg-primary/5"
            onClick={onEdit}
          >
            <Edit3 className="size-4" />
            <span>اصلاح و بازبینی درخواست</span>
          </Button>
        )}

        {/* Pending Approval (Director Actions) */}
        {(capabilities.approve || capabilities.return_for_revision || capabilities.reject) && (
          <div className="space-y-2.5">
            {capabilities.approve && <Button
              className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
              disabled={!!busyAction}
              onClick={() => run('approve')}
              title={capabilities.approve_with_delegation ? 'تأیید با صلاحیت تفویض‌شده' : undefined}
            >
              {busyAction === 'approve' ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                <CheckCircle2 className="size-4" />
              )}
              <span>تأیید درخواست جلسه</span>
            </Button>}
            {capabilities.approve_with_delegation && (
              <p className="text-center text-[11px] text-muted-foreground">
                تأیید با صلاحیت تفویض‌شده ثبت می‌شود.
              </p>
            )}

            {(capabilities.return_for_revision || capabilities.reject) && <div className="space-y-1.5 pt-1">
              <label className="text-[11px] font-semibold text-muted-foreground block">
                توضیحات یا دلیل (در صورت برگشت یا رد):
              </label>
              <Textarea
                rows={2}
                placeholder="دلیل را در صورت نیاز یادداشت کنید..."
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                className="text-xs rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 resize-y"
              />
            </div>}

            {(capabilities.return_for_revision || capabilities.reject) && <div className="grid grid-cols-2 gap-2">
              {capabilities.return_for_revision && <Button
                type="button"
                variant="outline"
                className="h-9 text-xs font-semibold rounded-xl gap-1.5 border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30"
                disabled={!!busyAction}
                onClick={() =>
                  setConfirmModal({
                    url: 'return',
                    label: 'برگشت جهت اصلاح',
                    body: { reason },
                  })
                }
              >
                <RotateCcw className="size-3.5" />
                <span>برگشت جهت اصلاح</span>
              </Button>}

              {capabilities.reject && <Button
                type="button"
                variant="destructive"
                className="h-9 text-xs font-semibold rounded-xl gap-1.5"
                disabled={!!busyAction}
                onClick={() =>
                  setConfirmModal({
                    url: 'reject',
                    label: 'رد درخواست',
                    destructive: true,
                    body: { reason },
                  })
                }
              >
                <XCircle className="size-3.5" />
                <span>رد درخواست</span>
              </Button>}
            </div>}
          </div>
        )}

        {/* Approved -> Start Scheduling */}
        {capabilities.start_scheduling && (
          <Button
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white"
            disabled={!!busyAction}
            onClick={() => run('start-scheduling')}
          >
            {busyAction === 'start-scheduling' ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <CalendarCheck className="size-4" />
            )}
            <span>شروع و ورود به زمان‌بندی رسمی جلسه</span>
          </Button>
        )}

        {/* Scheduling In-Progress */}
        {capabilities.open_scheduling && onScheduling && (
          <Button
            variant="outline"
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 border-primary text-primary hover:bg-primary/5"
            onClick={onScheduling}
          >
            <CalendarCheck className="size-4" />
            <span>ادامه تنظیم زمان‌بندی جلسه</span>
          </Button>
        )}

        {/* Meeting Created */}
        {hasMeeting && (
          <Button asChild variant="outline" className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2">
            <a href={`/meetings/${request.meeting.id}`}>
              <Eye className="size-4" />
              <span>مشاهده جلسه ایجادشده</span>
            </a>
          </Button>
        )}

        {!hasAction && (
          <p className="rounded-xl border border-border/70 bg-muted/40 px-3 py-3 text-center text-[11px] leading-5 text-muted-foreground">
            در وضعیت فعلی، اقدامی برای شما وجود ندارد.
          </p>
        )}
      </CardContent>

      {/* Confirmation Dialog */}
      <ConfirmDialog
        open={!!confirmModal}
        onOpenChange={(open) => {
          if (!open) setConfirmModal(null)
        }}
        title={`${confirmModal?.label || 'عملیات'} انجام شود؟`}
        description="این اقدام در گردش کار و تاریخچه درخواست ثبت خواهد شد."
        confirmLabel={confirmModal?.label || 'تأیید'}
        variant={confirmModal?.destructive ? 'destructive' : 'default'}
        onConfirm={async () => {
          if (!confirmModal) return
          const target = confirmModal
          await run(target.url, target.body)
          setConfirmModal(null)
        }}
      />
    </Card>
  )
}
