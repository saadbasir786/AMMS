import {
  CalendarDays,
  Clock,
  Fingerprint,
  CheckCircle2,
  BarChart3,
  Shield,
  Bell,
  Crown,
  MonitorCheck,
  Users,
} from 'lucide-react'

export type CategoryMeta = {
  id: string
  label: string
  description: string
  icon: typeof Shield
  color: string
  bg: string
}

export const PERMISSION_CATEGORIES: Record<string, CategoryMeta> = {
  'meeting-request': {
    id: 'meeting-request',
    label: 'درخواست‌های جلسه',
    description: 'ایجاد، بررسی، تأیید، رد و مدیریت ضمائم درخواست‌های جلسه',
    icon: CalendarDays,
    color: 'text-blue-600 dark:text-blue-400',
    bg: 'bg-blue-500/10',
  },
  meeting: {
    id: 'meeting',
    label: 'جلسات رسمی و صورت‌جلسات',
    description: 'زمان‌بندی، اجرای جلسه، تدوین و نهایی‌سازی صورت‌جلسه رسمی',
    icon: Clock,
    color: 'text-indigo-600 dark:text-indigo-400',
    bg: 'bg-indigo-500/10',
  },
  'authority-request': {
    id: 'authority-request',
    label: 'امور مقام عالی و جلسات رهبری',
    description: 'ارسال درخواست به مقام، بررسی، صدور دستورات و جلسات ابتکاری',
    icon: Crown,
    color: 'text-purple-600 dark:text-purple-400',
    bg: 'bg-purple-500/10',
  },
  'authority-schedule': {
    id: 'authority-schedule',
    label: 'زمان‌بندی جلسات مقام عالی',
    description: 'هماهنگی و تأیید زمان جلسات تحت نظر دفتر مقام عالی',
    icon: Crown,
    color: 'text-purple-600 dark:text-purple-400',
    bg: 'bg-purple-500/10',
  },
  'authority-directive': {
    id: 'authority-directive',
    label: 'هدایات و دستورات مقام عالی',
    description: 'هماهنگی، ابلاغ و پیگیری دستورات صادره از سوی مقام عالی',
    icon: Crown,
    color: 'text-purple-600 dark:text-purple-400',
    bg: 'bg-purple-500/10',
  },
  'authority-initiated-meeting': {
    id: 'authority-initiated-meeting',
    label: 'جلسات ابتکاری مقام عالی',
    description: 'آماده‌سازی، صدور و توزیع دعوتنامه‌های جلسات ابتکاری مقام',
    icon: Crown,
    color: 'text-purple-600 dark:text-purple-400',
    bg: 'bg-purple-500/10',
  },
  'authority-participant': {
    id: 'authority-participant',
    label: 'حاضرین جلسات مقام عالی',
    description: 'مدیریت و تأیید ترکیب هیئت‌ها و اشتراک‌کنندگان جلسات مقام',
    icon: Users,
    color: 'text-purple-600 dark:text-purple-400',
    bg: 'bg-purple-500/10',
  },
  'authority-meeting': {
    id: 'authority-meeting',
    label: 'نهایی‌سازی جلسات مقام عالی',
    description: 'نهایی‌سازی و اختتام رسمی جلسات برگزارشده با مقام عالی',
    icon: Crown,
    color: 'text-purple-600 dark:text-purple-400',
    bg: 'bg-purple-500/10',
  },
  'meeting-schedule': {
    id: 'meeting-schedule',
    label: 'پیشنهاد و هماهنگی زمان‌بندی',
    description: 'ارائه پیشنهاد زمان جلسه و توافق میان ریاست‌های اشتراک‌کننده',
    icon: Clock,
    color: 'text-blue-600 dark:text-blue-400',
    bg: 'bg-blue-500/10',
  },
  'meeting-participant': {
    id: 'meeting-participant',
    label: 'تعیین اعضا و حاضرین جلسه',
    description: 'معرفی و تأیید کارمندان و اعضای اشتراک‌کننده در جلسه',
    icon: Users,
    color: 'text-teal-600 dark:text-teal-400',
    bg: 'bg-teal-500/10',
  },
  'meeting-reschedule': {
    id: 'meeting-reschedule',
    label: 'تغییر زمان و باززمان‌بندی',
    description: 'درخواست و تأیید تغییر موعد برگزاری جلسات رسمی',
    icon: Clock,
    color: 'text-amber-600 dark:text-amber-400',
    bg: 'bg-amber-500/10',
  },
  'meeting-participant-change': {
    id: 'meeting-participant-change',
    label: 'تغییر ترکیب اشتراک‌کنندگان',
    description: 'درخواست تعویض یا جایگزینی اعضای معرفی‌شده به جلسه',
    icon: Users,
    color: 'text-teal-600 dark:text-teal-400',
    bg: 'bg-teal-500/10',
  },
  attendance: {
    id: 'attendance',
    label: 'حاضری و ثبت ورود/خروج',
    description: 'مشاهده و مدیریت وضعیت حاضری حاضرین در جلسات',
    icon: Fingerprint,
    color: 'text-emerald-600 dark:text-emerald-400',
    bg: 'bg-emerald-500/10',
  },
  biometric: {
    id: 'biometric',
    label: 'ثبت و بایومتریک حاضرین',
    description: 'ثبت‌نام، تمدید و مدیریت اطلاعات بایومتریک کارمندان',
    icon: Fingerprint,
    color: 'text-emerald-600 dark:text-emerald-400',
    bg: 'bg-emerald-500/10',
  },
  terminal: {
    id: 'terminal',
    label: 'ترمینال‌های سخت‌افزاری',
    description: 'ثبت، اعتباربخشی و مدیریت ترمینال‌های تالار جلسات',
    icon: MonitorCheck,
    color: 'text-slate-600 dark:text-slate-400',
    bg: 'bg-slate-500/10',
  },
  'meeting-terminal': {
    id: 'meeting-terminal',
    label: 'تخصیص ترمینال به جلسات',
    description: 'اتصال ترمینال به جلسه و مدیریت نشست‌های جاری',
    icon: MonitorCheck,
    color: 'text-slate-600 dark:text-slate-400',
    bg: 'bg-slate-500/10',
  },
  'terminal-session': {
    id: 'terminal-session',
    label: 'نشست‌های ترمینال',
    description: 'مشاهده وضعیت فعال ترمینال‌های حاضر در جلسه',
    icon: MonitorCheck,
    color: 'text-slate-600 dark:text-slate-400',
    bg: 'bg-slate-500/10',
  },
  decision: {
    id: 'decision',
    label: 'تصامیم و مصوبات جلسه',
    description: 'ثبت و طبقه‌بندی مصوبات اتخاذشده در جلسات رسمی',
    icon: CheckCircle2,
    color: 'text-amber-600 dark:text-amber-400',
    bg: 'bg-amber-500/10',
  },
  'action-item': {
    id: 'action-item',
    label: 'اقدامات قابل انجام (پیگیری مصوبات)',
    description: 'تعریف اقدامات، تخصیص به کارمندان، ثبت پیشرفت و تأیید اجرا',
    icon: CheckCircle2,
    color: 'text-amber-600 dark:text-amber-400',
    bg: 'bg-amber-500/10',
  },
  reporting: {
    id: 'reporting',
    label: 'گزارش‌ها و داشبوردها',
    description: 'مشاهده داشبوردهای آماری، گزارش‌های مدیریتی و استخراج خروجی',
    icon: BarChart3,
    color: 'text-violet-600 dark:text-violet-400',
    bg: 'bg-violet-500/10',
  },
  organization: {
    id: 'organization',
    label: 'مدیریت تشکیلات و امنیت',
    description: 'مدیریت ساختار واحدها، کارمندان، حساب‌های کاربری و نقش‌ها',
    icon: Shield,
    color: 'text-rose-600 dark:text-rose-400',
    bg: 'bg-rose-500/10',
  },
  notification: {
    id: 'notification',
    label: 'اعلان‌ها و پیام‌ها',
    description: 'دریافت و مدیریت اعلان‌های سیستمی و کارتابل',
    icon: Bell,
    color: 'text-cyan-600 dark:text-cyan-400',
    bg: 'bg-cyan-500/10',
  },
}

