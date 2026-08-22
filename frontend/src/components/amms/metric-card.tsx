import type { LucideIcon } from 'lucide-react'

import { Card, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'

type MetricTone = 'default' | 'success' | 'warning' | 'danger'

type MetricCardProps = {
  label: string
  value: string | number
  icon: LucideIcon
  tone?: MetricTone
  hint?: string
}

const toneClasses: Record<MetricTone, { icon: string; value: string }> = {
  default: {
    icon: 'bg-primary/10 text-primary',
    value: 'text-foreground',
  },
  success: {
    icon: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    value: 'text-emerald-800 dark:text-emerald-200',
  },
  warning: {
    icon: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
    value: 'text-amber-800 dark:text-amber-200',
  },
  danger: {
    icon: 'bg-destructive/10 text-destructive',
    value: 'text-destructive',
  },
}

export function MetricCard({
  label,
  value,
  icon: Icon,
  tone = 'default',
  hint,
}: MetricCardProps) {
  const styles = toneClasses[tone]

  return (
    <Card className="border-border/70 shadow-sm transition-shadow hover:shadow-md">
      <CardContent className="flex items-start gap-4 p-5">
        <div className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', styles.icon)}>
          <Icon className="size-[18px]" />
        </div>
        <div className="min-w-0">
          <span className="block text-xs font-medium text-muted-foreground">{label}</span>
          <strong className={cn('mt-1 block text-2xl font-bold tabular-nums', styles.value)}>{value}</strong>
          {hint ? <small className="mt-1 block text-[11px] leading-5 text-muted-foreground">{hint}</small> : null}
        </div>
      </CardContent>
    </Card>
  )
}
