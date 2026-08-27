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
    <div className="relative w-full max-w-md z-10">
      {/* Main Card */}
      <div className="rounded-3xl border border-white/10 bg-slate-900/80 backdrop-blur-2xl p-8 sm:p-10 shadow-2xl shadow-black/50">
        {/* Header & Logo */}
        <div className="text-center space-y-3 mb-8">
          <div className="inline-flex p-3.5 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 shadow-lg shadow-blue-500/20 text-white mb-2">
            <CalendarCheck className="size-8" />
          </div>
          <h1 className="text-2xl font-black tracking-tight text-white">
            سیستم مدیریت جلسات
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 font-normal leading-relaxed">
            جهت ورود به حساب کاربری، اطلاعات سازمانی خود را وارد نمایید
          </p>
        </div>

        {/* Error Message */}
        {error && (
          <div className="mb-6 p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center gap-2.5 animate-in fade-in slide-in-from-top-2">
            <AlertCircle className="size-4 shrink-0 text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Email Field */}
          <div className="space-y-1.5 text-right">
            <label className="text-xs font-semibold text-slate-300 block">
              ایمیل سازمانی
            </label>
            <div className="relative">
              <Mail className="size-4 text-slate-400 absolute start-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                type="email"
                dir="ltr"
                placeholder="user@organization.gov"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={isSubmitting}
                className="h-11 ps-10 pe-4 text-xs font-mono bg-white/5 border-white/10 text-white placeholder:text-slate-500 focus-visible:border-blue-500 focus-visible:ring-blue-500/20 rounded-xl"
              />
            </div>
          </div>

          {/* Password Field */}
          <div className="space-y-1.5 text-right">
            <label className="text-xs font-semibold text-slate-300 block">
              رمز عبور
            </label>
            <div className="relative">
              <Lock className="size-4 text-slate-400 absolute start-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                type={showPassword ? 'text' : 'password'}
                dir="ltr"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={isSubmitting}
                className="h-11 ps-10 pe-10 text-xs font-mono bg-white/5 border-white/10 text-white placeholder:text-slate-500 focus-visible:border-blue-500 focus-visible:ring-blue-500/20 rounded-xl"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute end-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors"
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
            className="w-full h-11 text-xs font-bold gap-2 mt-4 text-white !text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 shadow-lg shadow-blue-600/25 rounded-xl transition-all"
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
        <div className="mt-8 pt-6 border-t border-white/10 text-center">
          <div className="inline-flex items-center gap-1.5 text-[11px] text-slate-400 font-medium">
            <ShieldCheck className="size-3.5 text-emerald-400" />
            <span>اتصال امن و رمزنگاری‌شده سازمانی</span>
          </div>
        </div>
      </div>
    </div>
  )
}