export const PERMISSION_TRANSLATIONS: Record<string, { label: string; description: string }> = {
  // Meeting Requests
  'meeting-request.view': { label: 'مشاهده درخواست‌های جلسه', description: 'دسترسی به فهرست و جزئیات درخواست‌های جلسه مرتبط با حوزه کاری' },
  'meeting-request.create': { label: 'ایجاد درخواست جلسه جدید', description: 'ثبت و تعریف پیش‌نویس درخواست جلسه رسمی' },
  'meeting-request.update': { label: 'ویرایش پیش‌نویس درخواست جلسه', description: 'اصلاح عنوان، اهداف، آجندا و نیازمندی‌های درخواست قبل از ارسال' },
  'meeting-request.submit': { label: 'ارسال درخواست جلسه به ریاست', description: 'ارسال رسمی پیش‌نویس جهت بررسی و طی مراحل تأیید' },
  'meeting-request.resubmit': { label: 'ارسال مجدد درخواست پس از اصلاح', description: 'ثبت اصلاحات خواسته‌شده و ارسال مجدد به جریان کار' },
  'meeting-request.approve': { label: 'تأیید درخواست جلسه (ریاست مبدأ)', description: 'موافقت رسمی ریاست درخواست‌کننده با برگزاری جلسه' },
  'meeting-request.return': { label: 'بازگرداندن درخواست جهت اصلاح', description: 'ارجاع درخواست به کارشناس مربوطه برای اعمال بازنگری و نواقص' },
  'meeting-request.reject': { label: 'رد درخواست جلسه', description: 'عدم موافقت نهایی با درخواست برگزاری جلسه' },
  'meeting-request.attach': { label: 'بارگذاری فایل‌های ضمیمه درخواست', description: 'پیوست اسناد، پروپوزال و مدارک توجیهی به درخواست جلسه' },
  'meeting-request.attachment-download': { label: 'دانلود اسناد ضمیمه درخواست', description: 'دریافت و مطالعه فایل‌های ضمیمه‌شده به درخواست جلسه' },
  'meeting-request.attachment-delete': { label: 'حذف اسناد ضمیمه', description: 'پاک‌سازی فایل‌های پیوست نامرتبط یا آزمایشی' },
  'meeting-request.view-incoming': { label: 'مشاهده درخواست‌های وارده از سایر ادارات', description: 'بررسی درخواست‌های جلسات دوجانبه یا چندجانبه ارسال‌شده به این ریاست' },
  'meeting-request.approve-target': { label: 'تأیید درخواست وارده (ریاست مقصد)', description: 'موافقت ریاست مقصد با شرکت و برگزاری جلسه دوجانبه' },
  'meeting-request.return-target': { label: 'بازگرداندن درخواست وارده جهت اصلاح', description: 'درخواست بازنگری یا تغییر مشخصات از ریاست فرستنده' },
  'meeting-request.reject-target': { label: 'رد درخواست وارده از سایر ادارات', description: 'عدم پذیرش دعوت جلسه ارسالی از اداره دیگر' },
  'meeting-request.approve-delegated': { label: 'تأیید تفویض‌شده درخواست جلسه', description: 'تأیید درخواست بر مبنای صلاحیت تفویض‌شده رسمی از سوی رئیس اداره' },

  // Schedules & Participants
  'meeting-schedule.propose': { label: 'پیشنهاد زمان برگزاری جلسه', description: 'ثبت ساعت و تاریخ پیشنهادی برای انعقاد جلسه' },
  'meeting-schedule.accept': { label: 'پذیرش زمان پیشنهادی جلسه', description: 'تأیید موعد پیشنهادی هماهنگ‌شده' },
  'meeting-participant.nominate': { label: 'معرفی کارمندان و اعضای هیئت', description: 'تعیین و ثبت اسامی کارمندان جهت اشتراک در جلسه' },
  'meeting-participant.confirm': { label: 'تأیید نهایی ترکیب هیئت اشتراک‌کننده', description: 'تصویب ترکیب حاضرین از سوی مقام مسئول' },

  // Meetings Core
  'meeting.schedule': { label: 'زمان‌بندی رسمی و قطعی جلسه', description: 'تبدیل درخواست تأییدشده به جلسه رسمی و تثبیت تقویم' },
  'meeting.create': { label: 'ثبت مستقیم جلسه رسمی', description: 'تعریف مستقیم جلسه در تقویم اداری' },
  'meeting.view': { label: 'مشاهده جلسات رسمی', description: 'دسترسی به اطلاعات، آجندا، تالار و مشخصات جلسات' },
  'meeting.execution.start': { label: 'اعلام شروع رسمی جلسه', description: 'آغاز جلسه و فعال‌سازی فرآیند حاضری و ثبت مذاکرات' },
  'meeting.execution.end': { label: 'اعلام پایان رسمی جلسه', description: 'اختتام جلسه و بستن حاضری آنلاین' },
  'meeting.postpone': { label: 'به تعویق انداختن موقت جلسه', description: 'تغییر موعد جلسه به دلیل موارد پیش‌بینی‌نشده' },
  'meeting.cancel': { label: 'لغو رسمی جلسه برنامه‌ریزی‌شده', description: 'ابطال جلسه با درج علت موجه' },
  'meeting.reopen': { label: 'بازگشایی مجدد صورت‌جلسه', description: 'بازگرداندن صورت‌جلسه به حالت ویرایش در موارد استثنایی' },
  'meeting.resume': { label: 'ازسرگیری جلسه معلق‌شده', description: 'ادامه برگزاری جلسه‌ای که قبلاً متوقف شده بود' },

  // Minutes
  'meeting.minutes.view': { label: 'مشاهده صورت‌جلسه رسمی', description: 'مطالعه متن مصوبات، مذاکرات و حاضران صورت‌جلسه' },
  'meeting.minutes.prepare': { label: 'تدوین پیش‌نویس صورت‌جلسه', description: 'نگارش شرح گفتگوها و پیش‌نویس تصامیم جلسه' },
  'meeting.minutes.submit': { label: 'ارسال صورت‌جلسه جهت مرور و تأیید', description: 'ارسال متن صورت‌جلسه به رؤسا و اشتراک‌کنندگان' },
  'meeting.minutes.review': { label: 'مرور و بررسی صورت‌جلسه (ریاست‌ها)', description: 'اعلام نظر و تأیید درستی مندرجات صورت‌جلسه' },
  'meeting.minutes.review-delegated': { label: 'مرور تفویض‌شده صورت‌جلسه', description: 'بررسی صورت‌جلسه بر مبنای صلاحیت تفویض‌شده از رئیس اداره' },
  'meeting.minutes.approve_authority': { label: 'تأیید نهایی صورت‌جلسه توسط مقام عالی', description: 'توشیح و تأیید نهایی مصوبات توسط مقام عالی' },
  'meeting.minutes.finalize': { label: 'نهایی‌سازی و رسمیت‌بخشی به صورت‌جلسه', description: 'صدور شماره رسمی و نهایی‌سازی سند صورت‌جلسه' },
  'meeting.minutes.document.view': { label: 'دانلود سند رسمی صورت‌جلسه', description: 'دریافت نسخه رسمی و امضاشده صورت‌جلسه' },

  // Authority Operations
  'authority-request.view': { label: 'مشاهده درخواست‌های مرتبط با مقام عالی', description: 'رؤیت پرونده‌های ارجاعی به دفتر مقام عالی' },
  'authority-request.review': { label: 'بررسی کارشناسی درخواست در دفتر مقام', description: 'ارزیابی حقوقی و تشکیلاتی درخواست قبل از طرح نزد مقام' },
  'authority-request.forward': { label: 'ارجاع درخواست به مقام عالی', description: 'ارسال پرونده تکمیل‌شده به کارتابل مقام عالی' },
  'authority-request.return': { label: 'بازگرداندن پرونده از دفتر مقام', description: 'ارجاع پرونده به اداره درخواست‌کننده جهت تکمیل مدارک' },
  'authority-request.decide': { label: 'ثبت تصمیم نهایی مقام عالی', description: 'اعلام نظر مستقیم مقام عالی پیرامون برگزاری جلسه' },
  'authority-request.approve': { label: 'موافقت رسمی مقام عالی با جلسه', description: 'تصویب تشکیل جلسه تحت نظر مقام عالی' },
  'authority-request.reject': { label: 'رد درخواست جلسه توسط مقام عالی', description: 'عدم موافقت مقام عالی با برگزاری جلسه مورد نظر' },
  'authority-request.return-by-authority': { label: 'بازگرداندن درخواست مستقیماً توسط مقام عالی', description: 'دستور بازنگری یا هدایت خاص از سوی مقام عالی' },
  'authority-schedule.view': { label: 'مشاهده تقویم جلسات مقام عالی', description: 'دسترسی به جدول زمان‌بندی ملاقات‌ها و جلسات مقام' },
  'authority-schedule.propose': { label: 'پیشنهاد زمان برای جلسه با مقام', description: 'تنظیم وقت‌های متناسب با برنامه زمانی مقام عالی' },
  'authority-schedule.confirm': { label: 'تثبیت نهایی زمان جلسه مقام عالی', description: 'تأیید نهایی موعد جلسه توسط منشی یا دفتر مقام' },
  'authority-schedule.acknowledge': { label: 'رویت و تأیید اطلاع از زمان جلسه مقام', description: 'اعلام دریافت وقت و آماده‌باش اداره مربوطه' },
  'authority-participant.manage': { label: 'تعیین اشتراک‌کنندگان جلسه با مقام', description: 'تنظیم لیست مهمانان و مقامات حاضر در جلسه' },
  'authority-participant.confirm': { label: 'تأیید لیست نهایی حاضرین نزد مقام', description: 'تصویب نهایی اسامی افراد مجاز به حضور در جلسه مقام' },
  'authority-meeting.finalize': { label: 'نهایی‌سازی تشریفات جلسه مقام عالی', description: 'تکمیل تمامی مراحل اداری جلسه برگزارشده با مقام' },
  'authority-initiated-meeting.prepare': { label: 'آماده‌سازی جلسه با دستور مستقیم مقام', description: 'تدوین آجندا و هماهنگی برای جلسات به ابتکار مقام عالی' },
  'authority-initiated-meeting.issue': { label: 'صدور ابلاغیه جلسه ابتکاری مقام', description: 'انتشار دعوتنامه و دستور جلسه صادره از سوی مقام عالی' },
  'authority-initiated-meeting.distribute': { label: 'ابلاغ دستور جلسه به ادارات تابعه', description: 'توزیع رسمی اسناد جلسه ابتکاری به مراجع ذیربط' },
  'authority-directive.view': { label: 'مشاهده هدایات و رهنمودهای مقام عالی', description: 'دسترسی به دستورات ثبت‌شده در سیستم از سوی مقام' },
  'authority-directive.coordinate': { label: 'هماهنگی اجرای دستورات مقام عالی', description: 'پیگیری اجرایی شدن احکام صادره از مقام عالی' },
  'authority-directive.acknowledge': { label: 'اعلام وصول و پذیرش دستور مقام عالی', description: 'ثبت اعلام آمادگی جهت اجرای هدایت مقام' },
  'authority-directive.clarification-request': { label: 'درخواست توضیح و استفسار از مقام عالی', description: 'ارسال سوال پیرامون نحوه تطبیق دستور مقام' },
  'authority-directive.clarification-respond': { label: 'پاسخ به استفسارات ادارات پیرامون دستور', description: 'ارائه توضیحات تکمیلی از سوی دفتر مقام' },

  // Reschedule & Participants
  'meeting-reschedule.create': { label: 'ثبت درخواست تغییر زمان جلسه', description: 'ایجاد درخواست رسمی جابجایی تاریخ یا ساعت جلسه' },
  'meeting-reschedule.propose': { label: 'پیشنهاد موعد جایگزین برای جلسه', description: 'ارائه تاریخ و ساعت جدید برای جلسه به تعویق افتاده' },
  'meeting-reschedule.acknowledge': { label: 'اطلاع از درخواست تغییر زمان', description: 'مشاهده و پذیرش درخواست تغییر موعد توسط ادارات' },
  'meeting-reschedule.request-alternative': { label: 'پیشنهاد ساعت دیگر در فرآیند تغییر زمان', description: 'عدم موافقت با موعد پیشنهادی و ارائه زمان دوم' },
  'meeting-reschedule.confirm': { label: 'تصویب موعد جدید جلسه', description: 'نهایی‌سازی تغییر تاریخ/ساعت در تقویم اداری' },
  'meeting-reschedule.view-history': { label: 'مشاهده سوابق تغییر زمان‌های جلسه', description: 'گزارش تاریخچه جابجایی‌ها و دلایل ارائه‌شده' },
  'meeting-participant-change.create': { label: 'درخواست تعویض اشتراک‌کننده', description: 'ثبت درخواست جایگزینی کارمند معرفی‌شده' },
  'meeting-participant-change.manage': { label: 'مدیریت و پردازش تغییرات حاضرین', description: 'بررسی دلایل تعویض اعضای جلسه' },
  'meeting-participant-change.submit': { label: 'ارسال درخواست جایگزینی عضو', description: 'ارسال رسمی جهت تأیید ریاست مربوطه' },
  'meeting-participant-change.confirm': { label: 'تأیید جایگزینی کارمند در جلسه', description: 'موافقت رسمی با تغییر فرد اشتراک‌کننده' },
  'meeting-participant-change.return': { label: 'رد درخواست جایگزینی یا درخواست اصلاح', description: 'عدم پذیرش فرد جایگزین و بازگرداندن پرونده' },
  'meeting-participant-change.reject': { label: 'رد قطعی درخواست تغییر عضو', description: 'مخالفت نهایی با جابجایی عضو معرفی‌شده' },
  'meeting-participant-change.view-history': { label: 'مشاهده سوابق جابجایی اشتراک‌کنندگان', description: 'تاریخچه تغییرات ترکیب هیئت‌ها در جلسات' },

  // Hardware Terminals & Attendance
  'terminal.view': { label: 'مشاهده ترمینال‌های سخت‌افزاری تالار', description: 'دسترسی به فهرست دستگاه‌های ثبت حاضری تالارها' },
  'terminal.register': { label: 'ثبت دستگاه یا ترمینال جدید', description: 'معرفی ترمینال به پایگاه داده مرکزی سامانه' },
  'terminal.manage': { label: 'مدیریت تنظیمات ترمینال‌ها', description: 'پیکربندی وضعیت و مکان دستگاه‌های فیزیکی' },
  'terminal.rotate-credential': { label: 'تغییر کلید امنیتی ترمینال', description: 'تجدید رمز یا توکن ارتباطی دستگاه حضور و غیاب' },
  'terminal.revoke': { label: 'ابطال مجوز دسترسی ترمینال', description: 'غیرفعال‌سازی دستگاه نامعتبر یا معیوب' },
  'meeting-terminal.view': { label: 'مشاهده ترمینال‌های تخصیص‌یافته به جلسه', description: 'بررسی دستگاه‌های متصل به تالار جلسه جاری' },
  'meeting-terminal.assign': { label: 'تخصیص ترمینال به تالار جلسه', description: 'پیوند دستگاه ثبت حاضری با جلسه فعال' },
  'meeting-terminal.refresh': { label: 'همگام‌سازی ارتباط ترمینال جلسه', description: 'بروزرسانی داده‌های دستگاه در حین برگزاری جلسه' },
  'meeting-terminal.revoke': { label: 'قطع اتصال ترمینال از جلسه', description: 'جدا کردن دستگاه ثبت از جلسه مورد نظر' },
  'terminal-session.view': { label: 'مشاهده وضعیت نشست‌های برخط ترمینال', description: 'نظارت بر لاگ‌ها و اتصال زنده ترمینال‌ها' },
  'biometric.status.view': { label: 'مشاهده وضعیت ثبت بایومتریک کارمندان', description: 'استعلام اینکه آیا اثر انگشت کارمند ثبت شده است یا خیر' },
  'biometric.enroll': { label: 'ثبت‌نام اولیه بایومتریک (انگشت‌نگاری)', description: 'ثبت الگوی بایومتریک کارمندان در پایگاه امن داده' },
  'biometric.reenroll': { label: 'ثبت مجدد اطلاعات بایومتریک', description: 'انگشت‌نگاری مجدد در صورت تغییر یا ابهام الگو' },
  'biometric.revoke': { label: 'حذف یا ابطال الگوی بایومتریک', description: 'غیرفعال‌سازی اثر انگشت ثبت‌شده از سیستم' },
  'biometric.enrollment.cancel': { label: 'انصراف از عملیات ثبت بایومتریک', description: 'توقف فرآیند اسکن در جریان' },
  'attendance.view': { label: 'مشاهده حاضری اشتراک‌کنندگان جلسه', description: 'رؤیت لیست افراد حاضر، غایب و با تأخیر در جلسه' },
  'attendance.override': { label: 'ثبت دستی یا اصلاح وضعیت حاضری', description: 'تغییر دستی وضعیت کارمند به حاضر یا معذور با ذکر علت' },

  // Decisions & Action Items
  'decision.followup.view': { label: 'مشاهده تصامیم و مصوبات جلسه', description: 'دسترسی به بندهای مصوب در صورت‌جلسات رسمی' },
  'decision.followup.initialize': { label: 'تعریف مصوبه جدید برای پیگیری', description: 'ایجاد بند تصمیم رسمی حاصل از مذاکرات جلسه' },
  'decision.followup.classify': { label: 'دسته‌بندی و اولویت‌بندی مصوبه', description: 'تعیین درجه اهمیت و حوزه اداری تصمیم مصوب' },
  'action-item.create': { label: 'ایجاد اقدام قابل انجام جدید', description: 'شکستن مصوبه به وظایف و اقدامات اجرایی مشخص' },
  'action-item.view': { label: 'مشاهده اقدامات و وظایف پیگیری', description: 'رؤیت جزئیات، مهلت‌ها و مسئولین اقدام' },
  'action-item.start': { label: 'شروع اجرای اقدام', description: 'تغییر وضعیت اقدام به «در حال اجرا» توسط کارمند مربوطه' },
  'action-item.progress': { label: 'ثبت گزارش درصد پیشرفت اقدام', description: 'درج توضیحات و مستندات گام‌های طی‌شده در انجام وظیفه' },
  'action-item.block': { label: 'اعلام مانع یا توقف در اجرای اقدام', description: 'گزارش چالش‌های خارج از اختیار و توقف موقت کار' },
  'action-item.submit-completion': { label: 'اعلام تکمیل و اتمام اقدام', description: 'ارسال گزارش نهایی اقدام جهت ارزیابی و صحه‌گذاری' },
  'action-item.verify-completion': { label: 'تأیید نهایی انجام و بستن اقدام', description: 'صحه بر اجرای درست مصوبه و بایگانی اقدام' },
  'action-item.assign': { label: 'تخصیص اقدام به کارمند یا اداره', description: 'تعیین مسئول اصلی پاسخگویی و تطبیق وظیفه' },
  'action-item.change-unit': { label: 'تغییر اداره مسئول اقدام', description: 'ارجاع وظیفه به ریاست یا واحد سازمانی دیگر' },
  'action-item.extension.request': { label: 'درخواست تمدید مهلت اقدام', description: 'تقاضای فرصت اضافی به همراه ادله توجیهی' },
  'action-item.extension.review': { label: 'بررسی و پاسخ به درخواست تمدید مهلت', description: 'موافقت یا مخالفت با افزایش مهلت انجام وظیفه' },
  'action-item.cancel': { label: 'لغو یا ابطال اقدام تعریف‌شده', description: 'کان لم یکن اعلام کردن اقدام به دستور مقام مسئول' },
  'action-item.evidence.view': { label: 'مشاهده اسناد و شواهد پیشرفت اقدام', description: 'بررسی مدارک و خروجی‌های بارگذاری‌شده' },
  'action-item.evidence.upload': { label: 'بارگذاری مستندات و خروجی اقدام', description: 'پیوست گزارش‌ها و فکت‌های اثبات‌کننده تکمیل وظیفه' },

  // Exceptions
  'meeting.exception.view': { label: 'مشاهده موارد استثنائی جلسات', description: 'بررسی رویدادهای اضطراری یا مغایرت‌های رخ‌داده در جلسه' },
  'meeting.exception.create': { label: 'ثبت گزارش استثناء یا تخطی', description: 'ایجاد پرونده برای موارد خارج از پروتکل‌های عادی' },
  'meeting.exception.update-draft': { label: 'ویرایش پیش‌نویس استثناء', description: 'تکمیل توضیحات قبل از ارائه به مقام تصمیم‌گیرنده' },
  'meeting.exception.submit': { label: 'ارسال پرونده استثناء جهت تصمیم‌گیری', description: 'ارجاع به مراجع ذیصلاح اداری' },
  'meeting.exception.review': { label: 'بررسی گزارش رویداد استثنایی', description: 'ارزیابی علل و پیامدهای رویداد ثبت‌شده' },
  'meeting.exception.approve': { label: 'تأیید راهکار یا پذیرش استثناء', description: 'تأیید اداری برخورد با رویداد غیرمترقبه' },
  'meeting.exception.acknowledge': { label: 'رویت و تأیید دریافت گزارش استثناء', description: 'ثبت اعلام آگاهی از وقوع مورد خاص' },
  'meeting.exception.apply': { label: 'اعمال اثر اداری استثناء بر جلسه', description: 'ثبت نهایی تغییرات حاصل از پذیرش استثناء' },
  'meeting.exception.withdraw': { label: 'استرداد یا پس‌گرفتن گزارش استثناء', description: 'بستن پرونده مغایرت در صورت رفع موضوع' },
  'meeting.exception.history.view': { label: 'مشاهده سوابق و لاگ استثنائات', description: 'گزارش تحلیلی از رویدادهای غیرعادی جلسات' },

  // Reporting & Dashboards
  'reporting.dashboard.executive': { label: 'داشبورد مدیریتی کلان سازمان', description: 'شاخص‌های استراتژیک جلسات، مصوبات و حاضری در سطح ارشد' },
  'reporting.dashboard.directorate': { label: 'داشبورد اختصاصی ریاست', description: 'آمارهای عملکردی، جلسات و اقدامات مربوط به همان ریاست' },
  'reporting.dashboard.personal': { label: 'داشبورد کاربری و کارتابل شخصی', description: 'نمای اختصاصی جلسات پیش‌رو، وظایف و اعلان‌های کارمند' },
  'reporting.dashboard.authority': { label: 'داشبورد نظارتی دفتر مقام عالی', description: 'داشبورد تخصصی تصمیمات، جلسات و هدایات مقام عالی' },
  'reporting.dashboard.technical': { label: 'داشبورد نظارت فنی و زیرساخت', description: 'آمار فنی سیستم، ترمینال‌ها، کاربران و امنیت' },
  'reporting.meetings.view': { label: 'گزارش جامع جلسات و تحلیل روند', description: 'گزارش‌های دوره‌ای از تعداد، ساعات و خروجی جلسات' },
  'reporting.requests.view': { label: 'گزارش درخواست‌های جلسه', description: 'تحلیل نسبت تأیید، رد و زمان پاسخگویی به درخواست‌ها' },
  'reporting.attendance.summary': { label: 'گزارش تحلیلی حاضری و غیبت', description: 'آمار تفکیکی اشتراک، وقت‌شناسی و غیبت کارمندان' },
  'reporting.attendance.detail': { label: 'گزارش ریز ورود و خروج جلسات', description: 'مشاهده لاگ‌های دقیق زمانی ثبت‌شده در ترمینال‌ها' },
  'reporting.decisions.view': { label: 'گزارش جامع مصوبات و تصامیم', description: 'پیگیری آماری درصد تطبیق مصوبات به تفکیک ادارات' },
  'reporting.action-items.view': { label: 'گزارش وضعیت اجرای اقدامات', description: 'تحلیل وظایف جاری، تکمیل‌شده، دارای تأخیر و مسدود' },
  'reporting.exceptions.view': { label: 'گزارش آماری استثنائات جلسات', description: 'فهرست مغایرت‌ها و تصمیمات ویژه اتخاذشده' },
  'reporting.exports.create': { label: 'تولید فایل‌های خروجی گزارش (اکسل/PDF)', description: 'استخراج داده‌های سیستمی در قالب فایل‌های رسمی' },
  'reporting.exports.download': { label: 'دانلود فایل‌های گزارش آماده‌شده', description: 'دریافت خروجی‌های معتبر و امن از سرور' },
  'reporting.technical.view': { label: 'مشاهده لاگ‌های فنی و رویدادهای سیستم', description: 'بررسی وضعیت سلامت سرور و عملکرد پلتفرم' },

  // System Administration & Security
  'organization.manage': { label: 'مدیریت ساختار تشکیلاتی واحدها', description: 'تعریف، ویرایش و ارتباط سلسله‌مراتبی ادارات و ریاست‌ها' },
  'employee.manage': { label: 'مدیریت کارمندان و سوابق استخدامی', description: 'ثبت مشخصات کارمندان، بست‌های اداری و تخصیص واحدها' },
  'user.manage': { label: 'مدیریت کاربران و حساب‌های ورود', description: 'ایجاد حساب کاربری، بازنشانی رمز و مدیریت دسترسی کاربران' },
  'role.manage': { label: 'مدیریت نقش‌ها و تخصیص دسترسی‌ها', description: 'ایجاد نقش‌های سفارشی و تنظیم اختیارات در سطح سامانه' },
  'notification.view': { label: 'مشاهده اعلان‌ها و پیام‌های اداری', description: 'دسترسی به رویدادهای جدید و اطلاعیه‌های سامانه' },
  'notification.mark-read': { label: 'خوانده‌شده کردن اعلان‌ها', description: 'علامت‌گذاری پیام‌های دریافت‌شده به عنوان مشاهده‌شده' },
}

