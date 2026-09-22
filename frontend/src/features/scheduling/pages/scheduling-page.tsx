import React, { useEffect, useState, useMemo, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowRight, RotateCw, AlertCircle } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'
import { SchedulingStageBar } from '../components/scheduling-stage-bar'
import { ScheduleFormCard } from '../components/schedule-form-card'
import { ScheduleReviewCard } from '../components/schedule-review-card'
import { RequestReferenceCard } from '../components/request-reference-card'
import { ScheduleProposalsCard } from '../components/schedule-proposals-card'
import type { SchedulingFormValues } from '../types'
import type { EmployeeItem, OrganizationalUnit } from '@/shared/types'

type SchedulingPageProps = {
  currentUser?: any
}

export function SchedulingPage({ currentUser }: SchedulingPageProps) {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [request, setRequest] = useState<any>(null)
  const [employees, setEmployees] = useState<EmployeeItem[]>([])
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [venues, setVenues] = useState<Array<{ id: number; name: string; location_details?: string | null }>>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [conflictResult, setConflictResult] = useState<{ blocking_conflicts: any[]; warnings: any[] } | null>(null)

  const [form, setForm] = useState<SchedulingFormValues>({
    meeting_date: '',
    start_time: '',
    duration_minutes: 60,
    location: '',
    venue_type: 'EXTERNAL_LOCATION',
    venue_id: null,
    chairperson_employee_id: '',
    participant_ids: [],
    agenda: '',
    agenda_items: [{ title: '', description: '' }],
  })

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [reqRes, empRes, unitRes] = await Promise.all([
        api.get(`/meeting-requests/${id}`),
        fetchAllPages<EmployeeItem>('/employees'),
        api.get('/organizational-units'),
      ])
      let venueData: any[] = []
      try {
        venueData = await fetchAllPages('/meeting-venues')
      } catch {
        // Venue catalogue access is optional for external/online scheduling.
      }

      const reqData = apiData(reqRes)
      const empData = empRes
      const unitData = apiData(unitRes) || []

      setRequest(reqData)
      setEmployees(empData)
      setUnits(unitData)
      setVenues(venueData)

      const hasFinalParticipants = (reqData.participants || []).some(
        (p: any) => p.participant_type === 'FINAL'
      )
      const finalIds = (reqData.participants || [])
        .filter((p: any) => p.participant_type === 'FINAL')
        .map((p: any) => p.employee_id || p.employee?.id)
        .filter(Boolean)

      const proposedIds = (reqData.participants || [])
        .map((p: any) => p.employee_id || p.employee?.id)
        .filter(Boolean)

      // Check if there is an accepted proposal
      const acceptedProposal = (reqData.schedule_proposals || []).find(
        (p: any) => p.status === 'ACCEPTED'
      )
      const proposedVenue = venueData.find((venue) => venue.name === reqData.proposed_location)
      const hasConfirmedSchedule = Boolean(reqData.scheduled_date)
      const initialVenueType = acceptedProposal?.venue_type
        || (hasConfirmedSchedule ? reqData.scheduled_venue_type : null)
        || (proposedVenue ? 'INTERNAL_VENUE' : 'EXTERNAL_LOCATION')
      const initialVenueId = acceptedProposal?.venue_id
        ?? (hasConfirmedSchedule ? reqData.scheduled_venue_id : null)
        ?? proposedVenue?.id
        ?? null

      setForm((prev) => ({
        ...prev,
        meeting_date:
          acceptedProposal?.meeting_date?.slice(0, 10) ||
          reqData.scheduled_date?.slice(0, 10) ||
          reqData.preferred_date?.slice(0, 10) ||
          prev.meeting_date ||
          '',
        start_time:
          acceptedProposal?.start_time?.slice(0, 5) ||
          reqData.scheduled_start_time?.slice(0, 5) ||
          reqData.preferred_start_time?.slice(0, 5) ||
          prev.start_time ||
          '',
        duration_minutes:
          reqData.scheduled_duration_minutes ||
          reqData.estimated_duration_minutes ||
          prev.duration_minutes ||
          60,
        location:
          acceptedProposal?.location ||
          reqData.scheduled_location ||
          reqData.proposed_location ||
          prev.location ||
          '',
        venue_type:
          initialVenueType,
        venue_id:
          initialVenueId,
        chairperson_employee_id:
          reqData.scheduled_chairperson_employee_id ||
          prev.chairperson_employee_id ||
          '',
        participant_ids:
          hasFinalParticipants && finalIds.length > 0
            ? finalIds
            : proposedIds.length > 0
            ? proposedIds
            : prev.participant_ids,
        agenda: prev.agenda || '',
        agenda_items: prev.agenda_items?.length ? prev.agenda_items : [{ title: '', description: '' }],
      }))
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات زمان‌بندی با خطا مواجه شد. لطفاً مجدداً تلاش کنید.'
      )
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleFieldChange = (field: keyof SchedulingFormValues, value: any) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  // Calculate hierarchical units allowed
  const allowedUnitIds = useMemo(() => {
    const selected = Number(request?.requesting_unit_id)
    if (!selected) return new Set<number>()
    const ids = new Set<number>([selected])
    let changed = true
    while (changed) {
      changed = false
      units.forEach((unit) => {
        if (unit.parent_id && ids.has(Number(unit.parent_id)) && !ids.has(Number(unit.id))) {
          ids.add(Number(unit.id))
          changed = true
        }
      })
    }
    return ids
  }, [units, request?.requesting_unit_id])

  const eligibleEmployees = useMemo(() => {
    if (!allowedUnitIds.size) return employees
    return employees.filter((e) => allowedUnitIds.has(Number(e.organizational_unit_id)))
  }, [employees, allowedUnitIds])

  // Calculate End Time
  const endTime = useMemo(() => {
    const time =
      request?.status === 'SCHEDULE_CONFIRMED'
        ? request.scheduled_start_time?.slice(0, 5) || form.start_time
        : form.start_time
    const duration =
      request?.status === 'SCHEDULE_CONFIRMED'
        ? request.scheduled_duration_minutes || form.duration_minutes
        : form.duration_minutes

    if (!time) return ''
    const [h, m] = time.split(':').map(Number)
    if (isNaN(h) || isNaN(m)) return ''
    const totalMinutes = h * 60 + m + (Number(duration) || 0)
    const endH = Math.floor(totalMinutes / 60) % 24
    const endM = totalMinutes % 60
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`
  }, [form.start_time, form.duration_minutes, request])

  useEffect(() => {
    if (!id || !form.meeting_date || !form.start_time || !endTime || !form.chairperson_employee_id) return
    let cancelled = false
    api.get(`/meeting-requests/${id}/schedule-conflicts`, { params: {
      meeting_date: form.meeting_date, start_time: form.start_time, end_time: endTime,
      location: form.location || undefined, venue_type: form.venue_type, venue_id: form.venue_id || undefined,
      chairperson_employee_id: Number(form.chairperson_employee_id), participant_ids: form.participant_ids,
    }}).then((response) => { if (!cancelled) setConflictResult(apiData(response)) }).catch(() => {})
    return () => { cancelled = true }
  }, [id, form.meeting_date, form.start_time, endTime, form.chairperson_employee_id, form.participant_ids, form.location, form.venue_type, form.venue_id])

  // Final participants list for review
  const finalParticipants = useMemo(() => {
    return (request?.participants || []).filter((p: any) => p.participant_type === 'FINAL')
  }, [request?.participants])

  // Schedule Proposals Handlers
  const handleProposeSchedule = async (data: any) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      if (request?.flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
        await api.post(`/meeting-requests/${id}/authority-schedule/proposals`, data)
      } else {
        await api.post(`/meeting-requests/${id}/schedule-proposals`, data)
      }
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت پیشنهاد زمان جدید با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const handleAcceptProposal = async (proposalId: number) => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      if (request?.flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
        await api.post(`/meeting-requests/${id}/authority-schedule/proposals/${proposalId}/acknowledge`)
      } else {
        await api.post(`/meeting-requests/${id}/schedule-proposals/${proposalId}/accept`)
      }
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'پذیرش پیشنهاد زمان‌بندی با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  // Confirm schedule handler
  const handleConfirmSchedule = async () => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      let endpoint = `/meeting-requests/${id}/confirm-schedule`
      if (request?.flow_type === 'INTER_DIRECTORATE') {
        endpoint = `/meeting-requests/${id}/confirm-inter-directorate-schedule`
      }

      await api.post(endpoint, {
        meeting_date: form.meeting_date,
        start_time: form.start_time,
        duration_minutes: Number(form.duration_minutes),
        location: form.location,
        venue_type: form.venue_type,
        venue_id: form.venue_id || undefined,
        chairperson_employee_id: Number(form.chairperson_employee_id) || undefined,
        participant_ids: form.participant_ids,
      })
      await loadData()
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'تأیید برنامه زمان‌بندی انجام نشد. لطفاً اطلاعات را بررسی کنید.'
      )
    } finally {
      setBusy(false)
    }
  }

  // Finalize meeting handler
  const handleFinalize = async () => {
    if (!id) return
    setBusy(true)
    setError(null)
    try {
      let endpoint = `/meeting-requests/${id}/finalize`
      if (request?.flow_type === 'INTER_DIRECTORATE') {
        endpoint = `/meeting-requests/${id}/finalize-inter-directorate`
      } else if (request?.flow_type === 'MULTI_DIRECTORATE') {
        endpoint = `/meeting-requests/${id}/finalize-multi`
      } else if (request?.flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
        endpoint = `/meeting-requests/${id}/finalize-high-authority`
      }

      await api.post(endpoint, {
        meeting_date: request.scheduled_date?.slice(0, 10) || form.meeting_date,
        start_time: request.scheduled_start_time?.slice(0, 5) || form.start_time,
        end_time: endTime,
        location: request.scheduled_location || form.location,
        venue_type: request.scheduled_venue_type || form.venue_type,
        venue_id: request.scheduled_venue_id || form.venue_id || undefined,
        chairperson_employee_id: Number(request.scheduled_chairperson_employee_id || form.chairperson_employee_id),
        agenda: form.agenda || form.agenda_items.map((item, index) => `${index + 1}. ${item.title}`).filter(Boolean).join('\n'),
        agenda_items: form.agenda_items
          .map((item) => ({ title: item.title.trim(), description: item.description.trim() }))
          .filter((item) => item.title),
      })
      navigate(`/meeting-requests/${id}`)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'ایجاد و صدور جلسه رسمی با خطا مواجه شد.'
      )
    } finally {
      setBusy(false)
    }
  }

  const isConfirmed = request?.status === 'SCHEDULE_CONFIRMED'
  const isInternalDirectorate = request?.flow_type === 'INTERNAL_DIRECTORATE'
  const hasProposals = !isInternalDirectorate && (
    (request?.schedule_proposals || []).length > 0 || request?.flow_type !== 'STANDARD'
  )

  const requestUnits = request?.units?.map((u: any) => ({
    id: u.organizational_unit_id || u.unit?.id,
    name: u.unit?.name || 'ریاست',
    organizational_unit_id: u.organizational_unit_id,
  })) || []

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        eyebrow={request?.request_number || undefined}
        title={isConfirmed ? 'بازبینی و صدور جلسه رسمی' : 'تنظیم و نهایی‌سازی زمان‌بندی جلسه'}
        description={
          request
            ? `${request.request_number || 'پیش‌نویس'} • ${request.subject}`
            : 'مدیریت زمان، مکان، مذاکره پیشنهادها و اشتراک‌کنندگان جلسه'
        }
        action={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate(`/meeting-requests/${id}`)}
              className="text-xs font-semibold rounded-xl gap-1.5"
            >
              <ArrowRight className="size-3.5" />
              <span>بازگشت به درخواست</span>
            </Button>
          </div>
        }
      />

      {/* Stage Bar */}
      {request && <SchedulingStageBar status={request.status} />}

      {/* Loading Skeleton */}
      {loading && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="h-80 rounded-2xl bg-card border border-border/70 animate-pulse" />
          <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
        </div>
      )}

      {/* Error State */}
      {!loading && error && !request && (
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
      {!loading && request && (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          {/* Main Column */}
          <section className="space-y-6">
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
            {/* Multi-party Proposals Negotiation */}
            {hasProposals && !isConfirmed && (
              <ScheduleProposalsCard
                proposals={request.schedule_proposals || []}
                canPropose={['SCHEDULING', 'BILATERAL_APPROVED', 'MULTILATERAL_APPROVED', 'AUTHORITY_SCHEDULING'].includes(request.status)}
                canAccept={['SCHEDULING', 'AUTHORITY_SCHEDULING'].includes(request.status)}
                units={requestUnits}
                venues={venues}
                busy={busy}
                onPropose={handleProposeSchedule}
                onAccept={handleAcceptProposal}
              />
            )}

            {!isConfirmed ? (
              <>
              {conflictResult?.blocking_conflicts?.length ? <Alert variant="destructive" className="rounded-2xl"><AlertCircle className="size-4" /><AlertDescription className="text-xs">این زمان به دلیل تعارض فردی یا محل داخلی قابل ثبت نیست.</AlertDescription></Alert> : null}
              {conflictResult?.warnings?.length ? <Alert className="rounded-2xl border-amber-300 bg-amber-50 text-amber-900"><AlertDescription className="text-xs">هم‌پوشانی زمانی ریاست مشاهده شد؛ در صورت نبود تعارض فردی یا محل، می‌توانید ادامه دهید.</AlertDescription></Alert> : null}
              <ScheduleFormCard
                form={form}
                onChange={handleFieldChange}
                employees={employees}
                eligibleEmployees={eligibleEmployees}
                endTime={endTime}
                busy={busy}
                onSubmit={handleConfirmSchedule}
                venues={venues}
              />
              </>
            ) : (
              <ScheduleReviewCard
                request={request}
                employees={employees}
                finalParticipants={finalParticipants}
                agendaItems={form.agenda_items}
                onAgendaItemsChange={(items) => handleFieldChange('agenda_items', items)}
                endTime={endTime}
                busy={busy}
                onFinalize={handleFinalize}
              />
            )}
          </section>

          {/* Sidebar Column */}
          <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <RequestReferenceCard request={request} />
          </aside>
        </div>
      )}
    </div>
  )
}
