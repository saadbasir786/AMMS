import React from 'react'
import { Link } from 'react-router-dom'
import { Bell, CheckCheck, Clock, ArrowRight, Inbox, MailCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { SystemNotification } from '../types'

type NotificationsListProps = {
  notifications: SystemNotification[]
  busy: boolean
  onMarkAsRead?: (id: number) => void
}

export function NotificationsList({
  notifications,
  busy,
  onMarkAsRead,
}: NotificationsListProps) {
  if (notifications.length === 0) {
    return (
      <div
        className="text-center py-12 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <Inbox className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
        <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
          اعلان جدیدی در صندوق پیام‌ها وجود ندارد
        </h4>
        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto leading-relaxed">
          کلیه رویدادها، تغییرات زمان جلسات و وظایف جدید از طریق این مرکز به اطلاع شما خواهد رسید.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-3" dir="rtl">
      {notifications.map((n) => {
        const isUnread = !n.read_at

        return (
          <Card
            key={n.id}
            className={cn(
              'border shadow-xs transition-all',
              isUnread
                ? 'border-primary/40 bg-card shadow-sm'
                : 'border-border/60 bg-slate-50/50 dark:bg-slate-900/30'
            )}
          >
            <CardContent className="p-4 sm:p-5">
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                  <div
                    className={cn(
                      'size-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
                      isUnread
                        ? 'bg-primary/15 text-primary'
                        : 'bg-slate-200/60 dark:bg-slate-800 text-muted-foreground'
                    )}
                  >
                    <Bell className="size-4" />
                  </div>

                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <h4 className="text-xs sm:text-sm font-bold text-foreground">
                        {n.title}
                      </h4>
                      {isUnread && (
                        <Badge className="bg-primary text-white text-[10px] px-1.5 py-0 font-semibold">
                          جدید
                        </Badge>
                      )}
                    </div>

                    <p className="text-xs text-muted-foreground leading-relaxed">
                      {n.message}
                    </p>

                    <div className="flex items-center gap-2 pt-1 font-mono text-[10px] text-muted-foreground/70">
                      <Clock className="size-3" />
                      <span>{n.created_at ? formatSolarDateTime(n.created_at) : '—'}</span>
                    </div>
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-2 shrink-0">
                  {n.action_url && (
                    <Button
                      asChild
                      variant="outline"
                      size="sm"
                      className="h-8 text-xs font-semibold rounded-xl gap-1"
                    >
                      <Link to={n.action_url}>
                        <span>مشاهده</span>
                        <ArrowRight className="size-3" />
                      </Link>
                    </Button>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}
