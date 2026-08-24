import React from 'react'
import { FileText, Download, Paperclip } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import type { ActionItemEvidence } from '../types'

type ActionItemEvidenceCardProps = {
  evidence?: ActionItemEvidence[]
}

export function ActionItemEvidenceCard({
  evidence = [],
}: ActionItemEvidenceCardProps) {
  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center gap-2">
          <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Paperclip className="size-4" />
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-foreground">
              مستندات و شواهد اجرایی
            </h3>
            <p className="text-[11px] text-muted-foreground">
              فایل‌ها و مدارک ثبت‌شده جهت اثبات انجام کار
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5">
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
