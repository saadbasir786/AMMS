import React from 'react'
import {
  FilePlus2,
  Users2,
  Layers,
  ShieldAlert,
  CalendarDays,
  CheckCircle2,
  ArrowLeft
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'

type QuickActionsCardProps = {
  canCreateRequest: boolean
}

type QuickActionItem = {
  title: string
  description: string
  to: string
  icon: React.ElementType
  badge?: string
  accentColor: string
}

export function QuickActionsCard({ canCreateRequest }: QuickActionsCardProps) {
  const actions: QuickActionItem[] = [
    {
      title: 'جلسه داخلی ریاست',
      description: 'ثبت درخواست جلسه درون‌واحدی و ارسال جهت تایید رئیس',
      to: '/meeting-requests/create',
      icon: FilePlus2,
      accentColor: 'text-sky-600 bg-sky-50 dark:bg-sky-950/50 dark:text-sky-300',
    },
    {
      title: 'جلسه بین دو ریاست',
      description: 'هماهنگی و تنظیم نشست رسمی میان دو ریاست مختلف',
      to: '/meeting-requests/create-inter-directorate',
      icon: Users2,
      accentColor: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 dark:text-indigo-300',
    },
    {
      title: 'جلسه چندریاستی',
      description: 'تشکیل نشست مشترک و هماهنگی چندجانبه اداری',
      to: '/meeting-requests/create-multi-directorate',
      icon: Layers,
      accentColor: 'text-teal-600 bg-teal-50 dark:bg-teal-950/50 dark:text-teal-300',
    },
    {
      title: 'جلسه با مقام عالی',
      description: 'ثبت درخواست نشست رسمی با حضور و هدایت مقام',
      to: '/meeting-requests/create-high-authority',
      icon: ShieldAlert,
      accentColor: 'text-amber-600 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300',
    },
  ]

  if (!canCreateRequest) {
    return (
      <Card className="border border-border/70 shadow-sm">
        <CardHeader className="p-4 sm:p-5 border-b border-border/60">
          <div className="flex items-center gap-2">
            <CalendarDays className="size-4 text-primary" />
            <h3 className="text-sm font-bold text-foreground">دسترسی‌های سریع</h3>
          </div>
        </CardHeader>
        <CardContent className="p-4 sm:p-5">
          <div className="flex items-center gap-3 p-3.5 rounded-2xl bg-muted/40 text-muted-foreground text-xs">
            <CheckCircle2 className="size-4 text-emerald-500 shrink-0" />
            <span>شما به عنوان کاربر مشاهده‌گر در سیستم فعال هستید.</span>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="border border-border/70 shadow-sm">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <FilePlus2 className="size-4 text-primary" />
            <h3 className="text-sm font-bold text-foreground">ثبت درخواست جدید</h3>
          </div>
          <span className="text-[11px] text-muted-foreground">انتخاب نوع گردش کار</span>
        </div>
      </CardHeader>

      <CardContent className="p-3 sm:p-4 space-y-2">
        {actions.map((act) => {
          const Icon = act.icon
          return (
            <a
              key={act.to}
              href={act.to}
              className="flex items-center justify-between p-3 rounded-xl border border-transparent hover:border-border/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-all duration-150 group"
            >
              <div className="flex items-center gap-3 min-w-0">
                <div
                  className={cn(
                    'size-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105',
                    act.accentColor
                  )}
                >
                  <Icon className="size-4.5" />
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="text-xs sm:text-sm font-bold text-foreground group-hover:text-primary transition-colors truncate">
                      {act.title}
                    </span>
                    {act.badge && (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-primary/10 text-primary font-mono font-semibold">
                        {act.badge}
                      </span>
                    )}
                  </div>
                  <p className="text-[11px] text-muted-foreground truncate mt-0.5">
                    {act.description}
                  </p>
                </div>
              </div>

              <div className="size-7 rounded-lg flex items-center justify-center text-muted-foreground group-hover:text-primary group-hover:bg-primary/10 transition-colors shrink-0 ms-2">
                <ArrowLeft className="size-3.5" />
              </div>
            </a>
          )
        })}
      </CardContent>
    </Card>
  )
}
