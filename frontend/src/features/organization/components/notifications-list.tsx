import React from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  Bell,
  Clock,
  ArrowRight,
  Inbox,
  Check,
  ChevronRight,
  ChevronLeft,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { cn } from '@/lib/utils'
import type { SystemNotification, PaginationMeta } from '../types'

type NotificationsListProps = {
  notifications: SystemNotification[]
  busy: boolean
  pagination?: PaginationMeta | null
  onPageChange?: (page: number) => void
  onMarkAsRead?: (id: number) => void
}

export function NotificationsList({
  notifications,
  busy,
  pagination,
  onPageChange,
  onMarkAsRead,
}: NotificationsListProps) {
  const navigate = useNavigate()

  if (notifications.length === 0) {
    return (
      <div
        className="text-center py-14 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <Inbox className="size-10 mx-auto text-muted-foreground/40 mb-3" />
        <h4 className="text-sm font-bold text-foreground mb-1.5">
          اعلان جدیدی در صندوق پیام‌ها وجود ندارد
        </h4>
        <p className="text-xs text-muted-foreground max-w-md mx-auto leading-relaxed">
          کلیه رویدادهای اداری، گردش کار جلسات، دعوت‌نامه‌ها و وظایف جدید از طریق این بخش به اطلاع شما خواهد رسید.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-4" dir="rtl">
      <div className="space-y-3">
        {notifications.map((n) => {
          const isUnread = !n.read_at

          return (
            <Card
              key={n.id}
              className={cn(
                'rounded-2xl transition-all',
                isUnread
                  ? 'border-primary/40 bg-card shadow-xs ring-1 ring-primary/10'
                  : 'border-border/60 bg-card/60'
              )}
            >
              <CardContent
                className={cn('p-4 sm:p-5', n.action_url && 'cursor-pointer')}
                onClick={() => {
                  if (!n.action_url) return
                  if (isUnread) onMarkAsRead?.(n.id)
                  navigate(n.action_url)
                }}
              >
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                  <div className="flex items-start gap-3">
                    <div
                      className={cn(
                        'size-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
                        isUnread
                          ? 'bg-primary/15 text-primary'
                          : 'bg-muted text-muted-foreground'
                      )}
                    >
                      <Bell className="size-4" />
                    </div>

                    <div className="space-y-1.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-xs sm:text-sm font-bold text-foreground">
                          {n.title}
                        </h4>
                        {isUnread ? (
                          <Badge className="bg-primary text-white text-[10px] px-2 py-0 font-semibold rounded-lg">
                            جدید
                          </Badge>
                        ) : (
                          <span className="text-[10px] text-muted-foreground/80 bg-muted px-2 py-0.5 rounded-lg">
                            خوانده‌شده
                          </span>
                        )}
                      </div>

                      <p className="text-xs text-muted-foreground leading-relaxed">
                        {n.message}
                      </p>

                      <div className="flex items-center gap-1.5 font-mono text-[11px] text-muted-foreground/70 pt-1">
                        <Clock className="size-3 text-muted-foreground/60" />
                        <span>{n.created_at ? formatSolarDateTime(n.created_at) : '—'}</span>
                      </div>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 shrink-0 self-end sm:self-start pt-2 sm:pt-0">
                    {isUnread && onMarkAsRead && (
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={busy}
                        onClick={(event) => {
                          event.stopPropagation()
                          onMarkAsRead(n.id)
                        }}
                        className="h-8 text-xs font-semibold rounded-xl gap-1.5 hover:bg-primary/10 hover:text-primary hover:border-primary/30"
                      >
                        <Check className="size-3.5" />
                        <span>خوانده شد</span>
                      </Button>
                    )}

                    {n.action_url && (
                      <Button
                        asChild
                        variant={isUnread ? 'default' : 'outline'}
                        size="sm"
                        onClick={(event) => {
                          event.stopPropagation()
                          if (isUnread && onMarkAsRead) {
                            onMarkAsRead(n.id)
                          }
                        }}
                        className="h-8 text-xs font-semibold rounded-xl gap-1.5"
                      >
                        <Link to={n.action_url} onClick={(event) => event.stopPropagation()}>
                          <span>مشاهده جزئیات</span>
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

      {/* Pagination Footer */}
      {pagination && pagination.total > 0 && pagination.last_page > 1 && (
        <div className="p-3 sm:px-4 rounded-2xl border border-border/70 bg-card/60 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
          <div>
            نمایش{' '}
            <strong className="font-semibold text-foreground">
              {pagination.from || 1}
            </strong>{' '}
            تا{' '}
            <strong className="font-semibold text-foreground">
              {pagination.to || notifications.length}
            </strong>{' '}
            از مجموع{' '}
            <strong className="font-semibold text-foreground">
              {pagination.total}
            </strong>{' '}
            اعلان ثبت‌شده
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page <= 1 || busy}
              onClick={() => onPageChange?.(pagination.current_page - 1)}
              className="h-8 px-3 text-xs font-semibold gap-1 rounded-xl"
            >
              <ChevronRight className="size-3.5" />
              <span>صفحه قبل</span>
            </Button>

            <span className="px-2.5 py-1 text-xs font-bold text-foreground">
              صفحه {pagination.current_page} از {pagination.last_page || 1}
            </span>

            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page || busy}
              onClick={() => onPageChange?.(pagination.current_page + 1)}
              className="h-8 px-3 text-xs font-semibold gap-1 rounded-xl"
            >
              <span>صفحه بعد</span>
              <ChevronLeft className="size-3.5" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
