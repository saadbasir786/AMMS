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
  has_active_delegation?: boolean
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
  const [activeDelegationUserId, setActiveDelegationUserId] = useState<number | null>(null)
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
      setActiveDelegationUserId(null)
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
      setActiveDelegationUserId(data?.active_delegation_user_id ?? null)
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

  const selectedDelegate = delegates.find((d) => String(d.id) === String(delegateUserId))
  const isSelectedDelegateActive = Boolean(selectedDelegate?.has_active_delegation)
  const activeMinutesHolder =
    capability === 'MINUTES' && activeDelegationUserId
      ? delegates.find((d) => d.id === activeDelegationUserId)
      : null

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

    if (isSelectedDelegateActive) {
      setError('این کاربر در حال حاضر دارای تفویض صلاحیت فعال برای این بخش می‌باشد و امکان تفویض مجدد وجود ندارد.')
      return
    }

    if (capability === 'MINUTES' && activeDelegationUserId) {
      setError('در حال حاضر یک تفویض فعال برای بررسی صورت‌جلسات در این اداره وجود دارد. ابتدا تفویض قبلی را لغو فرمایید.')
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
              disabled={loadingCandidates || delegates.length === 0 || (capability === 'MINUTES' && Boolean(activeDelegationUserId))}
              value={delegateUserId}
              onChange={(e) => setDelegateUserId(e.target.value)}
              className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
            >
              <option value="">
                {delegates.length === 0
                  ? 'هیچ فرد واجد صلاحیتی در این اداره یافت نشد'
                  : capability === 'MINUTES' && Boolean(activeDelegationUserId)
                  ? 'تفویض صورت‌جلسات در این اداره قبلاً اختصاص یافته است'
                  : 'انتخاب فرد واجد صلاحیت (معاون یا مدیر اجرائیه)...'}
              </option>
              {delegates.map((d) => (
                <option
                  key={d.id}
                  value={d.id}
                  disabled={d.has_active_delegation || (capability === 'MINUTES' && Boolean(activeDelegationUserId))}
                >
                  {d.employee?.full_name || d.name} — {d.role_title || 'کادر اداری'}
                  {d.email ? ` (${d.email})` : ''}
                  {d.has_active_delegation ? ' (دارای تفویض فعال)' : ''}
                </option>
              ))}
            </select>

            {capability === 'MINUTES' && activeMinutesHolder && (
              <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-[11px] flex items-start gap-2.5">
                <AlertCircle className="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                <div className="space-y-1">
                  <p className="font-bold">تفویض فعال در این اداره وجود دارد</p>
                  <p className="leading-relaxed text-amber-700 dark:text-amber-300/90 text-[10.5px]">
                    در حال حاضر صلاحیت بررسی صورت‌جلسات به <strong>{activeMinutesHolder.employee?.full_name || activeMinutesHolder.name} ({activeMinutesHolder.role_title || 'کادر اداری'})</strong> تفویض شده است. طبق مقررات سازمانی، در هر اداره حداکثر یک تفویض فعال مجاز است. در صورت نیاز به تغییر، ابتدا تفویض قبلی را لغو فرمایید.
                  </p>
                </div>
              </div>
            )}

            {isSelectedDelegateActive && (
              <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-[11px] flex items-center gap-2">
                <AlertCircle className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <span>این کاربر در حال حاضر دارای تفویض صلاحیت فعال برای این بخش می‌باشد و امکان تفویض مجدد وجود ندارد.</span>
              </div>
            )}

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
              disabled={
                busy ||
                loadingCandidates ||
                isSelectedDelegateActive ||
                (capability === 'MINUTES' && Boolean(activeDelegationUserId))
              }
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
