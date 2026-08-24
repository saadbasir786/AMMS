import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  FilePlus2,
  CalendarDays,
  ShieldAlert,
  Loader2
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { RequestDetailSummary } from '../components/request-detail-summary'
import { RequestParticipantsCard } from '../components/request-participants-card'
import { RequestActionsCard } from '../components/request-actions-card'
import { RequestTimelineCard } from '../components/request-timeline-card'

const STATUS_LABELS: Record<string, { label: string; variant: 'default' | 'outline' | 'destructive' | 'secondary' }> = {
  DRAFT: { label: 'پیش‌نویس', variant: 'outline' },
  PENDING_INTERNAL_APPROVAL: { label: 'در انتظار تأیید داخلی', variant: 'secondary' },
  RETURNED_FOR_REVISION: { label: 'برگشت جهت اصلاح', variant: 'destructive' },
  APPROVED: { label: 'تأیید شده', variant: 'default' },
  REJECTED: { label: 'رد شده', variant: 'destructive' },
  SCHEDULING: { label: 'در حال زمان‌بندی', variant: 'secondary' },
  SCHEDULE_CONFIRMED: { label: 'زمان‌بندی نهایی', variant: 'default' },
  CONVERTED_TO_MEETING: { label: 'تبدیل به جلسه', variant: 'default' },
}

type RequestDetailPageProps = {
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
  currentUser?: any
}

export function RequestDetailPage({
  purposeLabels,
  priorityLabels,
  currentUser: initialUser,
}: RequestDetailPageProps) {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [request, setRequest] = useState<any>(null)
  const [currentUser, setCurrentUser] = useState<any>(initialUser || null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [reqRes, meRes] = await Promise.allSettled([
        api.get(`/meeting-requests/${id}`),
        initialUser ? Promise.resolve({ data: initialUser }) : api.get('/me'),
      ])

      if (reqRes.status === 'fulfilled') {
        setRequest(apiData(reqRes.value))
      } else {
        throw reqRes.reason
      }

      if (meRes.status === 'fulfilled') {
        setCurrentUser(apiData(meRes.value))
      }
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری مشخصات درخواست با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id, initialUser])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleAction = async (actionUrl: string, body?: any) => {
    if (!id) return
    await api.post(`/meeting-requests/${id}/${actionUrl}`, body)
    await loadData()
  }

  // Permissions calculation
  const canApprove =
    currentUser?.roles?.some((r: any) => r.role === 'DIRECTOR') ?? false
  const canManage =
    currentUser?.roles?.some((r: any) => r.role === 'EXECUTIVE_MANAGEMENT') ?? false

  const statusInfo = request?.status
    ? STATUS_LABELS[request.status] || { label: request.status, variant: 'outline' }
    : null

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={request?.request_number || undefined}
        title={request?.subject || 'درخواست جلسه'}
        description={
          request
            ? `${request.requesting_unit?.name || 'واحد نامشخص'} • ${
                purposeLabels[request.purpose] || request.purpose || ''
              }`
            : 'مشاهده و پیگیری گردش کار درخواست جلسه'
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
              onClick={() => navigate('/meeting-requests')}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به فهرست</span>
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
            <div className="h-48 rounded-2xl bg-card border border-border/70 animate-pulse" />
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
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
      {!loading && !error && request && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            <RequestDetailSummary
              request={request}
              purposeLabels={purposeLabels}
              priorityLabels={priorityLabels}
            />

            <RequestParticipantsCard participants={request.participants} />
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <RequestActionsCard
              request={request}
              canApprove={canApprove}
              canManage={canManage}
              onAction={handleAction}
              onEdit={() => navigate(`/meeting-requests/${request.id}/edit`)}
              onScheduling={() => navigate(`/meeting-requests/${request.id}/scheduling`)}
            />

            <RequestTimelineCard actions={request.actions} />
          </aside>
        </div>
      )}
    </div>
  )
}
