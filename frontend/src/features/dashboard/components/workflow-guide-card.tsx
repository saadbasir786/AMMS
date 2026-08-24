import React from 'react'
import {
  FilePlus2,
  CheckCircle2,
  CalendarDays,
  FileCheck2,
  HelpCircle
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'

export function WorkflowGuideCard() {
  const steps = [
    {
      num: '۱',
      title: 'ثبت درخواست جلسه',
      desc: 'تعیین اهداف، موضوع و اشتراک‌کنندگان پیشنهادی',
      icon: FilePlus2,
      color: 'bg-sky-500 text-white',
    },
    {
      num: '۲',
      title: 'بررسی و تأیید ریاست',
      desc: 'ارزیابی نیاز و صدور موافقت رسمی ریاست‌ها',
      icon: CheckCircle2,
      color: 'bg-indigo-500 text-white',
    },
    {
      num: '۳',
      title: 'هماهنگی و زمان‌بندی',
      desc: 'تنظیم زمان رسمی، مکان و فهرست نهایی حاضرین',
      icon: CalendarDays,
      color: 'bg-teal-500 text-white',
    },
    {
      num: '۴',
      title: 'برگزاری و پیگیری مصوبات',
      desc: 'ثبت حاضری، صورت‌جلسه و ارجاع وظایف اجرایی',
      icon: FileCheck2,
      color: 'bg-emerald-500 text-white',
    },
  ]

  return (
    <Card className="border border-border/70 shadow-sm">
      <CardHeader className="border-b border-border/60 p-4 sm:p-5">
        <div className="flex items-center gap-2">
          <HelpCircle className="size-4 text-primary" />
          <h2 className="text-sm sm:text-base font-bold text-foreground">
            مراحل گردش کار جلسات
          </h2>
        </div>
        <p className="text-xs text-muted-foreground mt-0.5">
          راهنمای گام‌به‌گام فرآیند اداری تشکیل جلسه از درخواست تا پیگیری
        </p>
      </CardHeader>
      <CardContent className="p-4 sm:p-5 space-y-3">
        <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
          {steps.map((step) => {
            const Icon = step.icon
            return (
              <div
                key={step.num}
                className="flex items-start gap-3 p-3 rounded-xl border border-border/60 bg-slate-50/50 dark:bg-slate-900/30 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition-colors"
              >
                <div
                  className={`size-8 rounded-xl flex items-center justify-center text-xs font-black shrink-0 shadow-xs ${step.color}`}
                >
                  <Icon className="size-4" />
                </div>
                <div className="space-y-0.5 min-w-0">
                  <div className="flex items-center gap-1.5">
                    <span className="text-[11px] font-bold text-primary">گام {step.num}:</span>
                    <strong className="text-xs font-bold text-foreground truncate block">
                      {step.title}
                    </strong>
                  </div>
                  <p className="text-[11px] text-muted-foreground leading-relaxed">
                    {step.desc}
                  </p>
                </div>
              </div>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}
