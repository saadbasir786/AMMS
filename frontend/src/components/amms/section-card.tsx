import type { ReactNode } from 'react'

import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { cn } from '@/lib/utils'

type SectionCardProps = {
  eyebrow?: string
  title: string
  description?: string
  action?: ReactNode
  children: ReactNode
  className?: string
  contentClassName?: string
}

export function SectionCard({
  eyebrow,
  title,
  description,
  action,
  children,
  className,
  contentClassName,
}: SectionCardProps) {
  return (
    <Card className={cn('border-border/70 shadow-sm', className)}>
      <CardHeader className="flex flex-row items-start justify-between gap-4 border-b border-border/60 px-5 py-4">
        <div className="min-w-0">
          {eyebrow ? <span className="mb-1 block text-[10px] font-semibold text-primary">{eyebrow}</span> : null}
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
          {description ? <p className="mt-1 text-xs leading-5 text-muted-foreground">{description}</p> : null}
        </div>
        {action ? <div className="shrink-0">{action}</div> : null}
      </CardHeader>
      <CardContent className={cn('p-5', contentClassName)}>{children}</CardContent>
    </Card>
  )
}
