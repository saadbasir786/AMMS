import React from 'react'
import {
  GitBranch,
  CalendarDays,
  Clock,
  MapPin,
  CheckCircle2,
  AlertCircle
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { formatSolarDate } from '@/lib/calendar-utils'
import type { RescheduleCase } from '../types'
import { governanceLabels, workspaceStatusLabels } from '../lib/workspace-labels'

type RescheduleCaseCardProps = {
  activeCase: RescheduleCase
  requiredUnitCount: number
}

export function RescheduleCaseCard({
  activeCase,
  requiredUnitCount,
}: RescheduleCaseCardProps) {
  const activeProposal = activeCase.proposals?.find((p) => p.status === 'ACTIVE')
  const ackCount = activeProposal?.acknowledgements?.length || 0
  const openAlternatives =
    activeCase.alternative_requests?.filter((a) => a.status === 'OPEN') || []

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between gap-3">
          <div>
            <span className="text-[11px] font-semibold text-muted-foreground block">
              پرونده رسمی تغییر زمان‌بندی • {governanceLabels[activeCase.governance_mode] || 'نامشخص'}
            </span>
            <h3 className="text-sm sm:text-base font-bold text-foreground mt-0.5">
              پرونده فعال تغییر زمان جلسه
            </h3>
          </div>
          <Badge variant="outline" className="text-xs px-2.5 py-1">
            {workspaceStatusLabels[activeCase.status] || 'نامشخص'}
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {activeCase.reason && (
          <p className="text-xs text-muted-foreground leading-relaxed">
            {activeCase.reason}
          </p>
        )}

        {/* Counters */}
        <div className="grid grid-cols-3 gap-3 text-xs">
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">نسخه مبنا</span>
            <strong className="text-sm font-bold text-foreground block mt-0.5 font-mono">
              نسخه {activeCase.baseline_schedule_version_id}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">تأییدهای ثبت‌شده</span>
            <strong className="text-sm font-bold text-foreground block mt-0.5 font-mono">
              {ackCount} از {requiredUnitCount}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">درخواست‌های بدیل باز</span>
            <strong className="text-sm font-bold text-foreground block mt-0.5 font-mono">
              {openAlternatives.length}
            </strong>
          </div>
        </div>

        {/* Active Proposal Card */}
        {activeProposal && (
          <div className="p-3.5 rounded-xl bg-primary/5 border border-primary/20 space-y-2">
            <div className="flex items-center gap-1.5 text-xs font-bold text-primary">
              <GitBranch className="size-3.5" />
              <span>پیشنهاد فعال جاری:</span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-foreground">
              <div className="flex items-center gap-1.5">
                <CalendarDays className="size-3.5 text-primary shrink-0" />
                <span>{formatSolarDate(activeProposal.meeting_date)} هـ.ش</span>
              </div>

              <div className="flex items-center gap-1.5">
                <Clock className="size-3.5 text-primary shrink-0" />
                <span>
                  {activeProposal.start_time} تا {activeProposal.end_time}
                </span>
              </div>

              <div className="flex items-center gap-1.5">
                <MapPin className="size-3.5 text-primary shrink-0" />
                <span className="truncate">{activeProposal.location}</span>
              </div>
            </div>

            {activeProposal.comment && (
              <p className="text-[11px] text-muted-foreground pt-1 border-t border-primary/10">
                یادداشت: {activeProposal.comment}
              </p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
