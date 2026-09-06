import React from 'react'
import { Users, User, ShieldCheck } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'

type RequestParticipantsCardProps = {
  participants?: any[]
}

export function RequestParticipantsCard({
  participants = [],
}: RequestParticipantsCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <Users className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">
                فهرست اشتراک‌کنندگان
              </h3>
              <p className="text-[11px] text-muted-foreground">
                افراد مدعو و اشتراک‌کننده در جلسه
              </p>
            </div>
          </div>

          <Badge variant="secondary" className="text-[11px] font-mono px-2 py-0.5">
            {participants.length} نفر
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5">
        {participants.length === 0 ? (
          <div className="text-center py-6 text-muted-foreground text-xs space-y-1">
            <User className="size-6 mx-auto opacity-40 mb-1" />
            <p>اشتراک‌کننده‌ای برای این درخواست ثبت نشده است.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            {participants.map((p: any) => {
              const fullName = p.employee?.full_name || 'کارمند'
              const unitName = p.employee?.unit?.name || 'واحد سازمانی'
              const positionTitle = p.employee?.position?.title
              const initial = fullName.charAt(0)
              const isFinal = p.participant_type === 'FINAL'

              return (
                <div
                  key={p.id}
                  className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 hover:bg-slate-100/60 dark:hover:bg-slate-800/50 transition-colors"
                >
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div className="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                      {initial}
                    </div>
                    <div className="min-w-0">
                      <strong className="text-xs font-bold text-foreground block truncate">
                        {fullName}
                      </strong>
                      <span className="text-[10px] text-muted-foreground block truncate">
                        {positionTitle ? `${positionTitle} · ${unitName}` : unitName}
                      </span>
                    </div>
                  </div>

                  <Badge
                    variant={isFinal ? 'default' : 'outline'}
                    className="text-[10px] px-2 py-0.5 shrink-0"
                  >
                    {isFinal ? 'نهایی' : 'پیشنهادی'}
                  </Badge>
                </div>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
