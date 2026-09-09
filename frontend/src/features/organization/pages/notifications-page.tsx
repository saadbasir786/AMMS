import React, { useEffect, useState, useCallback } from 'react'
import { Bell, RotateCw, AlertCircle, CheckCheck, Check, Volume2, VolumeX } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiPage } from '@/lib/api-client'
import {
  playNotificationChime,
  isNotificationSoundEnabled,
  setNotificationSoundEnabled,
} from '@/lib/notification-sound'
import { NotificationsList } from '../components/notifications-list'
import type { SystemNotification, PaginationMeta } from '../types'

export function NotificationsPage() {
  const [notifications, setNotifications] = useState<SystemNotification[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState<number>(1)
  const [filter, setFilter] = useState<'ALL' | 'UNREAD'>('ALL')
  const [unreadCount, setUnreadCount] = useState<number>(0)
  const [totalCount, setTotalCount] = useState<number>(0)
  const [soundEnabled, setSoundEnabled] = useState(isNotificationSoundEnabled)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleToggleSound = () => {
    const next = !soundEnabled
    setSoundEnabled(next)
    setNotificationSoundEnabled(next)
    if (next) {
      playNotificationChime(true)
    }
  }

  const loadData = useCallback(async (targetPage = 1, targetFilter = 'ALL') => {
    setLoading(true)
    setError(null)
    try {
      const params: Record<string, any> = {
        limit: 15,
        page: targetPage,
      }
      if (targetFilter === 'UNREAD') {
        params.filter = 'UNREAD'
      }

      const res = await api.get('/notifications', { params })
      const pageInfo = apiPage(res)
      setNotifications(pageInfo.data || [])
      setPagination(pageInfo.meta || null)

      if (res?.data && typeof res.data.unread_count === 'number') {
        setUnreadCount(res.data.unread_count)
      }
      if (res?.data && typeof res.data.total_count === 'number') {
        setTotalCount(res.data.total_count)
      }
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
    loadData(page, filter)
  }, [loadData, page, filter])

  const handleFilterChange = (newFilter: 'ALL' | 'UNREAD') => {
    setFilter(newFilter)
    setPage(1)
  }

  const handlePageChange = (newPage: number) => {
    setPage(newPage)
  }

  const handleMarkAsRead = async (id: number) => {
    try {
      const res = await api.post(`/notifications/${id}/read`)
      if (res?.data && typeof res.data.unread_count === 'number') {
        setUnreadCount(res.data.unread_count)
      } else {
        setUnreadCount((prev) => Math.max(0, prev - 1))
      }

      if (filter === 'UNREAD') {
        // Reload current page from server to reflect removal from unread list
        loadData(page, filter)
      } else {
        setNotifications((prev) =>
          prev.map((n) =>
            n.id === id ? { ...n, read_at: new Date().toISOString() } : n
          )
        )
      }
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'خطا در علامت‌گذاری اعلان به عنوان خوانده‌شده.'
      )
    }
  }

  const handleMarkAllAsRead = async () => {
    setBusy(true)
    setError(null)
    try {
      await api.post('/notifications/read-all')
      setUnreadCount(0)
      // Reload server-side data for the current page and filter
      await loadData(page, filter)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'خطا در علامت‌گذاری کلیه اعلان‌ها.'
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="مرکز اعلان‌ها و رویدادهای زنده سیستم"
        description="پیگیری لحظه‌ای گردش کار جلسات، دعوت‌نامه‌ها، تغییرات زمان‌بندی و مصوبات منتسب به شما"
        action={
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleToggleSound}
              className="h-8 px-2.5 rounded-xl text-xs gap-1.5 font-medium"
              title={soundEnabled ? 'قطع صدای اعلان‌ها' : 'فعال‌سازی صدای اعلان‌ها'}
            >
              {soundEnabled ? (
                <>
                  <Volume2 className="size-3.5 text-primary" />
                  <span>صدای اعلان: روشن</span>
                </>
              ) : (
                <>
                  <VolumeX className="size-3.5 text-muted-foreground" />
                  <span>صدای اعلان: خاموش</span>
                </>
              )}
            </Button>

            {unreadCount > 0 ? (
              <Badge variant="outline" className="text-xs px-2.5 py-1 text-primary border-primary/30">
                <Bell className="size-3.5 ml-1" />
                <span>{unreadCount} پیام خوانده‌نشده</span>
              </Badge>
            ) : (
              <Badge variant="outline" className="text-xs px-2.5 py-1 text-muted-foreground border-border/60">
                <Check className="size-3.5 ml-1" />
                <span>تمامی پیام‌ها خوانده شده است</span>
              </Badge>
            )}
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
              onClick={() => loadData(page, filter)}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Toolbar & Filter Tabs */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        {/* Tabs */}
        <div className="flex items-center gap-1.5 p-1 rounded-2xl bg-muted/60 border border-border/70 w-fit">
          <button
            onClick={() => handleFilterChange('ALL')}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all ${
              filter === 'ALL'
                ? 'bg-card text-foreground shadow-xs'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            همه پیام‌ها ({totalCount})
          </button>

          <button
            onClick={() => handleFilterChange('UNREAD')}
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

        {/* Action Buttons */}
        <div className="flex items-center gap-2">
          {unreadCount > 0 && (
            <Button
              variant="outline"
              size="sm"
              disabled={busy || loading}
              onClick={handleMarkAllAsRead}
              className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 hover:bg-primary/10 hover:text-primary hover:border-primary/30"
            >
              <CheckCheck className="size-3.5" />
              <span>علامت‌گذاری همه به عنوان خوانده‌شده</span>
            </Button>
          )}

          <Button
            variant="outline"
            size="sm"
            disabled={busy || loading}
            onClick={() => loadData(page, filter)}
            className="h-8.5 text-xs font-semibold rounded-xl gap-1.5"
          >
            <RotateCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
            <span>تازه‌سازی</span>
          </Button>
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="space-y-3">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-card border border-border/70 rounded-2xl animate-pulse" />
          ))}
        </div>
      )}

      {/* Notifications List with Server-Side Pagination */}
      {!loading && (
        <NotificationsList
          notifications={notifications}
          pagination={pagination}
          busy={busy}
          onPageChange={handlePageChange}
          onMarkAsRead={handleMarkAsRead}
        />
      )}
    </div>
  )
}
