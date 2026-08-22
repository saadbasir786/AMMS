import {
  AlertCircle,
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  Clock3,
  FilePlus2,
  Plus,
} from 'lucide-react'

import { MetricCard } from '@/components/amms/metric-card'
import { PageHeader } from '@/components/amms/page-header'
import { SectionCard } from '@/components/amms/section-card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

type DashboardData = {
  my_requests?: number
  pending_approval?: number
  returned?: number
  upcoming_meetings?: number
}

type DashboardRequest = {
  id: number | string
  subject?: string | null
  request_number?: string | null
  status?: string | null
  requesting_unit?: { name?: string | null } | null
}

type DashboardOverviewProps = {
  data: DashboardData
  requests: DashboardRequest[]
  canCreateRequest: boolean
  statusLabels: Record<string, string>
}

function statusTone(status?: string | null) {
  const value = status || ''
  if (/(REJECTED|CANCELLED)/.test(value)) {
    return 'border-destructive/20 bg-destructive/10 text-destructive'
  }
  if (/(RETURNED|ACTION_REQUIRED|CLARIFICATION_REQUIRED)/.test(value)) {
    return 'border-amber-500/20 bg-amber-500/10 text-amber-800 dark:text-amber-200'
  }
  if (/(APPROVED|ACKNOWLEDGED|ISSUED|CONFIRMED|COMPLETED|HELD)/.test(value)) {
    return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'
  }
  return 'border-primary/15 bg-primary/8 text-primary'
}

const workflowSteps = ['ثبت درخواست', 'تأیید ریاست', 'زمان‌بندی', 'ایجاد جلسه']

export function DashboardOverview({
  data,
  requests,
  canCreateRequest,
  statusLabels,
}: DashboardOverviewProps) {
  return (
    <div>
      <PageHeader
        eyebrow="نمای کلی سیستم"
        title="داشبورد مدیریتی"
        description="وضعیت درخواست‌ها و جلسات حوزه صلاحیت شما در یک نگاه."
        action={
          canCreateRequest ? (
            <Button asChild size="lg" className="gap-2">
              <a href="/meeting-requests/create">
                <Plus className="size-4" />
                درخواست جدید
              </a>
            </Button>
          ) : undefined
        }
      />

      <section aria-label="شاخص‌های کلیدی" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="درخواست‌های من" value={data.my_requests || 0} icon={FilePlus2} />
        <MetricCard label="منتظر تأیید" value={data.pending_approval || 0} icon={Clock3} tone="warning" />
        <MetricCard label="برگشتی جهت اصلاح" value={data.returned || 0} icon={AlertCircle} tone="danger" />
        <MetricCard label="جلسات آینده" value={data.upcoming_meetings || 0} icon={CalendarDays} tone="success" />
      </section>

      <div className="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.85fr)]">
        <SectionCard
          eyebrow="فعالیت اخیر"
          title="درخواست‌های اخیر"
          description="آخرین درخواست‌های قابل مشاهده در حوزه دسترسی شما."
          action={
            <Button asChild variant="ghost" size="sm" className="gap-1.5 text-primary">
              <a href="/meeting-requests">
                مشاهده همه
                <ArrowLeft className="size-3.5" />
              </a>
            </Button>
          }
          contentClassName="p-0"
        >
          {requests.length > 0 ? (
            <div className="divide-y divide-border/60">
              {requests.slice(0, 5).map((request) => (
                <a
                  key={request.id}
                  href={`/meeting-requests/${request.id}`}
                  className="group flex items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/55 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring/50"
                >
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground transition-colors group-hover:bg-primary/10 group-hover:text-primary">
                    <FilePlus2 className="size-4" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <strong className="block truncate text-sm font-semibold text-foreground">
                      {request.subject || 'بدون موضوع'}
                    </strong>
                    <span className="mt-1 block truncate text-[11px] text-muted-foreground">
                      {request.request_number || 'پیش‌نویس'} · {request.requesting_unit?.name || 'ریاست نامشخص'}
                    </span>
                  </div>
                  <Badge
                    variant="outline"
                    className={cn('max-w-40 shrink-0 truncate border text-[10px] font-medium', statusTone(request.status))}
                  >
                    {request.status ? statusLabels[request.status] || request.status : 'نامشخص'}
                  </Badge>
                  <ArrowLeft className="hidden size-4 shrink-0 text-muted-foreground transition-transform group-hover:-translate-x-0.5 sm:block" />
                </a>
              ))}
            </div>
          ) : (
            <div className="flex min-h-52 flex-col items-center justify-center px-6 py-10 text-center">
              <div className="flex size-11 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                <FilePlus2 className="size-5" />
              </div>
              <strong className="mt-4 text-sm font-semibold text-foreground">هنوز درخواستی ثبت نشده است</strong>
              <span className="mt-1 text-xs text-muted-foreground">درخواست‌های اخیر شما در این بخش نمایش داده می‌شوند.</span>
            </div>
          )}
        </SectionCard>

        <SectionCard
          eyebrow="راهنمای جریان کاری"
          title="FLOW-01"
          description="جریان استاندارد درخواست داخلی ریاست"
          className="overflow-hidden"
        >
          <div className="rounded-xl border border-primary/10 bg-primary/[0.045] p-4">
            <div className="flex items-start gap-3">
              <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                <CheckCircle2 className="size-[18px]" />
              </div>
              <p className="text-xs leading-6 text-muted-foreground">
                درخواست داخلی ریاست پس از ثبت توسط مدیریت اجرائیه، برای بررسی رئیس ریاست ارسال شده و سپس در فضای جداگانه زمان‌بندی می‌گردد.
              </p>
            </div>
          </div>

          <div className="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
            {workflowSteps.map((step, index) => (
              <div key={step} className="flex items-center gap-3 rounded-xl border border-border/70 bg-background px-3 py-3">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-muted text-[11px] font-bold text-primary">
                  {index + 1}
                </span>
                <span className="text-xs font-medium text-foreground">{step}</span>
              </div>
            ))}
          </div>
        </SectionCard>
      </div>
    </div>
  )
}
