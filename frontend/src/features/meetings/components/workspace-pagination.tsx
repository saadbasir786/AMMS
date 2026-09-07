import { Button } from '@/components/ui/button'
import type { PaginationMeta } from '../types'

export function WorkspacePagination({ meta, onChange, busy = false }: {
  meta?: PaginationMeta | null; onChange: (page: number) => void; busy?: boolean
}) {
  if (!meta || meta.last_page <= 1) return null
  return <nav aria-label="صفحه‌بندی" className="flex items-center justify-between gap-3 text-xs py-3" dir="rtl">
    <Button variant="outline" disabled={busy || meta.current_page <= 1} onClick={() => onChange(meta.current_page - 1)}>قبلی</Button>
    <span>صفحهٔ {meta.current_page} از {meta.last_page} · {meta.total} مورد</span>
    <Button variant="outline" disabled={busy || meta.current_page >= meta.last_page} onClick={() => onChange(meta.current_page + 1)}>بعدی</Button>
  </nav>
}
