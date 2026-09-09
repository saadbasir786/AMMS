import React, { useState, useEffect, useMemo } from 'react'
import {
  Building2,
  Network,
  RotateCw,
  AlertCircle,
  CheckCircle2,
  Layers,
} from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api-client'
import { cn } from '@/lib/utils'
import type { OrganizationalUnit } from '../types'

type OrganizationalUnitDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  unit?: OrganizationalUnit | null
  defaultParentId?: number | null
  allUnits: OrganizationalUnit[]
  onSuccess: () => void
}

const UNIT_TYPE_OPTIONS: Array<{ value: string; label: string; description: string }> = [
  { value: 'GENERAL_PRESIDENCY', label: 'ریاست عمومی (مقام عالی اداره)', description: 'ریاست عمومی و مراجع عالی اداره' },
  { value: 'DEPUTY_PRESIDENCY', label: 'معاونیت ریاست عمومی', description: 'معاونیت‌های عالی مقام اداره' },
  { value: 'GENERAL_DIRECTORATE', label: 'ریاست عمومی (بخشی)', description: 'ریاست‌های عمومی بخشی و سکتوری' },
  { value: 'DIRECTORATE', label: 'ریاست', description: 'ریاست‌های اصلی و اداری' },
  { value: 'ATTACHED_AGENCY', label: 'اداره تابعه', description: 'ادارات و واحدهای تابعه' },
  { value: 'DEPUTY_DIRECTORATE', label: 'معاونیت ریاست', description: 'معاونیت‌های تخصصی ریاست‌ها' },
  { value: 'AMIRIATE', label: 'آمریت', description: 'آمریت‌های زیرمجموعه ریاست‌ها' },
  { value: 'GENERAL_MANAGEMENT', label: 'مدیریت عمومی', description: 'مدیریت‌های عمومی هماهنگی و تخصصی' },
  { value: 'MANAGEMENT', label: 'مدیریت', description: 'مدیریت‌های اجرایی و دفتری' },
  { value: 'SECTION', label: 'شعبه', description: 'شعبات تخصصی و اداری' },
  { value: 'OTHER', label: 'سایر بخش‌ها', description: 'واحدهای پشتیبانی، خدمات و سایر موارد' },
]

