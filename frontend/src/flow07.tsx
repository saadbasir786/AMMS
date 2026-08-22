import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { CalendarDays } from 'lucide-react'

import { MeetingRescheduleWorkspace as LegacyMeetingRescheduleWorkspace } from './flow07_legacy'
import { PageHeader } from '@/components/amms/page-header'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { WorkflowTimeline } from '@/components/amms/workflow-timeline'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'

type Any = any

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const dataOf = (response: Any) => response.data?.data ?? response.data
const roleUnitId = (role: Any) => Number(role?.organizational_unit_id ?? role?.unit?.id ?? 0)
const hasScopedRole = (user: Any, roleName: string, unitId: number) => !!unitId && !!user?.roles?.some((role: Any) => role.role === roleName && roleUnitId(role) === Number(unitId))

export function MeetingRescheduleWorkspace() {
  const id = location.pathname.split('/')[2]
  const [workspace, setWorkspace] = useState<Any>(null)
  const [user, setUser] = useState<Any>(null)
  const [reason, setReason] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const load = async () => {
    try {
      const response = await api.get(`/meetings/${id}/reschedule`)
      setWorkspace(dataOf(response))
      setError('')
    } catch (e: Any) {
      setError(e?.response?.data?.message || 'دسترسی به فضای تغییر زمان ممکن نیست.')
    }
  }

  useEffect(() => { load(); api.get('/me').then(response => setUser(dataOf(response))).catch(() => {}) }, [id])

  if (!workspace) return error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="screen-center"><div className="spinner" /></div>
  if (workspace.active_reschedule_case) return <LegacyMeetingRescheduleWorkspace />

  const meeting = workspace.meeting
  const current = workspace.current_schedule_version
  const flowType = meeting?.request?.flow_type
  const authorityControlled = ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'HIGH_AUTHORITY_INITIATED'].includes(flowType) || !!meeting?.authority_context
  const authorityOfficeId = Number(meeting?.authority_context?.authority_office_unit_id || 0)
  const required = flowType === 'HIGH_AUTHORITY_INITIATED'
    ? (meeting?.units || []).filter((unit: Any) => unit.role === 'PARTICIPATING')
    : (meeting?.units || []).filter((unit: Any) => ['REQUESTING', 'PARTICIPATING'].includes(unit.role))
  const formalUnitIds = required.map((unit: Any) => Number(unit.organizational_unit_id)).filter(Boolean)
  const actorFormalUnitId = formalUnitIds.find((unitId: number) => hasScopedRole(user, 'EXECUTIVE_MANAGEMENT', unitId) || hasScopedRole(user, 'DIRECTOR', unitId)) || 0
  const isSecretary = authorityControlled && hasScopedRole(user, 'EXECUTIVE_SECRETARY', authorityOfficeId)
  const canCreateCase = authorityControlled ? isSecretary : (!!actorFormalUnitId && (hasScopedRole(user, 'EXECUTIVE_MANAGEMENT', actorFormalUnitId) || hasScopedRole(user, 'DIRECTOR', actorFormalUnitId)))

  const history = useMemo(() => [...(workspace.schedule_versions || [])].sort((a: Any, b: Any) => a.version_number - b.version_number), [workspace])
  const createCase = async () => {
    setBusy(true); setError('')
    try { await api.post(`/meetings/${id}/reschedule-cases`, { reason }); await load(); setReason('') }
    catch (e: Any) { setError(e?.response?.data?.message || 'ایجاد پروندهٔ تغییر زمان انجام نشد.') }
    finally { setBusy(false) }
  }

  return <>
    <PageHeader eyebrow="FLOW-07 · تغییر زمان‌بندی" title="فضای تغییر زمان جلسه" description={`${meeting?.meeting_number || 'جلسه'} · ${meeting?.title || ''}`} action={<Button asChild variant="outline"><a href={`/meetings/${id}`}>بازگشت به جلسه</a></Button>} />
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section className="space-y-6">
        <DetailSummaryCard eyebrow="نسخهٔ جاری زمان‌بندی" title={meeting?.title || 'جلسه'} icon={<CalendarDays className="size-5" />} items={[
          { label: 'تاریخ', value: current?.meeting_date ? new Date(current.meeting_date).toLocaleDateString('fa-AF') : '—' },
          { label: 'ساعت', value: current ? `${current.start_time || '—'} تا ${current.end_time || '—'}` : '—' },
          { label: 'محل', value: current?.location || '—' },
          { label: 'نسخه', value: current?.version_number || '—' },
        ]} description="تا زمان تأیید نهایی پروندهٔ تغییر زمان، همین نسخهٔ زمان‌بندی معتبر باقی می‌ماند." />
        <WorkflowTimeline title="تاریخچهٔ زمان‌بندی" items={history.map((version: Any) => ({ id: version.id, title: `نسخهٔ ${version.version_number}`, meta: `${version.meeting_date ? new Date(version.meeting_date).toLocaleDateString('fa-AF') : '—'} · ${version.start_time || '—'} تا ${version.end_time || '—'} · ${version.location || '—'}`, comment: version.id === current?.id ? 'نسخهٔ جاری' : undefined }))} />
      </section>
      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
        <Card>
          <CardHeader className="border-b"><CardTitle className="text-base">ایجاد پروندهٔ رسمی تغییر زمان</CardTitle></CardHeader>
          <CardContent className="space-y-3 pt-5">
            {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
            {canCreateCase ? <><p className="text-sm text-muted-foreground">دلیل تغییر زمان اجباری است؛ جلسهٔ اصلی فعلاً بدون تغییر باقی می‌ماند.</p><Textarea value={reason} onChange={event => setReason(event.target.value)} placeholder="دلیل رسمی تغییر زمان" /><Button className="w-full" disabled={busy || !reason.trim()} onClick={createCase}>{busy ? 'در حال ثبت...' : 'ایجاد پروندهٔ تغییر زمان'}</Button></> : <Alert><AlertDescription>برای این جلسه صلاحیت ایجاد پروندهٔ تغییر زمان ندارید.</AlertDescription></Alert>}
          </CardContent>
        </Card>
      </aside>
    </div>
  </>
}
