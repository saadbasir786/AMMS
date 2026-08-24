import React, { useEffect, useState, useCallback } from 'react'
import { Bell, RotateCw, AlertCircle, CheckCheck, Inbox, Mail } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage } from '@/lib/api-client'
import { NotificationsList } from '../components/notifications-list'
import type { SystemNotification } from '../types'

export function NotificationsPage() {
  const [notifications, setNotifications] = useState<SystemNotification[]>([])
  const [filter, setFilter] = useState<'ALL' | 'UNREAD'>('ALL')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/notifications')
      const pageInfo = apiPage(res)
      setNotifications(pageInfo.data || [])
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات اعلان‌ها با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const unreadCount = notifications.filter((n) => !n.read_at).length
  const filteredList =
    filter === 'UNREAD'
      ? notifications.filter((n) => !n.read_at)
      : notifications

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="مرکز اعلان‌ها و رویدادهای زنده سامانه"
        description="پیگیری لحظه‌ای گردش کار جلسات، دعوت‌نامه‌ها، تغییرات زمان‌بندی و مصوبات منتسب به شما"
        action={
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-xs px-2.5 py-1 text-primary border-primary/30">
              <Bell className="size-3.5 ml-1" />
              <span>{unreadCount} پیام جدید</span>
            </Badge>
          </div>
        }
      />

      {/* Error Alert */}
      {error && (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertCircle className="size-4" />
          <AlertDescription className="text-xs flex items-center justify-between gap-2">
            <span>{error}</span>
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Filter Tabs */}
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-1.5 p-1 rounded-2xl bg-muted/60 border border-border/70 w-fit">
          <button
            onClick={() => setFilter('ALL')}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${
              filter === 'ALL'
                ? 'bg-card text-foreground shadow-xs'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            همه پیام‌ها ({notifications.length})
          </button>

          <button
            onClick={() => setFilter('UNREAD')}
            className={`flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${
              filter === 'UNREAD'
                ? 'bg-card text-foreground shadow-xs'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            <span>خوانده‌نشده‌ها</span>
            {unreadCount > 0 && (
              <Badge className="bg-primary text-white text-[10px] px-1.5 py-0 font-semibold font-mono">
                {unreadCount}
              </Badge>
            )}
          </button>
        </div>

        <Button
          variant="outline"
          size="sm"
          disabled={busy || loading}
          onClick={loadData}
          className="h-8.5 text-xs font-semibold rounded-xl gap-1.5"
        >
          <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
          <span>تازه‌سازی</span>
        </Button>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="space-y-3">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-card border border-border/70 rounded-2xl animate-pulse" />
          ))}
        </div>
      )}

      {/* Notifications List */}
      {!loading && (
        <NotificationsList
          notifications={filteredList}
          busy={busy}
        />
      )}
    </div>
  )
}