export function OrganizationalUnitDialog({
  open,
  onOpenChange,
  unit,
  defaultParentId,
  allUnits,
  onSuccess,
}: OrganizationalUnitDialogProps) {
  const isEditing = Boolean(unit)

  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [unitType, setUnitType] = useState('DIRECTORATE')
  const [parentId, setParentId] = useState<string>('')
  const [status, setStatus] = useState<'ACTIVE' | 'INACTIVE'>('ACTIVE')

  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      if (unit) {
        setName(unit.name || '')
        setCode(unit.code || '')
        setUnitType(unit.unit_type || 'DIRECTORATE')
        setParentId(unit.parent_id ? String(unit.parent_id) : '')
        setStatus((unit.status as 'ACTIVE' | 'INACTIVE') || 'ACTIVE')
      } else {
        setName('')
        setCode('')
        setUnitType(defaultParentId ? 'AMIRIATE' : 'DIRECTORATE')
        setParentId(defaultParentId ? String(defaultParentId) : '')
        setStatus('ACTIVE')
      }
      setError(null)
      setSuccessMessage(null)
    }
  }, [open, unit, defaultParentId])

  // Filter available candidates for parent unit, strictly excluding this unit and all of its descendants
  const parentCandidates = useMemo(() => {
    if (!unit) return allUnits

    const descendantIds = new Set<number>()
    const collectDescendants = (currentId: number) => {
      descendantIds.add(currentId)
      for (const u of allUnits) {
        if (u.parent_id === currentId && !descendantIds.has(u.id)) {
          collectDescendants(u.id)
        }
      }
    }
    collectDescendants(unit.id)

    return allUnits.filter((u) => !descendantIds.has(u.id))
  }, [allUnits, unit])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!name.trim()) {
      setError('لطفاً عنوان واحد یا ریاست را وارد نمایید.')
      return
    }

    if (!code.trim()) {
      setError('لطفاً کد اختصاری اداری را وارد نمایید.')
      return
    }

    setBusy(true)
    setError(null)
    setSuccessMessage(null)

    try {
      const payload: Record<string, any> = {
        name: name.trim(),
        code: code.trim().toUpperCase(),
        unit_type: unitType,
        parent_id: parentId ? Number(parentId) : null,
        status,
      }

      let res
      if (isEditing && unit) {
        res = await api.put(`/admin/organizational-units/${unit.id}`, payload)
      } else {
        res = await api.post('/admin/organizational-units', payload)
      }

      const msg =
        res?.data?.message ||
        (isEditing
          ? 'مشخصات واحد تشکیلاتی با موفقیت ویرایش شد.'
          : 'واحد تشکیلاتی جدید با موفقیت در چارت ثبت شد.')

      setSuccessMessage(msg)
      setTimeout(() => {
        onOpenChange(false)
        onSuccess()
      }, 700)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'ثبت اطلاعات واحد تشکیلاتی با خطا مواجه شد. لطفاً مقادیر را بررسی فرمایید.'
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
              <Building2 className="size-4" />
            </div>
            <span>
              {isEditing ? 'ویرایش واحد / ریاست سازمانی' : 'ثبت ریاست یا واحد سازمانی جدید'}
            </span>
          </DialogTitle>
          <DialogDescription className="text-xs text-muted-foreground">
            {isEditing
              ? `اصلاح مشخصات، کد و جایگاه سلسله‌مراتبی واحد «${unit?.name}»`
              : 'افزودن ریاست جدید یا دیپارتمنت تابعه در ساختار اداری سازمان'}
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
          {/* 1. Name & Code */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div className="sm:col-span-2 space-y-1.5">
              <label className="font-bold text-foreground block">
                عنوان رسمی واحد سازمانی <span className="text-destructive">*</span>
              </label>
              <Input
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="مثلاً: ریاست تکنالوژی معلوماتی"
                className="h-9.5 text-xs rounded-xl"
              />
            </div>

            <div className="space-y-1.5">
              <label className="font-bold text-foreground block">
                کد اداری <span className="text-destructive">*</span>
              </label>
              <Input
                required
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="مثلاً: TECH"
                className="h-9.5 text-xs rounded-xl font-mono uppercase"
              />
            </div>
          </div>

          {/* 2. Unit Type */}
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">
              سطح و نوع واحد اداری <span className="text-destructive">*</span>
            </label>
            <select
              value={unitType}
              onChange={(e) => setUnitType(e.target.value)}
              className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
            >
              {UNIT_TYPE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label} — {opt.description}
                </option>
              ))}
            </select>
          </div>

          {/* 3. Parent Unit in Hierarchy */}
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">
              واحد مافوق در چارت تشکیلاتی
            </label>
            <select
              value={parentId}
              onChange={(e) => setParentId(e.target.value)}
              className="h-9.5 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
            >
              <option value="">— بالاترین سطح سازمان (رأس هرم چارت / فاقد مافوق) —</option>
              {parentCandidates.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.code || 'بدون کد'}) {p.unit_type ? `[${p.unit_type}]` : ''}
                </option>
              ))}
            </select>
            <p className="text-[11px] text-muted-foreground leading-relaxed">
              {parentId
                ? 'این واحد اداری به عنوان زیرمجموعهٔ مستقیم واحد انتخابی فوق ثبت می‌گردد.'
                : 'در صورت عدم انتخاب مافوق، این واحد در بالاترین سطح چارت اداری (فاقد مافوق) قرار می‌گیرد.'}
            </p>
          </div>

          {/* 4. Status */}
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">
              وضعیت اداری واحد
            </label>
            <div className="grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setStatus('ACTIVE')}
                className={cn(
                  'p-2.5 rounded-xl border text-center font-bold text-xs transition-all',
                  status === 'ACTIVE'
                    ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shadow-xs'
                    : 'border-border/70 hover:bg-muted/40 text-muted-foreground'
                )}
              >
                فعال و جاری
              </button>

              <button
                type="button"
                onClick={() => setStatus('INACTIVE')}
                className={cn(
                  'p-2.5 rounded-xl border text-center font-bold text-xs transition-all',
                  status === 'INACTIVE'
                    ? 'border-destructive bg-destructive/10 text-destructive shadow-xs'
                    : 'border-border/70 hover:bg-muted/40 text-muted-foreground'
                )}
              >
                غیرفعال / منحل‌شده
              </button>
            </div>
          </div>

          <DialogFooter className="gap-2 pt-2 sm:justify-start">
            <Button
              type="submit"
              disabled={busy}
              className="h-9 text-xs font-semibold rounded-xl gap-1.5 flex-1"
            >
              {busy ? (
                <RotateCw className="size-3.5 animate-spin" />
              ) : (
                <Building2 className="size-3.5" />
              )}
              <span>{isEditing ? 'ذخیره تغییرات واحد' : 'ثبت واحد در چارت'}</span>
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
