import React, { useState, useEffect, useCallback } from 'react'
import { ShieldCheck, FileCheck, RotateCw, AlertCircle, CheckCircle2 } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { api, apiData } from '@/lib/api-client'
import { cn } from '@/lib/utils'

export type DelegationCapabilityType = 'INTERNAL' | 'MINUTES'

type Unit = { id: number; name: string; code?: string }

type Delegate = {
  id: number
  name: string
  email?: string
  role_title?: string
  roles?: string[]
  employee?: {
    full_name: string
    organizational_unit_id?: number
    unit?: { id: number; name: string } | null
  } | null
}

type UnifiedDelegationDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  initialCapability?: DelegationCapabilityType
  onSuccess?: (capability: DelegationCapabilityType) => void
}

export function UnifiedDelegationDialog({
  open,
  onOpenChange,
  initialCapability = 'INTERNAL',
  onSuccess,
}: UnifiedDelegationDialogProps) {
  const [capability, setCapability] = useState<DelegationCapabilityType>(initialCapability)
  const [unitId, setUnitId] = useState('')
  const [delegateUserId, setDelegateUserId] = useState('')
  const [expiresAt, setExpiresAt] = useState('')
  const [reason, setReason] = useState('')

  const [units, setUnits] = useState<Unit[]>([])
  const [delegates, setDelegates] = useState<Delegate[]>([])
  const [loadingCandidates, setLoadingCandidates] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      setCapability(initialCapability)
      setDelegateUserId('')
      setExpiresAt('')
      setReason('')
      setError(null)
      setSuccessMessage(null)
    }
  }, [open, initialCapability])

  const loadCandidates = useCallback(async (cap: DelegationCapabilityType) => {
    setLoadingCandidates(true)
    setError(null)
    try {
      const endpoint =
        cap === 'INTERNAL'
          ? '/internal-meeting-approval-delegations'
          : '/minutes-approval-delegations'

      const data = apiData(await api.get(endpoint, { params: { per_page: 1 } }))
      const fetchedUnits = data?.units || []
      const fetchedDelegates = data?.delegates || []

      setUnits(fetchedUnits)
      setDelegates(fetchedDelegates)
      if (fetchedUnits.length > 0) {
        setUnitId((curr) => curr || String(fetchedUnits[0].id))
      }
    } catch (err: any) {
      setError(
        err?.response?.data?.message || 'بارگذاری فهرست افراد واجد صلاحیت با خطا مواجه شد.'
      )
    } finally {
      setLoadingCandidates(false)
    }
  }, [])

  useEffect(() => {
    if (open) {
      loadCandidates(capability)
    }
  }, [open, capability, loadCandidates])

  useEffect(() => {
    setDelegateUserId('')
  }, [unitId, capability])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    const effectiveUnitId = unitId || (units.length > 0 ? String(units[0].id) : '')

    if (!delegateUserId || !expiresAt || !reason.trim()) {
      setError('لطفاً فرد واجد صلاحیت، تاریخ پایان اعتبار و استناد اداری را تکمیل فرمایید.')
      return
    }

    if (!effectiveUnitId) {
      setError('اطلاعات اداره مربوطه در دسترس نیست. لطفاً مجدداً تلاش نمایید.')
      return
    }

    setBusy(true)
    setError(null)
    setSuccessMessage(null)

    try {
      const endpoint =
        capability === 'INTERNAL'
          ? '/internal-meeting-approval-delegations'
          : '/minutes-approval-delegations'

      const res = await api.post(endpoint, {
        organizational_unit_id: Number(effectiveUnitId),
        delegate_user_id: Number(delegateUserId),
        expires_at: expiresAt,
        reason: reason.trim(),
      })

      const msg =
        res?.data?.message ||
        (capability === 'INTERNAL'
          ? 'صلاحیت تأیید جلسات داخلی با موفقیت تفویض شد.'
          : 'صلاحیت تأیید و بازبینی صورت‌جلسات با موفقیت تفویض شد.')

      setSuccessMessage(msg)
      setTimeout(() => {
        onOpenChange(false)
        onSuccess?.(capability)
      }, 800)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'ثبت تفویض صلاحیت اداری با خطا مواجه شد. لطفاً ورودی‌ها را بررسی نمایید.'
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg rounded-2xl" dir="rtl">
        <DialogHeader className="space-y-1.5 text-right">
          <DialogTitle className="text-base font-bold flex items-center gap-2 text-foreground">
            <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <ShieldCheck className="size-4" />
            </div>
            <span>مرکز تفویض اختیارات سازمانی ریاست</span>
          </DialogTitle>
          <DialogDescription className="sr-only">
            ثبت تفویض صلاحیت اداری
          </DialogDescription>
        </DialogHeader>

        {error && (
          <div className="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2">
            <AlertCircle className="size-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {successMessage && (
          <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 text-xs flex items-center gap-2">
            <CheckCircle2 className="size-4 shrink-0" />
            <span>{successMessage}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4 pt-1 text-xs">
          {/* 1. Capability Selector */}
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">
              نوع صلاحیت تفویضی <span className="text-destructive">*</span>
            </label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setCapability('INTERNAL')}
                className={cn(
                  'p-3 rounded-xl border text-right transition-all flex flex-col gap-1',
                  capability === 'INTERNAL'
                    ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/30'
                    : 'border-border/70 hover:bg-muted/40'
                )}
              >
                <div className="flex items-center gap-2 font-bold text-foreground">
                  <ShieldCheck className="size-4 text-primary" />
                  <span>تأیید جلسات داخلی</span>
                </div>
                <span className="text-[10px] text-muted-foreground">
                  تأیید درخواست‌های جلسات درون‌اداره
                </span>
              </button>

              <button
                type="button"
                onClick={() => setCapability('MINUTES')}
                className={cn(
                  'p-3 rounded-xl border text-right transition-all flex flex-col gap-1',
                  capability === 'MINUTES'
                    ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/30'
                    : 'border-border/70 hover:bg-muted/40'
                )}
              >
                <div className="flex items-center gap-2 font-bold text-foreground">
                  <FileCheck className="size-4 text-primary" />
                  <span>بررسی و تأیید صورت‌جلسات</span>
                </div>
                <span className="text-[10px] text-muted-foreground">
                  بررسی، اعلام نظر و تأیید مینوت رسمی
                </span>
              </button>
            </div>
          </div>

          {/* 2. Delegate User Dropdown */}
          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <label className="font-bold text-foreground block">
                فرد واجد صلاحیت (معاونت / مدیریت اجرائیه) <span className="text-destructive">*</span>
              </label>
              {loadingCandidates && (
                <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                  <RotateCw className="size-3 animate-spin" /> در حال بارگذاری افراد...
                </span>
              )}
            </div>
            <select
              required
              disabled={loadingCandidates || delegates.length === 0}
              value={delegateUserId}
              onChange={(e) => setDelegateUserId(e.target.value)}
              className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
            >
              <option value="">
                {delegates.length === 0
                  ? 'هیچ فرد واجد صلاحیتی در این اداره یافت نشد'
                  : 'انتخاب فرد واجد صلاحیت (معاون یا مدیر اجرائیه)...'}
              </option>
              {delegates.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.employee?.full_name || d.name} — {d.role_title || 'کادر اداری'}{d.email ? ` (${d.email})` : ''}
                </option>
              ))}
            </select>
            <p className="text-[10px] text-muted-foreground">
              فقط کادر دارای اهلیت سازمانی (معاونت اداره یا مدیریت اجرائیه) در این لیست مجاز می‌باشند.
            </p>
          </div>

          {/* 4. Expiry Date */}
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">
              تاریخ پایان اعتبار تفویض <span className="text-destructive">*</span>
            </label>
            <MultiCalendarDatePicker
              value={expiresAt}
              onChange={setExpiresAt}
              placeholder="انتخاب تاریخ انقضا..."
            />
          </div>

          {/* 5. Administrative Reason */}
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">
              دلیل و استناد اداری تفویض <span className="text-destructive">*</span>
            </label>
            <Textarea
              required
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="مثلاً: تفویض صلاحیت موقت به دلیل سفر کاری ریاست، به استناد مکتوب اداری شماره..."
              className="rounded-xl text-xs leading-relaxed"
            />
          </div>

          <DialogFooter className="gap-2 pt-2 sm:justify-start">
            <Button
              type="submit"
              disabled={busy || loadingCandidates}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 flex-1"
            >
              {busy ? (
                <RotateCw className="size-3.5 animate-spin" />
              ) : (
                <ShieldCheck className="size-3.5" />
              )}
              <span>ثبت و اعطای تفویض صلاحیت</span>
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={busy}
              onClick={() => onOpenChange(false)}
              className="h-9 text-xs font-semibold rounded-xl"
            >
              انصراف
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
