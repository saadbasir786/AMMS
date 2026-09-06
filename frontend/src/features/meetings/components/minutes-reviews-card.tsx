import React, { useState } from 'react'
import {
  FileCheck2,
  CheckCircle2,
  AlertCircle,
  RotateCcw,
  Download,
  Building2,
  ShieldCheck
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { cn } from '@/lib/utils'
import type { MeetingMinutes, MinutesReview } from '../types'

const REVIEW_STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  PENDING: {
    label: 'در انتظار بررسی',
    color: 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200',
  },
  ACKNOWLEDGED: {
    label: 'تأیید شده',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200',
  },
  APPROVED: {
    label: 'تأیید مقام عالی',
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200',
  },
  CORRECTION_REQUESTED: {
    label: 'درخواست اصلاح',
    color: 'text-rose-700 bg-rose-50 dark:bg-rose-950/40 border-rose-200',
  },
}

type MinutesReviewsCardProps = {
  meetingId: number | string
  minutes: MeetingMinutes | null
  busy: boolean
  onAcknowledge: (unitId: number) => Promise<void>
  onRequestCorrection: (review: MinutesReview, reason: string) => Promise<void>
  onApproveAuthority: () => Promise<void>
  onFinalize: () => Promise<void>
  onDownloadPdf: () => Promise<void>
  canReview?: boolean
  reviewableUnitIds?: number[]
  delegatedReviewUnitIds?: number[]
  canApproveAuthority?: boolean
  canFinalize?: boolean
  canDocument?: boolean
}

