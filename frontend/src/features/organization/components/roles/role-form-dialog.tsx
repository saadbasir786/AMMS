import React from 'react'
import {
  KeyRound,
  RefreshCw,
  Info,
  Shield,
  Tag,
  AlignLeft,
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
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import type { AmmsRoleDefinition } from '../../types'

type RoleFormState = {
  name: string
  label: string
  description: string
}

type RoleFormDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  selectedRole: AmmsRoleDefinition | null
  form: RoleFormState
  setForm: React.Dispatch<React.SetStateAction<RoleFormState>>
  onSave: () => Promise<void>
  saving: boolean
  error: string | null
}

export function RoleFormDialog({
  open,
  onOpenChange,
  selectedRole,
  form,
  setForm,
  onSave,
  saving,
  error,
}: RoleFormDialogProps) {
  const isEditing = !!selectedRole

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    void onSave()
  }

  return (
    <Dialog open={open} onOpenChange={(val) => { if (!saving) onOpenChange(val) }}>
      <DialogContent className="rounded-2xl sm:max-w-lg border-border/80 shadow-lg" dir="rtl">
        <DialogHeader className="space-y-2 text-right">
          <div className="flex items-center gap-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
              <KeyRound className="size-5" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-foreground">
                {isEditing ? 'ویرایش مشخصات نقش سفارشی' : 'تعریف نقش سفارشی جدید'}
              </DialogTitle>
              <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                {isEditing
                  ? `اصلاح عنوان و توضیحات نقش «${selectedRole.label}». دسترسی‌ها از پنل جزئیات قابل تغییر است.`
                  : 'مشخصات اولیه نقش را ثبت کنید؛ سپس دسترسی‌های مجاز آن را تعیین نمایید.'}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        {error && (
          <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive leading-relaxed">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4 pt-2 text-xs">
          {/* Technical Name */}
          <div className="space-y-1.5">
            <label htmlFor="role-name" className="block font-bold text-foreground">
              نام فنی نقش (شناسه سیستمی) <span className="text-destructive">*</span>
            </label>
            <div className="relative">
              <Input
                id="role-name"
                required
                value={form.name}
                disabled={isEditing || saving}
                onChange={(e) =>
                  setForm((curr) => ({ ...curr, name: e.target.value.toUpperCase().replace(/\s+/g, '_') }))
                }
                placeholder="REPORT_COORDINATOR"
                className="h-10 rounded-xl font-mono text-xs uppercase pl-3 pr-9 tracking-wider"
                dir="ltr"
              />
              <Tag className="absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            </div>
            <p className="text-[10px] text-muted-foreground leading-relaxed flex items-center gap-1 mt-1">
              <Info className="size-3 shrink-0" />
              <span>فقط حروف بزرگ انگلیسی، عدد و زیرخط (_). پس از ایجاد نقش غیرقابل تغییر است.</span>
            </p>
          </div>

          {/* Persian Label */}
          <div className="space-y-1.5">
            <label htmlFor="role-label" className="block font-bold text-foreground">
              عنوان نمایشی (فارسی اداری) <span className="text-destructive">*</span>
            </label>
            <div className="relative">
              <Input
                id="role-label"
                required
                value={form.label}
                disabled={saving}
                onChange={(e) => setForm((curr) => ({ ...curr, label: e.target.value }))}
                placeholder="هماهنگ‌کننده امور گزارش‌ها"
                className="h-10 rounded-xl text-xs pl-3 pr-9"
              />
              <Shield className="absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            </div>
            <p className="text-[10px] text-muted-foreground leading-relaxed">
              عنوانی که در کارتابل، تخصیص نقش به کارمندان و گزارش‌ها نمایش داده می‌شود.
            </p>
          </div>

          {/* Description */}
          <div className="space-y-1.5">
            <label htmlFor="role-description" className="block font-bold text-foreground">
              توضیحات و دامنه اختیارات
            </label>
            <div className="relative">
              <Textarea
                id="role-description"
                value={form.description}
                disabled={saving}
                onChange={(e) => setForm((curr) => ({ ...curr, description: e.target.value }))}
                placeholder="هدف از تعریف این نقش، حدود مسئولیت‌ها و حوزه اداری مرتبط را به اختصار بیان کنید."
                className="min-h-24 resize-none rounded-xl text-xs p-3 leading-relaxed"
              />
            </div>
          </div>

          {/* Live Preview Card */}
          <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 p-3 space-y-1.5">
            <span className="text-[10px] font-semibold text-muted-foreground block">پیش‌نمایش کارت نقش:</span>
            <div className="flex items-center gap-2">
              <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <KeyRound className="size-3.5" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5">
                  <span className="font-bold text-xs text-foreground truncate">
                    {form.label.trim() || 'عنوان نقش…'}
                  </span>
                  <Badge variant="outline" className="h-4 px-1 text-[9px] text-primary border-primary/30">
                    سفارشی
                  </Badge>
                </div>
                <span className="block font-mono text-[9px] text-muted-foreground truncate" dir="ltr">
                  {form.name.trim() || 'ROLE_CODE'}
                </span>
              </div>
            </div>
          </div>

          <DialogFooter className="gap-2 pt-2 sm:justify-start">
            <Button
              type="submit"
              disabled={saving || !form.name.trim() || !form.label.trim()}
              className="h-9.5 flex-1 gap-1.5 rounded-xl text-xs font-semibold shadow-xs"
            >
              {saving ? (
                <RefreshCw className="size-3.5 animate-spin" />
              ) : (
                <KeyRound className="size-3.5" />
              )}
              <span>{saving ? 'در حال ثبت اطلاعات…' : isEditing ? 'ذخیره تغییرات' : 'ایجاد نقش سفارشی'}</span>
            </Button>

            <Button
              type="button"
              variant="outline"
              disabled={saving}
              onClick={() => onOpenChange(false)}
              className="h-9.5 rounded-xl text-xs font-semibold"
            >
              انصراف
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
