import React, { useCallback, useEffect, useRef, useState } from 'react'
import { Eye, GitCompareArrows, History, LoaderCircle } from 'lucide-react'
import { api, apiData } from '@/lib/api-client'
import { formatSolarDate, formatSolarDateTime } from '@/lib/calendar-utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'

type VersionSummary = { id: number; version_number: number; current?: boolean; actor?: string | null; created_at?: string | null; finalized_at?: string | null; label?: string | null }
const domainTitles: Record<string, string> = { schedule: 'زمان‌بندی جلسه', participants: 'اشتراک‌کنندگان جلسه', minutes: 'صورت‌جلسه' }
const businessValues: Record<string, string> = {
  DRAFT: 'پیش‌نویس', ISSUED: 'صادرشده', PENDING_ISSUANCE: 'در انتظار صدور', PENDING_REISSUANCE: 'در انتظار صدور مجدد', RETURNED_FOR_CORRECTION: 'برگشت جهت اصلاح',
  SCHEDULED: 'زمان‌بندی‌شده', COMPLETED: 'تکمیل‌شده', CANCELLED: 'لغوشده', INTERNAL_VENUE: 'محل داخلی', EXTERNAL_LOCATION: 'محل بیرونی', ONLINE: 'برخط',
  INTERNAL: 'محل داخلی', EXTERNAL: 'محل بیرونی', NORMAL: 'عادی', HIGH: 'بلند', URGENT: 'فوری', CRITICAL: 'بسیار مهم',
  PRESENT: 'حاضر', ABSENT: 'غایب', LATE: 'با تأخیر', EXCUSED: 'با عذر', CHAIR: 'رئیس جلسه', MEMBER: 'عضو', PARTICIPATING: 'اشتراک‌کننده',
  ACTIVE: 'فعال', INACTIVE: 'غیرفعال', DIRECTORATE: 'ریاست', DEPARTMENT: 'مدیریت', UNIT: 'واحد',
}

const displayValue = (key: string, value: any): string => {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'بلی' : 'نخیر'
  if (Array.isArray(value)) return key === 'participating_unit_ids' ? `${value.length} واحد` : value.map((part) => displayValue(key, part)).join('، ')
  if (typeof value === 'object') {
    if (key === 'venue_snapshot') return value.name || value.label || value.location || 'جزئیات محل ثبت‌شده'
    return value.name || value.full_name || value.title || value.label || '—'
  }
  if (key.endsWith('_at')) return formatSolarDateTime(String(value))
  if (key.endsWith('_date')) return formatSolarDate(String(value))
  if (key === 'preferred_start_time' || key === 'start_time' || key === 'end_time') return String(value).slice(0, 5)
  if (key === 'estimated_duration_minutes') return `${value} دقیقه`
  return businessValues[String(value)] || String(value)
}

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  if (value === null || value === undefined || value === '') return null
  return <div className="min-w-0 rounded-xl bg-muted/35 p-3"><div className="mb-1 text-xs text-muted-foreground">{label}</div><div className="break-words text-sm font-medium">{value}</div></div>
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  if (!children) return null
  return <section className="space-y-3"><h5 className="font-semibold">{title}</h5>{children}</section>
}

