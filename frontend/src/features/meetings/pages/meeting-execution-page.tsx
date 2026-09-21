import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  Play,
  Users,
  FileText,
  Clock
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { StatusBadge } from '@/components/amms/status-badge'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { ExecutionSessionCard } from '../components/execution-session-card'
import { MeetingActionsCard } from '../components/meeting-actions-card'
import { MeetingParticipantsCard } from '../components/meeting-participants-card'
import { MeetingWorkspaceSummary } from '../components/meeting-workspace-summary'
import { MeetingLifecycleBar } from '../components/meeting-lifecycle-bar'
import type { MeetingItem, MeetingExecutionState } from '../types'
import { fetchMeetingActionCapabilities } from '../lib/meeting-capabilities'

export function MeetingExecutionPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [state, setState] = useState<MeetingExecutionState | null>(null)
  const [meeting, setMeeting] = useState<MeetingItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [execRes, meetRes, capabilities] = await Promise.all([
        api.get(`/meetings/${id}/execution`),
        api.get(`/meetings/${id}`),
        fetchMeetingActionCapabilities(id),
      ])
      setState(apiData(execRes))
      setMeeting({ ...apiData(meetRes), action_capabilities: capabilities })
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری فضای اجرای جلسه با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleStart = async (notes: string) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/execution/start`, {
        start_notes: notes || undefined,
      })
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'خطا در ثبت شروع جلسه.')
    } finally {
      setBusy(false)
    }
  }

  const handleEnd = async (notes: string) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      await api.post(`/meetings/${id}/execution/end`, {
        end_notes: notes || undefined,
      })
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'خطا در ثبت پایان جلسه.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="فضای اجرای رسمی جلسه"
        description="ثبت آغاز و خاتمه واقعی جلسه بر پایه نسخه‌های مصوب زمان‌بندی و اشتراک‌کنندگان"
        action={
          <div className="flex flex-wrap items-center gap-2">
            {meeting && <StatusBadge status={meeting.status} />}
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate(`/meetings/${id}`)}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به جلسه</span>
            </Button>
          </div>
        }
      />

      {meeting && <MeetingLifecycleBar status={meeting.status} />}

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
            <div className="h-44 rounded-2xl bg-card border border-border/70 animate-pulse" />
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
        </div>
      )}

      {/* Main Content */}
      {!loading && state && meeting && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            <MeetingWorkspaceSummary meeting={meeting} />
            <ExecutionSessionCard
              state={state}
              busy={busy}
              onStart={handleStart}
              onEnd={handleEnd}
              canStart={meeting.action_capabilities?.execution.start === true}
              canEnd={meeting.action_capabilities?.execution.end === true}
            />

            <MeetingParticipantsCard meeting={meeting} participants={meeting.participants} />

          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <MeetingActionsCard meeting={meeting} activeWorkspace="execution" />
          </aside>
        </div>
      )}
    </div>
  )
}
