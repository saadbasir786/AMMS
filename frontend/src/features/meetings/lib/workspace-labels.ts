export const caseTypeLabels: Record<string, string> = {
  POSTPONEMENT: 'تعویق جلسه', CANCELLATION: 'لغو جلسه',
  REOPENING: 'بازگشایی جلسه', RESUMPTION: 'فعال‌سازی مجدد جلسه',
}

export const workspaceStatusLabels: Record<string, string> = {
  DRAFT: 'پیش‌نویس', OPEN: 'باز', NEGOTIATING: 'در حال هماهنگی',
  READY_TO_CONFIRM: 'آماده تأیید', CONFIRMED: 'تأییدشده', UNDER_REVIEW: 'در حال بررسی',
  RETURNED_FOR_CORRECTION: 'برگشت جهت اصلاح', PENDING_CONFIRMATION: 'در انتظار تأیید',
  READY_TO_APPLY: 'آماده اجرا', APPLIED: 'اجراشده', REJECTED: 'ردشده', WITHDRAWN: 'پس‌گرفته‌شده',
}

export const governanceLabels: Record<string, string> = {
  INTERNAL_SINGLE_UNIT: 'داخل ریاست', PEER_MULTI_UNIT: 'میان ریاست‌ها', AUTHORITY_CONTROLLED: 'تحت مدیریت مقام',
}

workspaceStatusLabels.SUBMITTED = 'ارسال‌شده برای تأیید'
workspaceStatusLabels.APPROVED = 'تأییدشده توسط ریاست'

export const participantRoleLabels: Record<string, string> = {
  PARTICIPATING: 'اشتراک‌کننده', REQUESTING: 'نمایندهٔ ریاست درخواست‌کننده',
  CHAIRPERSON: 'رئیس جلسه', HIGH_AUTHORITY: 'مقام عالی', MEMBER: 'عضو',
}
