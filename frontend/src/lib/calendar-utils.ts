/**
 * Calendar Conversion and Formatting Utilities for AMMS
 * Supports Solar Hijri (هجری خورشیدی), Lunar Hijri (هجری قمری), and Gregorian (میلادی)
 */

export const AFGHAN_SOLAR_MONTHS = [
  'حمل',
  'ثور',
  'جوزا',
  'سرطان',
  'اسد',
  'سنبله',
  'میزان',
  'عقرب',
  'قوس',
  'جدی',
  'دلو',
  'حوت',
]

export const GREGORIAN_MONTHS = [
  'جنوری (January)',
  'فبروری (February)',
  'مارچ (March)',
  'اپریل (April)',
  'می (May)',
  'جون (June)',
  'جولای (July)',
  'اگست (August)',
  'سپتمبر (September)',
  'اکتوبر (October)',
  'نوامبر (November)',
  'دسمبر (December)',
]

export const ISLAMIC_MONTHS = [
  'محرم',
  'صفر',
  'ربیع‌الاول',
  'ربیع‌الثانی',
  'جمادی‌الاول',
  'جمادی‌الثانی',
  'رجب',
  'شعبان',
  'رمضان',
  'شوال',
  'ذی‌القعده',
  'ذی‌الحجه',
]

export const PERSIAN_WEEK_DAYS = [
  { key: 'sa', short: 'ش', full: 'شنبه' },
  { key: 'su', short: 'ی', full: 'یکشنبه' },
  { key: 'mo', short: 'د', full: 'دوشنبه' },
  { key: 'tu', short: 'س', full: 'سه‌شنبه' },
  { key: 'we', short: 'چ', full: 'چهارشنبه' },
  { key: 'th', short: 'پ', full: 'پنجشنبه' },
  { key: 'fr', short: 'ج', full: 'جمعه' },
]

/**
 * Converts Gregorian date (gy, gm, gd) to Solar Hijri (jy, jm, jd)
 */
export function gregorianToJalaali(gy: number, gm: number, gd: number): { jy: number; jm: number; jd: number } {
  const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
  let jy = gy <= 1600 ? 0 : 979
  gy -= gy <= 1600 ? 621 : 1600
  const gy2 = gm > 2 ? gy + 1 : gy
  let days =
    365 * gy +
    Math.floor((gy2 + 3) / 4) -
    Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) -
    80 +
    gd +
    g_d_m[gm - 1]
  jy += 33 * Math.floor(days / 12053)
  days %= 12053
  jy += 4 * Math.floor(days / 1461)
  days %= 1461
  jy += Math.floor((days - 1) / 365)
  if (days > 0) days = (days - 1) % 365
  const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30)
  const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30)
  return { jy, jm, jd }
}

/**
 * Converts Solar Hijri (jy, jm, jd) to Gregorian date (gy, gm, gd)
 */
export function jalaaliToGregorian(jy: number, jm: number, jd: number): { gy: number; gm: number; gd: number } {
  let gy = jy <= 979 ? 621 : 1600
  jy -= jy <= 979 ? 0 : 979
  let days =
    365 * jy +
    Math.floor(jy / 33) * 8 +
    Math.floor(((jy % 33) + 3) / 4) +
    78 +
    jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186)
  gy += 400 * Math.floor(days / 146097)
  days %= 146097
  if (days > 36524) {
    gy += 100 * Math.floor(--days / 36524)
    days %= 36524
    if (days >= 365) days++
  }
  gy += 4 * Math.floor(days / 1461)
  days %= 1461
  gy += Math.floor((days - 1) / 365)
  if (days > 0) days = (days - 1) % 365
  let gd = days + 1
  const sal_a = [
    0,
    31,
    (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28,
    31,
    30,
    31,
    30,
    31,
    31,
    30,
    31,
    30,
    31,
  ]
  let gm = 0
  for (gm = 0; gm < 13; gm++) {
    const v = sal_a[gm]
    if (gd <= v) break
    gd -= v
  }
  return { gy, gm, gd }
}

/**
 * Checks if a Solar Hijri year is a leap year
 */
export function isJalaaliLeapYear(jy: number): boolean {
  return [1, 5, 9, 13, 17, 22, 26, 30].includes(jy % 33)
}

/**
 * Returns number of days in a Solar Hijri month
 */
export function getJalaaliMonthDays(jy: number, jm: number): number {
  if (jm <= 6) return 31
  if (jm <= 11) return 30
  return isJalaaliLeapYear(jy) ? 30 : 29
}

/**
 * Format date to standard ISO (YYYY-MM-DD)
 */
export function toIsoDate(gy: number, gm: number, gd: number): string {
  const m = String(gm).padStart(2, '0')
  const d = String(gd).padStart(2, '0')
  return `${gy}-${m}-${d}`
}

/**
 * Parses YYYY-MM-DD string to Date object
 */
export function parseIsoDate(isoStr?: string | null): Date | null {
  if (!isoStr) return null
  const parts = isoStr.split('-').map(Number)
  if (parts.length !== 3 || parts.some(isNaN)) return null
  return new Date(parts[0], parts[1] - 1, parts[2])
}

/**
 * Format a Date or ISO string to Solar Hijri string (e.g. "۱۵ سنبله ۱۴۰۵")
 */
export function formatSolarDate(dateInput: Date | string | null | undefined): string {
  if (!dateInput) return ''
  const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
  if (isNaN(date.getTime())) return ''
  const { jy, jm, jd } = gregorianToJalaali(
    date.getFullYear(),
    date.getMonth() + 1,
    date.getDate()
  )
  const monthName = AFGHAN_SOLAR_MONTHS[jm - 1] || ''
  return `${jd} ${monthName} ${jy}`
}

/**
 * Format a Date or ISO string to Solar Hijri DateTime string (e.g. "۱۵ سنبله ۱۴۰۵، ساعت ۱۴:۳۰")
 */
export function formatSolarDateTime(dateInput: Date | string | null | undefined): string {
  if (!dateInput) return ''
  const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
  if (isNaN(date.getTime())) return ''
  const dateStr = formatSolarDate(date)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${dateStr}، ساعت ${hours}:${minutes}`
}

/**
 * Format a Date to Lunar Hijri (Islamic) string using Intl API (e.g. "۲۳ صفر ۱۴۴۸")
 */
export function formatLunarDate(dateInput: Date | string | null | undefined): string {
  if (!dateInput) return ''
  const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
  if (isNaN(date.getTime())) return ''
  try {
    const formatter = new Intl.DateTimeFormat('fa-AF-u-ca-islamic-umalqura', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
    return formatter.format(date).replace(/[ٔء]/g, '')
  } catch {
    try {
      const formatter = new Intl.DateTimeFormat('ar-SA-u-ca-islamic', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
      return formatter.format(date)
    } catch {
      return ''
    }
  }
}

/**
 * Format full Triple-Calendar equivalence summary
 */
export function getTripleDateSummary(isoStr?: string | null): {
  solar: string
  lunar: string
  gregorian: string
} | null {
  const d = parseIsoDate(isoStr)
  if (!d) return null
  return {
    solar: `${formatSolarDate(d)} هـ.ش`,
    // Intl's fa-AF Islamic formatter already includes the Hijri era suffix.
    lunar: formatLunarDate(d),
    gregorian: `${isoStr} M`,
  }
}
