export const statusLabels: Record<string, string> = {
  SCHEDULED: 'زمان‌بندی‌شده',
  POSTPONED: 'به تعویق افتاده',
  CANCELLED: 'لغو شده',
  IN_PROGRESS: 'در حال برگزاری',
  HELD: 'پایان‌یافته',
  COMPLETED: 'تکمیل‌شده',
  DRAFT: 'پیش‌نویس',
  PENDING_INTERNAL_APPROVAL: 'منتظر تأیید',
  RETURNED_FOR_REVISION: 'برگشت جهت اصلاح',
  REJECTED: 'رد شده',
  APPROVED: 'تأیید شده',
  SCHEDULING: 'در حال زمان‌بندی',
  SCHEDULE_CONFIRMED: 'زمان‌بندی نهایی',
  CONVERTED_TO_MEETING: 'تبدیل به جلسه',
  PENDING_REQUESTER_APPROVAL: 'منتظر تأیید ریاست درخواست‌کننده',
  RETURNED_TO_REQUESTER: 'برگشت به درخواست‌کننده',
  REQUESTER_APPROVED: 'تأیید ریاست درخواست‌کننده',
  REJECTED_INTERNAL: 'رد داخلی',
  SUBMITTED_TO_TARGET: 'ارسال‌شده به ریاست مقصد',
  UNDER_TARGET_REVIEW: 'در حال بررسی ریاست مقصد',
  RETURNED_BY_TARGET: 'برگشت از ریاست مقصد',
  RESUBMITTED_TO_TARGET: 'ارسال مجدد به ریاست مقصد',
  TARGET_APPROVED: 'تأیید ریاست مقصد',
  REJECTED_BY_TARGET: 'رد توسط ریاست مقصد',
  BILATERAL_APPROVED: 'تأیید دوطرفه',
  SUBMITTED_TO_TARGETS: 'ارسال‌شده به ریاست‌ها',
  UNDER_MULTI_TARGET_REVIEW: 'در حال بررسی ریاست‌ها',
  TARGET_ACTION_REQUIRED: 'نیازمند اصلاح از سوی ریاست‌ها',
  MULTILATERAL_APPROVED: 'تأیید چندجانبه',
  SUBMITTED_TO_PARTICIPATING_DIRECTORATES: 'ارسال‌شده به ریاست‌های اشتراک‌کننده',
  UNDER_PARTICIPATING_REVIEW: 'در حال بررسی ریاست‌های اشتراک‌کننده',
  DIRECTORATE_ACTION_REQUIRED: 'نیازمند اصلاح از سوی ریاست اشتراک‌کننده',
  DIRECTORATES_APPROVED: 'تأیید همهٔ ریاست‌ها',
  REJECTED_BY_PARTICIPATING_DIRECTORATE: 'رد توسط ریاست اشتراک‌کننده',
  SUBMITTED_TO_AUTHORITY_OFFICE: 'ارسال‌شده به دفتر مقام',
  UNDER_AUTHORITY_OFFICE_REVIEW: 'در حال بررسی دفتر مقام',
  RETURNED_BY_AUTHORITY_OFFICE: 'برگشت از دفتر مقام',
  PENDING_HIGH_AUTHORITY_DECISION: 'منتظر تصمیم مقام عالی',
  RETURNED_BY_HIGH_AUTHORITY: 'برگشت از مقام عالی',
  REJECTED_BY_HIGH_AUTHORITY: 'رد توسط مقام عالی',
  HIGH_AUTHORITY_APPROVED: 'تأیید مقام عالی',
  AUTHORITY_SCHEDULING: 'در حال زمان‌بندی دفتر مقام',
  PENDING_HIGH_AUTHORITY_ISSUANCE: 'منتظر صدور مقام عالی',
  RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION: 'برگشت برای اصلاح مسوده',
  ISSUED_BY_HIGH_AUTHORITY: 'صادرشده توسط مقام عالی',
  DISTRIBUTED_TO_DIRECTORATES: 'توزیع‌شده به ریاست‌ها',
  UNDER_DIRECTORATE_COORDINATION: 'در حال هماهنگی ریاست‌ها',
  DIRECTORATE_CLARIFICATION_REQUIRED: 'نیازمند وضاحت ریاست',
  PENDING_HIGH_AUTHORITY_REISSUANCE: 'منتظر صدور مجدد مقام عالی',
  DIRECTORATES_ACKNOWLEDGED: 'تأیید دریافت همهٔ ریاست‌ها',
}

export const meetingActionLabels: Record<string, string> = {
  APPROVED_UNDER_DELEGATION: 'تأیید با صلاحیت تفویض‌شده',
  MINUTES_UNIT_ACKNOWLEDGED_UNDER_DELEGATION: 'تأیید صورت‌جلسه با صلاحیت تفویض‌شده',
  MINUTES_CORRECTION_REQUESTED_UNDER_DELEGATION: 'درخواست اصلاح صورت‌جلسه با صلاحیت تفویض‌شده',
}

export function meetingActionLabel(action: string): string {
  return meetingActionLabels[action] || action
}

export const purposeLabels: Record<string, string> = {
  DECISION: 'تصمیم‌گیری',
  COORDINATION: 'هماهنگی',
  CONSULTATION: 'مشورت',
  REPORTING: 'گزارش‌دهی',
  APPROVAL: 'تأیید',
  FOLLOW_UP: 'پیگیری',
  OTHER: 'سایر',
}

export const priorityLabels: Record<string, string> = {
  NORMAL: 'عادی',
  HIGH: 'بلند',
  URGENT: 'عاجل',
}
