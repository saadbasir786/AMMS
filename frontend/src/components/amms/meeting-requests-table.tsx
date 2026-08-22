import { ClipboardCheck, Eye } from 'lucide-react'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'

type RequestRow = Record<string, any>

type MeetingRequestsTableProps = {
  rows: RequestRow[]
  mode?: 'owned' | 'incoming'
  statusLabels: Record<string, string>
  priorityLabels?: Record<string, string>
  purposeLabels?: Record<string, string>
  emptyText: string
}

function statusTone(value?: string) {
  const normalized = String(value || '').toUpperCase()
  if (normalized.includes('REJECT') || normalized.includes('CANCEL') || normalized.includes('RETURN')) {
    return 'destructive'
  }
  if (normalized.includes('APPROV') || normalized.includes('COMPLET') || normalized.includes('FINAL')) {
    return 'default'
  }
  return 'secondary'
}

function PriorityBadge({ value, labels }: { value?: string; labels?: Record<string, string> }) {
  const normalized = String(value || '').toUpperCase()
  const label = labels?.[normalized] || value || '—'

  return (
    <Badge
      variant={normalized === 'URGENT' ? 'destructive' : 'outline'}
      className={cn(
        normalized === 'HIGH' && 'border-amber-200 bg-amber-50 text-amber-700',
        normalized === 'NORMAL' && 'text-muted-foreground'
      )}
    >
      {label}
    </Badge>
  )
}

function StatusBadge({ value, labels }: { value?: string; labels: Record<string, string> }) {
  return (
    <Badge variant={statusTone(value)} className="max-w-48 truncate">
      {labels[value || ''] || value || '—'}
    </Badge>
  )
}

function requestingUnit(row: RequestRow, mode: 'owned' | 'incoming') {
  if (mode === 'incoming') {
    return row.units?.find((unit: any) => unit.role === 'REQUESTER')?.unit?.name || row.requesting_unit?.name || '—'
  }
  return row.requesting_unit?.name || '—'
}

export function MeetingRequestsTable({
  rows,
  mode = 'owned',
  statusLabels,
  priorityLabels,
  purposeLabels,
  emptyText,
}: MeetingRequestsTableProps) {
  return (
    <div className="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
      <Table className="min-w-[860px]">
        <TableHeader className="bg-muted/55">
          <TableRow className="hover:bg-transparent">
            <TableHead className="ps-4">شماره درخواست</TableHead>
            {mode === 'incoming' ? <TableHead>ریاست درخواست‌کننده</TableHead> : null}
            <TableHead>موضوع</TableHead>
            {mode === 'owned' ? <TableHead>ریاست</TableHead> : null}
            <TableHead>اولویت</TableHead>
            <TableHead>وضعیت</TableHead>
            {mode === 'owned' ? <TableHead>تاریخ ایجاد</TableHead> : null}
            <TableHead className="pe-4 text-center">عملیات</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row) => (
            <TableRow key={row.id}>
              <TableCell className="ps-4 font-mono text-xs text-muted-foreground">
                {row.request_number || '—'}
              </TableCell>
              {mode === 'incoming' ? <TableCell>{requestingUnit(row, mode)}</TableCell> : null}
              <TableCell className="max-w-[360px] whitespace-normal py-3">
                <a
                  href={`/meeting-requests/${row.id}`}
                  className="block font-medium text-foreground transition-colors hover:text-primary"
                >
                  {row.subject || '—'}
                </a>
                {mode === 'owned' && row.purpose ? (
                  <span className="mt-1 block text-xs text-muted-foreground">
                    {purposeLabels?.[row.purpose] || row.purpose}
                  </span>
                ) : null}
              </TableCell>
              {mode === 'owned' ? <TableCell>{requestingUnit(row, mode)}</TableCell> : null}
              <TableCell>
                <PriorityBadge value={row.priority} labels={priorityLabels} />
              </TableCell>
              <TableCell>
                <StatusBadge value={row.status} labels={statusLabels} />
              </TableCell>
              {mode === 'owned' ? (
                <TableCell className="text-muted-foreground">
                  {row.created_at ? new Date(row.created_at).toLocaleDateString('fa-AF') : '—'}
                </TableCell>
              ) : null}
              <TableCell className="pe-4 text-center">
                <Button asChild variant="ghost" size="icon" aria-label="مشاهده درخواست">
                  <a href={`/meeting-requests/${row.id}`}>
                    <Eye className="size-4" />
                  </a>
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {rows.length === 0 ? (
        <div className="flex min-h-52 flex-col items-center justify-center gap-3 border-t border-border/70 px-6 py-10 text-center">
          <div className="flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <ClipboardCheck className="size-5" />
          </div>
          <div>
            <strong className="block text-sm font-semibold text-foreground">موردی یافت نشد</strong>
            <span className="mt-1 block text-xs leading-6 text-muted-foreground">{emptyText}</span>
          </div>
        </div>
      ) : null}
    </div>
  )
}
