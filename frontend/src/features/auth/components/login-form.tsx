import React, { useState } from 'react'
import {
  ShieldCheck,
  ArrowRight,
  Eye,
  EyeOff,
  Lock,
  Mail,
  CalendarCheck,
  AlertCircle,
  Loader2
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

export type LoginFormProps = {
  onLogin: (email: string, password: string) => Promise<void>
}

export function LoginForm({ onLogin }: LoginFormProps) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!email.trim() || !password) {
      setError('لطفاً ایمیل و رمز عبور را وارد نمایید.')
      return
    }

    setIsSubmitting(true)
    setError('')

    try {
      await onLogin(email.trim(), password)
    } catch (err: any) {
      const serverMsg =
        err?.response?.data?.message ||
        err?.response?.data?.error?.message ||
        'ایمیل یا رمز عبور وارد شده نادرست است.'
      setError(serverMsg)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="relative z-10 w-full max-w-md">
      {/* Main Card */}
      <div className="rounded-2xl border border-border/70 bg-card/95 p-8 shadow-sm backdrop-blur-xl sm:p-10">
        {/* Header & Logo */}
        <div className="text-center space-y-3 mb-8">
          <div className="mb-2 inline-flex rounded-2xl bg-primary p-3.5 text-primary-foreground shadow-sm">
            <CalendarCheck className="size-8" />
          </div>
          <h1 className="text-2xl font-black tracking-tight text-foreground">
            سیستم مدیریت جلسات
          </h1>
          <p className="text-xs font-normal leading-relaxed text-muted-foreground sm:text-sm">
            جهت ورود به حساب کاربری، اطلاعات سازمانی خود را وارد نمایید
          </p>
        </div>

        {/* Error Message */}
        {error && (
          <div className="mb-6 flex animate-in items-center gap-2.5 rounded-2xl border border-destructive/20 bg-destructive/10 p-3.5 text-xs text-destructive fade-in slide-in-from-top-2">
            <AlertCircle className="size-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Email Field */}
          <div className="space-y-1.5 text-right">
            <label className="block text-xs font-semibold text-foreground">
              ایمیل سازمانی
            </label>
            <div className="relative">
              <Mail className="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                type="email"
                dir="ltr"
                placeholder="user@organization.gov"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={isSubmitting}
                className="h-11 rounded-xl border-border bg-background/80 pe-4 ps-10 font-mono text-xs text-foreground placeholder:text-muted-foreground focus-visible:border-primary focus-visible:ring-primary/20"
              />
            </div>
          </div>

          {/* Password Field */}
          <div className="space-y-1.5 text-right">
            <label className="block text-xs font-semibold text-foreground">
              رمز عبور
            </label>
            <div className="relative">
              <Lock className="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                type={showPassword ? 'text' : 'password'}
                dir="ltr"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={isSubmitting}
                className="h-11 rounded-xl border-border bg-background/80 pe-10 ps-10 font-mono text-xs text-foreground placeholder:text-muted-foreground focus-visible:border-primary focus-visible:ring-primary/20"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute end-3.5 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                aria-label={showPassword ? 'پنهان کردن رمز' : 'نمایش رمز'}
              >
                {showPassword ? (
                  <EyeOff className="size-4" />
                ) : (
                  <Eye className="size-4" />
                )}
              </button>
            </div>
          </div>

          {/* Submit Button */}
          <Button
            type="submit"
            disabled={isSubmitting}
            className="mt-4 h-11 w-full gap-2 rounded-xl bg-primary text-xs font-bold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="size-4 animate-spin" />
                <span>در حال بررسی اطلاعات...</span>
              </>
            ) : (
              <>
                <span>ورود به سیستم</span>
                <ArrowRight className="size-4 rotate-180" />
              </>
            )}
          </Button>
        </form>

        {/* Security Notice Footer */}
        <div className="mt-8 border-t border-border/70 pt-6 text-center">
          <div className="inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
            <ShieldCheck className="size-3.5 text-emerald-600 dark:text-emerald-400" />
            <span>اتصال امن و رمزنگاری‌شده سازمانی</span>
          </div>
        </div>
      </div>
    </div>
  )
}
