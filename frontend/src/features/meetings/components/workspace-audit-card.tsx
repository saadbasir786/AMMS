import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { formatSolarDateTime } from '@/lib/calendar-utils'
import { meetingActionLabel } from '@/app/config/labels'
import type { PaginationMeta, WorkspaceAuditItem } from '../types'
import { WorkspacePagination } from './workspace-pagination'

export function WorkspaceAuditCard({ actions = [], meta, onPageChange, busy }: {
  actions?: WorkspaceAuditItem[]; meta?: PaginationMeta; onPageChange: (page: number) => void; busy: boolean
}) {
  return <Card className="rounded-2xl" dir="rtl">
    <CardHeader><h3 className="text-sm font-bold">سوابق اقدامات و دلایل بررسی</h3></CardHeader>
    <CardContent className="space-y-3 text-xs">
      {actions.length === 0 && <p className="text-muted-foreground">در این صفحه سابقه‌ای وجود ندارد.</p>}
      {actions.map((action) => {
        const label = meetingActionLabel(action.action)
        return <div key={action.id} className="rounded-xl border p-3 space-y-2">
          <p className="font-semibold">{label === action.action ? 'ثبت تغییر در جلسه' : label}</p>
          {action.comment && <p className="leading-relaxed">{action.comment}</p>}
          <p className="text-muted-foreground">{action.actor?.name || 'کاربر سیستم'}{action.created_at ? ` · ${formatSolarDateTime(action.created_at)}` : ''}</p>
        </div>
      })}
      <WorkspacePagination meta={meta} onChange={onPageChange} busy={busy} />
    </CardContent>
  </Card>
}
