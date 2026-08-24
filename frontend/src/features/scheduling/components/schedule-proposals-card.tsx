import React, { useState } from 'react'
import {
  CalendarDays,
  Clock3,
  MapPin,
  CheckCircle2,
  Plus,
  Send,
  Building2,
  MessageSquare,
  Sparkles
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'
import { formatSolarDate } from '@/lib/calendar-utils'

type ScheduleProposal = {
  id: number
  proposed_by_unit_id?: number
  proposed_by_unit?: { id: number; name: string }
  meeting_date: string
  start_time: string
  end_time?: string
  location?: string
  comment?: string
  status: 'PROPOSED' | 'ACCEPTED' | 'SUPERSEDED' | 'COUNTER_PROPOSED' | string
  created_at?: string
}

type ScheduleProposalsCardProps = {
  proposals: ScheduleProposal[]
  canPropose: boolean
  canAccept: boolean
  userUnitId?: number
  units?: Array<{ id: number; name: string; organizational_unit_id?: number }>
  busy: boolean
  onPropose: (data: {
    proposed_by_unit_id: number | string
    meeting_date: string
    start_time: string
    end_time: string
    location: string
    comment: string
  }) => Promise<void>
  onAccept: (proposalId: number) => Promise<void>
}

export function ScheduleProposalsCard({
  proposals,
  canPropose,
  canAccept,
  userUnitId,
  units = [],
  busy,
  onPropose,
  onAccept,
}: ScheduleProposalsCardProps) {
  const [showForm, setShowForm] = useState(false)
  const [formData, setFormData] = useState({
    proposed_by_unit_id: userUnitId || (units[0]?.id ?? ''),
    meeting_date: '',
    start_time: '09:00',
    end_time: '10:00',
    location: '',
    comment: '',
  })

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!formData.meeting_date || !formData.start_time) return
    await onPropose(formData)
    setShowForm(false)
    setFormData((prev) => ({ ...prev, comment: '', location: '' }))
  }

  const activeProposal = proposals.find((p) => p.status === 'ACCEPTED') || proposals[0]

  return (
    <Card className="border border-border/70 shadow-xs overflow-hidden" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60 flex flex-row items-center justify-between">
        <div className="space-y-0.5">
          <h3 className="text-xs sm:text-sm font-bold text-foreground flex items-center gap-2">
            <CalendarDays className="size-4 text-primary" />
            <span>تاریخچه و مذاکره پیشنهادهای زمان‌بندی</span>
          </h3>
          <p className="text-[11px] text-muted-foreground">
            پیشنهادهای ثبت‌شده توسط طرفین جلسه به ترتیب تاریخی و با حفظ سوابق
          </p>
        </div>

        {canPropose && !showForm && (
          <Button
            size="sm"
            onClick={() => setShowForm(true)}
            className="h-8 text-xs font-semibold rounded-xl gap-1.5 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Plus className="size-3.5" />
            <span>ثبت پیشنهاد زمان جدید</span>
          </Button>
        )}
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {/* Proposal Creation Form */}
        {showForm && (
          <form
            onSubmit={handleSubmit}
            className="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-900/50 border border-primary/30 space-y-3.5"
          >
            <div className="flex items-center justify-between border-b border-border/50 pb-2">
              <span className="text-xs font-bold text-foreground flex items-center gap-1.5">
                <Sparkles className="size-3.5 text-primary" />
                <span>ثبت پیشنهاد جدید تاریخ، ساعت و مکان</span>
              </span>
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="text-[11px] text-muted-foreground hover:text-foreground"
              >
                انصراف
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
              {units.length > 1 && (
                <div className="space-y-1.5">
                  <label className="font-bold text-foreground block">ریاست پیشنهاددهنده</label>
                  <select
                    value={formData.proposed_by_unit_id}
                    onChange={(e) =>
                      setFormData({ ...formData, proposed_by_unit_id: Number(e.target.value) })
                    }
                    className="w-full h-9 text-xs rounded-xl border border-border/70 bg-card px-2.5"
                  >
                    {units.map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">تاریخ پیشنهادی</label>
                <MultiCalendarDatePicker
                  value={formData.meeting_date}
                  onChange={(iso) => setFormData({ ...formData, meeting_date: iso })}
                  className="h-9 text-xs rounded-xl"
                />
              </div>

              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">ساعت آغاز</label>
                <TimePicker
                  value={formData.start_time}
                  onChange={(t) => setFormData({ ...formData, start_time: t })}
                  className="h-9 text-xs rounded-xl"
                />
              </div>

              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">ساعت پایان</label>
                <TimePicker
                  value={formData.end_time}
                  onChange={(t) => setFormData({ ...formData, end_time: t })}
                  className="h-9 text-xs rounded-xl"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">محل پیشنهادی جلسه</label>
                <Input
                  value={formData.location}
                  onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                  placeholder="تالار کنفرانس، اتاق جلسات ریاست..."
                  className="h-9 text-xs rounded-xl"
                />
              </div>

              <div className="space-y-1.5">
                <label className="font-bold text-foreground block">توضیحات و یادداشت</label>
                <Input
                  value={formData.comment}
                  onChange={(e) => setFormData({ ...formData, comment: e.target.value })}
                  placeholder="دلایل انتخاب زمان یا شرایط جلسه..."
                  className="h-9 text-xs rounded-xl"
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button
                type="submit"
                disabled={busy || !formData.meeting_date || !formData.start_time}
                size="sm"
                className="h-8.5 text-xs font-semibold rounded-xl gap-1.5 bg-primary hover:bg-primary/90 text-white"
              >
                <Send className="size-3" />
                <span>ارسال پیشنهاد</span>
              </Button>
            </div>
          </form>
        )}

        {/* Proposals List */}
        {proposals.length === 0 ? (
          <div className="text-center py-8 text-xs text-muted-foreground">
            هنوز پیشنهاد زمان‌بندی برای این جلسه ثبت نشده است.
          </div>
        ) : (
          <div className="space-y-2.5">
            {proposals.map((p) => {
              const isAccepted = p.status === 'ACCEPTED'
              const isSuperseded = p.status === 'SUPERSEDED'

              return (
                <div
                  key={p.id}
                  className={`flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border text-xs transition-colors ${
                    isAccepted
                      ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-900'
                      : isSuperseded
                      ? 'bg-slate-50/50 dark:bg-slate-900/20 border-border/40 opacity-70'
                      : 'bg-card border-border/70 hover:border-primary/40'
                  }`}
                >
                  <div className="flex items-start gap-3">
                    <div
                      className={`size-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5 ${
                        isAccepted
                          ? 'bg-emerald-500/15 text-emerald-600'
                          : 'bg-primary/10 text-primary'
                      }`}
                    >
                      <Clock3 className="size-4" />
                    </div>

                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <strong className="text-xs font-bold text-foreground">
                          {p.proposed_by_unit?.name || 'پیشنهاد زمان‌بندی'}
                        </strong>
                        <Badge
                          variant="outline"
                          className={`text-[10px] font-bold ${
                            isAccepted
                              ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border-emerald-300'
                              : isSuperseded
                              ? 'bg-slate-100 text-muted-foreground border-slate-200'
                              : 'bg-indigo-50 text-indigo-700 border-indigo-200'
                          }`}
                        >
                          {isAccepted
                            ? 'پذیرفته‌شده'
                            : isSuperseded
                            ? 'منسوخ'
                            : p.status === 'COUNTER_PROPOSED'
                            ? 'پیشنهاد متقابل'
                            : 'پیشنهاد فعال'}
                        </Badge>
                      </div>

                      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground font-mono text-[11px]">
                        <span>📅 {formatSolarDate(p.meeting_date)}</span>
                        <span>⏰ {p.start_time?.slice(0, 5)} {p.end_time ? `تا ${p.end_time.slice(0, 5)}` : ''}</span>
                        {p.location && <span>📍 {p.location}</span>}
                      </div>

                      {p.comment && (
                        <p className="text-[11px] text-muted-foreground/90 pt-0.5">
                          یادداشت: {p.comment}
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Actions */}
                  {canAccept && !isAccepted && !isSuperseded && (
                    <div className="flex items-center gap-2 self-end sm:self-center">
                      <Button
                        size="sm"
                        disabled={busy}
                        onClick={() => onAccept(p.id)}
                        className="h-8 text-xs font-semibold rounded-xl gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                      >
                        <CheckCircle2 className="size-3.5" />
                        <span>پذیرش این زمان</span>
                      </Button>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
