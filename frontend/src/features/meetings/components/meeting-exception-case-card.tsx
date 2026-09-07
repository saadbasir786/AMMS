import React from 'react'
import {
  ShieldCheck,
  Send,
  RotateCcw,
  CheckCircle2,
  CalendarDays,
  FileCheck2,
  CalendarClock
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { ExceptionCase } from '../types'
import { StatusBadge } from '@/components/amms/status-badge'
import { governanceLabels } from '../lib/workspace-labels'

const CASE_LABELS: Record<string, string> = {
  POSTPONEMENT: 'تعویق جلسه',
  CANCELLATION: 'لغو جلسه',
  REOPENING: 'بازگشایی جلسه',
  RESUMPTION: 'فعال‌سازی مجدد و تعیین زمان جدید',
}

const STATUS_LABELS: Record<string, { label: string; color: string }> = {
  DRAFT: { label: 'پیش‌نویس', color: 'text-slate-600 bg-slate-100' },
  UNDER_REVIEW: { label: 'در حال بررسی', color: 'text-indigo-700 bg-indigo-50 border-indigo-200' },
  RETURNED_FOR_CORRECTION: { label: 'برگشت جهت اصلاح', color: 'text-amber-700 bg-amber-50 border-amber-200' },
  READY_TO_APPLY: { label: 'آماده اجرا و اعمال', color: 'text-emerald-700 bg-emerald-50 border-emerald-200' },
  APPLIED: { label: 'اجرا و اعمال‌شده', color: 'text-emerald-800 bg-emerald-100 border-emerald-300' },
  REJECTED: { label: 'رد شده', color: 'text-rose-700 bg-rose-50 border-rose-200' },
  WITHDRAWN: { label: 'پس‌گرفته‌شده', color: 'text-slate-600 bg-slate-100' },
}

type MeetingExceptionCaseCardProps = {
  item: ExceptionCase
  busy: boolean
  onAction: (kind: string, item: ExceptionCase) => void
  onReviewAction: (reviewId: number, action: 'acknowledge' | 'approve' | 'correction') => Promise<void>
}

export function MeetingExceptionCaseCard({
  item,
  busy,
  onAction,
  onReviewAction,
}: MeetingExceptionCaseCardProps) {
  const capabilities = item.action_capabilities
  const statusCfg = STATUS_LABELS[item.status] || {
    label: item.status,
    color: 'text-slate-600 bg-slate-100',
  }

  const reviews = item.reviews || []
  const currentVer = item.current_version

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <span className="text-[11px] font-semibold text-muted-foreground block font-mono">
              پرونده #{item.id} • نسخه {currentVer?.version_number || '۱'}
            </span>
            <h3 className="text-sm sm:text-base font-bold text-foreground mt-0.5">
              {CASE_LABELS[item.case_type] || item.case_type}
            </h3>
          </div>

          <span
            className={cn(
              'px-2.5 py-1 rounded-lg text-xs font-bold border w-fit',
              statusCfg.color
            )}
          >
            {statusCfg.label}
          </span>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Case Meta Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">وضعیت مبنا</span>
            <strong className="text-xs font-bold text-foreground block mt-0.5">
              {item.baseline?.meeting_status ? <StatusBadge status={item.baseline.meeting_status} /> : '—'}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">نوع حاکمیت</span>
            <strong className="text-xs font-bold text-foreground block mt-0.5">
              {governanceLabels[item.governance_mode] || 'نامشخص'}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">زمان ثبت پرونده</span>
            <strong className="text-xs font-bold text-foreground block mt-0.5">
              {item.created_at ? formatSolarDateTime(item.created_at) : '—'}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">وضعیت نسخه فعلی</span>
            <strong className="text-xs font-bold text-foreground block mt-0.5">
              {currentVer?.submitted_at ? 'ارسال‌شده' : 'پیش‌نویس'}
            </strong>
          </div>
        </div>

        {/* Reason Box */}
        {currentVer?.reason && (
          <div className="p-3.5 rounded-xl bg-slate-50/60 dark:bg-slate-900/30 border border-border/50 space-y-1">
            <span className="text-[11px] font-bold text-muted-foreground block">دلیل رسمی ثبت‌شده:</span>
            <p className="text-xs text-foreground leading-relaxed">
              {currentVer.reason}
            </p>
          </div>
        )}

        {/* Reviews Matrix */}
        <div className="space-y-2.5 pt-2 border-t border-border/50">
          <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
            <ShieldCheck className="size-3.5 text-primary" />
            <span>ماتریس بررسی و تأییدات نسخه فعلی:</span>
          </div>

          {reviews.length === 0 ? (
            <p className="text-xs text-muted-foreground py-2">
              بررسی‌ها پس از ارسال پرونده تشکیل می‌شوند.
            </p>
          ) : (
            <div className="space-y-2">
              {reviews.map((r) => (
                <div
                  key={r.id}
                  className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                >
                  <div>
                    <strong className="font-semibold text-foreground block">
                      {r.reviewer_type === 'UNIT' ? r.unit_name || 'ریاست مربوطه' : 'مقام عالی'} • {r.review_requirement === 'APPROVAL' ? 'تأیید رسمی' : 'تأیید دریافت'}
                    </strong>
                    <span className="text-[10px] text-muted-foreground">
                      {r.organizational_unit_id ? `واحد سازمانی #${r.organizational_unit_id}` : 'مقام'}
                    </span>
                    {r.reason && <p className="mt-2 text-xs leading-relaxed">دلیل بررسی: {r.reason}</p>}
                  </div>

                  <div className="flex items-center gap-2">
                    <Badge variant={r.status === 'APPROVED' ? 'default' : 'outline'} className="text-[10px]">
                      {r.status === 'APPROVED' ? 'تأییدشده' : r.status === 'ACKNOWLEDGED' ? 'تأیید دریافت' : r.status === 'CORRECTION_REQUESTED' ? 'درخواست اصلاح' : 'در انتظار'}
                    </Badge>

                    {r.status === 'PENDING' && (
                      <div className="flex gap-1.5">
                        {r.action_capabilities?.acknowledge && <Button
                          size="sm"
                          variant="outline"
                          disabled={busy}
                          onClick={() => onReviewAction(r.id, 'acknowledge')}
                          className="h-7.5 text-xs font-semibold rounded-lg"
                        >
                          تأیید دریافت
                        </Button>}
                        {r.action_capabilities?.approve && <Button
                          size="sm"
                          disabled={busy}
                          onClick={() => onReviewAction(r.id, 'approve')}
                          className="h-7.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                          تأیید
                        </Button>}
                        {r.action_capabilities?.correction && <Button variant="outline" disabled={busy} onClick={() => onReviewAction(r.id, 'correction')}>برگشت جهت اصلاح</Button>}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Action Controls */}
        <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
          {capabilities?.edit && <Button variant="outline" disabled={busy} onClick={() => onAction('edit', item)}>ویرایش و اصلاح دلیل</Button>}
          {capabilities?.reject && <Button variant="destructive" disabled={busy} onClick={() => onAction('reject', item)}>رد پرونده</Button>}
          {capabilities?.submit && (
            <Button
              disabled={busy}
              onClick={() => onAction('submit', item)}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 bg-primary hover:bg-primary/90 text-white shadow-xs"
            >
              <Send className="size-3.5" />
              <span>ارسال پرونده برای بررسی</span>
            </Button>
          )}

          {capabilities?.withdraw && (
            <Button
              variant="outline"
              disabled={busy}
              onClick={() => onAction('withdraw', item)}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 text-muted-foreground hover:text-rose-600"
            >
              <RotateCcw className="size-3.5" />
              <span>پس‌گرفتن پرونده</span>
            </Button>
          )}

          {capabilities?.apply && (
            <Button
              disabled={busy}
              onClick={() => onAction('apply', item)}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
            >
              <CheckCircle2 className="size-3.5" />
              <span>اجرا و اعمال تصمیم نهایی</span>
            </Button>
          )}

          {capabilities?.resume && (
            <Button
              disabled={busy}
              onClick={() => onAction('resume_modal', item)}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 bg-primary hover:bg-primary/90 text-white shadow-xs"
            >
              <CalendarClock className="size-3.5" />
              <span>تعیین زمان جدید و فعال‌سازی جلسه</span>
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
