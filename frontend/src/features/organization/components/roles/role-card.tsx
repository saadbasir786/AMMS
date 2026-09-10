import React from 'react'
import {
  LockKeyhole,
  KeyRound,
  Shield,
  Crown,
  Users,
  Pencil,
  Power,
  ChevronLeft,
  Briefcase,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { StatusBadge } from '@/components/amms/status-badge'
import { cn } from '@/lib/utils'
import type { AmmsRoleDefinition } from '../../types'

type RoleCardProps = {
  role: AmmsRoleDefinition
  isSelected: boolean
  onSelect: () => void
  onEdit?: () => void
  onToggleStatus?: () => void
  disabled?: boolean
}

export function RoleCard({
  role,
  isSelected,
  onSelect,
  onEdit,
  onToggleStatus,
  disabled = false,
}: RoleCardProps) {
  const isSystem = role.role_kind === 'SYSTEM'

  // Icon and tone selector based on role name
  const getRoleIcon = () => {
    if (role.name === 'HIGH_AUTHORITY' || role.name === 'AUTHORITY_OFFICE' || role.name === 'EXECUTIVE_SECRETARY') {
      return Crown
    }
    if (role.name === 'DIRECTOR') return Shield
    if (role.name === 'SYSTEM_ADMIN') return Shield
    if (role.name === 'EXECUTIVE_MANAGEMENT' || role.name === 'DEPUTY_DIRECTOR') return Briefcase
    if (role.name === 'EMPLOYEE') return Users
    return KeyRound
  }

  const RoleIcon = getRoleIcon()

  return (
    <Card
      onClick={onSelect}
      className={cn(
        'group relative cursor-pointer overflow-hidden rounded-2xl border transition-all duration-200',
        'hover:border-primary/40 hover:shadow-md',
        isSelected
          ? 'border-primary/60 bg-primary/[0.02] shadow-sm ring-2 ring-primary/20'
          : 'border-border/70 bg-card hover:bg-muted/10'
      )}
    >
      {isSelected && (
        <div className="absolute top-0 bottom-0 right-0 w-1 bg-primary rounded-r-full" />
      )}

      <CardContent className="p-4 sm:p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-start gap-3 min-w-0">
            <div
              className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors',
                isSystem
                  ? 'bg-muted text-foreground/80 group-hover:bg-primary/10 group-hover:text-primary'
                  : 'bg-primary/10 text-primary',
                isSelected && 'bg-primary text-primary-foreground'
              )}
            >
              <RoleIcon className="size-5" />
            </div>

            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2 flex-wrap">
                <h4 className="font-bold text-sm sm:text-base text-foreground truncate">
                  {role.label}
                </h4>
                {isSystem ? (
                  <Badge
                    variant="secondary"
                    className="h-5 gap-1 rounded-md px-1.5 text-[10px] font-medium bg-muted/80 text-muted-foreground border border-border/60"
                  >
                    <LockKeyhole className="size-2.5" />
                    سیستمی
                  </Badge>
                ) : (
                  <Badge
                    variant="outline"
                    className="h-5 gap-1 rounded-md px-1.5 text-[10px] font-medium border-primary/30 text-primary bg-primary/5"
                  >
                    <KeyRound className="size-2.5" />
                    سفارشی
                  </Badge>
                )}
              </div>

              <span className="block font-mono text-[11px] text-muted-foreground mt-0.5" dir="ltr">
                {role.name}
              </span>
            </div>
          </div>

          <div className="shrink-0">
            <StatusBadge status={role.status} />
          </div>
        </div>

        {role.description ? (
          <p className="mt-3 line-clamp-2 text-xs text-muted-foreground leading-relaxed">
            {role.description}
          </p>
        ) : (
          <p className="mt-3 text-xs italic text-muted-foreground/70">
            {isSystem
              ? 'نقش پایه و حاکمیتی با اختیارات مصوب در چارچوب AMMS.'
              : 'نقش اداری سفارشی با سطح دسترسی محدود.'}
          </p>
        )}

        <div className="mt-4 flex items-center justify-between border-t border-border/50 pt-3">
          <div className="flex items-center gap-3 text-xs text-muted-foreground">
            <div className="flex items-center gap-1.5">
              <span className="text-[11px]">دسترسی‌ها:</span>
              <strong className="font-mono text-foreground font-semibold">
                {role.permissions.length}
              </strong>
            </div>
            <span className="text-border">•</span>
            <div className="flex items-center gap-1.5">
              <span className="text-[11px]">کاربران:</span>
              <strong className="font-mono text-foreground font-semibold">
                {role.assigned_user_count}
              </strong>
            </div>
          </div>

          <div className="flex items-center gap-1.5">
            {!isSystem && (
              <>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  disabled={disabled}
                  onClick={(e) => {
                    e.stopPropagation()
                    onEdit?.()
                  }}
                  className="h-7 w-7 p-0 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted"
                  title="ویرایش مشخصات نقش"
                >
                  <Pencil className="size-3.5" />
                </Button>

                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  disabled={disabled}
                  onClick={(e) => {
                    e.stopPropagation()
                    onToggleStatus?.()
                  }}
                  className={cn(
                    'h-7 w-7 p-0 rounded-lg hover:bg-muted',
                    role.status === 'ACTIVE'
                      ? 'text-rose-600 hover:text-rose-700'
                      : 'text-emerald-600 hover:text-emerald-700'
                  )}
                  title={role.status === 'ACTIVE' ? 'غیرفعال‌سازی نقش' : 'فعال‌سازی نقش'}
                >
                  <Power className="size-3.5" />
                </Button>
              </>
            )}

            <div
              className={cn(
                'flex items-center gap-1 text-[11px] font-semibold transition-colors mr-1',
                isSelected ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground'
              )}
            >
              <span>{isSelected ? 'در حال نمایش' : 'بررسی'}</span>
              <ChevronLeft className="size-3.5" />
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
