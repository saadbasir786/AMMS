import type { ApplicationShellNavItem } from '@/components/layout/application-shell'
import {
  BarChart3,
  Bell,
  CalendarDays,
  ClipboardCheck,
  FilePlus2,
  Fingerprint,
  LayoutDashboard,
  Monitor,
  Network,
  ShieldCheck,
  Users,
} from 'lucide-react'

export const navigationItems: ApplicationShellNavItem[] = [
  { to: '/internal-meeting-approval-delegations', label: 'تفویض صلاحیت جلسات داخلی', icon: ShieldCheck, role: 'DIRECTOR' },
  { to: '/dashboard', label: 'داشبورد', icon: LayoutDashboard },
  { to: '/meeting-requests', label: 'درخواست‌های جلسه', icon: FilePlus2, roles: ['EMPLOYEE', 'DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'] },
  { to: '/incoming-meeting-requests', label: 'درخواست‌های وارده', icon: ClipboardCheck, roles: ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'] },
  { to: '/incoming-high-authority', label: 'درخواست‌های دفتر مقام', icon: ShieldCheck, roles: ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'] },
  { to: '/incoming-authority-initiated', label: 'دستورهای ابتکاری مقام', icon: ShieldCheck, roles: ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'] },
  { to: '/approvals', label: 'تأیید درخواست‌ها', icon: ClipboardCheck, role: 'DIRECTOR' },
  { to: '/meetings', label: 'جلسات', icon: CalendarDays, roles: ['EMPLOYEE', 'DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'] },
  { to: '/action-items', label: 'اقدامات من', icon: ClipboardCheck, roles: ['EMPLOYEE', 'DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'] },
  { to: '/notifications', label: 'اعلان‌ها', icon: Bell },
  { to: '/admin/organization', label: 'ساختار تشکیلاتی', icon: Network, role: 'SYSTEM_ADMIN' },
  { to: '/admin/employees', label: 'کارمندان', icon: Users, role: 'SYSTEM_ADMIN' },
  { to: '/admin/roles', label: 'کاربران و صلاحیت‌ها', icon: ShieldCheck, role: 'SYSTEM_ADMIN' },
  { to: '/admin/terminals', label: 'ترمینال‌ها', icon: Monitor, role: 'SYSTEM_ADMIN' },
  { to: '/admin/biometrics', label: 'بایومتریک', icon: Fingerprint, role: 'SYSTEM_ADMIN' },
  { to: '/reports', label: 'گزارش‌ها و تحلیل', icon: BarChart3, roles: ['EXECUTIVE_MANAGEMENT', 'HIGH_AUTHORITY', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'DIRECTOR'] },
]
