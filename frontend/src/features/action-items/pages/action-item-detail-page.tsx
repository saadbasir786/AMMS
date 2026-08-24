import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { ActionItemSummaryCard } from '../components/action-item-summary-card'
import { ActionItemProgressForm } from '../components/action-item-progress-form'
import { ActionItemExtensionCard } from '../components/action-item-extension-card'
import { ActionItemEvidenceCard } from '../components/action-item-evidence-card'
import { ActionItemTimelineCard } from '../components/action-item-timeline-card'
import type { ActionItem } from '../types'

const STATUS_LABELS: Record<string, { label: string; variant: 'default' | 'outline' | 'destructive' | 'secondary' }> = {
  OPEN: { label: 'باز', variant: 'outline' },
  IN_PROGRESS: { label: 'در حال اجرا', variant: 'default' },
  BLOCKED: { label: 'دارای مانع', variant: 'destructive' },
  PENDING_VERIFICATION: { label: 'منتظر تأیید', variant: 'outline' },
  COMPLETED: { label: 'تکمیل‌شده', variant: 'default' },
  CANCELLED: { label: 'لغوشده', variant: 'secondary' },
}

export function ActionItemDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [item, setItem] = useState<ActionItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await api.get(`/action-items/${id}`)
      setItem(apiData(res))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات اقدام با خطا مواجه شد. لطفاً مجدداً تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleAction = async (endpoint: string, body?: any) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      await api.post(`/action-items/${id}/${endpoint}`, body)
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'انجام عملیات با خطا مواجه شد.')
      throw err
    } finally {
      setBusy(false)
    }
  }

  const handleExtend = async (dueDate: string, reason: string) => {
    await handleAction('extensions', {
      requested_due_date: dueDate,
      reason,
    })
  }

  const statusInfo = item?.status
    ? STATUS_LABELS[item.status] || { label: item.status, variant: 'outline' }
    : null

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        eyebrow={item?.action_number || undefined}
        title={item?.title || 'مشخصات اقدام'}
        description={
          item
            ? `${item.responsible_unit?.name || 'واحد نامشخص'} • ${
                item.decision_number ? `مصوبه شماره ${item.decision_number}` : ''
              }`
            : 'مدیریت و ثبت گزارش پیشرفت اقدام محوله'
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
              onClick={() => navigate('/action-items')}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به کارتابل</span>
            </Button>
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

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
            <div className="h-44 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
        </div>
      )}

      {/* Content */}
      {!loading && item && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            <ActionItemSummaryCard item={item} />

            <ActionItemProgressForm
              item={item}
              busy={busy}
              onAction={handleAction}
            />

            {!['COMPLETED', 'CANCELLED'].includes(item.status) && (
              <ActionItemExtensionCard
                busy={busy}
                onExtend={handleExtend}
              />
            )}
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <ActionItemTimelineCard updates={item.updates} />

            <ActionItemEvidenceCard evidence={item.evidence} />
          </aside>
        </div>
      )}
    </div>
  )
}