export type SystemRoleGovernanceInfo = {
  role: string
  label: string
  boundary: string
  governanceScope: string
  flowAuthoritySummary: string
  isProtected: boolean
  accentColor: string
}

export const SYSTEM_ROLE_GOVERNANCE: Record<string, SystemRoleGovernanceInfo> = {
  SYSTEM_ADMIN: {
    role: 'SYSTEM_ADMIN',
    label: 'مدیر سیستم (فنی)',
    boundary: 'صرفاً مدیریت فنی، زیرساخت، امنیت و پیکربندی سامانه. این نقش به هیچ عنوان دسترسی به تصمیم‌گیری، تأیید یا گردش کارهای تجاری جلسات ندارد.',
    governanceScope: 'سطح سیستم / زیرساخت فنی (فارغ از حوزه‌های اداری و تشکیلاتی)',
    flowAuthoritySummary: 'مدیریت حساب‌ها، نقش‌ها، ترمینال‌های فنی و مشاهده داشبورد فنی. فاقد صلاحیت تأیید جلسات یا مصوبات.',
    isProtected: true,
    accentColor: 'rose',
  },
  EMPLOYEE: {
    role: 'EMPLOYEE',
    label: 'کارمند',
    boundary: 'کارتابل فردی، حضور در جلسات دعوت‌شده و انجام اقدامات و وظایفی که صراحتاً به خود کارمند محول شده است.',
    governanceScope: 'محدود به حیطه وظایف شخصی و جلسات ارجاع‌شده',
    flowAuthoritySummary: 'مشاهده جلسات دعوت‌شده، مشاهده و پیشبرد اقدامات محوله به شخص، دریافت اعلان‌ها و ثبت درصد پیشرفت.',
    isProtected: true,
    accentColor: 'slate',
  },
  DIRECTOR: {
    role: 'DIRECTOR',
    label: 'رئیس اداره',
    boundary: 'عالی‌ترین مقام تصمیم‌گیرنده در سطح همان ریاست. تأیید رسمی درخواست‌های جلسه، تصویب ترکیب اعضای اعزامی، مرور صورت‌جلسات و تأیید استثنائات در حوزه ریاست خود.',
    governanceScope: 'محدود به واحد سازمانی تحت مدیریت (Directorate Scope)',
    flowAuthoritySummary: 'تأیید یا رد درخواست‌های جلسه (مبدأ و مقصد)، تأیید ترکیب حاضرین، بررسی و توشیح صورت‌جلسات واحد خود، ارزیابی استثنائات و مشاهده داشبورد ریاست.',
    isProtected: true,
    accentColor: 'indigo',
  },
  DEPUTY_DIRECTOR: {
    role: 'DEPUTY_DIRECTOR',
    label: 'معاون اداره',
    boundary: 'پست معاونت رسمی در ریاست مربوطه. صرفاً با داشتن تفویض صلاحیت صریح و معتبر از سوی رئیس اداره می‌تواند وظایف مشخص (مانند مرور صورت‌جلسات یا تأیید درخواست داخلی) را اجرا کند.',
    governanceScope: 'محدود به واحد سازمانی مربوطه تحت پوشش تفویض فعال',
    flowAuthoritySummary: 'مشاهده جلسات و صورت‌جلسات واحد خود؛ ورود به تصمیم‌گیری صرفاً در صورت فعال بودن تفویض رسمی صلاحیت.',
    isProtected: true,
    accentColor: 'sky',
  },
  EXECUTIVE_MANAGEMENT: {
    role: 'EXECUTIVE_MANAGEMENT',
    label: 'مدیریت اجرائیه',
    boundary: 'هماهنگی و بازوی اجرایی ریاست: تنظیم پیش‌نویس درخواست‌ها، هماهنگی تقویم جلسات، مدیریت ورود و خروج، تهیه صورت‌جلسه و پیگیری اجرای مصوبات.',
    governanceScope: 'واحد سازمانی تحت مأموریت (مدیریت امور جلسات و هماهنگی ریاست)',
    flowAuthoritySummary: 'ایجاد و تدوین درخواست‌های جلسه، هماهنگی زمان‌بندی، ثبت و تدوین صورت‌جلسه رسمی، ایجاد و پیگیری اقدامات مصوب، مدیریت ترمینال‌های جلسه.',
    isProtected: true,
    accentColor: 'blue',
  },
  AUTHORITY_OFFICE: {
    role: 'AUTHORITY_OFFICE',
    label: 'دفتر مقام عالی',
    boundary: 'بررسی، کارشناسی و ارجاع پرونده‌ها و درخواست‌های متقاضی حضور یا تشکیل جلسه نزد مقام عالی. ابلاغ دستورات و جلسات ابتکاری مقام.',
    governanceScope: 'حوزه دفتر مقام عالی (Authority Office Domain)',
    flowAuthoritySummary: 'بررسی و فیلتر درخواست‌های وارده به مقام، تهیه دستور جلسات ابتکاری، ابلاغ و پیگیری هدایات مقام عالی، تأیید ترکیب حاضرین در سطح مقام.',
    isProtected: true,
    accentColor: 'purple',
  },
  EXECUTIVE_SECRETARY: {
    role: 'EXECUTIVE_SECRETARY',
    label: 'منشی مقام عالی',
    boundary: 'مدیریت مستقیم زمان‌بندی، تشریفات، اجرای جلسات و صورت‌جلسات رسمی مقام عالی و نظارت بر تطبیق رهنمودها.',
    governanceScope: 'تقویم رسمی و تشریفات جلسات تحت نظر مقام عالی',
    flowAuthoritySummary: 'زمان‌بندی قطعی جلسات مقام، هدایت اجرای جلسات، تنظیم صورت‌جلسه اختصاصی مقام عالی، پیگیری تصمیمات رهبری، رسیدگی به تغییرات زمان.',
    isProtected: true,
    accentColor: 'violet',
  },
  HIGH_AUTHORITY: {
    role: 'HIGH_AUTHORITY',
    label: 'مقام عالی',
    boundary: 'تصمیم‌گیری نهایی و صدور فرامین. موافقت یا رد درخواست‌های عالی، صدور جلسات ابتکاری و توشیح نهایی صورت‌جلسات عالی سامانه.',
    governanceScope: 'بالاترین سطح رهبری سازمانی (کل مجموعه تحت امر)',
    flowAuthoritySummary: 'تصمیم‌گیری پیرامون برگزاری جلسات رهبری، صدور دستور تشکیل جلسات ابتکاری، توشیح قطعی صورت‌جلسات رسمی.',
    isProtected: true,
    accentColor: 'amber',
  },
}

export function getPermissionDetails(name: string): { label: string; description: string; category: string } {
  const category = name.includes('.') ? name.split('.')[0] : name
  const existing = PERMISSION_TRANSLATIONS[name]

  if (existing) {
    return { ...existing, category }
  }

  // Fallback humanizer
  const fallbackLabel = name
    .replace(/[._-]/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())

  return {
    label: fallbackLabel,
    description: `دسترسی عملیاتی ${name} در سامانه`,
    category,
  }
}
