import React, { useMemo, useState } from 'react'
import { Users, User, ShieldCheck, Crown, Search, CheckCircle2, UserCheck } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'

type NormalizedParticipant = {
  id: string | number
  employeeId?: number | string
  fullName: string
  unitName: string
  position?: string
  role: string
  isChairperson?: boolean
  attendanceStatus?: string
}

type MeetingParticipantsCardProps = {
  meeting?: any
  participants?: any[]
  showSearch?: boolean
}

export function MeetingParticipantsCard({
  meeting,
  participants = [],
  showSearch = true,
}: MeetingParticipantsCardProps) {
  const [search, setSearch] = useState('')

  const allParticipants = useMemo<NormalizedParticipant[]>(() => {
    const list: NormalizedParticipant[] = []
    const seenEmployeeIds = new Set<string | number>()

    // 1. Add Chairperson if available
    if (meeting?.chairperson) {
      const cp = meeting.chairperson
      const cpId = cp.id || cp.employee_id || 'chairperson'
      seenEmployeeIds.add(String(cpId))
      list.push({
        id: `cp-${cpId}`,
        employeeId: cpId,
        fullName: cp.full_name || cp.name || 'رئیس جلسه',
        unitName: cp.unit?.name || cp.organizational_unit?.name || 'ریاست برگزارکننده',
        position: cp.position?.title || cp.position || 'ریاست جلسه',
        role: 'رئیس جلسه',
        isChairperson: true,
      })
    }

    // Helper to extract and push participant
    const addParticipant = (p: any, defaultRole = 'عضو رسمی') => {
      if (!p) return
      const emp = p.employee || (p.full_name ? p : null)
      const empId = emp?.id || p.employee_id || p.id
      const idKey = String(empId || Math.random())

      if (seenEmployeeIds.has(idKey)) return
      seenEmployeeIds.add(idKey)

      const fullName = emp?.full_name || emp?.name || p.full_name || p.name || 'کارمند'
      const unitName =
        emp?.unit?.name ||
        p.unit?.name ||
        p.organizational_unit?.name ||
        p.nominated_by_unit?.name ||
        'واحد سازمانی'
      const position = emp?.position?.title || emp?.position || p.position?.title || p.position
      const role =
        p.role === 'CHAIRPERSON' || p.is_chairperson
          ? 'رئیس جلسه'
          : p.role || p.nomination_status || defaultRole

      list.push({
        id: p.id || idKey,
        employeeId: empId,
        fullName,
        unitName,
        position,
        role: role === 'CONFIRMED' ? 'تأییدشده' : role,
        isChairperson: p.role === 'CHAIRPERSON' || p.is_chairperson,
        attendanceStatus: p.attendance_status || p.status,
      })
    }

    // 2. From meeting.current_participant_version.members
    if (meeting?.current_participant_version?.members) {
      for (const m of meeting.current_participant_version.members) {
        addParticipant(m, 'عضو رسمی')
      }
    }

    // 3. From direct participants prop / meeting.participants
    const directList = participants.length > 0 ? participants : meeting?.participants || []
    for (const p of directList) {
      addParticipant(p, 'عضو رسمی')
    }

    // 4. From meeting.request?.participants (FINAL or PROPOSED)
    if (meeting?.request?.participants) {
      for (const p of meeting.request.participants) {
        addParticipant(p, p.participant_type === 'FINAL' ? 'عضو مصوب' : 'نامزدشده')
      }
    }

    // 5. From meeting.request?.nominations
    if (meeting?.request?.nominations) {
      for (const n of meeting.request.nominations) {
        addParticipant(n, 'اشتراک‌کننده')
      }
    }

    return list
  }, [meeting, participants])

  const filtered = useMemo(() => {
    if (!search.trim()) return allParticipants
    const q = search.toLowerCase()
    return allParticipants.filter(
      (p) =>
        p.fullName.toLowerCase().includes(q) ||
        p.unitName.toLowerCase().includes(q) ||
        (p.position && p.position.toLowerCase().includes(q)) ||
        p.role.toLowerCase().includes(q)
    )
  }, [allParticipants, search])

  return (
    <Card className="border border-border/70 shadow-xs overflow-hidden" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
              <Users className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">
                فهرست کامل اشتراک‌کنندگان جلسه
              </h3>
              <p className="text-[11px] text-muted-foreground">
                ریاست جلسه، اعضای مصوب و مدعوین رسمی
              </p>
            </div>
          </div>

          <Badge variant="secondary" className="text-xs font-mono font-bold px-2.5 py-0.5">
            {allParticipants.length} نفر
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-3.5">
        {/* Search Bar if multiple participants */}
        {showSearch && allParticipants.length > 4 && (
          <div className="relative">
            <Search className="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="جستجو در نام، سمت یا واحد اشتراک‌کننده..."
              className="h-8.5 pr-8 text-xs rounded-xl"
            />
          </div>
        )}

        {filtered.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground text-xs space-y-1">
            <User className="size-7 mx-auto opacity-40 mb-1" />
            <p>اشتراک‌کننده‌ای با این مشخصات یافت نشد.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            {filtered.map((p) => {
              const isChair = p.isChairperson

              return (
                <div
                  key={p.id}
                  className={`flex items-center justify-between gap-3 p-3 rounded-xl border text-xs transition-colors ${
                    isChair
                      ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-300 dark:border-amber-900 shadow-2xs'
                      : 'bg-slate-50/70 dark:bg-slate-900/40 border-border/50 hover:bg-slate-100/70 dark:hover:bg-slate-800/50'
                  }`}
                >
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div
                      className={`size-8 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 ${
                        isChair
                          ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                          : 'bg-primary/10 text-primary'
                      }`}
                    >
                      {isChair ? <Crown className="size-4" /> : p.fullName.charAt(0)}
                    </div>

                    <div className="min-w-0">
                      <div className="flex items-center gap-1.5">
                        <strong className="text-xs font-bold text-foreground block truncate">
                          {p.fullName}
                        </strong>
                        {isChair && (
                          <span className="text-[10px] text-amber-700 dark:text-amber-300 font-bold bg-amber-100 dark:bg-amber-900/60 px-1.5 py-0.2 rounded-sm">
                            رئیس
                          </span>
                        )}
                      </div>
                      <span className="text-[10px] text-muted-foreground block truncate">
                        {p.unitName} {p.position ? `• ${p.position}` : ''}
                      </span>
                    </div>
                  </div>

                  <Badge
                    variant={isChair ? 'default' : 'outline'}
                    className={`text-[10px] px-2 py-0.5 shrink-0 font-medium ${
                      isChair ? 'bg-amber-600 hover:bg-amber-600 text-white' : ''
                    }`}
                  >
                    {p.role}
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
