import { useEffect, useState } from 'react'
import axios from 'axios'

import { AuthorityInitiatedDetail as LegacyAuthorityInitiatedDetail } from './flow06_legacy'
export { CreateAuthorityInitiated, AuthorityInitiatedScheduling, AuthorityInitiatedInbox } from './flow06_legacy'

import { Flow06DetailView } from '@/components/amms/flow06-detail-view'
import { PageHeader } from '@/components/amms/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type Any = any

type UnitCapability = {
  unitId: number
  canCoordinate: boolean
  canAcknowledge: boolean
}

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => {
  const token = sessionStorage.getItem('amms_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

const unwrap = (response: Any) => response.data?.data ?? response.data
const purposeLabels: Record<string, string> = { DECISION: 'تصمیم‌گیری', COORDINATION: 'هماهنگی', CONSULTATION: 'مشورت', REPORTING: 'گزارش‌دهی', APPROVAL: 'تأیید', FOLLOW_UP: 'پیگیری', OTHER: 'سایر' }
const priorityLabels: Record<string, string> = { NORMAL: 'عادی', HIGH: 'بلند', URGENT: 'عاجل' }
const statusLabels: Record<string, string> = { DRAFT: 'پیش‌نویس دستور مقام', PENDING_HIGH_AUTHORITY_ISSUANCE: 'منتظر صدور مقام عالی', RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION: 'برگشت برای اصلاح مسوده', ISSUED_BY_HIGH_AUTHORITY: 'صادرشده توسط مقام عالی', DISTRIBUTED_TO_DIRECTORATES: 'توزیع‌شده به ریاست‌ها', UNDER_DIRECTORATE_COORDINATION: 'در حال هماهنگی ریاست‌ها', DIRECTORATE_CLARIFICATION_REQUIRED: 'نیازمند وضاحت ریاست', PENDING_HIGH_AUTHORITY_REISSUANCE: 'منتظر صدور مجدد مقام عالی', DIRECTORATES_ACKNOWLEDGED: 'تأیید دریافت همهٔ ریاست‌ها', AUTHORITY_SCHEDULING: 'در حال زمان‌بندی رسمی', SCHEDULE_CONFIRMED: 'زمان‌بندی تأییدشده', CONVERTED_TO_MEETING: 'تبدیل به جلسه' }

function hasRole(user: Any, role: string, unitId?: number) {
  return !!user?.roles?.some((item: Any) => item.role === role && (unitId === undefined || Number(item.organizational_unit_id || item.unit?.id) === Number(unitId)))
}

export function AuthorityInitiatedDetail() {
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
      setError(e?.response?.data?.message || 'بارگذاری دستور ممکن نیست.')
    }
  }

  useEffect(() => { load() }, [id])
  useEffect(() => { api.get('/me').then(response => setUser(unwrap(response))).catch(() => {}) }, [])

  if (!request) return error ? <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{error}</div> : <div className="screen-center"><div className="spinner" /></div>

  if (['DRAFT', 'RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION', 'DIRECTORATE_CLARIFICATION_REQUIRED'].includes(request.status)) {
    return <LegacyAuthorityInitiatedDetail />
  }

  const ctx = request.authority_context
  const officeId = Number(ctx?.authority_office_unit_id)
  const units = (request.units || []).filter((unit: Any) => unit.role === 'PARTICIPATING')
  const isOffice = hasRole(user, 'AUTHORITY_OFFICE', officeId)
  const isSecretary = hasRole(user, 'EXECUTIVE_SECRETARY', officeId)
  const isAuthority = Number(user?.employee_id || user?.employee?.id) === Number(ctx?.authority_employee_id)
  const unitCapabilities: UnitCapability[] = units.map((unit: Any) => ({
    unitId: Number(unit.organizational_unit_id),
    canCoordinate: hasRole(user, 'EXECUTIVE_MANAGEMENT', Number(unit.organizational_unit_id)),
    canAcknowledge: hasRole(user, 'DIRECTOR', Number(unit.organizational_unit_id)),
  }))
  const canOpenScheduling = isSecretary || isOffice || unitCapabilities.some((item: UnitCapability) => item.canCoordinate || item.canAcknowledge)

  const action = async (path: string, body?: Any) => {
    await api.post(`/meeting-requests/${request.id}/authority-initiated/${path}`, body)
    await load()
  }

  return <>
    <PageHeader
      eyebrow={`${request.request_number || 'دستور مقام'} · FLOW-06`}
      title={request.subject}
      description={`ابتکار مقام عالی · ${units.map((unit: Any) => unit.unit?.name).filter(Boolean).join('، ') || 'ریاست‌ها'}`}
      action={<div className="flex flex-wrap items-center gap-2"><Badge variant="outline">{statusLabels[request.status] || request.status}</Badge><Button asChild variant="outline"><a href="/meeting-requests">بازگشت</a></Button></div>}
    />
    <Flow06DetailView
      request={request}
      units={units}
      unitCapabilities={unitCapabilities}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      statusLabels={statusLabels}
      isOffice={isOffice}
      isSecretary={isSecretary}
      isAuthority={isAuthority}
      canOpenScheduling={canOpenScheduling}
      onAction={action}
    />
  </>
}
