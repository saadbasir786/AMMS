import type { ReactNode } from 'react'

import { cn } from '@/lib/utils'

type PageHeaderProps = {
  eyebrow?: string
  title: string
  description?: string
  action?: ReactNode
  className?: string
}

export function PageHeader({
  eyebrow,
  title,
  description,
  action,
  className,
}: PageHeaderProps) {
  return (
    <header
      className={cn(
        'mb-6 flex flex-col gap-4 border-b border-border/70 pb-5 md:mb-7 md:flex-row md:items-end md:justify-between',
        className
      )}
    >
      <div className="min-w-0 max-w-3xl">
        {eyebrow ? (
          <span className="mb-2 block text-[11px] font-semibold tracking-wide text-primary">
            {eyebrow}
          </span>
        ) : null}
        <h1 className="text-balance text-2xl font-bold tracking-tight text-foreground md:text-[28px]">
          {title}
        </h1>
        {description ? (
          <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
            {description}
          </p>
        ) : null}
      </div>

      {action ? (
        <div className="flex shrink-0 flex-wrap items-center gap-2 md:justify-end">
          {action}
        </div>
      ) : null}
    </header>
  )
}
