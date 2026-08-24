import React from 'react'
import {
  FilePlus2,
  CheckCircle2,
  Users2,
  Calendar,
  Layers,
  ShieldCheck,
  HelpCircle,
  Clock3
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'

type FormSideGuideProps = {
  mode?: 'internal' | 'inter-directorate' | 'multi' | 'authority'
}

export function FormSideGuide({ mode = 'internal' }: FormSideGuideProps) {
  const getGuideContent = () => {
    switch (mode) {
      case 'inter-directorate':
        return {
          title: 'جلسه بین دو ریاست',
          subtitle: 'فرآیند تأیید دوطرفه اداری',
          steps: [
            {
              title: 'ثبت و پیش‌نویس',
              desc: 'درخواست توسط مدیر اجرائیه ریاست مبدأ ایجاد می‌شود.',
            },
            {
              title: 'تأیید رئیس مبدأ',
              desc: 'رئیس ریاست درخواست‌کننده صحت موضوع را تأیید می‌کند.',
            },
            {
              title: 'بررسی ریاست مقصد',
              desc: 'ریاست مقابل موضوع را بررسی و در صورت موافقت تأیید می‌کند.',
            },
            {
              title: 'مذاکره زمان‌بندی',
              desc: 'دو ریاست زمان و اشتراک‌کنندگان را نهایی می‌کنند.',
            },
          ],
        }
      case 'multi':
        return {
          title: 'جلسه چندریاستی',
          subtitle: 'هماهنگی چندجانبه اداری',
          steps: [
            {
              title: 'ثبت درخواست',
              desc: 'تعیین حداقل دو ریاست مقصد و اهداف مشترک.',
            },
            {
              title: 'تأیید ریاست مبدأ',
              desc: 'موافقت اولیه ریاست پیشنهاددهنده ثبت می‌شود.',
            },
            {
              title: 'تأیید ریاست‌های مقصد',
              desc: 'هر یک از ریاست‌ها درخواست را بررسی می‌کنند.',
            },
            {
              title: 'زمان‌بندی نهایی',
              desc: 'هماهنگی تالار و اشتراک‌کنندگان چندگانه.',
            },
          ],
        }
      case 'authority':
        return {
          title: 'جلسه با مقام عالی',
          subtitle: 'گردش کار رسمی مقام',
          steps: [
            {
              title: 'ثبت با توجیه اداری',
              desc: 'درج ضرورت جلسه و خروجی مورد انتظار از مقام.',
            },
            {
              title: 'تأیید رئیس مربوطه',
              desc: 'تأیید ضرورت جلسه توسط رئیس ریاست.',
            },
            {
              title: 'بررسی دفتر مقام',
              desc: 'ارزیابی و ارجاع به مقام عالی مربوطه.',
            },
            {
              title: 'تصمیم و زمان‌بندی',
              desc: 'موافقت و هماهنگی زمان توسط سکرتریت مقام.',
            },
          ],
        }
      default:
        return {
          title: 'جلسه داخلی ریاست',
          subtitle: 'گردش کار درون‌سازمانی',
          steps: [
            {
              title: 'ثبت درخواست اولیه',
              desc: 'تکمیل موضوع، هدف، زمان و اشتراک‌کنندگان مدنظر.',
            },
            {
              title: 'بررسی رئیس ریاست',
              desc: 'ارزیابی ضرورت برگزاری توسط رئیس مربوطه.',
            },
            {
              title: 'تنظیم برنامه رسمی',
              desc: 'تخصیص تالار و ثبت ساعت دقیق برگزاری.',
            },
            {
              title: 'ایجاد جلسه رسمی',
              desc: 'ارسال دعوت‌نامه و فعال‌سازی در تقویم اداری.',
            },
          ],
        }
    }
  }

  const { title, subtitle, steps } = getGuideContent()

  return (
    <Card className="border border-border/70 shadow-xs sticky top-4 bg-gradient-to-b from-card to-slate-50/40 dark:to-slate-900/20">
      <CardHeader className="p-3.5 sm:p-4 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <HelpCircle className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">{title}</h3>
            <p className="text-[11px] text-muted-foreground">{subtitle}</p>
          </div>
        </div>
      </CardHeader>
      <CardContent className="p-3.5 sm:p-4 space-y-3">
        <div className="space-y-2.5 relative">
          {steps.map((step, idx) => (
            <div key={idx} className="flex items-start gap-2.5 text-xs">
              <div className="size-5 rounded-full bg-primary/15 text-primary flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">
                {idx + 1}
              </div>
              <div className="space-y-0.5 min-w-0">
                <strong className="font-bold text-foreground text-xs block">
                  {step.title}
                </strong>
                <p className="text-[11px] text-muted-foreground leading-relaxed">
                  {step.desc}
                </p>
              </div>
            </div>
          ))}
        </div>

        <div className="pt-2.5 border-t border-border/50 text-[11px] text-muted-foreground flex items-center gap-1.5">
          <Clock3 className="size-3.5 text-primary shrink-0" />
          <span>امکان ذخیره به صورت پیش‌نویس فراهم است.</span>
        </div>
      </CardContent>
    </Card>
  )
}
