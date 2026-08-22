import { useEffect, useState } from 'react'
import axios from 'axios'

import { HighAuthorityDetail as LegacyHighAuthorityDetail } from './flow04_legacy'
export { CreateHighAuthority, HighAuthorityScheduling, HighAuthorityInbox } from './flow04_legacy'

import { Flow04DetailView } from '@/components/amms/flow04-detail-view'
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
const statusLabels: Record<string, string> = { DRAFT: 'پیش‌نویس', PENDING_REQUESTER_APPROVAL: 'منتظر تأیید ریاست درخواست‌کننده', RETURNED_TO_REQUESTER: 'برگشت به درخواست‌کننده', REQUESTER_APPROVED: 'تأیید ریاست درخواست‌کننده', SUBMITTED_TO_AUTHORITY_OFFICE: 'ارسال‌شده به دفتر مقام', UNDER_AUTHORITY_OFFICE_REVIEW: 'در حال بررسی دفتر مقام', RETURNED_BY_AUTHORITY_OFFICE: 'برگشت از دفتر مقام', PENDING_HIGH_AUTHORITY_DECISION: 'منتظر تصمیم مقام عالی', RETURNED_BY_HIGH_AUTHORITY: 'برگشت از مقام عالی', REJECTED_BY_HIGH_AUTHORITY: 'رد توسط مقام عالی', HIGH_AUTHORITY_APPROVED: 'تأیید مقام عالی', AUTHORITY_SCHEDULING: 'در حال زمان‌بندی دفتر مقام', SCHEDULE_CONFIRMED: 'زمان‌بندی رسمی تأییدشده', CONVERTED_TO_MEETING: 'تبدیل به جلسه' }

function hasRole(user: Any, role: string, unitId?: number) {
  return !!user?.roles?.some((item: Any) => item.role === role && (unitId === undefined || Number(item.unit?.id || item.organizational_unit_id) === Number(unitId)))
}

export function HighAuthorityDetail() {
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

  if (['RETURNED_TO_REQUESTER', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'].includes(request.status)) {
    return <LegacyHighAuthorityDetail />
  }

  const ctx = request.authority_context
  const requesterId = Number(request.requesting_unit_id)
  const officeId = Number(ctx?.authority_office_unit_id)
  const isCreator = Number(request.creator?.id) === Number(user?.id) || hasRole(user, 'EXECUTIVE_MANAGEMENT', requesterId)
  const isAuthorityOffice = hasRole(user, 'AUTHORITY_OFFICE', officeId)
  const isSecretary = hasRole(user, 'EXECUTIVE_SECRETARY', officeId)
  const isDirector = hasRole(user, 'DIRECTOR', requesterId)
  const isAuthority = Number(user?.employee?.id || user?.employee_id) === Number(ctx?.authority_employee_id)
  const canOpenScheduling = isCreator || isAuthorityOffice || isSecretary

  const action = async (url: string, body?: Any) => {
    await api.post(`/meeting-requests/${request.id}/${url}`, body)
    await load()
  }

  return <>
    <PageHeader
      eyebrow={`${request.request_number || 'پیش‌نویس'} · FLOW-04`}
      title={request.subject}
      description={`${request.requesting_unit?.name || '—'} · ${purposeLabels[request.purpose] || request.purpose}`}
      action={<div className="flex flex-wrap items-center gap-2"><Badge variant="outline">{statusLabels[request.status] || request.status}</Badge><Button asChild variant="outline"><a href="/meeting-requests">بازگشت</a></Button></div>}
    />
    <Flow04DetailView
      request={request}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      isCreator={isCreator}
      isAuthorityOffice={isAuthorityOffice}
      isSecretary={isSecretary}
      isDirector={isDirector}
      isAuthority={isAuthority}
      canOpenScheduling={canOpenScheduling}
      onAction={action}
    />
  </>
}
