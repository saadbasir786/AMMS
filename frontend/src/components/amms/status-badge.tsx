import React from 'react'
import { cn } from '@/lib/utils'

const STATUS_MAP: Record<string, { label: string; className: string }> = {
  // Positive / Final
  APPROVED: {
    label: 'تأییدشده',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },
  COMPLETED: {
    label: 'تکمیل‌شده',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },
  FINALIZED: {
    label: 'نهایی‌شده',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },
  ACTIVE: {
    label: 'فعال',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },
  READY_TO_FINALIZE: {
    label: 'آماده نهایی‌سازی',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },
  READY_TO_APPLY: {
    label: 'آماده اجرا',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },
  SCHEDULE_CONFIRMED: {
    label: 'زمان‌بندی مصوب',
    className: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
  },

  // Action Required / Returned
  RETURNED_FOR_CORRECTION: {
    label: 'برگشت جهت اصلاح',
    className: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
  },
  RETURNED_BY_TARGET: {
    label: 'برگشت جهت اصلاح',
    className: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
  },
  PENDING_REQUESTER_APPROVAL: {
    label: 'در انتظار تأیید',
    className: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
  },
  PENDING_CONFIRMATION: {
    label: 'در انتظار تأیید نهایی',
    className: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
  },
  STALE: {
    label: 'نیازمند همگام‌سازی',
    className: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
  },
  BLOCKED: {
    label: 'مسدودشده',
    className: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
  },

  // Negative / Destructive
  REJECTED: {
    label: 'رد شده',
    className: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-900',
  },
  CANCELLED: {
    label: 'لغوشده',
    className: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-900',
  },
  REVOKED: {
    label: 'لغوشده',
    className: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-900',
  },
  POSTPONED: {
    label: 'به‌تعویق‌افتاده',
    className: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-900',
  },

  // In Progress
  UNDER_REVIEW: {
    label: 'در حال بررسی',
    className: 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-900',
  },
  UNDER_TARGET_REVIEW: {
    label: 'در حال بررسی ریاست مقصد',
    className: 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-900',
  },
  SCHEDULING: {
    label: 'در حال زمان‌بندی',
    className: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-900',
  },
  SCHEDULED: {
    label: 'زمان‌بندی‌شده',
    className: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-900',
  },
  IN_PROGRESS: {
    label: 'در دست اقدام',
    className: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-900',
  },
  SUBMITTED_TO_TARGET: {
    label: 'ارسال‌شده به مقصد',
    className: 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-900',
  },

  // Draft / Muted
  DRAFT: {
    label: 'پیش‌نویس',
    className: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
  },
  PENDING: {
    label: 'در انتظار',
    className: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
  },
}

type StatusBadgeProps = {
  status?: string | null
  className?: string
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  if (!status) return null

  const config = STATUS_MAP[status] || {
    label: status,
    className: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200',
  }

  return (
    <span
      className={cn(
        'inline-flex items-center px-2.5 py-0.5 rounded-lg text-[11px] font-bold border whitespace-nowrap shadow-2xs',
        config.className,
        className
      )}
    >
      {config.label}
    </span>
  )
}
