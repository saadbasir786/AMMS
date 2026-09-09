import React, { useEffect, useState, useCallback } from 'react'
import { Plus, Monitor, KeyRound, RotateCw, AlertCircle, ShieldCheck } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { api, apiData } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'
import { TerminalsTable } from '../components/terminals-table'
import { TerminalFormDialog } from '../components/terminal-form-dialog'
import type { Terminal } from '../types'

export function TerminalsRegistryPage() {
  const [terminals, setTerminals] = useState<Terminal[]>([])
  const [organizations, setOrganizations] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Dialog & Credential state
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [issuedCredential, setIssuedCredential] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [termRes, orgRes] = await Promise.all([
        fetchAllPages<Terminal>('/terminals'),
        fetchAllPages<any>('/admin/organizations').catch(() => []),
      ])
      setTerminals(termRes)
      setOrganizations(orgRes)
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          'بارگذاری اطلاعات ترمینال‌ها با خطا مواجه شد.'
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleCreateTerminal = async (formData: any) => {
    setBusy(true)
    setError(null)
    try {
      await api.post('/terminals', formData)
      setShowCreateDialog(false)
      await loadData()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'ثبت ترمینال انجام نشد.')
    } finally {
      setBusy(false)
    }
  }

  const handleIssueCredential = async (terminal: Terminal) => {
    setBusy(true)
    setError(null)
    setIssuedCredential(null)
    try {
      const res = await api.post(`/terminals/${terminal.id}/credentials`)
      setIssuedCredential(apiData(res)?.credential?.token || null)
      await loadData()
    } catch (err: any) {
      if (err.response?.data?.code === 'TERMINAL_CREDENTIAL_ALREADY_ACTIVE') {
        try {
          const rotRes = await api.post(`/terminals/${terminal.id}/credentials/rotate`)
          setIssuedCredential(apiData(rotRes)?.credential?.token || null)
          await loadData()
        } catch (rotErr: any) {
          setError(rotErr?.response?.data?.message || 'تعویض اعتبارنامه انجام نشد.')
        }
      } else {
        setError(err?.response?.data?.message || 'صدور اعتبارنامه انجام نشد.')
      }
    } finally {
      setBusy(false)
    }
  }

  const activeCount = terminals.filter((t) => t.status === 'ACTIVE').length
  const maintenanceCount = terminals.filter((t) => t.status === 'MAINTENANCE').length

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <PageHeader
        title="رجیستری و ثبت ترمینال‌های فیزیکی"
        description="مدیریت دستگاه‌های فیزیکی مجاز و احراز هویت اختصاصی سخت‌افزارهای بایومتریک"
        action={
          <Button
            onClick={() => setShowCreateDialog(true)}
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Plus className="size-4" />
            <span>ثبت ترمینال جدید</span>
          </Button>
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

      {/* Single-use Credential Alert */}
      {issuedCredential && (
        <Alert className="rounded-2xl p-4 bg-indigo-50/50 dark:bg-indigo-950/30 border-indigo-200 dark:border-indigo-900">
          <KeyRound className="size-4 text-indigo-600" />
          <AlertDescription className="text-xs space-y-2">
            <strong className="block font-bold text-foreground">
              اعتبارنامه امن جدید صادر شد (فقط یک‌بار نمایش داده می‌شود):
            </strong>
            <code
              dir="ltr"
              className="block overflow-x-auto rounded-xl border bg-card p-3 font-mono text-xs font-bold text-primary select-all"
            >
              {issuedCredential}
            </code>
            <Button
              size="sm"
              variant="outline"
              onClick={() => setIssuedCredential(null)}
              className="h-8 text-xs font-semibold"
            >
              بستن و تأیید دریافت
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
            <Monitor className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">کل دستگاه‌های ثبت‌شده</span>
            <strong className="text-base font-bold text-foreground font-mono">{terminals.length}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0">
            <ShieldCheck className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">دستگاه‌های فعال</span>
            <strong className="text-base font-bold text-foreground font-mono text-emerald-600">
              {activeCount}
            </strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs shrink-0">
            <RotateCw className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">در حال نگهداری</span>
            <strong className="text-base font-bold text-foreground font-mono">{maintenanceCount}</strong>
          </div>
        </div>

        <div className="p-3.5 rounded-2xl bg-card border border-border/70 shadow-xs flex items-center gap-3">
          <div className="size-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
            <KeyRound className="size-4" />
          </div>
          <div>
            <span className="text-[10px] text-muted-foreground block font-medium">سازمان‌های متصل</span>
            <strong className="text-base font-bold text-foreground font-mono">{organizations.length}</strong>
          </div>
        </div>
      </div>

      {/* Loading Skeleton */}
      {loading && (
        <div className="rounded-2xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
          <div className="h-8 bg-muted/60 rounded-xl animate-pulse w-1/3" />
          <div className="space-y-3">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-12 bg-muted/40 rounded-xl animate-pulse" />
            ))}
          </div>
        </div>
      )}

      {/* Terminals Table */}
      {!loading && (
        <TerminalsTable
          terminals={terminals}
          busy={busy}
          onIssueCredential={handleIssueCredential}
        />
      )}

      {/* Create Dialog */}
      <TerminalFormDialog
        open={showCreateDialog}
        onOpenChange={setShowCreateDialog}
        organizations={organizations}
        busy={busy}
        onSubmit={handleCreateTerminal}
      />
    </div>
  )
}
