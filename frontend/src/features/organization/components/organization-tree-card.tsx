import React, { useState, useMemo } from 'react'
import {
  Building2,
  Network,
  Pencil,
  Plus,
  ChevronDown,
  ChevronLeft,
  Layers,
  FolderTree,
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { OrganizationalUnit } from '../types'

type OrganizationTreeCardProps = {
  rootUnit: OrganizationalUnit
  allUnits: OrganizationalUnit[]
  childUnits?: OrganizationalUnit[]
  onEditUnit?: (unit: OrganizationalUnit) => void
  onAddSubUnit?: (parentUnit: OrganizationalUnit) => void
}

const UNIT_TYPE_CONFIG: Record<
  string,
  { label: string; badgeClass: string; iconClass: string }
> = {
  GENERAL_PRESIDENCY: {
    label: 'ریاست عمومی',
    badgeClass: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30',
    iconClass: 'bg-purple-500/10 text-purple-600',
  },
  DEPUTY_PRESIDENCY: {
    label: 'معاونیت ریاست عمومی',
    badgeClass: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30',
    iconClass: 'bg-purple-500/10 text-purple-600',
  },
  GENERAL_DIRECTORATE: {
    label: 'ریاست عمومی (بخشی)',
    badgeClass: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30',
    iconClass: 'bg-sky-500/10 text-sky-600',
  },
  DIRECTORATE: {
    label: 'ریاست',
    badgeClass: 'bg-primary/15 text-primary border-primary/20',
    iconClass: 'bg-primary/10 text-primary',
  },
  ATTACHED_AGENCY: {
    label: 'اداره تابعه',
    badgeClass: 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border-cyan-500/30',
    iconClass: 'bg-cyan-500/10 text-cyan-600',
  },
  DEPUTY_DIRECTORATE: {
    label: 'معاونیت ریاست',
    badgeClass: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/30',
    iconClass: 'bg-indigo-500/10 text-indigo-600',
  },
  AMIRIATE: {
    label: 'آمریت',
    badgeClass: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    iconClass: 'bg-emerald-500/10 text-emerald-600',
  },
  GENERAL_MANAGEMENT: {
    label: 'مدیریت عمومی',
    badgeClass: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    iconClass: 'bg-amber-500/10 text-amber-600',
  },
  MANAGEMENT: {
    label: 'مدیریت',
    badgeClass: 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30',
    iconClass: 'bg-slate-500/10 text-slate-600',
  },
  SECTION: {
    label: 'شعبه',
    badgeClass: 'bg-muted text-muted-foreground border-border/70',
    iconClass: 'bg-muted text-muted-foreground',
  },
  DEPARTMENT: {
    label: 'دیپارتمنت',
    badgeClass: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/30',
    iconClass: 'bg-indigo-500/10 text-indigo-600',
  },
  OFFICE: {
    label: 'شعبه / دفتر',
    badgeClass: 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30',
    iconClass: 'bg-slate-500/10 text-slate-600',
  },
  OTHER: {
    label: 'سایر',
    badgeClass: 'bg-muted text-muted-foreground border-border/70',
    iconClass: 'bg-muted text-muted-foreground',
  },
}

function getUnitConfig(type?: string) {
  if (type && UNIT_TYPE_CONFIG[type]) {
    return UNIT_TYPE_CONFIG[type]
  }
  return {
    label: type || 'واحد سازمانی',
    badgeClass: 'bg-secondary text-secondary-foreground border-border/60',
    iconClass: 'bg-primary/10 text-primary',
  }
}

function TreeNode({
  unit,
  allUnits,
  level = 1,
  onEditUnit,
  onAddSubUnit,
}: {
  unit: OrganizationalUnit
  allUnits: OrganizationalUnit[]
  level?: number
  onEditUnit?: (unit: OrganizationalUnit) => void
  onAddSubUnit?: (parentUnit: OrganizationalUnit) => void
}) {
  const [isOpen, setIsOpen] = useState(true)

  const children = useMemo(
    () => allUnits.filter((u) => u.parent_id === unit.id),
    [allUnits, unit.id]
  )

  const config = getUnitConfig(unit.unit_type)
  const hasChildren = children.length > 0

  return (
    <div className="space-y-2">
      <div
        className={cn(
          'flex items-center justify-between gap-2.5 p-2.5 sm:p-3 rounded-xl border transition-all text-xs',
          'bg-slate-50/70 dark:bg-slate-900/40 border-border/60 hover:border-primary/40 hover:shadow-xs'
        )}
      >
        <div className="flex items-center gap-2.5 min-w-0 flex-1">
          {hasChildren ? (
            <button
              type="button"
              onClick={() => setIsOpen(!isOpen)}
              className="size-6 rounded-md hover:bg-muted flex items-center justify-center text-muted-foreground shrink-0 transition-transform"
              title={isOpen ? 'بستن شاخه‌ها' : 'باز کردن شاخه‌ها'}
            >
              {isOpen ? (
                <ChevronDown className="size-3.5" />
              ) : (
                <ChevronLeft className="size-3.5" />
              )}
            </button>
          ) : (
            <div className="size-6 flex items-center justify-center shrink-0">
              <span className="size-1.5 rounded-full bg-border" />
            </div>
          )}

          <div
            className={cn(
              'size-7 rounded-lg flex items-center justify-center shrink-0',
              config.iconClass
            )}
          >
            <Network className="size-3.5" />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <strong className="text-xs font-semibold text-foreground truncate block">
                {unit.name}
              </strong>
              {unit.code && (
                <span className="text-[10px] text-muted-foreground font-mono bg-muted/60 px-1 py-0.2 rounded border border-border/40">
                  {unit.code}
                </span>
              )}
            </div>
            {hasChildren && (
              <span className="text-[10px] text-muted-foreground block mt-0.5">
                {children.length} واحد زیرمجموعه مستقیم
              </span>
            )}
          </div>
        </div>

        <div className="flex items-center gap-1.5 shrink-0">
          <Badge
            variant="outline"
            className={cn('text-[10px] px-1.5 py-0 font-medium', config.badgeClass)}
          >
            {config.label}
          </Badge>

          {unit.status === 'INACTIVE' && (
            <Badge
              variant="outline"
              className="text-destructive border-destructive/30 text-[9px] px-1 py-0"
            >
              غیرفعال
            </Badge>
          )}

          {onAddSubUnit && (
            <button
              type="button"
              onClick={() => onAddSubUnit(unit)}
              className="size-6 rounded-md flex items-center justify-center text-muted-foreground/70 hover:text-primary hover:bg-primary/10 transition-colors"
              title="افزودن واحد زیرمجموعه"
              aria-label="افزودن زیرمجموعه"
            >
              <Plus className="size-3" />
            </button>
          )}

          {onEditUnit && (
            <button
              type="button"
              onClick={() => onEditUnit(unit)}
              className="size-6 rounded-md flex items-center justify-center text-muted-foreground/70 hover:text-primary hover:bg-primary/10 transition-colors"
              title="ویرایش مشخصات واحد"
              aria-label="ویرایش مشخصات واحد"
            >
              <Pencil className="size-3" />
            </button>
          )}
        </div>
      </div>

      {/* Recursive Children Container */}
      {hasChildren && isOpen && (
        <div className="pr-4 sm:pr-6 border-r-2 border-primary/20 space-y-2 mt-1.5">
          {children.map((child) => (
            <TreeNode
              key={child.id}
              unit={child}
              allUnits={allUnits}
              level={level + 1}
              onEditUnit={onEditUnit}
              onAddSubUnit={onAddSubUnit}
            />
          ))}
        </div>
      )}
    </div>
  )
}

export function OrganizationTreeCard({
  rootUnit,
  allUnits = [],
  childUnits,
  onEditUnit,
  onAddSubUnit,
}: OrganizationTreeCardProps) {
  const effectiveAllUnits = allUnits.length > 0 ? allUnits : (childUnits || [])
  const directChildren = useMemo(
    () => effectiveAllUnits.filter((u) => u.parent_id === rootUnit.id),
    [effectiveAllUnits, rootUnit.id]
  )

  const totalDescendants = useMemo(() => {
    let count = 0
    const queue = [rootUnit.id]
    while (queue.length > 0) {
      const currentId = queue.shift()!
      const subs = effectiveAllUnits.filter((u) => u.parent_id === currentId)
      count += subs.length
      subs.forEach((s) => queue.push(s.id))
    }
    return count
  }, [effectiveAllUnits, rootUnit.id])

  const rootConfig = getUnitConfig(rootUnit.unit_type)

  return (
    <Card className="border border-border/70 shadow-xs overflow-hidden rounded-2xl" dir="rtl">
      {/* Root Directorate Header */}
      <CardHeader className="p-4 sm:p-5 bg-slate-50/90 dark:bg-slate-900/60 border-b border-border/60">
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <div className="flex items-center gap-3 min-w-0">
            <div className="size-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
              <Building2 className="size-5" />
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h3 className="text-sm sm:text-base font-bold text-foreground truncate">
                  {rootUnit.name}
                </h3>
                {rootUnit.code && (
                  <Badge variant="outline" className="font-mono text-[10px] px-1.5 py-0">
                    {rootUnit.code}
                  </Badge>
                )}
              </div>
              <span className="text-[11px] text-muted-foreground block mt-0.5">
                {rootConfig.label} • {totalDescendants} واحد زیرمجموعه در کل شاخه
              </span>
            </div>
          </div>

          <div className="flex items-center gap-1.5">
            {rootUnit.status === 'INACTIVE' && (
              <Badge
                variant="outline"
                className="text-destructive border-destructive/30 text-[10px] px-1.5 py-0"
              >
                غیرفعال
              </Badge>
            )}
            <Badge
              variant="outline"
              className={cn('text-[10px] px-2 py-0.5 border font-semibold', rootConfig.badgeClass)}
            >
              {rootConfig.label}
            </Badge>

            {onAddSubUnit && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onAddSubUnit(rootUnit)}
                className="h-7 px-2 rounded-lg text-xs gap-1 hover:bg-primary/10 hover:text-primary transition-colors text-muted-foreground"
                title="افزودن زیرمجموعه جدید به این واحد"
              >
                <Plus className="size-3" />
                <span className="hidden sm:inline">افزودن زیرمجموعه</span>
              </Button>
            )}

            {onEditUnit && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onEditUnit(rootUnit)}
                className="h-7 px-2 rounded-lg text-xs gap-1 hover:bg-primary/10 hover:text-primary transition-colors text-muted-foreground"
                title="ویرایش مشخصات"
              >
                <Pencil className="size-3" />
                <span>ویرایش</span>
              </Button>
            )}
          </div>
        </div>
      </CardHeader>

      {/* Children Sub-units (Recursive Multi-Level Tree) */}
      <CardContent className="p-4 sm:p-5">
        {directChildren.length === 0 ? (
          <div className="text-center py-6 px-4 rounded-xl border border-dashed border-border/70 bg-muted/20">
            <FolderTree className="size-6 mx-auto text-muted-foreground/50 mb-1.5" />
            <p className="text-xs text-muted-foreground">
              واحد یا مدیریت تابعه‌ای مستقیماً زیر این واحد ثبت نشده است.
            </p>
            {onAddSubUnit && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => onAddSubUnit(rootUnit)}
                className="mt-2.5 h-7.5 text-xs rounded-xl gap-1.5"
              >
                <Plus className="size-3" />
                <span>ثبت اولین واحد زیرمجموعه</span>
              </Button>
            )}
          </div>
        ) : (
          <div className="space-y-2.5">
            {directChildren.map((child) => (
              <TreeNode
                key={child.id}
                unit={child}
                allUnits={effectiveAllUnits}
                level={1}
                onEditUnit={onEditUnit}
                onAddSubUnit={onAddSubUnit}
              />
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
