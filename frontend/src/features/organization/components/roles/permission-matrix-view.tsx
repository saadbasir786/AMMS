import { useState, useMemo } from 'react'
import {
  Check,
  Search,
  Filter,
  Shield,
  LockKeyhole,
  CheckCircle2,
  Minus,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import {
  PERMISSION_CATEGORIES,
  getPermissionDetails,
} from '../../lib/roles-metadata-helper'
import type { AmmsPermissionMetadata } from '../../lib/amms-metadata'
import type { AmmsRoleDefinition } from '../../types'

type PermissionMatrixViewProps = {
  permissions: AmmsPermissionMetadata[]
  roles: AmmsRoleDefinition[]
}

export function PermissionMatrixView({ permissions, roles }: PermissionMatrixViewProps) {
  const [search, setSearch] = useState('')
  const [selectedCategory, setSelectedCategory] = useState<string>('all')
  const [roleTypeFilter, setRoleTypeFilter] = useState<'ALL' | 'SYSTEM' | 'CUSTOM'>('SYSTEM')

  // Available categories
  const categories = useMemo(() => {
    const cats = new Set<string>()
    permissions.forEach((p) => {
      const details = getPermissionDetails(p.name)
      cats.add(details.category)
    })
    return Array.from(cats)
  }, [permissions])

  // Filtered roles
  const filteredRoles = useMemo(() => {
    if (roleTypeFilter === 'SYSTEM') return roles.filter((r) => r.role_kind === 'SYSTEM')
    if (roleTypeFilter === 'CUSTOM') return roles.filter((r) => r.role_kind === 'CUSTOM')
    return roles
  }, [roles, roleTypeFilter])

  // Filtered permissions
  const filteredPermissions = useMemo(() => {
    const searchLower = search.trim().toLowerCase()

    return permissions.filter((perm) => {
      const details = getPermissionDetails(perm.name)
      if (selectedCategory !== 'all' && details.category !== selectedCategory) {
        return false
      }

      if (!searchLower) return true

      return (
        details.label.toLowerCase().includes(searchLower) ||
        details.description.toLowerCase().includes(searchLower) ||
        perm.name.toLowerCase().includes(searchLower)
      )
    })
  }, [permissions, selectedCategory, search])

  return (
    <Card className="overflow-hidden rounded-2xl border-border/70 shadow-xs">
      {/* Matrix Controls */}
      <div className="p-4 sm:p-5 border-b border-border/60 bg-muted/20 space-y-4">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <h3 className="text-sm font-bold text-foreground">ماتریس جامع دسترسی‌های سامانه</h3>
            <p className="text-xs text-muted-foreground mt-0.5">
              مقایسه دقیق و ارزیابی اختیارات مصوب هر نقش در برابر دسترسی‌های عملیاتی AMMS
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            <div className="flex items-center bg-muted/60 p-1 rounded-xl border border-border/70 text-xs">
              <button
                type="button"
                onClick={() => setRoleTypeFilter('SYSTEM')}
                className={cn(
                  'px-3 py-1.5 rounded-lg font-semibold transition-all',
                  roleTypeFilter === 'SYSTEM'
                    ? 'bg-card text-primary shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                نقش‌های سیستمی ({roles.filter((r) => r.role_kind === 'SYSTEM').length})
              </button>
              <button
                type="button"
                onClick={() => setRoleTypeFilter('CUSTOM')}
                className={cn(
                  'px-3 py-1.5 rounded-lg font-semibold transition-all',
                  roleTypeFilter === 'CUSTOM'
                    ? 'bg-card text-primary shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                نقش‌های سفارشی ({roles.filter((r) => r.role_kind === 'CUSTOM').length})
              </button>
              <button
                type="button"
                onClick={() => setRoleTypeFilter('ALL')}
                className={cn(
                  'px-3 py-1.5 rounded-lg font-semibold transition-all',
                  roleTypeFilter === 'ALL'
                    ? 'bg-card text-primary shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
                )}
              >
                همه ({roles.length})
              </button>
            </div>
          </div>
        </div>

        {/* Search & Category Filter */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          <div className="relative flex-1 max-w-sm">
            <Search className="absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="جستجو در عنوان یا کد دسترسی…"
              className="h-9 pr-9 pl-3 text-xs rounded-xl bg-background"
            />
          </div>

          <div className="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full text-xs">
            <Button
              type="button"
              variant={selectedCategory === 'all' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setSelectedCategory('all')}
              className="h-8 rounded-lg text-xs font-semibold shrink-0"
            >
              همه بخش‌ها ({permissions.length})
            </Button>

            {categories.map((catId) => {
              const cat = PERMISSION_CATEGORIES[catId]
              const count = permissions.filter(
                (p) => getPermissionDetails(p.name).category === catId
              ).length

              return (
                <Button
                  key={catId}
                  type="button"
                  variant={selectedCategory === catId ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setSelectedCategory(catId)}
                  className="h-8 rounded-lg text-xs font-semibold shrink-0 gap-1.5"
                >
                  <span>{cat?.label || catId}</span>
                  <span className="opacity-70 font-mono text-[10px]">({count})</span>
                </Button>
              )
            })}
          </div>
        </div>
      </div>

      {/* Matrix Table */}
      <CardContent className="p-0">
        {filteredPermissions.length === 0 || filteredRoles.length === 0 ? (
          <div className="flex flex-col items-center justify-center p-12 text-center">
            <Filter className="size-8 text-muted-foreground/60 mb-2" />
            <p className="text-sm font-semibold text-foreground">داده‌ای جهت نمایش یافت نشد</p>
            <p className="text-xs text-muted-foreground mt-1">
              لطفاً فیلترها یا عبارت جستجوی خود را تنظیم کنید.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto max-h-[640px] relative">
            <table className="w-full min-w-[960px] text-right text-xs border-collapse">
              <thead className="bg-muted/70 sticky top-0 z-20 backdrop-blur-xs border-b border-border/70">
                <tr>
                  <th className="sticky right-0 z-30 bg-muted/95 p-3.5 text-xs font-bold text-foreground min-w-[280px] max-w-[340px] shadow-xs">
                    دسترسی عملیاتی و عنوان اداری
                  </th>
                  {filteredRoles.map((role) => (
                    <th
                      key={role.id}
                      className="p-3 text-center min-w-[120px] max-w-[150px] border-r border-border/50"
                    >
                      <div className="flex flex-col items-center gap-1">
                        <span className="font-bold text-foreground text-xs truncate max-w-[130px]">
                          {role.label}
                        </span>
                        <div className="flex items-center gap-1">
                          {role.role_kind === 'SYSTEM' ? (
                            <Badge
                              variant="outline"
                              className="h-4 px-1 text-[9px] text-muted-foreground gap-0.5 border-border/70"
                            >
                              <LockKeyhole className="size-2" />
                              سیستمی
                            </Badge>
                          ) : (
                            <Badge
                              variant="outline"
                              className="h-4 px-1 text-[9px] text-primary border-primary/30"
                            >
                              سفارشی
                            </Badge>
                          )}
                          <span className="font-mono text-[10px] text-muted-foreground">
                            ({role.permissions.length})
                          </span>
                        </div>
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody className="divide-y divide-border/50">
                {filteredPermissions.map((permission, index) => {
                  const details = getPermissionDetails(permission.name)
                  const cat = PERMISSION_CATEGORIES[details.category]

                  return (
                    <tr
                      key={permission.name}
                      className={cn(
                        'transition-colors hover:bg-muted/30',
                        index % 2 === 0 ? 'bg-card' : 'bg-muted/10'
                      )}
                    >
                      {/* Permission Title Column (Sticky Right in RTL) */}
                      <td className="sticky right-0 z-10 bg-inherit p-3.5 font-medium border-l border-border/50 shadow-2xs">
                        <div className="min-w-0 space-y-0.5">
                          <div className="flex items-center gap-2">
                            {cat && (
                              <span
                                className={cn(
                                  'size-2 rounded-full shrink-0',
                                  cat.color.replace('text-', 'bg-')
                                )}
                              />
                            )}
                            <span className="font-bold text-xs text-foreground truncate block">
                              {details.label}
                            </span>
                          </div>
                          <span
                            className="block font-mono text-[10px] text-muted-foreground/80 truncate pr-4"
                            dir="ltr"
                          >
                            {permission.name}
                          </span>
                        </div>
                      </td>

                      {/* Role Granted Checkmarks */}
                      {filteredRoles.map((role) => {
                        const hasPermission = role.permissions.includes(permission.name)

                        return (
                          <td
                            key={role.id}
                            className="p-3 text-center border-r border-border/40"
                          >
                            {hasPermission ? (
                              <div className="inline-flex size-6 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold shadow-2xs">
                                <Check className="size-3.5" />
                              </div>
                            ) : (
                              <div className="inline-flex size-6 items-center justify-center text-muted-foreground/30">
                                <Minus className="size-3" />
                              </div>
                            )}
                          </td>
                        )
                      })}
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
