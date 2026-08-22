import { Clock3 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export type WorkflowTimelineItem = {
  id: string | number
  title: string
  meta?: string
  comment?: string | null
}

export function WorkflowTimeline({
  title = 'خط زمانی گردش کار',
  eyebrow = 'تاریخچهٔ غیرقابل حذف',
  items,
  emptyText = 'هنوز رویدادی ثبت نشده است.',
}: {
  title?: string
  eyebrow?: string
  items: WorkflowTimelineItem[]
  emptyText?: string
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
        <div className="space-y-1">
          <p className="text-xs font-medium text-muted-foreground">{eyebrow}</p>
          <CardTitle className="text-base">{title}</CardTitle>
        </div>
        <Clock3 className="size-5 text-muted-foreground" />
      </CardHeader>
      <CardContent className="pt-5">
        {items.length === 0 ? (
          <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">{emptyText}</p>
        ) : (
          <ol className="relative space-y-5 before:absolute before:bottom-2 before:top-2 before:right-[7px] before:w-px before:bg-border">
            {items.map((item) => (
              <li key={item.id} className="relative pr-7">
                <span className="absolute right-0 top-1.5 size-3.5 rounded-full border-2 border-background bg-primary ring-1 ring-border" />
                <div className="space-y-1">
                  <strong className="block text-sm font-medium">{item.title}</strong>
                  {item.meta ? <span className="block text-xs text-muted-foreground">{item.meta}</span> : null}
                  {item.comment ? <p className="text-sm leading-6 text-muted-foreground">{item.comment}</p> : null}
                </div>
              </li>
            ))}
          </ol>
        )}
      </CardContent>
    </Card>
  )
}
