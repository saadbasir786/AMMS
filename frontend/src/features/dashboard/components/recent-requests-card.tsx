import React from 'react'
import {
  FileText,
  ArrowLeft,
  Calendar,
  Building,
  Plus
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn, formatPersianDate } from '@/lib/utils'

export type DashboardRequestItem = {
  id: number | string
  subject?: string | null
  request_number?: string | null
  status?: string | null
  created_at?: string | null
  requesting_unit?: { name?: string | null } | null
  purpose?: string | null
}

type RecentRequestsCardProps = {
  requests: DashboardRequestItem[]
  statusLabels: Record<string, string>
  canCreateRequest: boolean
}

function getStatusBadgeStyle(status?: string | null) {
  const s = status || ''
  if (/REJECTED|CANCELLED/.test(s)) {
    return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300'
  }
  if (/RETURNED|ACTION_REQUIRED|CLARIFICATION_REQUIRED/.test(s)) {
    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300'
  }
  if (/APPROVED|ACKNOWLEDGED|ISSUED|CONFIRMED|COMPLETED|HELD|SCHEDULE_CONFIRMED/.test(s)) {
    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300'
  }
  if (/PENDING|SCHEDULING|SUBMITTED|UNDER_/.test(s)) {
    return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-300'
  }
  return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-300'
}

export function RecentRequestsCard({
  requests,
  statusLabels,
  canCreateRequest,
}: RecentRequestsCardProps) {
  const displayRows = requests.slice(0, 6)

  return (
    <Card className="border border-border/70 shadow-sm overflow-hidden flex flex-col justify-between">
      <div>
        <CardHeader className="border-b border-border/60 p-4 sm:p-5 flex flex-row items-center justify-between">
          <div>
            <div className="flex items-center gap-2">
              <FileText className="size-4 text-primary" />
              <h3 className="text-sm font-bold text-foreground">درخواست‌های اخیر</h3>
            </div>
            <p className="text-xs text-muted-foreground mt-0.5">
              آخرین درخواست‌های ثبت‌شده در کارتابل شما
            </p>
          </div>

          <Button
            asChild
            variant="ghost"
            size="sm"
            className="text-xs text-primary hover:text-primary gap-1"
          >
            <a href="/meeting-requests">
              <span>مشاهده همه</span>
              <ArrowLeft className="size-3" />
            </a>
          </Button>
        </CardHeader>

        <CardContent className="p-0">
          {displayRows.length > 0 ? (
            <div className="divide-y divide-border/60">
              {displayRows.map((req) => {
                const statusLabel = (statusLabels && req.status && statusLabels[req.status]) || req.status || 'پیش‌نویس'
                return (
                  <div
                    key={req.id}
                    className="p-3.5 sm:p-4 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors flex items-center justify-between gap-3 group"
                  >
                    <div className="space-y-1 min-w-0">
                      <div className="flex items-center gap-2">
                        {req.request_number && (
                          <span className="text-[11px] font-mono text-muted-foreground bg-muted/60 px-1.5 py-0.5 rounded">
                            {req.request_number}
                          </span>
                        )}
                        <a
                          href={`/meeting-requests/${req.id}`}
                          className="text-xs sm:text-sm font-bold text-foreground group-hover:text-primary transition-colors truncate block"
                        >
                          {req.subject || 'بدون موضوع'}
                        </a>
                      </div>

                      <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                        {req.requesting_unit?.name && (
                          <span className="flex items-center gap-1">
                            <Building className="size-3 text-muted-foreground" />
                            {req.requesting_unit.name}
                          </span>
                        )}
                        {req.created_at && (
                          <span className="flex items-center gap-1">
                            <Calendar className="size-3 text-muted-foreground" />
                            {formatPersianDate(req.created_at)}
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      <Badge
                        variant="outline"
                        className={cn('text-[10px] font-semibold px-2 py-0.5', getStatusBadgeStyle(req.status))}
                      >
                        {statusLabel}
                      </Badge>
                      <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="size-7 p-0 rounded-lg text-muted-foreground group-hover:text-primary"
                        aria-label="مشاهده درخواست"
                      >
                        <a href={`/meeting-requests/${req.id}`}>
                          <ArrowLeft className="size-3.5" />
                        </a>
                      </Button>
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <div className="p-8 text-center space-y-2">
              <div className="size-10 rounded-2xl bg-muted/60 text-muted-foreground flex items-center justify-center mx-auto">
                <FileText className="size-5" />
              </div>
              <div className="space-y-1">
                <p className="text-xs font-semibold text-foreground">هنوز درخواستی ثبت نشده است</p>
                <p className="text-[11px] text-muted-foreground">
                  درخواست‌های جلسه به محض ثبت در این بخش نمایش داده می‌شوند.
                </p>
              </div>
              {canCreateRequest && (
                <div className="pt-2">
                  <Button asChild size="sm" className="text-xs gap-1.5">
                    <a href="/meeting-requests/create">
                      <Plus className="size-3.5" />
                      <span>ثبت اولین درخواست</span>
                    </a>
                  </Button>
                </div>
              )}
            </div>
          )}
        </CardContent>
      </div>
    </Card>
  )
}
