import { useEffect, useState } from 'react'
import axios from 'axios'

import { MultiHighAuthorityDetail as LegacyMultiHighAuthorityDetail } from './flow05_legacy'
export { CreateMultiHighAuthority, MultiHighAuthorityScheduling, MultiHighAuthorityInbox } from './flow05_legacy'

import { Flow05DetailView } from '@/components/amms/flow05-detail-view'
import { PageHeader } from '@/components/amms/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type Any = any

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => {
  const token = sessionStorage.getItem('amms_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

const unwrap = (response: Any) => response.data?.data ?? response.data
const purposeLabels: Record<string, string> = { DECISION: 'تصمیم‌گیری', COORDINATION: 'هماهنگی', CONSULTATION: 'مشورت', REPORTING: 'گزارش‌دهی', APPROVAL: 'تأیید', FOLLOW_UP: 'پیگیری', OTHER: 'سایر' }
const priorityLabels: Record<string, string> = { NORMAL: 'عادی', HIGH: 'بلند', URGENT: 'عاجل' }
const statusLabels: Record<string, string> = { DRAFT: 'پیش‌نویس', PENDING_REQUESTER_APPROVAL: 'منتظر تأیید ریاست درخواست‌کننده', RETURNED_TO_REQUESTER: 'برگشت به درخواست‌کننده', REQUESTER_APPROVED: 'تأیید ریاست درخواست‌کننده', REJECTED_INTERNAL: 'رد داخلی', SUBMITTED_TO_PARTICIPATING_DIRECTORATES: 'ارسال‌شده به ریاست‌های اشتراک‌کننده', UNDER_PARTICIPATING_REVIEW: 'در حال بررسی ریاست‌های اشتراک‌کننده', DIRECTORATE_ACTION_REQUIRED: 'نیازمند اصلاح از سوی ریاست اشتراک‌کننده', DIRECTORATES_APPROVED: 'تأیید همهٔ ریاست‌ها', REJECTED_BY_PARTICIPATING_DIRECTORATE: 'رد توسط ریاست اشتراک‌کننده', SUBMITTED_TO_AUTHORITY_OFFICE: 'ارسال‌شده به دفتر مقام', UNDER_AUTHORITY_OFFICE_REVIEW: 'در حال بررسی دفتر مقام', RETURNED_BY_AUTHORITY_OFFICE: 'برگشت از دفتر مقام', PENDING_HIGH_AUTHORITY_DECISION: 'منتظر تصمیم مقام عالی', RETURNED_BY_HIGH_AUTHORITY: 'برگشت از مقام عالی', HIGH_AUTHORITY_APPROVED: 'تأیید مقام عالی', REJECTED_BY_HIGH_AUTHORITY: 'رد توسط مقام عالی', AUTHORITY_SCHEDULING: 'در حال زمان‌بندی رسمی', SCHEDULE_CONFIRMED: 'زمان‌بندی تأییدشده', CONVERTED_TO_MEETING: 'تبدیل به جلسه' }

function hasRole(user: Any, role: string, unitId?: number) {
  return !!user?.roles?.some((item: Any) => item.role === role && (unitId === undefined || Number(item.organizational_unit_id || item.unit?.id) === Number(unitId)))
}

export function MultiHighAuthorityDetail() {
  const id = window.location.pathname.split('/').filter(Boolean).at(-1)
  const [user, setUser] = useState<Any>(null)
  const [request, setRequest] = useState<Any>(null)
  const [error, setError] = useState('')

  const load = async () => {
    try {
      const response = await api.get(`/meeting-requests/${id}`)
      setRequest(unwrap(response))
      setError('')
    } catch (e: Any) {
      setError(e?.response?.data?.message || 'بارگذاری درخواست ممکن نیست.')
    }
  }

  useEffect(() => { load() }, [id])
  useEffect(() => { api.get('/me').then(response => setUser(unwrap(response))).catch(() => {}) }, [])

  if (!request) return error ? <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{error}</div> : <div className="screen-center"><div className="spinner" /></div>

  if (['RETURNED_TO_REQUESTER', 'DIRECTORATE_ACTION_REQUIRED', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'].includes(request.status)) {
    return <LegacyMultiHighAuthorityDetail />
  }

  const requestId = Number(request.requesting_unit_id)
  const ctx = request.authority_context
  const officeId = Number(ctx?.authority_office_unit_id)
  const requesterUnit = request.units?.find((unit: Any) => unit.role === 'REQUESTER')
  const peers = (request.units || []).filter((unit: Any) => unit.role === 'PARTICIPATING')
  const isRequester = hasRole(user, 'EXECUTIVE_MANAGEMENT', requestId) || Number(request.creator?.id) === Number(user?.id)
  const isDirector = hasRole(user, 'DIRECTOR', requestId)
  const isOffice = hasRole(user, 'AUTHORITY_OFFICE', officeId)
  const isSecretary = hasRole(user, 'EXECUTIVE_SECRETARY', officeId)
  const isAuthority = Number(user?.employee_id || user?.employee?.id) === Number(ctx?.authority_employee_id)
  const peerCapabilities = peers.map((unit: Any) => ({
    unitId: Number(unit.organizational_unit_id),
    canStartReview: hasRole(user, 'EXECUTIVE_MANAGEMENT', Number(unit.organizational_unit_id)),
    canDecide: hasRole(user, 'DIRECTOR', Number(unit.organizational_unit_id)),
  }))

  const action = async (path: string, body?: Any) => {
    await api.post(`/meeting-requests/${request.id}/multi-high/${path}`, body)
    await load()
  }

  return <>
    <PageHeader
      eyebrow={`${request.request_number || 'پیش‌نویس'} · FLOW-05`}
      title={request.subject}
      description={`${requesterUnit?.unit?.name || '—'} · جلسهٔ چندریاستی با مقام عالی`}
      action={<div className="flex flex-wrap items-center gap-2"><Badge variant="outline">{statusLabels[request.status] || request.status}</Badge><Button asChild variant="outline"><a href="/meeting-requests">بازگشت</a></Button></div>}
    />
    <Flow05DetailView
      request={request}
      requesterUnit={requesterUnit}
      peers={peers}
      peerCapabilities={peerCapabilities}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      statusLabels={statusLabels}
      isRequester={isRequester}
      isDirector={isDirector}
      isOffice={isOffice}
      isSecretary={isSecretary}
      isAuthority={isAuthority}
      onAction={action}
    />
  </>
}