export function MinutesReviewsCard({
  meetingId,
  minutes,
  busy,
  onAcknowledge,
  onRequestCorrection,
  onApproveAuthority,
  onFinalize,
  onDownloadPdf,
  canReview = false,
  reviewableUnitIds = [],
  delegatedReviewUnitIds = [],
  canApproveAuthority = false,
  canFinalize = false,
  canDocument = false,
}: MinutesReviewsCardProps) {
  const [correctionReason, setCorrectionReason] = useState('')
  const [selectedReview, setSelectedReview] = useState<MinutesReview | null>(null)
  const [confirmFinalizeOpen, setConfirmFinalizeOpen] = useState(false)

  if (!minutes) return null

  const version = minutes.current_version
  const reviews = version?.reviews || []
  const isFinalized = minutes.status === 'FINALIZED'
  const isReadyToFinalize = minutes.status === 'READY_TO_FINALIZE'

  const handleCorrectionSubmit = async () => {
    if (!selectedReview || !correctionReason.trim()) return
    await onRequestCorrection(selectedReview, correctionReason)
    setSelectedReview(null)
    setCorrectionReason('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          بررسی و تاییدات صورت‌جلسه
        </h3>
        <p className="text-[11px] text-muted-foreground">
          وضعیت تایید ریاست‌های اشتراک‌کننده و تصویب نهایی
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Reviews List */}
        {reviews.length === 0 ? (
          <p className="text-xs text-muted-foreground text-center py-2">
            در این مرحله تأییدیه‌ای الزامی نیست یا پیش‌نویس ارسال نشده است.
          </p>
        ) : (
          <div className="space-y-2.5">
            {reviews.map((rev) => {
              const isAuthorityReview = rev.reviewer_type === 'HIGH_AUTHORITY'
              const canActOnReview = isAuthorityReview
                ? canApproveAuthority
                : canReview && reviewableUnitIds.includes(rev.organizational_unit_id)
              const isDelegatedReview = !isAuthorityReview && delegatedReviewUnitIds.includes(rev.organizational_unit_id)
              const cfg = REVIEW_STATUS_CONFIG[rev.status] || {
                label: rev.status,
                color: 'text-slate-600 bg-slate-100 border-slate-200',
              }

              return (
                <div
                  key={rev.id}
                  className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 space-y-2"
                >
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1.5 min-w-0">
                      <Building2 className="size-3.5 text-primary shrink-0" />
                      <strong className="text-xs font-bold text-foreground truncate">
                        {isAuthorityReview ? 'مقام عالی' : rev.unit?.name || 'واحد سازمانی'}
                      </strong>
                    </div>

                    <span
                      className={cn(
                        'text-[10px] font-bold px-2 py-0.5 rounded-md border shrink-0',
                        cfg.color
                      )}
                    >
                      {cfg.label}
                    </span>
                  </div>

                  {rev.reason && (
                    <p className="text-[11px] text-rose-700 dark:text-rose-400 bg-rose-50/50 dark:bg-rose-950/30 p-2 rounded-lg border border-rose-200/60">
                      دلیل درخواست اصلاح: {rev.reason}
                    </p>
                  )}

                  {rev.status === 'PENDING' && !isFinalized && canActOnReview && (
                    <div className="flex items-center gap-2 pt-1">
                      <Button
                        size="sm"
                        disabled={busy}
                        onClick={() =>
                          isAuthorityReview
                            ? onApproveAuthority()
                            : onAcknowledge(rev.organizational_unit_id)
                        }
                        className="h-7.5 text-[11px] font-semibold rounded-lg gap-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                      >
                        <CheckCircle2 className="size-3" />
                        <span>{isAuthorityReview ? 'تأیید مقام عالی' : 'تأیید صورت‌جلسه'}</span>
                      </Button>

                      {isDelegatedReview && (
                        <span className="text-[10px] font-semibold text-muted-foreground">با صلاحیت تفویض‌شده</span>
                      )}

                      <Button
                        size="sm"
                        variant="outline"
                        disabled={busy}
                        onClick={() => setSelectedReview(rev)}
                        className="h-7.5 text-[11px] font-semibold rounded-lg gap-1 text-rose-600 hover:bg-rose-50"
                      >
                        <RotateCcw className="size-3" />
                        <span>درخواست اصلاح</span>
                      </Button>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {/* Correction Dialog / Box */}
        {selectedReview && (
          <div className="p-3.5 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 space-y-2">
            <span className="text-xs font-bold text-amber-900 dark:text-amber-300 block">
              ثبت دلیل درخواست اصلاح ({selectedReview.unit?.name})
            </span>
            <Textarea
              rows={2}
              value={correctionReason}
              onChange={(e) => setCorrectionReason(e.target.value)}
              placeholder="توضیحات و موارد نیازمند اصلاح را وارد نمایید..."
              className="text-xs rounded-xl bg-card border-amber-300"
            />
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                disabled={busy || !correctionReason.trim()}
                onClick={handleCorrectionSubmit}
                className="h-7.5 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white"
              >
                ارسال درخواست اصلاح
              </Button>
              <Button
                size="sm"
                variant="ghost"
                onClick={() => setSelectedReview(null)}
                className="h-7.5 text-xs font-semibold rounded-lg"
              >
                انصراف
              </Button>
            </div>
          </div>
        )}

        {/* Finalize Button */}
        {!isFinalized && isReadyToFinalize && canFinalize && (
          <Button
            disabled={busy}
            onClick={() => setConfirmFinalizeOpen(true)}
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
          >
            <ShieldCheck className="size-4" />
            <span>نهایی‌سازی و صدور رسمی صورت‌جلسه</span>
          </Button>
        )}

        {/* PDF Download */}
        {isFinalized && canDocument && (
          <Button
            onClick={onDownloadPdf}
            disabled={busy}
            className="w-full h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Download className="size-4" />
            <span>{busy ? 'در حال دریافت…' : 'دریافت فایل PDF رسمی صورت‌جلسه'}</span>
          </Button>
        )}
      </CardContent>

      <ConfirmDialog
        open={confirmFinalizeOpen}
        onOpenChange={setConfirmFinalizeOpen}
        title="نهایی‌سازی و صدور رسمی صورت‌جلسه"
        description="آیا از نهایی‌سازی صورت‌جلسه اطمینان دارید؟ پس از تأیید ریاست‌های لازم، متن قفل شده و سند رسمی صادر می‌گردد."
        confirmLabel="تأیید و صدور نهایی"
        variant="default"
        onConfirm={onFinalize}
      />
    </Card>
  )
}
