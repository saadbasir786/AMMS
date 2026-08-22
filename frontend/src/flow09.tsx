import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { KeyRound, Monitor } from 'lucide-react'

export { TerminalRegistry, MeetingTerminalWorkspace } from './flow09_legacy'
import { PageHeader } from '@/components/amms/page-header'
import { DetailSummaryCard } from '@/components/amms/detail-summary-card'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'

type Any = any

const api = axios.create({ baseURL: '/api' })
api.interceptors.request.use(config => { const token = localStorage.getItem('amms_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config })
const bodyData = (response: Any) => response.data?.data ?? response.data
const statusLabels: Record<string, string> = { ACTIVE: 'فعال', INACTIVE: 'غیرفعال', MAINTENANCE: 'در حال نگهداری', REVOKED: 'لغوشده', ASSIGNED: 'تخصیص‌یافته', STALE: 'قدیمی', REVOKED_ASSIGNMENT: 'لغوشده', EXPIRED: 'منقضی' }

export function TerminalDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [terminal, setTerminal] = useState<Any>(null)
  const [credential, setCredential] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [revokeOpen, setRevokeOpen] = useState(false)
  const [reason, setReason] = useState('')

  const load = async () => {
    try { const response = await api.get(`/terminals/${id}`); setTerminal(bodyData(response)); setError('') }
    catch (e: Any) { setError(e?.response?.data?.message || 'دریافت جزئیات ترمینال ممکن نیست.') }
  }
  useEffect(() => { load() }, [id])

  const rotate = async () => {
    setBusy(true); setError(''); setCredential(null)
    try { const response = await api.post(`/terminals/${id}/credentials/rotate`); setCredential(bodyData(response)?.credential?.token || null); await load() }
    catch (e: Any) { setError(e?.response?.data?.message || 'تعویض اعتبارنامه انجام نشد.') }
    finally { setBusy(false) }
  }
  const revoke = async () => {
    if (!reason.trim()) return
    setBusy(true); setError('')
    try { await api.post(`/terminals/${id}/revoke`, { reason }); await load(); setReason(''); setRevokeOpen(false) }
    catch (e: Any) { setError(e?.response?.data?.message || 'لغو ترمینال انجام نشد.') }
    finally { setBusy(false) }
  }

  if (!terminal) return error ? <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert> : <div className="screen-center"><div className="spinner" /></div>

  const assignments = terminal.assignments || []
  return <>
    <PageHeader eyebrow={`FLOW-09 · Terminal Detail · ${terminal.code}`} title={terminal.name} description={`${terminal.manufacturer || '—'} · ${terminal.model || '—'} · ${terminal.serial_number || 'بدون سریال'}`} action={<div className="flex flex-wrap gap-2"><Button variant="outline" onClick={() => navigate('/admin/terminals')}>بازگشت</Button><Button disabled={busy || terminal.status === 'REVOKED'} onClick={rotate}><KeyRound className="size-4" /> تعویض اعتبارنامه</Button></div>} />

    {error ? <Alert variant="destructive" className="mb-6"><AlertDescription>{error}</AlertDescription></Alert> : null}
    {credential ? <Alert className="mb-6"><KeyRound className="size-4" /><AlertDescription><strong className="block">اعتبارنامهٔ جدید فقط یک‌بار نمایش داده می‌شود.</strong><code dir="ltr" className="mt-3 block overflow-x-auto rounded-lg border bg-background p-3 text-xs">{credential}</code></AlertDescription></Alert> : null}

    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <section className="space-y-6">
        <DetailSummaryCard eyebrow="مشخصات Registry" title="اطلاعات دستگاه" icon={<Monitor className="size-5" />} items={[
          { label: 'UUID', value: <span className="font-mono text-xs">{terminal.uuid}</span> },
          { label: 'وضعیت', value: <Badge variant={terminal.status === 'ACTIVE' ? 'default' : 'secondary'}>{statusLabels[terminal.status] || terminal.status}</Badge> },
          { label: 'Platform', value: terminal.platform || '—' },
          { label: 'سیستم‌عامل', value: terminal.os_version || '—' },
          { label: 'آخرین ارتباط', value: terminal.last_seen_at ? new Date(terminal.last_seen_at).toLocaleString('fa-AF') : '—' },
          { label: 'جلسهٔ فعلی', value: terminal.current_session_id || 'ندارد' },
          { label: 'سریال', value: terminal.serial_number || '—' },
          { label: 'تعداد تخصیص‌ها', value: String(assignments.length) },
        ]} description="FLOW-09 فقط foundation ثبت و تخصیص ترمینال است؛ این صفحه هیچ ادعایی دربارهٔ biometric یا attendance ندارد." />

        <Card>
          <CardHeader className="border-b"><CardTitle className="text-base">تاریخچهٔ تخصیص به جلسات</CardTitle></CardHeader>
          <CardContent className="space-y-3 pt-5">{assignments.length ? assignments.map((assignment: Any) => <div key={assignment.id} className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"><div><strong className="block text-sm">{assignment.meeting?.title || `Meeting #${assignment.meeting_id}`}</strong><span className="text-xs text-muted-foreground">{new Date(assignment.valid_from).toLocaleString('fa-AF')} تا {new Date(assignment.valid_until).toLocaleString('fa-AF')}</span></div><Badge variant="outline">{statusLabels[assignment.status] || assignment.status}</Badge></div>) : <p className="text-sm text-muted-foreground">تخصیصی ثبت نشده است.</p>}</CardContent>
        </Card>
      </section>

      <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
        <Card>
          <CardHeader className="border-b"><CardTitle className="text-base">کنترل امنیتی ترمینال</CardTitle></CardHeader>
          <CardContent className="space-y-3 pt-5"><p className="text-sm text-muted-foreground">تعویض credential کلید قبلی را از چرخه خارج می‌کند. لغو ترمینال یک اقدام حساس و ثبت‌شونده است.</p><Button className="w-full" variant="outline" disabled={busy || terminal.status === 'REVOKED'} onClick={rotate}>تعویض credential</Button><Textarea value={reason} onChange={event => setReason(event.target.value)} placeholder="دلیل لغو ترمینال" disabled={terminal.status === 'REVOKED'} /><Button className="w-full" variant="destructive" disabled={busy || terminal.status === 'REVOKED' || !reason.trim()} onClick={() => setRevokeOpen(true)}>لغو ترمینال</Button></CardContent>
        </Card>
      </aside>
    </div>

    <ConfirmDialog open={revokeOpen} onOpenChange={setRevokeOpen} title="لغو ترمینال انجام شود؟" description="پس از لغو، این دستگاه دیگر نباید برای تخصیص فعال استفاده شود. دلیل واردشده همراه درخواست ارسال می‌شود." confirmLabel="لغو ترمینال" variant="destructive" onConfirm={revoke} />
  </>
}
