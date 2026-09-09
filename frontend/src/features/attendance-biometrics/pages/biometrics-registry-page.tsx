import React, { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  Fingerprint,
  RotateCw,
  AlertCircle,
  ShieldCheck,
  Eye,
  Search,
  Users
} from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { fetchAllPages } from '@/lib/fetch-all-pages'

export function BiometricsRegistryPage() {
  const [employees, setEmployees] = useState<any[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const employees = await fetchAllPages<any>('/employees')
      setEmployees(employees)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات کارمندان با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const filteredEmployees = employees.filter((e) => {
    const q = search.toLowerCase()
    return (
      e.full_name?.toLowerCase().includes(q) ||
      e.employee_number?.toLowerCase().includes(q) ||
      e.unit?.name?.toLowerCase().includes(q)
    )
  })

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="رجیستری و ثبت امن متادیتای بایومتریک"
        description="مدیریت وضعیت ثبت اثر انگشت کارمندان؛ قالب خام اثر انگشت به مرورگر وب ارسال نمی‌شود."
        action={
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-xs px-2.5 py-1 text-emerald-700 dark:text-emerald-300 border-emerald-300">
              <ShieldCheck className="size-3.5 ml-1" />
              <span>پروتکل امنیتی Metadata-Only</span>
            </Badge>
          </div>
        }
      />

      {/* Error Alert */}
      {error && (
        <Alert variant="destructive" className="rounded-2xl p-4">
          <AlertCircle className="size-4" />
          <AlertDescription className="text-xs flex items-center justify-between gap-2">
            <span>{error}</span>
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              className="h-8 text-xs font-semibold gap-1.5 shrink-0"
            >
              <RotateCw className="size-3" />
              <span>تلاش مجدد</span>
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* Search Bar */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-sm">
          <Search className="size-4 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="جستجو بر اساس نام، شماره کارمندی یا ریاست..."
            className="h-9.5 pr-9 text-xs rounded-xl"
          />
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/3" />
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="h-12 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Employees Table */}
      {!loading && (
        <div
          className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs"
          dir="rtl"
        >
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-right border-collapse">
              <thead>
                <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
                  <th className="py-3.5 px-4 font-bold">شماره کارمندی</th>
                  <th className="py-3.5 px-4 font-bold">نام و نام خانوادگی</th>
                  <th className="py-3.5 px-4 font-bold">ریاست / مدیریت مربوطه</th>
                  <th className="py-3.5 px-4 font-bold text-center">مشاهده وضعیت بایومتریک</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {filteredEmployees.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="py-8 text-center text-xs text-muted-foreground">
                      کارمندی با این مشخصات یافت نشد.
                    </td>
                  </tr>
                ) : (
                  filteredEmployees.map((emp) => (
                    <tr
                      key={emp.id}
                      className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
                    >
                      {/* Employee Number */}
                      <td className="py-3.5 px-4 font-mono font-bold text-foreground">
                        <Badge variant="outline" className="font-mono text-[11px]">
                          {emp.employee_number || emp.id}
                        </Badge>
                      </td>

                      {/* Name */}
                      <td className="py-3.5 px-4">
                        <div className="flex items-center gap-2.5">
                          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] shrink-0">
                            {emp.full_name?.charAt(0) || 'ک'}
                          </div>
                          <strong className="text-xs font-semibold text-foreground">
                            {emp.full_name}
                          </strong>
                        </div>
                      </td>

                      {/* Unit */}
                      <td className="py-3.5 px-4 text-muted-foreground">
                        {emp.unit?.name || '—'}
                      </td>

                      {/* Action */}
                      <td className="py-3.5 px-4 text-center">
                        <Button
                          asChild
                          variant="outline"
                          size="sm"
                          className="h-8 text-xs font-semibold rounded-xl gap-1 text-primary hover:bg-primary/5"
                        >
                          <Link to={`/employees/${emp.id}/biometrics`}>
                            <Fingerprint className="size-3.5" />
                            <span>مشاهده وضعیت بایومتریک</span>
                          </Link>
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Security Info Card */}
      <Alert className="rounded-2xl p-4 bg-slate-50/50 dark:bg-slate-900/30 border-border/70">
        <ShieldCheck className="size-4 text-primary" />
        <AlertDescription className="text-xs text-muted-foreground leading-relaxed">
          در سیستم تحت وب AMMS، به منظور حفظ حریم خصوصی و امنیت سایبری، صرفاً وضعیت ثبت و متادیتای آماری نسخه‌ها نمایش داده می‌شود. تطبیق و اسکن اثر انگشت منحصراً درون سخت‌افزار ترمینال‌های تاییدشده انجام می‌پذیرد.
        </AlertDescription>
      </Alert>
    </div>
  )
}
