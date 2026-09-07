import React, { useState, useEffect } from 'react'
import { Plus, Send, UserCheck, UserMinus, UserPlus, Users } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import type { ParticipantChangeCase } from '../types'
import type { PaginationMeta } from '../types'
import { api } from '@/lib/api-client'
import { WorkspacePagination } from './workspace-pagination'

type ParticipantChangeFormCardProps = {
  meetingId: number
  activeCase: ParticipantChangeCase
  ownerParticipants: any[]
  employeeOptions: any[]
  busy: boolean
  onAddItem: (item: {
    operation: string
    old_employee_id: number | null
    new_employee_id: number | null
    participant_role: string
  }) => Promise<boolean>
  onRemoveItem: (oldEmployeeId: number) => Promise<boolean>
  onSubmitCase: () => Promise<boolean>
}

export function ParticipantChangeFormCard({
  meetingId,
  activeCase,
  ownerParticipants,
  employeeOptions,
  busy,
  onAddItem,
  onRemoveItem,
  onSubmitCase,
}: ParticipantChangeFormCardProps) {
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [candidates, setCandidates] = useState(employeeOptions)
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [searching, setSearching] = useState(false)
  const [operation, setOperation] = useState<'REPLACE' | 'ADD' | 'REMOVE'>('REPLACE')
  const [oldId, setOldId] = useState('')
  const [newId, setNewId] = useState('')
  const [role, setRole] = useState('PARTICIPATING')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    setSearching(true)
    const timer = setTimeout(() => {
      api.get(`/meetings/${meetingId}/participant-change/employees`, { params: { search, page, per_page: 15 }, signal: controller.signal })
        .then(({ data }) => { setCandidates(data.data); setPagination(data.meta) })
        .catch((err) => { if (!controller.signal.aborted) setError(err?.response?.data?.message || 'جستجوی کارمندان انجام نشد.') })
        .finally(() => { if (!controller.signal.aborted) setSearching(false) })
    }, 250)
    return () => { clearTimeout(timer); controller.abort() }
  }, [meetingId, search, page])

  const items = activeCase.items || []

  const handleAdd = async () => {
    if (operation !== 'ADD' && !oldId) {
      setError('انتخاب کارمند فعلی الزامی است.')
      return
    }
    if (operation !== 'REMOVE' && !newId) {
      setError('انتخاب کارمند جدید الزامی است.')
      return
    }
    setError(null)

    let saved: boolean
    if (operation === 'REMOVE') {
      saved = await onRemoveItem(Number(oldId))
    } else {
      saved = await onAddItem({
        operation,
        old_employee_id: operation === 'ADD' ? null : Number(oldId),
        new_employee_id: Number(newId) || null,
        participant_role: role,
      })
    }

    if (saved) { setOldId(''); setNewId('') }
  }

  const oldParticipantOptions = ownerParticipants.map((p) => ({
    value: String(p.employee_id),
    label: `${p.employee?.full_name || 'کارمند'} (${p.employee?.unit?.name || p.organizational_unit?.name || 'واحد'})`,
  }))

  const newEmployeeSelectOptions = candidates.map((e) => ({
    value: String(e.id),
    label: `${e.full_name} • ${e.unit?.name || 'واحد نامشخص'}`,
  }))

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          افزودن تغییر پیشنهادی در فهرست اعضا
        </h3>
        <p className="text-[11px] text-muted-foreground">
          تعیین نوع تغییر، عضو جدید یا جایگزین و ثبت در پیش‌نویس پرونده
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {operation !== 'REMOVE' && <div className="space-y-2">
          <label className="text-xs font-bold" htmlFor="participant-candidate-search">جستجوی کارمند جدید در حوزهٔ مجاز</label>
          <Input id="participant-candidate-search" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); setNewId('') }} placeholder="نام، تخلص یا شمارهٔ کارمند" />
          {searching && <p className="text-xs" role="status">در حال جستجو…</p>}
          <WorkspacePagination meta={pagination} busy={searching} onChange={(next) => { setPage(next); setNewId('') }} />
        </div>}
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
          {/* Operation Selector */}
          <div className="space-y-1.5 sm:col-span-2">
            <label className="text-xs font-bold text-foreground block">
              نوع عملیات تغییر
            </label>
            <div className="grid grid-cols-3 gap-2">
              <button
                type="button"
                onClick={() => setOperation('REPLACE')}
                className={`p-2.5 rounded-xl border text-xs font-semibold flex items-center justify-center gap-1.5 transition-all ${
                  operation === 'REPLACE'
                    ? 'bg-primary text-white border-primary shadow-xs'
                    : 'bg-card text-muted-foreground border-border/70 hover:border-border'
                }`}
              >
                <UserCheck className="size-3.5" />
                <span>جایگزینی عضو</span>
              </button>

              <button
                type="button"
                onClick={() => setOperation('ADD')}
                className={`p-2.5 rounded-xl border text-xs font-semibold flex items-center justify-center gap-1.5 transition-all ${
                  operation === 'ADD'
                    ? 'bg-primary text-white border-primary shadow-xs'
                    : 'bg-card text-muted-foreground border-border/70 hover:border-border'
                }`}
              >
                <UserPlus className="size-3.5" />
                <span>افزودن عضو جدید</span>
              </button>

              <button
                type="button"
                onClick={() => setOperation('REMOVE')}
                className={`p-2.5 rounded-xl border text-xs font-semibold flex items-center justify-center gap-1.5 transition-all ${
                  operation === 'REMOVE'
                    ? 'bg-primary text-white border-primary shadow-xs'
                    : 'bg-card text-muted-foreground border-border/70 hover:border-border'
                }`}
              >
                <UserMinus className="size-3.5" />
                <span>حذف عضو</span>
              </button>
            </div>
          </div>

          {/* Current Participant */}
          {operation !== 'ADD' && (
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                عضو یا اشتراک‌کننده فعلی
              </label>
              <SearchableSelect
                options={oldParticipantOptions}
                value={oldId}
                onChange={(val) => setOldId(String(val))}
                placeholder="انتخاب از لیست اعضای فعلی..."
                className="h-9.5 text-xs rounded-xl"
              />
            </div>
          )}

          {/* New Employee */}
          {operation !== 'REMOVE' && (
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground block">
                عضو یا اشتراک‌کننده جدید
              </label>
              <SearchableSelect
                options={newEmployeeSelectOptions}
                value={newId}
                onChange={(val) => setNewId(String(val))}
                placeholder="جستجو و انتخاب کارمند..."
                className="h-9.5 text-xs rounded-xl"
              />
            </div>
          )}

          {/* Role */}
          <div className="space-y-1.5 sm:col-span-2">
            <label className="text-xs font-bold text-foreground block">
              سمت یا نقش در جلسه
            </label>
            <Input
              value={role}
              onChange={(e) => setRole(e.target.value)}
              placeholder="عضو اصلی، کارشناس فنی، مدعو..."
              className="h-9.5 text-xs rounded-xl"
            />
          </div>
        </div>

        {/* Submit & Add buttons */}
        <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-border/50">
          <Button
            disabled={busy || (operation !== 'ADD' && !oldId) || (operation !== 'REMOVE' && !newId)}
            onClick={handleAdd}
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Plus className="size-3.5" />
            <span>ثبت تغییر در پرونده</span>
          </Button>

          {items.length > 0 && (
            <Button
              variant="outline"
              disabled={busy}
              onClick={onSubmitCase}
              className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-900"
            >
              <Send className="size-3.5" />
              <span>ارسال پرونده برای تأیید نهایی</span>
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
