import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  CalendarCheck,
  Building2,
  Calendar
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { MeetingDetailSummary } from '../components/meeting-detail-summary'
import { MeetingParticipantsCard } from '../components/meeting-participants-card'
import { MeetingActionsCard } from '../components/meeting-actions-card'
import { MeetingTimelineCard } from '../components/meeting-timeline-card'
import type { MeetingItem } from '../types'
import { fetchMeetingActionCapabilities } from '../lib/meeting-capabilities'

const STATUS_LABELS: Record<string, { label: string; variant: 'default' | 'outline' | 'destructive' | 'secondary' }> = {
  SCHEDULED: { label: 'زمان‌بندی‌شده', variant: 'secondary' },
  IN_PROGRESS: { label: 'در حال برگزاری', variant: 'default' },
  HELD: { label: 'پایان‌یافته', variant: 'default' },
  COMPLETED: { label: 'تکمیل‌شده', variant: 'default' },
  POSTPONED: { label: 'به تعویق افتاده', variant: 'outline' },
  CANCELLED: { label: 'لغو شده', variant: 'destructive' },
}

export function MeetingDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [meeting, setMeeting] = useState<MeetingItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [res, capabilities] = await Promise.all([
        api.get(`/meetings/${id}`),
        fetchMeetingActionCapabilities(id),
      ])
      setMeeting({ ...apiData(res), action_capabilities: capabilities })
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری مشخصات جلسه رسمی با خطا مواجه شد. لطفاً مجدداً تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const statusInfo = meeting?.status
    ? STATUS_LABELS[meeting.status] || { label: meeting.status, variant: 'outline' }
    : null

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title={meeting?.title || 'مشخصات جلسه رسمی'}
        description={
          meeting
            ? `${meeting.request?.requesting_unit?.name || 'واحد نامشخص'} • مکان: ${
                meeting.location || 'تعیین نشده'
              }`
            : 'مشاهده جزئیات و دسترسی به فضاهای عملیاتی و اجرایی جلسه'
        }
        action={
          <div className="flex flex-wrap items-center gap-2">
            {statusInfo && (
              <Badge variant={statusInfo.variant} className="text-xs px-2.5 py-1">
                {statusInfo.label}
              </Badge>
            )}

            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate('/meetings')}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به جلسات</span>
            </Button>
          </div>
        }
      />

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
            <div className="h-44 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
            <div className="h-44 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
        </div>
      )}

      {/* Error State */}
      {!loading && error && (
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

      {/* Loaded Content */}
      {!loading && !error && meeting && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            <MeetingDetailSummary meeting={meeting} />

            <MeetingParticipantsCard meeting={meeting} participants={meeting.participants} />
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <MeetingActionsCard meeting={meeting} />

            <MeetingTimelineCard actions={meeting.request?.actions} />
          </aside>
        </div>
      )}
    </div>
  )
}
