import { useCallback, useEffect, useState } from 'react'
import { AlertCircle, RotateCw, ShieldCheck, UserRoundCheck, XCircle } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { PageHeader } from '@/components/amms/page-header'
import { ConfirmDialog } from '@/components/amms/confirm-dialog'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { api, apiData } from '@/lib/api-client'

type Unit = { id: number; name: string }
type Delegate = { id: number; name: string; employee?: { full_name: string } | null }
type Delegation = {
  id: number
  unit?: Unit
  user?: Delegate
  expires_at?: string | null
  grant_reason?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
  active: boolean
}

export function MinutesApprovalDelegationsPage() {
  const [delegations, setDelegations] = useState<Delegation[]>([])
  const [delegates, setDelegates] = useState<Delegate[]>([])
  const [units, setUnits] = useState<Unit[]>([])
  const [unitId, setUnitId] = useState('')
  const [delegateUserId, setDelegateUserId] = useState('')
  const [expiresAt, setExpiresAt] = useState('')
  const [reason, setReason] = useState('')
  const [revokeTarget, setRevokeTarget] = useState<Delegation | null>(null)
  const [revocationReason, setRevocationReason] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = apiData(await api.get('/minutes-approval-delegations'))
      setDelegations(data?.delegations || [])
      setDelegates(data?.delegates || [])
      setUnits(data?.units || [])
      setUnitId((current) => current || String(data?.units?.[0]?.id || ''))
    } catch (err: any) {
      setError(err?.response?.data?.message || 'بارگذاری تفویض‌های صورت‌جلسه با خطا مواجه شد.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])
  useEffect(() => { setDelegateUserId('') }, [unitId])

  const grant = async () => {
    if (!unitId || !delegateUserId || !expiresAt || !reason.trim()) {
      setError('ریاست، معاون، تاریخ پایان و دلیل تفویض الزامی است.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await api.post('/minutes-approval-delegations', {
        organizational_unit_id: Number(unitId),
        delegate_user_id: Number(delegateUserId),
        expires_at: expiresAt,
        reason: reason.trim(),
      })
      setDelegateUserId('')
      setExpiresAt('')
      setReason('')
      await load()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت تفویض صلاحیت با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  const revoke = async () => {
    if (!revokeTarget || !revocationReason.trim()) return
    setBusy(true)
    setError(null)
    try {
      await api.delete(`/minutes-approval-delegations/${revokeTarget.id}`, { data: { reason: revocationReason.trim() } })
      setRevokeTarget(null)
      setRevocationReason('')
      await load()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'لغو تفویض صلاحیت با خطا مواجه شد.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6" dir="rtl">
      <PageHeader
        title="تفویض تأیید صورت‌جلسه"
        description="تفویض محدود، زمان‌دار و قابل لغو تأیید یا درخواست اصلاح صورت‌جلسه به معاون همان ریاست"
      />

      {error && (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertCircle className="size-4" />
          <AlertDescription className="flex items-center justify-between gap-3 text-xs">
            <span>{error}</span>
            <Button variant="outline" size="sm" onClick={load} className="h-8 rounded-xl text-xs"><RotateCw className="size-3.5" />تلاش مجدد</Button>
          </AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card className="border border-border/70 shadow-xs">
          <CardHeader className="border-b border-border/60 p-4 sm:p-5">
            <div className="flex items-center gap-2"><ShieldCheck className="size-4 text-primary" /><div><h2 className="text-sm font-bold">تفویض جدید</h2><p className="text-[11px] text-muted-foreground">فقط به معاونِ دارای نقش همان ریاست</p></div></div>
          </CardHeader>
          <CardContent className="space-y-4 p-4 sm:p-5">
            <label className="block space-y-1.5 text-xs font-bold">ریاست<select value={unitId} onChange={(event) => setUnitId(event.target.value)} className="h-9.5 w-full rounded-xl border border-border/70 bg-card px-3 text-xs font-normal">{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.name}</option>)}</select></label>
            <label className="block space-y-1.5 text-xs font-bold">معاون ریاست<select value={delegateUserId} onChange={(event) => setDelegateUserId(event.target.value)} className="h-9.5 w-full rounded-xl border border-border/70 bg-card px-3 text-xs font-normal"><option value="">انتخاب معاون</option>{delegates.map((delegate) => <option key={delegate.id} value={delegate.id}>{delegate.employee?.full_name || delegate.name}</option>)}</select></label>
            <div className="space-y-1.5"><label className="text-xs font-bold">تاریخ پایان اعتبار</label><MultiCalendarDatePicker value={expiresAt} onChange={setExpiresAt} placeholder="انتخاب تاریخ پایان" className="h-9.5 rounded-xl text-xs" /></div>
            <div className="space-y-1.5"><label className="text-xs font-bold">دلیل تفویض</label><Textarea value={reason} onChange={(event) => setReason(event.target.value)} rows={3} placeholder="دلیل و محدودهٔ اداری تفویض را بنویسید..." className="rounded-xl text-xs" /></div>
            <Button disabled={busy || loading} onClick={grant} className="h-9.5 w-full rounded-xl text-xs font-semibold"><UserRoundCheck className="size-4" />ثبت تفویض صلاحیت</Button>
          </CardContent>
        </Card>

        <Card className="border border-border/70 shadow-xs lg:col-span-2">
          <CardHeader className="border-b border-border/60 p-4 sm:p-5"><h2 className="text-sm font-bold">سابقهٔ تفویض‌ها</h2><p className="text-[11px] text-muted-foreground">تأیید یا درخواست اصلاح صورت‌جلسه؛ بدون حق نهایی‌سازی</p></CardHeader>
          <CardContent className="p-4 sm:p-5">
            {loading ? <div className="h-48 animate-pulse rounded-2xl bg-muted/40" /> : delegations.length === 0 ? <p className="py-10 text-center text-xs text-muted-foreground">هنوز تفویضی ثبت نشده است.</p> : <div className="space-y-3">{delegations.map((delegation) => <div key={delegation.id} className="rounded-2xl border border-border/60 bg-slate-50/60 p-4 dark:bg-slate-900/30"><div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div className="space-y-1.5"><div className="flex items-center gap-2"><strong className="text-xs">{delegation.user?.employee?.full_name || delegation.user?.name || 'معاون ریاست'}</strong><span className={`rounded-lg px-2 py-0.5 text-[10px] font-bold ${delegation.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}`}>{delegation.active ? 'فعال' : delegation.revoked_at ? 'لغوشده' : 'منقضی'}</span></div><p className="text-[11px] text-muted-foreground">{delegation.unit?.name || 'واحد سازمانی'} · تا {delegation.expires_at ? formatSolarDateTime(delegation.expires_at) : 'نامحدود'}</p><p className="text-[11px] leading-5 text-muted-foreground">{delegation.grant_reason || 'بدون توضیح'}</p>{delegation.revocation_reason && <p className="text-[11px] text-destructive">دلیل لغو: {delegation.revocation_reason}</p>}</div>{delegation.active && <Button variant="outline" size="sm" disabled={busy} onClick={() => setRevokeTarget(delegation)} className="h-8 rounded-xl border-destructive/40 text-xs text-destructive hover:bg-destructive/5"><XCircle className="size-3.5" />لغو تفویض</Button>}</div></div>)}</div>}
          </CardContent>
        </Card>
      </div>

      <ConfirmDialog open={!!revokeTarget} onOpenChange={(open) => { if (!open) setRevokeTarget(null) }} title="لغو تفویض صلاحیت" description="پس از لغو، معاون دیگر امکان تأیید یا درخواست اصلاح صورت‌جلسه با این صلاحیت را ندارد." confirmLabel="لغو تفویض" variant="destructive" onConfirm={revoke}>
        <div className="space-y-1.5" dir="rtl"><label className="text-xs font-bold">دلیل لغو</label><Input value={revocationReason} onChange={(event) => setRevocationReason(event.target.value)} placeholder="دلیل لغو تفویض را بنویسید..." className="h-9.5 rounded-xl text-xs" /></div>
      </ConfirmDialog>
    </div>
  )
}
