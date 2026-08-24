import React from 'react'
import { Building2, Network, ChevronDown, Users, Layers, Shield } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { OrganizationalUnit } from '../types'

type OrganizationTreeCardProps = {
  rootUnit: OrganizationalUnit
  childUnits: OrganizationalUnit[]
}

export function OrganizationTreeCard({
  rootUnit,
  childUnits,
}: OrganizationTreeCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs overflow-hidden" dir="rtl">
      {/* Root Directorate Header */}
      <CardHeader className="p-4 sm:p-5 bg-slate-50/80 dark:bg-slate-900/50 border-b border-border/60">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="size-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
              <Building2 className="size-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm sm:text-base font-bold text-foreground">
                  {rootUnit.name}
                </h3>
                <Badge variant="outline" className="font-mono text-[10px] px-1.5 py-0">
                  {rootUnit.code}
                </Badge>
              </div>
              <span className="text-[11px] text-muted-foreground block mt-0.5">
                ریاست اصلی • {childUnits.length} واحد زیرمجموعه
              </span>
            </div>
          </div>

          <Badge className="bg-primary/15 text-primary text-[10px] px-2 py-0.5 border border-primary/20">
            ریاست
          </Badge>
        </div>
      </CardHeader>

      {/* Children Sub-units */}
      <CardContent className="p-4 sm:p-5">
        {childUnits.length === 0 ? (
          <p className="text-xs text-muted-foreground text-center py-2">
            دیپارتمنت یا مدیریت تابعه‌ای برای این ریاست ثبت نشده است.
          </p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            {childUnits.map((child) => {
              const isExec = child.unit_type === 'MANAGEMENT'

              return (
                <div
                  key={child.id}
                  className="flex items-center justify-between gap-2.5 p-3 rounded-xl bg-slate-50/60 dark:bg-slate-900/30 border border-border/50 text-xs hover:border-primary/40 transition-colors"
                >
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div
                      className={`size-7 rounded-lg flex items-center justify-center shrink-0 ${
                        isExec
                          ? 'bg-amber-500/10 text-amber-600'
                          : 'bg-indigo-500/10 text-indigo-600'
                      }`}
                    >
                      <Network className="size-3.5" />
                    </div>

                    <div className="min-w-0">
                      <strong className="text-xs font-semibold text-foreground block truncate">
                        {child.name}
                      </strong>
                      <span className="text-[10px] text-muted-foreground block font-mono">
                        کد: {child.code}
                      </span>
                    </div>
                  </div>

                  <Badge variant="secondary" className="text-[10px] shrink-0 font-medium">
                    {isExec ? 'مدیریت اجرائیه' : 'دیپارتمنت'}
                  </Badge>
                </div>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
