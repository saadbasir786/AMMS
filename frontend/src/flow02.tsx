import { useEffect, useState } from 'react'
import axios from 'axios'
import { Flow02DetailV2 as LegacyFlow02DetailV2 } from './flow02_legacy'
export { CreateInterDirectorateRequestV2, Flow02SchedulingV2 } from './flow02_legacy'
import { Flow02DetailView } from '@/components/amms/flow02-detail-view'
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

const purposeLabels: Record<string, string> = {
  DECISION: 'تصمیم‌گیری',
  COORDINATION: 'هماهنگی',
  CONSULTATION: 'مشورت',
  REPORTING: 'گزارش‌دهی',
  APPROVAL: 'تأیید',
  FOLLOW_UP: 'پیگیری',
  OTHER: 'سایر',
}

const priorityLabels: Record<string, string> = { NORMAL: 'عادی', HIGH: 'بلند', URGENT: 'عاجل' }

const statusLabels: Record<string, string> = {
  DRAFT: 'پیش‌نویس',
  PENDING_REQUESTER_APPROVAL: 'منتظر تأیید ریاست درخواست‌کننده',
  RETURNED_TO_REQUESTER: 'برگشت به درخواست‌کننده',
  REQUESTER_APPROVED: 'تأیید ریاست درخواست‌کننده',
  SUBMITTED_TO_TARGET: 'ارسال‌شده به ریاست مقصد',
  UNDER_TARGET_REVIEW: 'در حال بررسی ریاست مقصد',
  RETURNED_BY_TARGET: 'برگشت از ریاست مقصد',
  TARGET_APPROVED: 'تأیید ریاست مقصد',
  REJECTED_BY_TARGET: 'رد توسط ریاست مقصد',
  BILATERAL_APPROVED: 'تأیید دوطرفه',
  SCHEDULING: 'در حال زمان‌بندی',
  SCHEDULE_CONFIRMED: 'زمان‌بندی نهایی',
  CONVERTED_TO_MEETING: 'تبدیل به جلسه',
}

function unitId(role: Any) {
  return Number(role?.unit?.id || role?.organizational_unit_id)
}

function hasScope(user: Any, role: string, id: number) {
  return !!user?.roles?.some((item: Any) => item.role === role && unitId(item) === Number(id))
}

export function Flow02DetailV2({ r, reload }: { r: Any; reload: () => void }) {
  const [user, setUser] = useState<Any>(null)
  useEffect(() => {
    api.get('/me').then(response => setUser(response.data?.data ?? response.data)).catch(() => {})
  }, [])

  if (['RETURNED_TO_REQUESTER', 'RETURNED_BY_TARGET'].includes(r.status)) {
    return <LegacyFlow02DetailV2 r={r} reload={reload} />
  }

  const requester = r.units?.find((unit: Any) => unit.role === 'REQUESTER')
  const target = r.units?.find((unit: Any) => unit.role === 'TARGET')
  const requesterId = Number(requester?.organizational_unit_id)
  const targetId = Number(target?.organizational_unit_id)
  const canRequesterExec = hasScope(user, 'EXECUTIVE_MANAGEMENT', requesterId)
  const canRequesterDirector = hasScope(user, 'DIRECTOR', requesterId)
  const canTargetExec = hasScope(user, 'EXECUTIVE_MANAGEMENT', targetId)
  const canTargetDirector = hasScope(user, 'DIRECTOR', targetId)

  const action = async (url: string, body?: Any) => {
    await api.post(`/meeting-requests/${r.id}/${url}`, body)
    reload()
  }

  return <>
    <PageHeader
      eyebrow={`${r.request_number || 'پیش‌نویس'} · جلسه بین دو ریاست`}
      title={r.subject}
      description={`${requester?.unit?.name || '—'} ↔ ${target?.unit?.name || '—'}`}
      action={<div className="flex flex-wrap items-center gap-2"><Badge variant="outline">{statusLabels[r.status] || r.status}</Badge><Button asChild variant="outline"><a href="/meeting-requests">بازگشت</a></Button></div>}
    />
    <Flow02DetailView
      request={r}
      requester={requester}
      target={target}
      canRequesterExec={canRequesterExec}
      canRequesterDirector={canRequesterDirector}
      canTargetExec={canTargetExec}
      canTargetDirector={canTargetDirector}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      onAction={action}
    />
  </>
}
