import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  RotateCw,
  AlertCircle,
  FileText,
  Download,
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent } from '@/components/ui/card'
import { api, apiData } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'
import { MinutesFormCard } from '../components/minutes-form-card'
import { MinutesReviewsCard } from '../components/minutes-reviews-card'
import { MeetingActionsCard } from '../components/meeting-actions-card'
import type {
  MeetingItem,
  MeetingMinutes,
  MinutesItem,
  MinutesDecision,
  MinutesReview
} from '../types'
import type { Employee, OrganizationalUnit } from '@/features/organization/types'
import { fetchMeetingActionCapabilities } from '../lib/meeting-capabilities'

const STATUS_LABELS: Record<string, string> = {
  DRAFT: 'پیش‌نویس',
  UNDER_REVIEW: 'در حال بررسی',
  RETURNED_FOR_CORRECTION: 'برگشت جهت اصلاح',
  READY_TO_FINALIZE: 'آماده تصویب نهایی',
  FINALIZED: 'تصویب و صادرشده',
}

export function MeetingMinutesPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [minutes, setMinutes] = useState<MeetingMinutes | null>(null)
  const [meeting, setMeeting] = useState<MeetingItem | null>(null)
  const [organizationalUnits, setOrganizationalUnits] = useState<OrganizationalUnit[]>([])
  const [employees, setEmployees] = useState<Employee[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [form, setForm] = useState<{
    summary: string
    general_notes: string
    items: MinutesItem[]
    decisions: MinutesDecision[]
  }>({
    summary: '',
    general_notes: '',
    items: [{ agenda_title: '', discussion_summary: '' }],
    decisions: [
      {
        decision_text: '',
        responsible_unit_id: '',
        responsible_employee_id: '',
        target_date: '',
        notes: '',
      },
    ],
  })

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [minRes, meetRes, capabilities, unitsRes, employeesRes] = await Promise.all([
        api.get(`/meetings/${id}/minutes`),
        api.get(`/meetings/${id}`),
        fetchMeetingActionCapabilities(id),
        api.get('/organizational-units').catch(() => ({ data: [] })),
        fetchAllPages<Employee>('/employees').catch(() => []),
      ])
      const minData = apiData(minRes)
      const meetData = apiData(meetRes)
      setMinutes(minData)
      setMeeting({ ...meetData, action_capabilities: capabilities })
      setOrganizationalUnits(apiData(unitsRes) || [])
      setEmployees(employeesRes)

      const version = minData?.current_version
      if (version) {
        setForm({
          summary: version.summary || '',
          general_notes: version.general_notes || '',
          items: version.items?.length
            ? version.items.map((x: any) => ({
                agenda_title: x.agenda_title,
                discussion_summary: x.discussion_summary,
              }))
            : [{ agenda_title: '', discussion_summary: '' }],
          decisions: version.decisions?.length
            ? version.decisions.map((x: any) => ({
                decision_text: x.decision_text,
                responsible_unit_id: x.responsible_unit_id || '',
                responsible_employee_id: x.responsible_employee_id || '',
                target_date: x.target_date || '',
                notes: x.notes || '',
              }))
            : [
                {
                  decision_text: '',
                  responsible_unit_id: '',
                  responsible_employee_id: '',
                  target_date: '',
                  notes: '',
                },
              ],
        })
      }
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات صورت‌جلسه با خطا مواجه شد. لطفاً دوباره تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const runAction = async (action: () => Promise<any>) => {
    setBusy(true)
    setError(null)
    try {
      await action()
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'عملیات با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const handleCreateDraft = () => runAction(() => api.post(`/meetings/${id}/minutes/draft`))

  const draftPayload = () => ({
    ...form,
    items: form.items.filter((x) => x.agenda_title && x.discussion_summary),
    decisions: form.decisions.filter((x) => x.decision_text),
  })

  const handleSaveDraft = () =>
    runAction(async () => {
      if (!minutes) await api.post(`/meetings/${id}/minutes/draft`)
      await api.put(`/meetings/${id}/minutes/draft`, draftPayload())
    })

  const handleSubmit = () =>
    runAction(async () => {
      // Persist the current form before submitting, including the first draft.
      // This prevents MINUTES_VERSION_STALE when the user presses submit directly.
      if (!minutes) await api.post(`/meetings/${id}/minutes/draft`)
      await api.put(`/meetings/${id}/minutes/draft`, draftPayload())
      await api.post(`/meetings/${id}/minutes/submit`)
    })

  const handleAcknowledge = (unitId: number) =>
    runAction(() => api.post(`/meetings/${id}/minutes/reviews/${unitId}/acknowledge`))

  const handleRequestCorrection = (review: MinutesReview, reason: string) =>
    runAction(() =>
      api.post(`/meetings/${id}/minutes/reviews/correction`, {
        reviewer_type: review.reviewer_type,
        organizational_unit_id: review.organizational_unit_id,
        reason,
      })
    )

  const handleApproveAuthority = () =>
    runAction(() => api.post(`/meetings/${id}/minutes/authority-approve`))

  const handleFinalize = () => runAction(() => api.post(`/meetings/${id}/minutes/finalize`))

  const handleDownloadPdf = async () => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      const response = await api.get(`/meetings/${id}/minutes/final.pdf`, {
        responseType: 'blob',
      })
      const blob = response.data instanceof Blob
        ? response.data
        : new Blob([response.data], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `minutes-${meeting?.meeting_number || id}.pdf`
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'دریافت فایل PDF رسمی با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Page Header */}
      <PageHeader
        eyebrow={meeting?.meeting_number || undefined}
        title="صورت‌جلسه رسمی و مصوبات"
        description="ثبت نسخه‌محور مباحث مطرح‌شده، تصویب تصمیمات و تعیین مسئولیت‌های اجرایی"
        action={
          <div className="flex flex-wrap items-center gap-2">
            {minutes && (
              <Badge variant="outline" className="text-xs px-2.5 py-1">
                {STATUS_LABELS[minutes.status] || minutes.status}
              </Badge>
            )}

            {minutes?.status === 'FINALIZED' && meeting?.action_capabilities?.minutes.document && (
              <Button
                disabled={busy}
                onClick={handleDownloadPdf}
                className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
              >
                <Download className="size-4" />
                <span>{busy ? 'در حال دریافت…' : 'PDF رسمی'}</span>
              </Button>
            )}

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
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
          <div className="space-y-6">
            <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          </div>
        </div>
      )}

      {/* When no minutes created yet */}
      {!loading && !minutes && meeting && meeting.action_capabilities?.minutes.prepare && (
        <Card className="border-dashed border-border/80 bg-card/60 rounded-2xl">
          <CardContent className="flex flex-col sm:flex-row items-center justify-between gap-4 p-6 sm:p-8">
            <div className="space-y-1 text-center sm:text-right">
              <h3 className="text-sm font-bold text-foreground">
                هنوز صورت‌جلسه‌ای برای این جلسه ایجاد نشده است
              </h3>
              <p className="text-xs text-muted-foreground leading-relaxed">
                می‌توانید با ایجاد اولین پیش‌نویس، خلاصه مباحث و مصوبات را ثبت و برای تایید اعضا ارسال کنید.
              </p>
            </div>
            <Button
              disabled={busy}
              onClick={handleCreateDraft}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs shrink-0"
            >
              <FileText className="size-4" />
              <span>{busy ? 'در حال ایجاد…' : 'ایجاد پیش‌نویس صورت‌جلسه'}</span>
            </Button>
          </CardContent>
        </Card>
      )}

      {/* Explicitly explain an authorization/data gap instead of leaving the workspace empty. */}
      {!loading && !minutes && meeting && !meeting.action_capabilities?.minutes.prepare && !error && (
        <Card className="border-border/70 bg-card/60 rounded-2xl">
          <CardContent className="p-6 sm:p-8 text-center space-y-2">
            <h3 className="text-sm font-bold text-foreground">
              این جلسه هنوز برای ثبت صورت‌جلسه در اختیار شما نیست
            </h3>
            <p className="text-xs text-muted-foreground leading-relaxed">
              دسترسی ثبت صورت‌جلسه بر اساس نقش، ریاست مربوطه و وضعیت جلسه تعیین می‌شود.
            </p>
          </CardContent>
        </Card>
      )}

      {/* Minutes Loaded */}
      {!loading && minutes && meeting && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
            <MinutesFormCard
              minutes={minutes}
              form={form}
              onChange={setForm}
              organizationalUnits={organizationalUnits}
              employees={employees}
              busy={busy}
              onSaveDraft={handleSaveDraft}
              onSubmit={handleSubmit}
              canPrepare={meeting.action_capabilities?.minutes.prepare === true}
              canSubmit={meeting.action_capabilities?.minutes.submit === true}
            />
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <MinutesReviewsCard
              meetingId={id!}
              minutes={minutes}
              busy={busy}
              onAcknowledge={handleAcknowledge}
              onRequestCorrection={handleRequestCorrection}
              onApproveAuthority={handleApproveAuthority}
              onFinalize={handleFinalize}
              onDownloadPdf={handleDownloadPdf}
              canReview={meeting.action_capabilities?.minutes.review === true}
              reviewableUnitIds={meeting.action_capabilities?.minutes.reviewable_unit_ids || []}
              delegatedReviewUnitIds={meeting.action_capabilities?.minutes.delegated_review_unit_ids || []}
              canApproveAuthority={meeting.action_capabilities?.minutes.approve_authority === true}
              canFinalize={meeting.action_capabilities?.minutes.finalize === true}
              canDocument={meeting.action_capabilities?.minutes.document === true}
            />

            <MeetingActionsCard meeting={meeting} />
          </aside>
        </div>
      )}
    </div>
  )
}
