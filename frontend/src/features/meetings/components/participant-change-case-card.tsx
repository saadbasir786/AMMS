import React, { useState } from 'react'
import { Trash2, UserCheck, UserMinus, UserPlus, Users } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import type { ParticipantChangeCase } from '../types'
import { workspaceStatusLabels, participantRoleLabels } from '../lib/workspace-labels'

const OP_CONFIG: Record<string, { label: string; icon: any; color: string }> = {
  REPLACE: {
    label: 'جایگزینی عضو',
    icon: UserCheck,
    color: 'text-indigo-700 bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200',
  },
  ADD: {
    label: 'افزودن عضو جدید',
    icon: UserPlus,
    color: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200',
  },
  REMOVE: {
    label: 'حذف عضو',
    icon: UserMinus,
    color: 'text-rose-700 bg-rose-50 dark:bg-rose-950/40 border-rose-200',
  },
}

type ParticipantChangeCaseCardProps = {
  activeCase: ParticipantChangeCase
  canEdit: boolean
  busy: boolean
  onDeleteItem: (itemId: number) => Promise<boolean>
  onSaveReason: (reason: string) => Promise<boolean>
}

export function ParticipantChangeCaseCard({
  activeCase,
  canEdit,
  busy,
  onDeleteItem,
  onSaveReason,
}: ParticipantChangeCaseCardProps) {
  const [reason, setReason] = useState(activeCase.reason)
  const items = activeCase.items || []

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between gap-3">
          <div>
            <span className="text-[11px] font-semibold text-muted-foreground block">
              پرونده رسمی تغییر اشتراک‌کنندگان
            </span>
            <h3 className="text-sm sm:text-base font-bold text-foreground mt-0.5">
              {'\u067e\u0631\u0648\u0646\u062f\u0647 \u0641\u0639\u0627\u0644 \u062a\u063a\u06cc\u06cc\u0631 \u0627\u0639\u0636\u0627'}
            </h3>
          </div>
          <div className="flex flex-col items-end gap-1.5">
            <Badge variant="outline" className="text-xs px-2.5 py-1">
              {workspaceStatusLabels[activeCase.status] || '\u0646\u0627\u0645\u0634\u062e\u0635'}
            </Badge>
            <Badge variant="outline" className="text-xs px-2.5 py-1">
            مالک: {activeCase.owner_type === 'AUTHORITY' ? 'دفتر مقام' : activeCase.owner_unit?.name || 'ریاست'}
            </Badge>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {canEdit && <div className="space-y-2">
          <Textarea aria-label="دلیل تغییر اشتراک‌کنندگان" value={reason} onChange={(event) => setReason(event.target.value)} disabled={busy} />
          <Button variant="outline" disabled={busy || !reason?.trim() || reason === activeCase.reason} onClick={() => onSaveReason(reason)}>ذخیرهٔ دلیل اصلاح‌شده</Button>
        </div>}
        {activeCase.reason && (
          <p className="text-xs text-muted-foreground leading-relaxed">
            {activeCase.reason}
          </p>
        )}

        {/* Info Grid */}
        <div className="grid grid-cols-3 gap-3 text-xs">
          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">نسخه مبنای اعضا</span>
            <strong className="text-sm font-bold text-foreground block mt-0.5 font-mono">
              نسخه {activeCase.baseline_participant_version_id}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">نسخه مبنای زمان</span>
            <strong className="text-sm font-bold text-foreground block mt-0.5 font-mono">
              نسخه {activeCase.baseline_schedule_version_id}
            </strong>
          </div>

          <div className="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50">
            <span className="text-[10px] text-muted-foreground block">تعداد تغییرات</span>
            <strong className="text-sm font-bold text-foreground block mt-0.5 font-mono">
              {items.length} مورد
            </strong>
          </div>
        </div>

        {/* Proposed Items List */}
        <div className="space-y-2">
          <span className="text-xs font-bold text-foreground block">
            اقلام تغییرات پیشنهادی در این پرونده:
          </span>

          {items.length === 0 ? (
            <p className="text-xs text-muted-foreground py-2">
              هنوز تغییری به این پرونده اضافه نشده است.
            </p>
          ) : (
            <div className="space-y-2">
              {items.map((item) => {
                const opCfg = OP_CONFIG[item.operation] || {
                  label: item.operation,
                  icon: Users,
                  color: 'text-slate-600 bg-slate-100',
                }
                const Icon = opCfg.icon

                return (
                  <div
                    key={item.id}
                    className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 text-xs"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span
                        className={cn(
                          'p-1.5 rounded-lg border text-[10px] font-bold flex items-center gap-1 shrink-0',
                          opCfg.color
                        )}
                      >
                        <Icon className="size-3" />
                        <span>{opCfg.label}</span>
                      </span>

                      <div className="min-w-0 truncate">
                        {item.operation === 'REPLACE' && (
                          <span className="font-semibold text-foreground">
                            {item.old_employee?.full_name || '—'} ←{' '}
                            <strong className="text-primary">{item.new_employee?.full_name || '—'}</strong>
                          </span>
                        )}
                        {item.operation === 'ADD' && (
                          <strong className="font-bold text-emerald-700 dark:text-emerald-300">
                            + {item.new_employee?.full_name || '—'}
                          </strong>
                        )}
                        {item.operation === 'REMOVE' && (
                          <span className="text-rose-600 line-through">
                            - {item.old_employee?.full_name || '—'}
                          </span>
                        )}
                        {item.participant_role && (
                          <span className="text-[10px] text-muted-foreground mr-2">
                            (سمت: {participantRoleLabels[item.participant_role] || item.participant_role || '\u0639\u0636\u0648'})
                          </span>
                        )}
                      </div>
                    </div>

                    {canEdit && (
                      <Button
                        size="sm"
                        variant="ghost"
                        disabled={busy}
                        onClick={() => onDeleteItem(item.id)}
                        className="size-7 p-0 text-muted-foreground hover:text-rose-600 rounded-lg shrink-0"
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
