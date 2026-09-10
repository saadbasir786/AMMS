import React from 'react'
import {
  LockKeyhole,
  KeyRound,
  Shield,
  Crown,
  Users,
  Pencil,
  Power,
  Briefcase,
  ChevronLeft,
  Settings2,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { StatusBadge } from '@/components/amms/status-badge'
import { cn } from '@/lib/utils'
import type { AmmsRoleDefinition } from '../../types'

type RolesTableProps = {
  roles: AmmsRoleDefinition[]
  selectedRoleId?: number | null
  onSelectRole: (role: AmmsRoleDefinition) => void
  onEditRole: (role: AmmsRoleDefinition) => void
  onToggleStatus: (role: AmmsRoleDefinition) => void
  disabled?: boolean
}

export function RolesTable({
  roles,
  selectedRoleId,
  onSelectRole,
  onEditRole,
  onToggleStatus,
  disabled = false,
}: RolesTableProps) {
  // Get icon for role
  const getRoleIcon = (name: string) => {
    if (name === 'HIGH_AUTHORITY' || name === 'AUTHORITY_OFFICE' || name === 'EXECUTIVE_SECRETARY') {
      return Crown
    }
    if (name === 'DIRECTOR' || name === 'SYSTEM_ADMIN') return Shield
    if (name === 'EXECUTIVE_MANAGEMENT' || name === 'DEPUTY_DIRECTOR') return Briefcase
    if (name === 'EMPLOYEE') return Users
    return KeyRound
  }

  return (
    <Card className="overflow-hidden rounded-2xl border-border/70 shadow-xs">
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-right text-xs border-collapse">
            <thead className="bg-muted/60 border-b border-border/70">
              <tr>
                <th className="p-3.5 text-xs font-bold text-foreground min-w-[200px]">
                  عنوان و مشخصات نقش
                </th>
                <th className="p-3.5 text-xs font-bold text-foreground min-w-[130px]">
                  شناسه سیستمی
                </th>
                <th className="p-3.5 text-xs font-bold text-foreground text-center min-w-[120px]">
                  نوع نقش
                </th>
                <th className="p-3.5 text-xs font-bold text-foreground text-center min-w-[90px]">
                  وضعیت
                </th>
                <th className="p-3.5 text-xs font-bold text-foreground text-center min-w-[100px]">
                  دسترسی‌ها
                </th>
                <th className="p-3.5 text-xs font-bold text-foreground text-center min-w-[100px]">
                  کاربران عضو
                </th>
                <th className="p-3.5 text-xs font-bold text-foreground text-center min-w-[140px]">
                  عملیات
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-border/50">
              {roles.length === 0 ? (
                <tr>
                  <td colSpan={7} className="p-8 text-center text-muted-foreground">
                    نقشی مطابق با معیارهای جستجو یا فیلتر یافت نشد.
                  </td>
                </tr>
              ) : (
                roles.map((role, idx) => {
                  const isSystem = role.role_kind === 'SYSTEM'
                  const RoleIcon = getRoleIcon(role.name)
                  const isSelected = selectedRoleId === role.id

                  return (
                    <tr
                      key={role.id}
                      onClick={() => onSelectRole(role)}
                      className={cn(
                        'group cursor-pointer transition-colors duration-150',
                        isSelected
                          ? 'bg-primary/5 hover:bg-primary/10'
                          : idx % 2 === 0
                            ? 'bg-card hover:bg-muted/35'
                            : 'bg-muted/10 hover:bg-muted/35'
                      )}
                    >
                      {/* Role Title & Description */}
                      <td className="p-3.5">
                        <div className="flex items-center gap-3">
                          <div
                            className={cn(
                              'flex size-9 shrink-0 items-center justify-center rounded-xl transition-colors',
                              isSystem
                                ? 'bg-muted text-foreground/80 group-hover:bg-primary/10 group-hover:text-primary'
                                : 'bg-primary/10 text-primary',
                              isSelected && 'bg-primary text-primary-foreground'
                            )}
                          >
                            <RoleIcon className="size-4" />
                          </div>

                          <div className="min-w-0">
                            <span className="font-bold text-xs text-foreground block truncate">
                              {role.label}
                            </span>
                            {role.description && (
                              <p className="text-[11px] text-muted-foreground truncate max-w-xs mt-0.5">
                                {role.description}
                              </p>
                            )}
                          </div>
                        </div>
                      </td>

                      {/* Technical Code */}
                      <td className="p-3.5">
                        <span className="font-mono text-[11px] text-muted-foreground bg-muted/60 px-2 py-0.5 rounded-md" dir="ltr">
                          {role.name}
                        </span>
                      </td>

                      {/* Kind Badge */}
                      <td className="p-3.5 text-center">
                        {isSystem ? (
                          <Badge
                            variant="secondary"
                            className="h-5 gap-1 rounded-md px-2 text-[10px] font-medium bg-muted/90 text-muted-foreground border border-border/70"
                          >
                            <LockKeyhole className="size-2.5" />
                            سیستمی
                          </Badge>
                        ) : (
                          <Badge
                            variant="outline"
                            className="h-5 gap-1 rounded-md px-2 text-[10px] font-medium text-primary border-primary/30 bg-primary/5"
                          >
                            <KeyRound className="size-2.5" />
                            سفارشی
                          </Badge>
                        )}
                      </td>

                      {/* Status Badge */}
                      <td className="p-3.5 text-center">
                        <StatusBadge status={role.status} />
                      </td>

                      {/* Permissions Count */}
                      <td className="p-3.5 text-center">
                        <Badge
                          variant="secondary"
                          className="h-5 px-2 font-mono text-[11px] font-semibold bg-muted/50 border border-border/60"
                        >
                          {role.permissions.length} دسترسی
                        </Badge>
                      </td>

                      {/* Assigned Users Count */}
                      <td className="p-3.5 text-center">
                        <span className="font-mono text-xs font-semibold text-foreground">
                          {role.assigned_user_count} کاربر
                        </span>
                      </td>

                      {/* Action Buttons */}
                      <td className="p-3.5 text-center">
                        <div className="flex items-center justify-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                          <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => onSelectRole(role)}
                            className="h-7.5 px-2.5 rounded-lg text-[11px] font-semibold gap-1 text-primary hover:text-primary shadow-2xs"
                          >
                            <Settings2 className="size-3.5" />
                            <span>دسترسی‌ها</span>
                            <ChevronLeft className="size-3 opacity-60" />
                          </Button>

                          {!isSystem && (
                            <>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={disabled}
                                onClick={() => onEditRole(role)}
                                className="h-7.5 w-7.5 p-0 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted"
                                title="ویرایش مشخصات نقش"
                              >
                                <Pencil className="size-3.5" />
                              </Button>

                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={disabled}
                                onClick={() => onToggleStatus(role)}
                                className={cn(
                                  'h-7.5 w-7.5 p-0 rounded-lg hover:bg-muted',
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
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  )
}
