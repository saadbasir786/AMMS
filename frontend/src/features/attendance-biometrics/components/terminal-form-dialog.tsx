import React, { useState } from 'react'
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

type TerminalFormDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  organizations: any[]
  busy: boolean
  onSubmit: (formData: any) => Promise<void>
}

export function TerminalFormDialog({
  open,
  onOpenChange,
  organizations,
  busy,
  onSubmit,
}: TerminalFormDialogProps) {
  const [form, setForm] = useState({
    organization_id: '',
    code: '',
    name: '',
    serial_number: '',
    manufacturer: 'DERMALOG',
    model: 'SVT3000 SE',
    platform: 'Android',
    os_version: '7.1.2',
    app_version: '0.1.0-laravel',
    status: 'ACTIVE',
  })
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!form.organization_id) {
      setError('انتخاب سازمان الزامی است.')
      return
    }
    if (!form.code.trim()) {
      setError('درج کد دستگاه الزامی است.')
      return
    }
    if (!form.name.trim()) {
      setError('درج نام دستگاه الزامی است.')
      return
    }
    setError(null)
    await onSubmit({
      ...form,
      organization_id: Number(form.organization_id),
    })
    setForm({
      organization_id: '',
      code: '',
      name: '',
      serial_number: '',
      manufacturer: 'DERMALOG',
      model: 'SVT3000 SE',
      platform: 'Android',
      os_version: '7.1.2',
      app_version: '0.1.0-laravel',
      status: 'ACTIVE',
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent dir="rtl" className="rounded-2xl sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="text-sm sm:text-base font-bold">
            ثبت ترمینال فیزیکی جدید در سیستم
          </DialogTitle>
          <DialogDescription className="text-xs text-muted-foreground">
            دستگاه فیزیکی مجاز جهت اسکن و تطبیق اثر انگشت در جلسات رسمی
          </DialogDescription>
        </DialogHeader>

        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 py-2 text-xs">
          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">سازمان یا وزارت‌خانه</label>
            <select
              value={form.organization_id}
              onChange={(e) => setForm({ ...form, organization_id: e.target.value })}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
            >
              <option value="">انتخاب سازمان مربوطه...</option>
              {organizations.map((org) => (
                <option key={org.id} value={org.id}>
                  {org.name || org.short_name}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">کد یکتای دستگاه (Code)</label>
            <Input
              dir="ltr"
              value={form.code}
              onChange={(e) => setForm({ ...form, code: e.target.value })}
              placeholder="مثلاً: TRM-001"
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">نام یا عنوان ترمینال</label>
            <Input
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="مثلاً: دستگاه ورودی تالار کنفرانس اصلی"
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">شماره سریال سخت‌افزار</label>
            <Input
              dir="ltr"
              value={form.serial_number}
              onChange={(e) => setForm({ ...form, serial_number: e.target.value })}
              placeholder="Serial Number..."
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">شرکت سازنده</label>
            <Input
              value={form.manufacturer}
              onChange={(e) => setForm({ ...form, manufacturer: e.target.value })}
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">مدل سخت‌افزار</label>
            <Input
              value={form.model}
              onChange={(e) => setForm({ ...form, model: e.target.value })}
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">پلتفرم و سیستم‌عامل</label>
            <div className="grid grid-cols-2 gap-2">
              <Input
                value={form.platform}
                onChange={(e) => setForm({ ...form, platform: e.target.value })}
                placeholder="Android"
                className="h-9.5 text-xs rounded-xl"
              />
              <Input
                dir="ltr"
                value={form.os_version}
                onChange={(e) => setForm({ ...form, os_version: e.target.value })}
                placeholder="7.1.2"
                className="h-9.5 text-xs rounded-xl"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="font-bold text-foreground block">وضعیت دستگاه</label>
            <select
              value={form.status}
              onChange={(e) => setForm({ ...form, status: e.target.value as any })}
              className="w-full h-9.5 text-xs rounded-xl border border-border/70 bg-card px-3"
            >
              <option value="ACTIVE">فعال و آماده تخصیص</option>
              <option value="INACTIVE">غیرفعال موقت</option>
              <option value="MAINTENANCE">در حال تعمیر و نگهداری</option>
            </select>
          </div>
        </div>

        <DialogFooter className="gap-2 sm:justify-start pt-2 border-t border-border/50">
          <Button
            disabled={busy || !form.organization_id || !form.code || !form.name}
            onClick={handleSubmit}
            className="h-9 text-xs font-semibold rounded-xl bg-primary hover:bg-primary/90 text-white"
          >
            {busy ? 'در حال ثبت…' : 'ثبت قطعی ترمینال'}
          </Button>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            className="h-9 text-xs font-semibold rounded-xl"
          >
            انصراف
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
