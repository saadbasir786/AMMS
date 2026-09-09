import React, { useState, useEffect, useCallback, useRef } from 'react'
import {
  Bell,
  CheckCheck,
  Clock,
  ArrowRight,
  Inbox,
  ExternalLink,
  Check,
  Volume2,
  VolumeX,
} from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { api } from '@/lib/api-client'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import {
  playNotificationChime,
  isNotificationSoundEnabled,
  setNotificationSoundEnabled,
} from '@/lib/notification-sound'
import { cn } from '@/lib/utils'
import type { SystemNotification } from '../types'

type NotificationBellDropdownProps = {
  onNavigate: (to: string) => void
}

export function NotificationBellDropdown({ onNavigate }: NotificationBellDropdownProps) {
  const [open, setOpen] = useState(false)
  const [notifications, setNotifications] = useState<SystemNotification[]>([])
  const [unreadCount, setUnreadCount] = useState<number>(0)
  const [soundEnabled, setSoundEnabled] = useState(isNotificationSoundEnabled)
  const [loading, setLoading] = useState(false)
  const [markingAll, setMarkingAll] = useState(false)

  const isInitialLoad = useRef(true)
  const previousUnreadCount = useRef<number>(0)

  const fetchUnreadCount = useCallback(async () => {
    try {
      const res = await api.get('/notifications/unread-count')
      if (res?.data && typeof res.data.unread_count === 'number') {
        const count = res.data.unread_count
        setUnreadCount(count)

        if (isInitialLoad.current) {
          isInitialLoad.current = false
          previousUnreadCount.current = count
        } else if (count > previousUnreadCount.current) {
          playNotificationChime()
          previousUnreadCount.current = count
        } else {
          previousUnreadCount.current = count
        }
      }
    } catch {
      // Gracefully ignore silent count polling failures
    }
  }, [])

  const handleToggleSound = (e: React.MouseEvent) => {
    e.stopPropagation()
    const next = !soundEnabled
    setSoundEnabled(next)
    setNotificationSoundEnabled(next)
    if (next) {
      playNotificationChime(true)
    }
  }

  const fetchRecent = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get('/notifications?limit=5')
      if (res?.data) {
        setNotifications(res.data.data || [])
        if (typeof res.data.unread_count === 'number') {
          setUnreadCount(res.data.unread_count)
        }
      }
    } catch {
      // Gracefully handle network hiccups
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchUnreadCount()
    const timer = setInterval(fetchUnreadCount, 45000)
    return () => clearInterval(timer)
  }, [fetchUnreadCount])

  useEffect(() => {
    if (open) {
      fetchRecent()
    }
  }, [open, fetchRecent])

  const handleMarkAsRead = async (id: number, actionUrl?: string | null) => {
    try {
      const res = await api.post(`/notifications/${id}/read`)
      if (res?.data && typeof res.data.unread_count === 'number') {
        setUnreadCount(res.data.unread_count)
      } else {
        setUnreadCount((prev) => Math.max(0, prev - 1))
      }
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      )
    } catch {
      // Mark read error
    }

    if (actionUrl) {
      setOpen(false)
      onNavigate(actionUrl)
    }
  }

  const handleMarkAllRead = async (e: React.MouseEvent) => {
    e.stopPropagation()
    setMarkingAll(true)
    try {
      await api.post('/notifications/read-all')
      setUnreadCount(0)
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
      )
    } catch {
      // Mark all read error
    } finally {
      setMarkingAll(false)
    }
  }

  const handleViewAll = () => {
    setOpen(false)
    onNavigate('/notifications')
  }

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative size-9 rounded-xl hover:bg-muted focus-visible:ring-1"
          aria-label="اعلان‌ها"
        >
          <Bell className="size-[18px] text-muted-foreground transition-colors hover:text-foreground" />
          {unreadCount > 0 && (
            <span
              className={cn(
                'absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white shadow-xs animate-in zoom-in-50'
              )}
            >
              {unreadCount > 9 ? '۹+' : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent
        align="end"
        sideOffset={8}
        className="w-80 sm:w-96 p-0 rounded-2xl border border-border/80 bg-card shadow-lg"
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border/70 bg-muted/30">
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold text-foreground">اعلان‌ها</span>
            {unreadCount > 0 ? (
              <Badge
                variant="secondary"
                className="bg-primary/10 text-primary border-primary/20 text-[10px] px-1.5 py-0 font-medium"
              >
                {unreadCount} جدید
              </Badge>
            ) : (
              <span className="text-[11px] text-muted-foreground">همه خوانده شده</span>
            )}
          </div>

          <div className="flex items-center gap-2">
            {/* Sound Toggle Button */}
            <button
              type="button"
              onClick={handleToggleSound}
              className="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors"
              title={soundEnabled ? 'صدای اعلان فعال است (کلیک برای قطع صدا)' : 'صدای اعلان قطع است (کلیک برای فعال‌سازی)'}
              aria-label="تنظیم صدای اعلان"
            >
              {soundEnabled ? (
                <Volume2 className="size-3.5 text-primary" />
              ) : (
                <VolumeX className="size-3.5 text-muted-foreground/60" />
              )}
            </button>

            {unreadCount > 0 && (
              <button
                onClick={handleMarkAllRead}
                disabled={markingAll}
                className="flex items-center gap-1 text-[11px] font-semibold text-primary hover:text-primary/80 transition-colors disabled:opacity-50"
              >
                <CheckCheck className="size-3.5" />
                <span>خواندن همه</span>
              </button>
            )}
          </div>
        </div>

        {/* Notifications List */}
        <div className="max-h-[360px] overflow-y-auto divide-y divide-border/60">
          {loading ? (
            <div className="p-6 space-y-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 bg-muted/60 rounded-xl animate-pulse" />
              ))}
            </div>
          ) : notifications.length === 0 ? (
            <div className="text-center py-8 px-4">
              <Inbox className="size-8 mx-auto text-muted-foreground/40 mb-2" />
              <p className="text-xs text-muted-foreground">اعلان جدیدی وجود ندارد</p>
            </div>
          ) : (
            notifications.map((n) => {
              const isUnread = !n.read_at
              return (
                <div
                  key={n.id}
                  onClick={() => handleMarkAsRead(n.id, n.action_url)}
                  className={cn(
                    'p-3.5 text-right transition-colors cursor-pointer flex items-start gap-3 hover:bg-muted/60',
                    isUnread
                      ? 'bg-primary/5 dark:bg-primary/10 font-medium'
                      : 'bg-card'
                  )}
                >
                  <div
                    className={cn(
                      'size-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                      isUnread
                        ? 'bg-primary/15 text-primary'
                        : 'bg-muted text-muted-foreground'
                    )}
                  >
                    <Bell className="size-3.5" />
                  </div>

                  <div className="flex-1 min-w-0 space-y-0.5">
                    <div className="flex items-center justify-between gap-1.5">
                      <h5 className="text-xs font-bold text-foreground truncate">
                        {n.title}
                      </h5>
                      {isUnread && (
                        <span className="size-1.5 rounded-full bg-primary shrink-0" />
                      )}
                    </div>
                    <p className="text-[11px] text-muted-foreground leading-relaxed line-clamp-2">
                      {n.message}
                    </p>
                    <div className="flex items-center gap-1 text-[10px] text-muted-foreground/70 font-mono pt-0.5">
                      <Clock className="size-3 shrink-0" />
                      <span>{n.created_at ? formatSolarDateTime(n.created_at) : '—'}</span>
                    </div>
                  </div>
                </div>
              )
            })
          )}
        </div>

        {/* Footer */}
        <div className="p-2 border-t border-border/70 bg-muted/30">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleViewAll}
            className="w-full h-8 text-xs font-semibold rounded-xl text-primary hover:text-primary hover:bg-primary/10 justify-center gap-1"
          >
            <span>مشاهده همه اعلان‌ها</span>
            <ArrowRight className="size-3.5" />
          </Button>
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