function SnapshotView({ domain, version }: { domain: string; version: any }) {
  if (domain === 'participants') {
    const members = version?.members ?? []
    return <div className="space-y-5">
      <div className="grid gap-3 sm:grid-cols-2"><Field label="شمار اشتراک‌کنندگان" value={members.length} /><Field label="اشتراک الزامی" value={members.filter((member: any) => member.is_mandatory).length} /></div>
      <Section title="فهرست اشتراک‌کنندگان">
        {members.length ? <div className="overflow-hidden rounded-xl border"><div className="hidden grid-cols-[minmax(0,1.4fr)_minmax(8rem,1fr)_minmax(8rem,1fr)_7rem] gap-3 bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground sm:grid"><span>نام</span><span>نقش</span><span>واحد مربوط</span><span>اشتراک</span></div><div className="divide-y">{members.map((member: any, index: number) => {
          const employee = member.employee ?? {}
          const name = employee.full_name || [employee.first_name, employee.last_name].filter(Boolean).join(' ') || '—'
          const unit = member.organizational_unit?.name || member.organizationalUnit?.name || '—'
          return <div key={member.id ?? member.employee_id ?? index} className="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(8rem,1fr)_minmax(8rem,1fr)_7rem] sm:items-center sm:gap-3"><strong className="text-sm">{name}</strong><span className="text-sm">{displayValue('participant_role', member.participant_role)}</span><span className="text-sm">{unit}</span><span className="text-xs text-muted-foreground">{member.is_mandatory ? 'الزامی' : 'اختیاری'}</span></div>
        })}</div></div> : <p className="rounded-xl bg-muted/35 p-4 text-sm text-muted-foreground">در این نسخه اشتراک‌کننده‌ای ثبت نشده است.</p>}
      </Section>
    </div>
  }

  if (domain === 'schedule') {
    const venue = version?.venue?.name || version?.venue_snapshot?.name || version?.location
    return <div className="space-y-5"><Section title="زمان و محل جلسه"><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <Field label="تاریخ جلسه" value={displayValue('meeting_date', version?.meeting_date)} /><Field label="زمان آغاز" value={displayValue('start_time', version?.start_time)} /><Field label="زمان پایان" value={displayValue('end_time', version?.end_time)} />
      <Field label="محل جلسه" value={venue} /><Field label="نوع محل" value={displayValue('venue_type', version?.venue_type)} />{version?.venue_snapshot?.address && <Field label="نشانی" value={version.venue_snapshot.address} />}
    </div></Section></div>
  }

  if (domain === 'minutes') {
    const items = version?.items ?? []
    const decisions = version?.decisions ?? []
    const attendance = version?.attendance_snapshots ?? version?.attendanceSnapshots ?? []
    return <div className="space-y-5">
      <Section title="خلاصهٔ جلسه"><div className="space-y-3"><Field label="خلاصه" value={version?.summary} />{version?.general_notes && <Field label="یادداشت‌های عمومی" value={version.general_notes} />}</div></Section>
      {!!items.length && <Section title="آجندا و دستورکار"><div className="grid gap-3 xl:grid-cols-2">{items.map((item: any, index: number) => <article key={item.id ?? index} className="rounded-xl border p-4"><div className="mb-2 flex items-start gap-3"><Badge variant="outline">{item.sequence_number ?? index + 1}</Badge><h6 className="font-medium">{item.agenda_title}</h6></div>{item.discussion_summary && <p className="whitespace-pre-wrap text-sm text-muted-foreground">{item.discussion_summary}</p>}</article>)}</div></Section>}
      {!!decisions.length && <Section title="تصمیم‌ها"><div className="space-y-3">{decisions.map((decision: any, index: number) => <article key={decision.id ?? index} className="rounded-xl border p-4"><div className="mb-2 flex items-start gap-3"><Badge>{decision.decision_number || index + 1}</Badge><p className="whitespace-pre-wrap font-medium">{decision.decision_text}</p></div><div className="grid gap-2 text-sm sm:grid-cols-2">{(decision.responsible_unit?.name || decision.responsible_employee?.full_name) && <Field label="مسئول اجرا" value={decision.responsible_unit?.name || decision.responsible_employee?.full_name} />}{decision.target_date && <Field label="مهلت اجرا" value={displayValue('target_date', decision.target_date)} />}{decision.notes && <Field label="یادداشت" value={decision.notes} />}</div></article>)}</div></Section>}
      {!!attendance.length && <Section title={`حاضری (${attendance.length})`}><div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">{attendance.map((person: any, index: number) => <div key={person.id ?? person.employee_id ?? index} className="flex items-center justify-between gap-3 rounded-xl bg-muted/35 p-3"><div><div className="text-sm font-medium">{person.display_name || person.employee?.full_name || '—'}</div><div className="text-xs text-muted-foreground">{displayValue('participant_role', person.participant_role)}</div></div><Badge variant="outline">{displayValue('attendance_status', person.attendance_status)}</Badge></div>)}</div></Section>}
    </div>
  }


  return <p className="text-sm text-muted-foreground">خلاصهٔ این نوع نسخه در دسترس نیست.</p>
}

function ComparisonValue({ field }: { field: any }) {
  return <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-start"><div className="rounded-lg bg-muted/35 p-3"><div className="mb-1 text-xs text-muted-foreground">پیشین</div>{displayValue(field.key, field.from)}</div><span className="hidden pt-3 text-muted-foreground sm:block">←</span><div className="rounded-lg bg-primary/5 p-3"><div className="mb-1 text-xs text-muted-foreground">جدید</div>{displayValue(field.key, field.to)}</div></div>
}

function ComparisonView({ domain, comparison }: { domain: string; comparison: any }) {
  if (domain === 'participants') {
    const history = comparison?.history ?? []
    if (history.length) {
      const addedCount = history.filter((event: any) => event.type === 'add').length
      const removedCount = history.filter((event: any) => event.type === 'remove').length
      const replacedCount = history.filter((event: any) => event.type === 'replace').length
      return <div className="space-y-5">
        <p className="text-sm text-muted-foreground">رویدادهای ثبت‌شده در فاصلهٔ دو نسخه، به ترتیب زمانی:</p>
        <div className="grid gap-3 sm:grid-cols-3"><Field label="افزوده‌شده" value={addedCount} /><Field label="جایگزینی" value={replacedCount} /><Field label="حذف‌شده" value={removedCount} /></div>
        <div className="space-y-3">{history.map((event: any, index: number) => <article key={`${event.version_number}-${index}`} className="rounded-xl border p-4">
          <div className="mb-3 flex items-center justify-between gap-3"><Badge variant="outline">نسخه {event.version_number}</Badge><span className="text-xs text-muted-foreground">{event.type === 'add' ? 'افزوده‌شدن' : event.type === 'remove' ? 'حذف' : 'جایگزینی'}</span></div>
          {event.type === 'replace' ? <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center"><div><div className="text-xs text-muted-foreground">پیشین</div><strong>{event.old?.name || '—'}</strong>{event.old?.unit && <div className="text-xs text-muted-foreground">{event.old.unit}</div>}</div><span className="text-center text-muted-foreground">←</span><div><div className="text-xs text-muted-foreground">جایگزین</div><strong>{event.new?.name || '—'}</strong>{event.new?.unit && <div className="text-xs text-muted-foreground">{event.new.unit}</div>}</div></div>
            : <div><strong>{event.type === 'remove' ? event.old?.name : event.new?.name || 'اشتراک‌کننده'}</strong>{(event.type === 'remove' ? event.old?.unit : event.new?.unit) && <div className="mt-1 text-xs text-muted-foreground">{event.type === 'remove' ? event.old?.unit : event.new?.unit}</div>}{event.role && <div className="mt-1 text-xs text-muted-foreground">{displayValue('participant_role', event.role)}</div>}</div>}
        </article>)}</div>
      </div>
    }
    const groups = [
      { key: 'added', title: 'افزوده‌شده', items: comparison?.added ?? [] },
      { key: 'removed', title: 'حذف‌شده', items: comparison?.removed ?? [] },
      { key: 'changed', title: 'تغییرکرده', items: comparison?.changed ?? [] },
    ]
    const unchangedCount = comparison?.unchanged?.length ?? 0
    return <div className="space-y-5"><div className="grid gap-3 sm:grid-cols-4">{groups.map((group) => <Field key={group.key} label={group.title} value={group.items.length} />)}<Field label="بدون تغییر" value={unchangedCount} /></div>{groups.filter((group) => group.items.length).map((group) => <Section key={group.key} title={group.title}><div className="space-y-2">{group.items.map((item: any, index: number) => <article key={item.employee_id ?? index} className="rounded-xl border p-4"><strong>{item.name || 'اشتراک‌کننده'}</strong>{group.key === 'changed' ? <ComparisonValue field={{ key: 'participant', from: `نقش: ${displayValue('participant_role', item.from?.role)} · واحد: ${item.from?.unit || '—'}`, to: `نقش: ${displayValue('participant_role', item.to?.role)} · واحد: ${item.to?.unit || '—'}` }} /> : <p className="mt-2 text-sm text-muted-foreground">{displayValue('participant_role', item.role)}{item.unit ? ` · ${item.unit}` : ''}</p>}</article>)}</div></Section>)}</div>
  }

  const fields = (comparison?.fields ?? []).filter((field: any) => field.changed && !field.key?.endsWith('_id'))
  const collections = Object.entries(comparison?.collections ?? {}) as [string, any][]
  const collectionChanges = collections.flatMap(([key, collection]) => ['added', 'removed', 'changed'].flatMap((changeType) => (collection?.[changeType] ?? []).map((item: any) => ({ key, label: collection.label, changeType, item }))))
  return <div className="space-y-5">
    {!!fields.length && <Section title="تغییرات مهم"><div className="space-y-3">{fields.map((field: any) => <article key={field.key} className="space-y-2 rounded-xl border p-4"><h6 className="font-medium">{field.label}</h6><ComparisonValue field={field} /></article>)}</div></Section>}
    {!!collectionChanges.length && <Section title="تغییرات آجندا، تصمیم‌ها و حاضری"><div className="space-y-3">{collectionChanges.map(({ key, label, changeType, item }, index) => {
      const record = changeType === 'changed' ? item.to : item
      const title = record?.agenda_title || record?.decision_text || record?.display_name || record?.name || 'مورد ثبت‌شده'
      const detail = record?.discussion_summary || record?.notes || ''
      const changeTitle = changeType === 'added' ? 'افزوده‌شده' : changeType === 'removed' ? 'حذف‌شده' : 'تغییرکرده'
      return <article key={`${key}-${index}`} className="rounded-xl border p-4"><div className="mb-2 flex flex-wrap items-center gap-2"><Badge variant={changeType === 'removed' ? 'destructive' : 'outline'}>{changeTitle}</Badge><span className="text-xs text-muted-foreground">{label}</span></div><strong>{title}</strong>{detail && <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{detail}</p>}{changeType === 'changed' && <div className="mt-3"><ComparisonValue field={{ key, from: item.from?.agenda_title || item.from?.decision_text || item.from?.display_name || 'پیشین', to: title }} /></div>}</article>
    })}</div></Section>}
    {!fields.length && !collectionChanges.length && <p className="rounded-xl bg-muted/35 p-4 text-sm text-muted-foreground">در موارد کاری این دو نسخه تفاوتی ثبت نشده است.</p>}
  </div>
}

function VersionMetadata({ metadata, version }: { metadata: any; version: any }) {
  const actor = metadata?.actor || version?.issuer?.name || version?.prepared_by?.name || version?.preparedBy?.name
  const recordedAt = metadata?.created_at || version?.issued_at || version?.submitted_at
  const finalizedAt = metadata?.finalized_at
  const finalizedBy = metadata?.finalized_by
  if (!actor && !recordedAt && !finalizedAt && !finalizedBy) return null
  return <div className="mb-5 flex flex-wrap gap-x-6 gap-y-2 rounded-xl bg-muted/40 p-3 text-sm">
    {actor && <span><span className="text-muted-foreground">ثبت‌کننده:</span> {actor}</span>}
    {recordedAt && <span><span className="text-muted-foreground">زمان ثبت:</span> {formatSolarDateTime(recordedAt)}</span>}
    {finalizedAt && <span><span className="text-muted-foreground">زمان نهایی‌شدن:</span> {formatSolarDateTime(finalizedAt)}</span>}
    {finalizedBy && <span><span className="text-muted-foreground">نهایی‌شده توسط:</span> {finalizedBy}</span>}
  </div>
}

export function VersionInspector({ endpoint, domain, currentVersionId, triggerPlacement = 'card', triggerLabel = 'مشاهده تاریخچه' }: { endpoint: string; domain: string; currentVersionId?: number | null; triggerPlacement?: 'card' | 'inline'; triggerLabel?: string }) {
  const [open, setOpen] = useState(false)
  const [versions, setVersions] = useState<VersionSummary[]>([])
  const [meta, setMeta] = useState<any>(null)
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<number | null>(null)
  const [compareTo, setCompareTo] = useState<number | null>(null)
  const [payload, setPayload] = useState<any>(null)
  const [mode, setMode] = useState<'view' | 'compare'>('view')
  const [versionSelectedAfterCompare, setVersionSelectedAfterCompare] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const contentScrollRef = useRef<HTMLDivElement>(null)

  const load = useCallback(async () => {
    setBusy(true); setError(null)
    try {
      const response = await api.get(endpoint, { params: { per_page: 15, page } })
      const data = response.data?.data ?? apiData(response) ?? []
      setVersions(data)
      setMeta(response.data?.meta)
      setSelected((current) => current ?? data[0]?.id ?? null)
      setCompareTo((current) => current ?? data.find((item: VersionSummary) => item.id !== (selected ?? data[0]?.id))?.id ?? null)
    } catch (e: any) { setError(e?.response?.data?.message || 'بارگذاری تاریخ نسخه‌ها انجام نشد.') }
    finally { setBusy(false) }
  }, [endpoint, page])

  useEffect(() => { if (open) void load() }, [open, load])

  const inspect = async (nextMode: 'view' | 'compare') => {
    if (!selected || (nextMode === 'compare' && !compareTo)) return
    if (nextMode === 'compare') setVersionSelectedAfterCompare(false)
    setBusy(true); setError(null); setMode(nextMode)
    try {
      const selectedSummary = versions.find((version) => version.id === selected)
      const compareSummary = versions.find((version) => version.id === compareTo)
      const selectedFirst = (selectedSummary?.version_number ?? 0) <= (compareSummary?.version_number ?? 0)
      const response = nextMode === 'view'
        ? await api.get(`${endpoint}/${selected}`)
        : await api.get(`${endpoint}/compare`, { params: { from_id: selectedFirst ? selected : compareTo, to_id: selectedFirst ? compareTo : selected } })
      setPayload(apiData(response))
    } catch (e: any) { setError(e?.response?.data?.message || 'خواندن نسخه انجام نشد.') }
    finally { setBusy(false) }
  }

  const current = versions.find((version) => version.current || version.id === currentVersionId)?.id
  const trigger = <Button variant="outline" className="shrink-0 rounded-xl" onClick={() => setOpen(true)}><Eye className="me-2 size-4" />{triggerLabel}</Button>
  return <>
    {triggerPlacement === 'inline' ? trigger : <Card className="rounded-2xl border-border/70 shadow-xs"><CardHeader className="flex flex-row items-center justify-between gap-3 p-4"><div className="flex items-center gap-2"><History className="size-4 text-primary" /><div><h3 className="text-sm font-bold">تاریخچه نسخه‌ها</h3><p className="text-xs text-muted-foreground">مشاهده و مقایسه نسخه‌های ثبت‌شده</p></div></div>{trigger}</CardHeader></Card>}
    <Dialog open={open} onOpenChange={(next) => { setOpen(next); if (next) { setPayload(null); if (contentScrollRef.current) contentScrollRef.current.scrollTop = 0 } }}>
      <DialogContent dir="rtl" className="flex h-[min(90vh,56rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-2xl p-5 sm:max-w-6xl sm:p-7">
        <DialogHeader className="shrink-0"><DialogTitle>تاریخچه نسخه‌های {domainTitles[domain] || 'سند'}</DialogTitle><DialogDescription>نسخه‌ها فقط برای بررسی و ممیزی نمایش داده می‌شوند.</DialogDescription></DialogHeader>
        <div ref={contentScrollRef} className="min-h-0 flex-1 space-y-4 overflow-y-auto">
        {error && <p role="alert" className="rounded-xl bg-destructive/10 p-3 text-sm text-destructive">{error}</p>}
        <div className="grid gap-5 lg:grid-cols-[19rem_minmax(0,1fr)]">
          <div className="space-y-2 lg:border-e lg:pe-4">{busy && <LoaderCircle className="size-4 animate-spin" />}{versions.map((version) => <button key={version.id} onClick={() => { setSelected(version.id); setPayload(null); if (mode === 'compare') setVersionSelectedAfterCompare(true) }} className={`w-full rounded-xl border p-3.5 text-start ${selected === version.id ? 'border-primary bg-primary/5' : 'border-border/60'}`}>
            <span className="flex items-center justify-between gap-2"><strong>نسخه {version.version_number}</strong>{(version.current || version.id === current) && <Badge>جاری</Badge>}</span>
            <span className="mt-1 block text-xs text-muted-foreground">{version.label || version.actor || version.created_at || ''}</span>
          </button>)}
            {meta?.last_page > 1 && <div className="flex items-center justify-between gap-2"><Button variant="outline" size="sm" disabled={page <= 1 || busy} onClick={() => { setPage(page - 1); setSelected(null); setCompareTo(null) }}>پیشین</Button><span className="text-xs text-muted-foreground">صفحه {meta.current_page} از {meta.last_page}</span><Button variant="outline" size="sm" disabled={page >= meta.last_page || busy} onClick={() => { setPage(page + 1); setSelected(null); setCompareTo(null) }}>بعدی</Button></div>}
            <div className="space-y-2 border-t pt-3"><label className="block text-xs font-medium">مقایسه با</label><select className="h-9 w-full rounded-xl border bg-background px-2 text-sm" value={compareTo ?? ''} onChange={(event) => setCompareTo(Number(event.target.value) || null)}><option value="">انتخاب نسخه</option>{versions.filter((v) => v.id !== selected).map((v) => <option key={v.id} value={v.id}>نسخه {v.version_number}</option>)}</select><Button className="w-full rounded-xl" variant="outline" disabled={!selected || !compareTo || busy} onClick={() => void inspect('compare')}><GitCompareArrows className="me-2 size-4" />مقایسه با نسخه انتخاب‌شده</Button></div>
          </div>
          <div className="min-h-[28rem] rounded-xl border border-border/60 p-4 sm:p-6">
            {payload ? <><div className="mb-3 flex items-center justify-between"><h4 className="font-semibold">{mode === 'view' ? `نمایش نسخه ${payload.version?.version_number ?? payload.version?.version ?? ''}` : `مقایسه نسخه ${payload.from?.version_number} با نسخه ${payload.to?.version_number}`}</h4>{mode === 'view' && payload.current && <Badge>نسخه جاری</Badge>}</div>{mode === 'view' ? <><VersionMetadata metadata={payload.metadata} version={payload.version} /><SnapshotView domain={domain} version={payload.version} /></> : <ComparisonView domain={domain} comparison={payload.comparison} />}</> : <p className="text-sm text-muted-foreground">یک نسخه را انتخاب کنید و نمایش یا مقایسه را بزنید.</p>}
            {selected && (mode !== 'compare' || versionSelectedAfterCompare) && <Button className="mt-3 rounded-xl" disabled={busy || (mode === 'view' && !!payload)} onClick={() => void inspect('view')}><Eye className="me-2 size-4" />نمایش نسخه انتخاب‌شده</Button>}
          </div>
        </div>
        </div>
      </DialogContent>
    </Dialog>
  </>
}
