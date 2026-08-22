import { useEffect, useState } from 'react'
import axios from 'axios'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/amms/page-header'
import { Flow03DetailView } from '@/components/amms/flow03-detail-view'
import { MultiDirectorateDetail as LegacyMultiDirectorateDetail } from './flow03_legacy'
export { CreateMultiDirectorate, MultiDirectorateScheduling } from './flow03_legacy'

type Any = any

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => {
  const token = sessionStorage.getItem('amms_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

const unwrap = (res: Any) => res.data?.data ?? res.data
const purposeLabels: Record<string, string> = { DECISION: 'تصمیم‌گیری', COORDINATION: 'هماهنگی', CONSULTATION: 'مشورت', REPORTING: 'گزارش‌دهی', APPROVAL: 'تأیید', FOLLOW_UP: 'پیگیری', OTHER: 'سایر' }
const priorityLabels: Record<string, string> = { NORMAL: 'عادی', HIGH: 'بلند', URGENT: 'عاجل' }
const statusLabels: Record<string, string> = { DRAFT: 'پیش‌نویس', PENDING_REQUESTER_APPROVAL: 'منتظر تأیید ریاست درخواست‌کننده', REQUESTER_APPROVED: 'تأیید ریاست درخواست‌کننده', SUBMITTED_TO_TARGETS: 'ارسال‌شده به ریاست‌های مقصد', UNDER_MULTI_TARGET_REVIEW: 'در حال بررسی ریاست‌های مقصد', TARGET_ACTION_REQUIRED: 'نیازمند اصلاح از سوی ریاست‌های مقصد', MULTILATERAL_APPROVED: 'تأیید چندجانبه', SCHEDULING: 'در حال زمان‌بندی', SCHEDULE_CONFIRMED: 'زمان‌بندی نهایی', CONVERTED_TO_MEETING: 'تبدیل به جلسه', RETURNED_TO_REQUESTER: 'برگشت به درخواست‌کننده', REJECTED_INTERNAL: 'رد داخلی', REJECTED_BY_TARGET: 'رد توسط ریاست مقصد' }
const reviewLabels: Record<string, string> = { PENDING_REVIEW: 'منتظر بررسی', UNDER_REVIEW: 'در حال بررسی', APPROVED: 'تأیید شده', RETURNED_FOR_CLARIFICATION: 'برگشت جهت وضاحت', REJECTED: 'رد شده' }

function activeUnitIds(user: Any, roles: string[]) {
  return (user?.roles || []).filter((role: Any) => roles.includes(role.role)).map((role: Any) => Number(role.unit?.id || role.organizational_unit_id)).filter(Boolean)
}

export function MultiDirectorateDetail() {
  const id = location.pathname.split('/')[2]
  const [user, setUser] = useState<Any>(null)
  const [request, setRequest] = useState<Any>(null)
  const [error, setError] = useState('')

  const load = () => api.get(`/meeting-requests/${id}`).then(response => setRequest(unwrap(response))).catch((e: Any) => setError(e.response?.data?.message || 'بارگذاری درخواست ممکن نشد.'))

  useEffect(() => {
    load()
    api.get('/me').then(response => setUser(unwrap(response))).catch(() => {})
  }, [id])

  if (!request) return error ? <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{error}</div> : <div className="screen-center"><div className="spinner" /></div>

  if (['RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'].includes(request.status)) {
    return <LegacyMultiDirectorateDetail />
  }

  const requester = request.units?.find((unit: Any) => unit.role === 'REQUESTER')
  const targets = request.units?.filter((unit: Any) => unit.role === 'TARGET') || []
  const requesterId = Number(requester?.organizational_unit_id)
  const execIds = activeUnitIds(user, ['EXECUTIVE_MANAGEMENT'])
  const directorIds = activeUnitIds(user, ['DIRECTOR'])
  const targetCapabilities = targets.map((target: Any) => ({
    unitId: Number(target.organizational_unit_id),
    canStartReview: execIds.includes(Number(target.organizational_unit_id)),
    canDecide: directorIds.includes(Number(target.organizational_unit_id)),
  }))
  const canEnterScheduling = execIds.some((unitId: number) => request.units?.some((unit: Any) => Number(unit.organizational_unit_id) === unitId))

  const action = async (url: string, body?: Any) => {
    await api.post(`/meeting-requests/${id}/${url}`, body)
    await load()
  }

  return <>
    <PageHeader
      eyebrow={`${request.request_number || 'پیش‌نویس'} · FLOW-03`}
      title={request.subject}
      description={`${requester?.unit?.name || '—'} + ${targets.map((unit: Any) => unit.unit?.name).join(' + ')}`}
      action={<div className="flex flex-wrap items-center gap-2"><Badge variant="outline">{statusLabels[request.status] || request.status}</Badge><Button asChild variant="outline"><a href="/meeting-requests">بازگشت</a></Button></div>}
    />
    <Flow03DetailView
      request={request}
      requester={requester}
      targets={targets}
      targetCapabilities={targetCapabilities}
      canRequesterManage={execIds.includes(requesterId)}
      canRequesterApprove={directorIds.includes(requesterId)}
      canEnterScheduling={canEnterScheduling}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      statusLabels={statusLabels}
      reviewLabels={reviewLabels}
      onAction={action}
    />
  </>
}
