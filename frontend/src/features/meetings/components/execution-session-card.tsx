import React, { useState } from 'react'
import {
  Play,
  Square,
  Clock,
  FileText,
  LockKeyhole,
  AlertOctagon
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import type { MeetingExecutionState } from '../types'

type ExecutionSessionCardProps = {
  state: MeetingExecutionState
  busy: boolean
  onStart: (notes: string) => Promise<void>
  onEnd: (notes: string) => Promise<void>
  canStart?: boolean
  canEnd?: boolean
}

export function ExecutionSessionCard({
  state,
  busy,
  onStart,
  onEnd,
  canStart = false,
  canEnd = false,
}: ExecutionSessionCardProps) {
  const [notes, setNotes] = useState('')
  const [confirmEndOpen, setConfirmEndOpen] = useState(false)

  const session = state.execution_session
  const isScheduled = state.status === 'SCHEDULED'
  const isInProgress = state.status === 'IN_PROGRESS'
  const isHeldOrCompleted = ['HELD', 'COMPLETED'].includes(state.status)

  const handleStart = async () => {
    await onStart(notes)
    setNotes('')
  }

  const handleEnd = async () => {
    await onEnd(notes)
    setNotes('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <Clock className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">
                ثبت زمان و وضعیت واقعی برگزاری جلسه
              </h3>
              <p className="text-[11px] text-muted-foreground">
                شروع، ثبت یادداشت‌های حین جلسه و پایان رسمی جلسه
              </p>
            </div>
          </div>

          <Badge
            variant={isInProgress ? 'default' : isHeldOrCompleted ? 'secondary' : 'outline'}
            className="text-[11px] font-bold px-2.5 py-0.5"
          >
            {isInProgress
              ? 'در حال برگزاری'
              : isHeldOrCompleted
              ? 'پایان‌یافته'
              : 'شروع‌نشده'}
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Real-time Session KPI Strip */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">زمان شروع واقعی</span>
            <strong className="text-xs font-bold text-foreground block mt-1">
              {session?.started_at ? formatSolarDateTime(session.started_at) : '—'}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">زمان پایان واقعی</span>
            <strong className="text-xs font-bold text-foreground block mt-1">
              {session?.ended_at
                ? formatSolarDateTime(session.ended_at)
                : isInProgress
                ? 'در حال برگزاری…'
                : '—'}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">وضعیت نشست</span>
            <strong className="text-xs font-bold text-foreground block mt-1">
              {isInProgress
                ? 'نشست فعال'
                : isHeldOrCompleted
                ? 'نشست مختومه'
                : 'در انتظار شروع'}
            </strong>
          </div>
        </div>

        {/* Executive Notes Input */}
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-foreground flex items-center gap-1.5">
            <FileText className="size-3.5 text-primary" />
            یادداشت اجرایی جلسه
          </label>
          <Textarea
            rows={3}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="یادداشت اختیاری برای ثبت نکات اجرایی شروع یا پایان جلسه..."
            className="text-xs rounded-xl border-border/70"
            disabled={busy || isHeldOrCompleted}
          />
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
          {isScheduled && canStart && (
            <Button
              disabled={busy}
              onClick={handleStart}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
            >
              <Play className="size-3.5" />
              <span>{busy ? 'در حال ثبت شروع…' : 'شروع رسمی جلسه'}</span>
            </Button>
          )}

          {isInProgress && canEnd && (
            <Button
              variant="destructive"
              disabled={busy}
              onClick={() => setConfirmEndOpen(true)}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 shadow-xs"
            >
              <Square className="size-3.5" />
              <span>پایان رسمی جلسه</span>
            </Button>
          )}

        </div>

        {/* Governance Notice */}
        <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-border/60 text-[11px] text-muted-foreground leading-relaxed flex items-start gap-2">
          <LockKeyhole className="size-4 text-primary shrink-0 mt-0.5" />
          <span>
            با شروع رسمی جلسه، نسخه‌های زمان‌بندی و اشتراک‌کنندگان قفل و مبنای رسمی ثبت سوابق قرار می‌گیرند. پس از پایان جلسه، امکان ثبت حضور جدید مسدود شده و صورت‌جلسه قابل تدوین خواهد بود.
          </span>
        </div>
      </CardContent>

      <ConfirmDialog
        open={confirmEndOpen}
        onOpenChange={setConfirmEndOpen}
        title="پایان رسمی جلسه تأیید شود؟"
        description="با ثبت پایان جلسه، نشست مختومه اعلام شده و امکان ویرایش فهرست حاضران بسته می‌شود. آیا مایل به خاتمه جلسه هستید؟"
        confirmLabel="تأیید و پایان جلسه"
        variant="destructive"
        onConfirm={handleEnd}
      />
    </Card>
  )
}
