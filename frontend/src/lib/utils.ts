import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatPersianDate(date: Date | string = new Date()): string {
  try {
    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''
    const formatter = new Intl.DateTimeFormat('fa-AF-u-ca-persian', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
    const parts = formatter.formatToParts(d)
    const weekday = parts.find((p) => p.type === 'weekday')?.value || ''
    const day = parts.find((p) => p.type === 'day')?.value || ''
    const month = (parts.find((p) => p.type === 'month')?.value || '').replace(/[ٔء]/g, '')
    const year = parts.find((p) => p.type === 'year')?.value || ''

    if (weekday) {
      return `${weekday}، ${day} ${month} ${year}`
    }
    return `${day} ${month} ${year}`
  } catch {
    return String(date)
  }
}
