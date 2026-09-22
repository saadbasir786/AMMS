import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { formatSolarDate, formatSolarDateTime } from '@/lib/calendar-utils'
import { meetingActionLabel } from '@/app/config/labels'
import type { PaginationMeta, WorkspaceAuditItem } from '../types'
import { WorkspacePagination } from './workspace-pagination'

type ScheduleSnapshot = {
  meeting_date?: string | null
  start_time?: string | null
  end_time?: string | null
  location?: string | null
}

type ParticipantAuditEmployee = {
  full_name?: string | null
}

type ParticipantAuditOperation = {
  operation?: string | null
  old_employee_id?: number | null
  new_employee_id?: number | null
  old_employee?: ParticipantAuditEmployee | null
  new_employee?: ParticipantAuditEmployee | null
}

function participantName(value: unknown): string {
  const employee = value && typeof value === 'object' ? value as ParticipantAuditEmployee : null
  if (employee?.full_name) return employee.full_name
  return 'عضو نامشخص'
}

function participantChangeDetails(metadata: Record<string, unknown>): string[] {
  const operations = Array.isArray(metadata.operations)
    ? metadata.operations as ParticipantAuditOperation[]
    : metadata.operation ? [metadata as ParticipantAuditOperation] : []

  return operations.flatMap((operation) => {
    const oldName = participantName(operation.old_employee)
    const newName = participantName(operation.new_employee)
    if (operation.operation === 'ADD') return [`\u0627\u0641\u0632\u0648\u062f\u0646 \u0639\u0636\u0648: ${newName}`]
    if (operation.operation === 'REMOVE') return [`\u062d\u0630\u0641 \u0639\u0636\u0648: ${oldName}`]
    if (operation.operation === 'REPLACE') return [`\u062c\u0627\u06cc\u06af\u0632\u06cc\u0646\u06cc \u0639\u0636\u0648: ${oldName} \u2190 ${newName}`]
    return []
  })
}

function scheduleSummary(value: unknown): string | null {
  if (!value || typeof value !== 'object') return null
  const schedule = value as ScheduleSnapshot
  const startTime = schedule.start_time ? String(schedule.start_time).slice(0, 5) : null
  const endTime = schedule.end_time ? String(schedule.end_time).slice(0, 5) : null
  const parts = [
    schedule.meeting_date ? formatSolarDate(schedule.meeting_date) : null,
    startTime && endTime ? `${startTime} تا ${endTime}` : null,
    schedule.location || null,
  ]
  return parts.filter(Boolean).join(' • ') || null
}

function auditDetails(metadata: Record<string, unknown> | null | undefined): string[] {
  if (!metadata) return []
  const details: string[] = []
  if (metadata.old_schedule) {
    const summary = scheduleSummary(metadata.old_schedule)
    if (summary) details.push(`زمان قبلی: ${summary}`)
  }
  if (metadata.new_schedule) {
    const summary = scheduleSummary(metadata.new_schedule)
    if (summary) details.push(`زمان جدید: ${summary}`)
  }
  if (metadata.proposed_schedule) {
    const summary = scheduleSummary(metadata.proposed_schedule)
    if (summary) details.push(`زمان پیشنهادی: ${summary}`)
  }
  details.push(...participantChangeDetails(metadata))
  return details
}

export function WorkspaceAuditCard({ actions = [], meta, onPageChange, busy }: {
  actions?: WorkspaceAuditItem[]
  meta?: PaginationMeta
  onPageChange: (page: number) => void
  busy: boolean
}) {
  return <Card className="rounded-2xl" dir="rtl">
    <CardHeader className="space-y-1">
      <h3 className="text-sm font-bold">تاریخچه و گردش کار</h3>
      <p className="text-xs text-muted-foreground">سوابق اداری ثبت‌شده برای این جلسه</p>
    </CardHeader>
    <CardContent className="space-y-3 text-xs">
      <div className="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
        {actions.length === 0 && <p className="text-muted-foreground">در این بخش سابقه‌ای ثبت نشده است.</p>}
        {actions.map((action) => {
          const details = auditDetails(action.metadata)
          return <div key={action.id} className="rounded-xl border p-3 space-y-1.5">
            <p className="font-semibold leading-relaxed">{meetingActionLabel(action.action)}</p>
            {action.comment && <p className="leading-relaxed break-words">
              <span className="font-semibold">دلیل یا توضیح: </span>{action.comment}
            </p>}
            {details.length > 0 && <p className="leading-relaxed text-muted-foreground break-words">{details.join(' • ')}</p>}
            <p className="text-muted-foreground">{action.actor?.name || 'کاربر سیستم'}{action.created_at ? ` • ${formatSolarDateTime(action.created_at)}` : ''}</p>
          </div>
        })}
      </div>
      <WorkspacePagination meta={meta} onChange={onPageChange} busy={busy} />
    </CardContent>
  </Card>
}
