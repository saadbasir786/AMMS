import React, { useRef, useState } from 'react'
import { FileText, Download, Paperclip, Upload, Loader2 } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import type { ActionItemEvidence } from '../types'

type ActionItemEvidenceCardProps = {
  evidence?: ActionItemEvidence[]
  canUpload?: boolean
  busy?: boolean
  onUpload?: (file: File) => Promise<void>
}

export function ActionItemEvidenceCard({
  evidence = [],
  canUpload = false,
  busy = false,
  onUpload,
}: ActionItemEvidenceCardProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file || !onUpload) return
    setError(null)
    try {
      await onUpload(file)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'بارگذاری سند با خطا مواجه شد.')
    }
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Paperclip className="size-4" />
          </div>
          <div className="min-w-0">
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              مستندات و شواهد اجرایی
            </h3>
            <p className="text-[11px] text-muted-foreground">
              فایل‌ها و مدارک ثبت‌شده جهت اثبات انجام کار
            </p>
          </div>
          {canUpload && onUpload && (
            <>
              <input ref={inputRef} type="file" className="hidden" onChange={handleFileChange} disabled={busy} />
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => inputRef.current?.click()}
                disabled={busy}
                className="h-8 shrink-0 rounded-lg text-[11px] gap-1.5"
              >
                {busy ? <Loader2 className="size-3 animate-spin" /> : <Upload className="size-3" />}
                <span>{busy ? 'در حال بارگذاری' : 'بارگذاری سند'}</span>
              </Button>
            </>
          )}
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5">
        {error && <p className="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-[11px] text-rose-700">{error}</p>}
        {evidence.length === 0 ? (
          <p className="text-xs text-muted-foreground text-center py-3">
            هنوز مدرک یا سندی برای این اقدام بارگذاری نشده است.
          </p>
        ) : (
          <div className="space-y-2">
            {evidence.map((e) => (
              <a
                key={e.id}
                href={e.download_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/50 hover:bg-slate-100/60 dark:hover:bg-slate-800/40 transition-colors group"
              >
                <div className="flex items-center gap-2 min-w-0">
                  <FileText className="size-4 text-primary shrink-0" />
                  <div className="min-w-0">
                    <strong className="text-xs font-bold text-foreground block truncate group-hover:text-primary transition-colors">
                      {e.original_filename}
                    </strong>
                    <span className="text-[10px] text-muted-foreground block font-mono">
                      {e.mime_type} • {e.file_size} بایت
                    </span>
                  </div>
                </div>

                <Download className="size-4 text-muted-foreground group-hover:text-primary transition-colors shrink-0" />
              </a>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
