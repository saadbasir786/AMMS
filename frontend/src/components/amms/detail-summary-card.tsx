import type { ReactNode } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export type DetailSummaryItem = {
  label: string
  value: ReactNode
}

export function DetailSummaryCard({
  eyebrow,
  title,
  icon,
  items,
  description,
}: {
  eyebrow?: string
  title: string
  icon?: ReactNode
  items: DetailSummaryItem[]
  description?: ReactNode
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-4 border-b">
        <div className="space-y-1">
          {eyebrow ? <p className="text-xs font-medium text-muted-foreground">{eyebrow}</p> : null}
          <CardTitle className="text-base">{title}</CardTitle>
        </div>
        {icon ? <div className="rounded-lg border bg-muted/40 p-2 text-muted-foreground">{icon}</div> : null}
      </CardHeader>
      <CardContent className="space-y-5 pt-5">
        <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {items.map((item) => (
            <div key={item.label} className="rounded-xl border bg-muted/20 p-3">
              <dt className="text-xs text-muted-foreground">{item.label}</dt>
              <dd className="mt-1 text-sm font-medium text-foreground">{item.value}</dd>
            </div>
          ))}
        </dl>
        {description ? <div className="rounded-xl border bg-background p-4 text-sm leading-7 text-muted-foreground">{description}</div> : null}
      </CardContent>
    </Card>
  )
}
