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
import { fetchMeetingRequestActionCapabilities, type MeetingRequestActionCapabilities } from '../lib/request-capabilities'
import { InterDirectorateRequestDetailView } from '../components/request-detail/inter-directorate-request-detail-view'
import { MultiDirectorateRequestDetailView } from '../components/request-detail/multi-directorate-request-detail-view'
import { DirectorateHighAuthorityRequestDetailView } from '../components/request-detail/directorate-high-authority-request-detail-view'
import { MultiDirectorateHighAuthorityRequestDetailView } from '../components/request-detail/multi-directorate-high-authority-request-detail-view'
import { HighAuthorityInitiatedRequestDetailView } from '../components/request-detail/high-authority-initiated-request-detail-view'

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

function hasRole(user: any, role: string, unitId?: number) {
  return !!user?.roles?.some(
    (r: any) =>
      r.role === role &&
      (unitId === undefined || Number(r.unit?.id || r.organizational_unit_id) === Number(unitId))
  )
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
  const [capabilities, setCapabilities] = useState<MeetingRequestActionCapabilities | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [reqRes, meRes, capabilitiesRes] = await Promise.allSettled([
        api.get(`/meeting-requests/${id}`),
        initialUser ? Promise.resolve({ data: initialUser }) : api.get('/me'),
        fetchMeetingRequestActionCapabilities(id),
      ])

      if (reqRes.status === 'fulfilled') {
        setRequest(apiData(reqRes.value))
      } else {
        throw reqRes.reason
      }

      if (meRes.status === 'fulfilled') {
        setCurrentUser(apiData(meRes.value))
      }

      if (capabilitiesRes.status === 'fulfilled') {
        setCapabilities(capabilitiesRes.value)
      } else {
        throw capabilitiesRes.reason
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

  const statusInfo = request?.status
    ? STATUS_LABELS[request.status] || { label: request.status, variant: 'outline' }
    : null

  if (loading) {
    return (
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" dir="rtl">
        <div className="space-y-6">
          <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
          <div className="h-44 rounded-2xl bg-card border border-border/70 animate-pulse" />
        </div>
        <div className="space-y-6">
          <div className="h-48 rounded-2xl bg-card border border-border/70 animate-pulse" />
          <div className="h-64 rounded-2xl bg-card border border-border/70 animate-pulse" />
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <Alert variant="destructive" className="rounded-2xl p-4" dir="rtl">
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
    )
  }

  if (!request) return null

  // Specialized Flow Views
  if (request.flow_type === 'INTER_DIRECTORATE') {
    const requester = request.units?.find((u: any) => u.role === 'REQUESTER')
    const target = request.units?.find((u: any) => u.role === 'TARGET')
    const reqUnitId = Number(requester?.organizational_unit_id)
    const tgtUnitId = Number(target?.organizational_unit_id)

    return (
      <InterDirectorateRequestDetailView
        request={request}
        requester={requester}
        target={target}
        canRequesterExec={hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', reqUnitId)}
        canRequesterDirector={hasRole(currentUser, 'DIRECTOR', reqUnitId)}
        canTargetExec={hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', tgtUnitId)}
        canTargetDirector={hasRole(currentUser, 'DIRECTOR', tgtUnitId)}
        purposeLabels={purposeLabels}
        priorityLabels={priorityLabels}
        onAction={handleAction}
      />
    )
  }

  if (request.flow_type === 'MULTI_DIRECTORATE') {
    const requester = request.units?.find((u: any) => u.role === 'REQUESTER')
    const targets = request.units?.filter((u: any) => u.role === 'TARGET') || []
    const reqUnitId = Number(requester?.organizational_unit_id)
    const canRequesterManage = hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', reqUnitId)
    const canRequesterApprove = hasRole(currentUser, 'DIRECTOR', reqUnitId)
    const targetCapabilities = targets.map((t: any) => {
      const uid = Number(t.organizational_unit_id)
      return {
        unitId: uid,
        canStartReview: hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', uid),
        canDecide: hasRole(currentUser, 'DIRECTOR', uid),
      }
    })
    const canEnterScheduling = ['SCHEDULING', 'SCHEDULE_CONFIRMED'].includes(request.status)

    return (
      <MultiDirectorateRequestDetailView
        request={request}
        requester={requester}
        targets={targets}
        targetCapabilities={targetCapabilities}
        canRequesterManage={canRequesterManage}
        canRequesterApprove={canRequesterApprove}
        canEnterScheduling={canEnterScheduling}
        purposeLabels={purposeLabels}
        priorityLabels={priorityLabels}
        statusLabels={{}}
        reviewLabels={{}}
        onAction={handleAction}
      />
    )
  }

  if (request.flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
    const ctx = request.authority_context
    const requesterId = Number(request.requesting_unit_id)
    const officeId = Number(ctx?.authority_office_unit_id)
    const isCreator =
      Number(request.creator?.id) === Number(currentUser?.id) ||
      hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', requesterId)
    const isAuthorityOffice = hasRole(currentUser, 'AUTHORITY_OFFICE', officeId)
    const isSecretary = hasRole(currentUser, 'EXECUTIVE_SECRETARY', officeId)
    const isDirector = hasRole(currentUser, 'DIRECTOR', requesterId)
    const isAuthority =
      Number(currentUser?.employee?.id || currentUser?.employee_id) ===
      Number(ctx?.authority_employee_id)
    const canOpenScheduling = isCreator || isAuthorityOffice || isSecretary

    return (
      <DirectorateHighAuthorityRequestDetailView
        request={request}
        purposeLabels={purposeLabels}
        priorityLabels={priorityLabels}
        isCreator={isCreator}
        isAuthorityOffice={isAuthorityOffice}
        isSecretary={isSecretary}
        isDirector={isDirector}
        isAuthority={isAuthority}
        canOpenScheduling={canOpenScheduling}
        onAction={handleAction}
      />
    )
  }

  if (request.flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY') {
    const requesterUnit = request.units?.find((u: any) => u.role === 'REQUESTER')
    const peers = request.units?.filter((u: any) => u.role === 'PARTICIPATING') || []
    const reqUnitId = Number(requesterUnit?.organizational_unit_id)
    const officeId = Number(request.authority_context?.authority_office_unit_id)
    const isRequester = hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', reqUnitId)
    const isDirector = hasRole(currentUser, 'DIRECTOR', reqUnitId)
    const isOffice = hasRole(currentUser, 'AUTHORITY_OFFICE', officeId)
    const isSecretary = hasRole(currentUser, 'EXECUTIVE_SECRETARY', officeId)
    const isAuthority =
      Number(currentUser?.employee?.id || currentUser?.employee_id) ===
      Number(request.authority_context?.authority_employee_id)

    const peerCapabilities = peers.map((p: any) => {
      const uid = Number(p.organizational_unit_id)
      return {
        unitId: uid,
        canStartReview: hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', uid),
        canDecide: hasRole(currentUser, 'DIRECTOR', uid),
      }
    })

    return (
      <MultiDirectorateHighAuthorityRequestDetailView
        request={request}
        requesterUnit={requesterUnit}
        peers={peers}
        peerCapabilities={peerCapabilities}
        purposeLabels={purposeLabels}
        priorityLabels={priorityLabels}
        statusLabels={{}}
        isRequester={isRequester}
        isDirector={isDirector}
        isOffice={isOffice}
        isSecretary={isSecretary}
        isAuthority={isAuthority}
        onAction={handleAction}
      />
    )
  }

  if (request.flow_type === 'HIGH_AUTHORITY_INITIATED') {
    const units = request.units || []
    const officeId = Number(request.authority_context?.authority_office_unit_id)
    const isOffice = hasRole(currentUser, 'AUTHORITY_OFFICE', officeId)
    const isSecretary = hasRole(currentUser, 'EXECUTIVE_SECRETARY', officeId)
    const isAuthority =
      Number(currentUser?.employee?.id || currentUser?.employee_id) ===
      Number(request.authority_context?.authority_employee_id)
    const canOpenScheduling = isOffice || isSecretary

    const unitCapabilities = units.map((u: any) => {
      const uid = Number(u.organizational_unit_id)
      return {
        unitId: uid,
        canCoordinate: hasRole(currentUser, 'EXECUTIVE_MANAGEMENT', uid),
        canAcknowledge: hasRole(currentUser, 'DIRECTOR', uid),
      }
    })

    return (
      <HighAuthorityInitiatedRequestDetailView
        request={request}
        units={units}
        unitCapabilities={unitCapabilities}
        purposeLabels={purposeLabels}
        priorityLabels={priorityLabels}
        statusLabels={{}}
        isOffice={isOffice}
        isSecretary={isSecretary}
        isAuthority={isAuthority}
        canOpenScheduling={canOpenScheduling}
        onAction={handleAction}
      />
    )
  }

  // Standard Internal Request View
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

      {/* Loaded Content */}
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
            capabilities={capabilities || {
              edit: false,
              submit: false,
              approve: false,
              return_for_revision: false,
              reject: false,
              start_scheduling: false,
              open_scheduling: false,
              finalize: false,
            }}
            onAction={handleAction}
            onEdit={() => navigate(`/meeting-requests/${request.id}/edit`)}
            onScheduling={() => navigate(`/meeting-requests/${request.id}/scheduling`)}
          />

          <RequestTimelineCard actions={request.actions} />
        </aside>
      </div>
    </div>
  )
}
