import { useEffect, useMemo, useState } from 'react'
import axios from 'axios'
import { Users } from 'lucide-react'

import { MeetingParticipantChangeWorkspace as LegacyMeetingParticipantChangeWorkspace } from './flow08_legacy'
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

export function MeetingParticipantChangeWorkspace() {
  const id = location.pathname.split('/')[2]
  const [workspace, setWorkspace] = useState<Any>(null)
  const [user, setUser] = useState<Any>(null)
  const [reason, setReason] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const load = async () => {
    try {
      const response = await api.get(`/meetings/${id}/participant-change`)
      setWorkspace(dataOf(response))
      setError('')
    } catch (e: Any) {
      setError(e?.response?.data?.message || 'دسترسی به فضای تغییر اشتراک‌کنندگان ممکن نیست.')
    }
  }

  useEffect(() => { load(); api.get('/me').then(response => setUser(dataOf(response))).catch(() => {}) }, [id])

  if (!workspace) return error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="screen-center"><div className="spinner" /></div>
  if (workspace.active_participant_change_case) return <LegacyMeetingParticipantChangeWorkspace />

  const meeting = workspace.meeting
  const current = workspace.current_participant_version
  const currentParticipants = workspace.current_participants || []
  const formalUnitIds = (meeting?.units || []).filter((unit: Any) => ['REQUESTING', 'PARTICIPATING'].includes(unit.role)).map((unit: Any) => Number(unit.organizational_unit_id)).filter(Boolean)
  const authorityOfficeId = Number(meeting?.authority_context?.authority_office_unit_id || 0)
  const actorExecutiveUnitId = formalUnitIds.find((unitId: number) => hasScopedRole(user, 'EXECUTIVE_MANAGEMENT', unitId)) || 0
  const isAuthorityOffice = hasScopedRole(user, 'AUTHORITY_OFFICE', authorityOfficeId)
  const canCreateCase = !!actorExecutiveUnitId || isAuthorityOffice

  const history = useMemo(() => [...(workspace.participant_versions || [])].sort((a: Any, b: Any) => a.version_number - b.version_number), [workspace])
  const createCase = async () => {
    setBusy(true); setError('')
    try { await api.post(`/meetings/${id}/participant-change-cases`, { reason }); await load(); setReason('') }
    catch (e: Any) { setError(e?.response?.data?.message || 'ایجاد پروندهٔ تغییر فهرست انجام نشد.') }
    finally { setBusy(false) }
  }

  return <>
    <PageHeader eyebrow="FLOW-08 · کنترل فهرست اشتراک‌کنندگان" title="تغییر فهرست اشتراک‌کنندگان" description={`${meeting?.meeting_number || 'جلسه'} · ${meeting?.title || ''}`} action={<Button asChild variant="outline"><a href={`/meetings/${id}`}>بازگشت به جلسه</a></Button>} />
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section className="space-y-6">
        <DetailSummaryCard eyebrow="نسخهٔ جاری فهرست" title={meeting?.title || 'جلسه'} icon={<Users className="size-5" />} items={[
          { label: 'نسخهٔ participant', value: current?.version_number || '—' },
          { label: 'تعداد اشتراک‌کنندگان', value: String(currentParticipants.length) },
          { label: 'نسخهٔ زمان‌بندی مبنا', value: meeting?.current_schedule_version_id || '—' },
          { label: 'وضعیت جلسه', value: meeting?.status || '—' },
        ]} description="فهرست رسمی فقط بعد از confirmation پروندهٔ تغییر، نسخهٔ جدید می‌گیرد." />

        <Card>
          <CardHeader className="border-b"><CardTitle className="text-base">اشتراک‌کنندگان فعلی</CardTitle></CardHeader>
          <CardContent className="space-y-2 pt-5">{currentParticipants.length ? currentParticipants.map((participant: Any) => <div key={participant.id} className="flex items-center justify-between gap-3 rounded-xl border p-3"><div><strong className="block text-sm">{participant.employee?.full_name || '—'}</strong><span className="text-xs text-muted-foreground">{participant.employee?.unit?.name || participant.organizational_unit?.name || '—'}</span></div><span className="text-xs text-muted-foreground">{participant.participant_role || 'PARTICIPATING'}</span></div>) : <p className="text-sm text-muted-foreground">فهرست فعلی خالی است.</p>}</CardContent>
        </Card>

        <WorkflowTimeline title="تاریخچهٔ نسخه‌های participant" items={history.map((version: Any) => ({ id: version.id, title: `نسخهٔ ${version.version_number}`, meta: version.created_at ? new Date(version.created_at).toLocaleString('fa-AF') : undefined, comment: version.id === current?.id ? 'نسخهٔ جاری فهرست' : undefined }))} />
      </section>
      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
        <Card>
          <CardHeader className="border-b"><CardTitle className="text-base">ایجاد پروندهٔ رسمی تغییر فهرست</CardTitle></CardHeader>
          <CardContent className="space-y-3 pt-5">
            {error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : null}
            {canCreateCase ? <><p className="text-sm text-muted-foreground">پرونده بر اساس scope فعلی شما به ریاست یا دفتر مقام تعلق می‌گیرد.</p><Textarea value={reason} onChange={event => setReason(event.target.value)} placeholder="دلیل رسمی تغییر فهرست" /><Button className="w-full" disabled={busy || !reason.trim()} onClick={createCase}>{busy ? 'در حال ثبت...' : 'ایجاد پروندهٔ تغییر فهرست'}</Button></> : <Alert><AlertDescription>برای این جلسه صلاحیت ایجاد پروندهٔ تغییر اشتراک‌کنندگان ندارید.</AlertDescription></Alert>}
          </CardContent>
        </Card>
      </aside>
    </div>
  </>
}
