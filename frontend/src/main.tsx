import React, { createContext, useContext, useEffect, useState } from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard,
  FilePlus2,
  ClipboardCheck,
  CalendarDays,
  Bell,
  Network,
  Users,
  ShieldCheck,
  Monitor,
  Fingerprint,
  BarChart3,
  Download,
  Eye
} from 'lucide-react'
import './styles.css'
import { ApplicationShell } from '@/components/layout/application-shell'
import { PageHeader } from '@/components/amms/page-header'
import { LoginPage } from '@/features/auth'
import { DashboardPage } from '@/features/dashboard'
import {
  RequestsPage,
  IncomingRequestsPage,
  CreateRequestPage,
  RequestDetailPage,
  EditRequestPage,
  ApprovalsPage,
} from '@/features/meeting-requests'
import { SchedulingPage } from '@/features/scheduling'
import {
  MeetingsPage,
  MeetingDetailPage,
  MeetingExecutionPage,
  MeetingMinutesPage,
  MeetingReschedulePage,
  MeetingParticipantChangePage,
  MeetingExceptionsPage,
} from '@/features/meetings'
import {
  ActionItemsPage,
  ActionItemDetailPage,
  MeetingFollowUpPage,
} from '@/features/action-items'
import {
  MeetingAttendancePage,
  MeetingTerminalsPage,
  TerminalsRegistryPage,
  TerminalDetailPage,
  BiometricsRegistryPage,
  EmployeeBiometricsPage,
} from '@/features/attendance-biometrics'
import { ReportsPage } from '@/features/reports'
import {
  OrganizationPage,
  EmployeesPage,
  NotificationsPage,
} from '@/features/organization'
import { api, apiData, apiPage } from '@/lib/api-client'

type User = {
  id: number
  name: string
  email: string
  employee?: { full_name: string; unit?: { name: string } }
  roles?: { role: string; unit?: { name: string } }[]
}

type AuthState = {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState>({
  user: null,
  loading: true,
  login: async () => {},
  logout: async () => {},
})

const useAuth = () => useContext(AuthContext)

const statusLabels: Record<string, string> = {
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
  SUBMITTED_TO_TARGETS: 'ارسال‌شده به ریاست‌های مقصد',
  UNDER_MULTI_TARGET_REVIEW: 'در حال بررسی ریاست‌های مقصد',
  TARGET_ACTION_REQUIRED: 'نیازمند اصلاح از سوی ریاست‌های مقصد',
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

const purposeLabels: Record<string, string> = {
  DECISION: 'تصمیم‌گیری',
  COORDINATION: 'هماهنگی',
  CONSULTATION: 'مشورت',
  REPORTING: 'گزارش‌دهی',
  APPROVAL: 'تأیید',
  FOLLOW_UP: 'پیگیری',
  OTHER: 'سایر',
}

const priorityLabels: Record<string, string> = {
  NORMAL: 'عادی',
  HIGH: 'بلند',
  URGENT: 'عاجل',
}

function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!sessionStorage.getItem('amms_token')) return setLoading(false)
    api
      .get('/me')
      .then((r) => setUser(apiData(r)))
      .catch(() => sessionStorage.removeItem('amms_token'))
      .finally(() => setLoading(false))
  }, [])

  const login = async (email: string, pass: string) => {
    const data = apiData(await api.post('/login', { email, password: pass }))
    sessionStorage.setItem('amms_token', data.token)
    setUser(data.user)
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } finally {
      sessionStorage.removeItem('amms_token')
      setUser(null)
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

function Protected({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="screen-center"><div className="spinner" /></div>
  return user ? <>{children}</> : <Navigate to="/login" replace />
}

const navItems = [
  { to: '/dashboard', label: 'داشبورد', icon: LayoutDashboard },
  {
    to: '/meeting-requests',
    label: 'درخواست‌های جلسه',
    icon: FilePlus2,
    roles: ['EMPLOYEE', 'DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'],
  },
  {
    to: '/incoming-meeting-requests',
    label: 'درخواست‌های وارده',
    icon: ClipboardCheck,
    roles: ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'],
  },
  {
    to: '/incoming-high-authority',
    label: 'درخواست‌های دفتر مقام',
    icon: ShieldCheck,
    roles: ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'],
  },
  {
    to: '/incoming-authority-initiated',
    label: 'دستورهای ابتکاری مقام',
    icon: ShieldCheck,
    roles: ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'],
  },
  { to: '/approvals', label: 'تأیید درخواست‌ها', icon: ClipboardCheck, role: 'DIRECTOR' },
  {
    to: '/meetings',
    label: 'جلسات',
    icon: CalendarDays,
    roles: ['EMPLOYEE', 'DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'],
  },
  {
    to: '/action-items',
    label: 'اقدامات من',
    icon: ClipboardCheck,
    roles: ['EMPLOYEE', 'DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'],
  },
  { to: '/notifications', label: 'اعلان‌ها', icon: Bell },
  { to: '/admin/organization', label: 'ساختار تشکیلاتی', icon: Network, role: 'SYSTEM_ADMIN' },
  { to: '/admin/employees', label: 'کارمندان', icon: Users, role: 'SYSTEM_ADMIN' },
  { to: '/admin/roles', label: 'کاربران و صلاحیت‌ها', icon: ShieldCheck, role: 'SYSTEM_ADMIN' },
  { to: '/admin/terminals', label: 'ترمینال‌ها', icon: Monitor, role: 'SYSTEM_ADMIN' },
  { to: '/admin/biometrics', label: 'بایومتریک', icon: Fingerprint, role: 'SYSTEM_ADMIN' },
  {
    to: '/reports',
    label: 'گزارش‌ها و تحلیل',
    icon: BarChart3,
    roles: ['EXECUTIVE_MANAGEMENT', 'HIGH_AUTHORITY', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'DIRECTOR'],
  },
  {
    to: '/reports/exports',
    label: 'صادرات گزارش‌ها',
    icon: Download,
    roles: ['EXECUTIVE_MANAGEMENT', 'HIGH_AUTHORITY', 'AUTHORITY_OFFICE', 'DIRECTOR'],
  },
]

function Shell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  return (
    <ApplicationShell
      user={user}
      navItems={navItems}
      currentPath={location.pathname}
      onNavigate={(target) => navigate(target)}
      onLogout={logout}
    >
      {children}
    </ApplicationShell>
  )
}

function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  if (user) return <Navigate to="/dashboard" replace />
  const handleLogin = async (email: string, pass: string) => {
    await login(email, pass)
    navigate('/dashboard')
  }
  return <LoginPage onLogin={handleLogin} />
}

function Dashboard() {
  const { user } = useAuth()
  return <DashboardPage user={user} statusLabels={statusLabels} />
}

function Requests() {
  return (
    <RequestsPage
      statusLabels={statusLabels}
      priorityLabels={priorityLabels}
      purposeLabels={purposeLabels}
    />
  )
}

function RequestDetail() {
  const { user } = useAuth()
  return (
    <RequestDetailPage
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      currentUser={user}
    />
  )
}

function Scheduling() {
  const { user } = useAuth()
  return <SchedulingPage currentUser={user} />
}

function Meetings() {
  return <MeetingsPage />
}

function MeetingDetail() {
  return <MeetingDetailPage />
}

function ReportingPageWrapper({ exportsOnly = false }: { exportsOnly?: boolean }) {
  const { user } = useAuth()
  return <ReportsPage user={user} exportsOnly={exportsOnly} />
}

function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route
          path="*"
          element={
            <Protected>
              <Shell>
                <Routes>
                  <Route path="/dashboard" element={<Dashboard />} />
                  <Route path="/meeting-requests" element={<Requests />} />
                  <Route path="/meeting-requests/create" element={<CreateRequestPage mode="internal" purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/meeting-requests/create-inter-directorate" element={<CreateRequestPage mode="inter-directorate" purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/meeting-requests/create-multi-directorate" element={<CreateRequestPage mode="multi-directorate" purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/meeting-requests/create-high-authority" element={<CreateRequestPage mode="high-authority" purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/meeting-requests/create-multi-high-authority" element={<CreateRequestPage mode="multi-high-authority" purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/meeting-requests/create-authority-initiated" element={<CreateRequestPage mode="authority-initiated" purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  
                  <Route path="/incoming-meeting-requests" element={<IncomingRequestsPage inboxType="standard" statusLabels={statusLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/incoming-high-authority" element={<IncomingRequestsPage inboxType="high-authority" statusLabels={statusLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/incoming-multi-high-authority" element={<IncomingRequestsPage inboxType="multi-high-authority" statusLabels={statusLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/incoming-authority-initiated" element={<IncomingRequestsPage inboxType="authority-initiated" statusLabels={statusLabels} priorityLabels={priorityLabels} />} />

                  <Route path="/meeting-requests/:id" element={<RequestDetail />} />
                  <Route path="/meeting-requests/:id/edit" element={<EditRequestPage purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
                  <Route path="/meeting-requests/:id/scheduling" element={<Scheduling />} />
                  <Route path="/meeting-requests/:id/multi-scheduling" element={<Scheduling />} />
                  <Route path="/meeting-requests/:id/high-authority-scheduling" element={<Scheduling />} />
                  <Route path="/meeting-requests/:id/multi-high-scheduling" element={<Scheduling />} />
                  <Route path="/meeting-requests/:id/authority-initiated-scheduling" element={<Scheduling />} />

                  <Route path="/approvals" element={<ApprovalsPage priorityLabels={priorityLabels} purposeLabels={purposeLabels} />} />
                  <Route path="/meetings" element={<Meetings />} />
                  <Route path="/meetings/:id/reschedule" element={<MeetingReschedulePage />} />
                  <Route path="/meetings/:id/participants/change" element={<MeetingParticipantChangePage />} />
                  <Route path="/meetings/:id/terminals" element={<MeetingTerminalsPage />} />
                  <Route path="/meetings/:id/attendance" element={<MeetingAttendancePage />} />
                  <Route path="/meetings/:id/execution" element={<MeetingExecutionPage />} />
                  <Route path="/meetings/:id/minutes" element={<MeetingMinutesPage />} />
                  <Route path="/meetings/:id/follow-up" element={<MeetingFollowUpPage />} />
                  <Route path="/meetings/:id/exceptions" element={<MeetingExceptionsPage />} />
                  <Route path="/action-items" element={<ActionItemsPage />} />
                  <Route path="/action-items/:id" element={<ActionItemDetailPage />} />
                  <Route path="/employees/:id/biometrics" element={<EmployeeBiometricsPage />} />
                  <Route path="/meetings/:id" element={<MeetingDetail />} />
                  <Route path="/notifications" element={<NotificationsPage />} />
                  <Route path="/reports" element={<ReportingPageWrapper />} />
                  <Route path="/reports/exports" element={<ReportingPageWrapper exportsOnly />} />
                  <Route path="/admin/organization" element={<OrganizationPage />} />
                  <Route path="/admin/employees" element={<EmployeesPage />} />
                  <Route path="/admin/roles" element={<EmployeesPage isRolesView />} />
                  <Route path="/admin/terminals" element={<TerminalsRegistryPage />} />
                  <Route path="/admin/terminals/:id" element={<TerminalDetailPage />} />
                  <Route path="/admin/biometrics" element={<BiometricsRegistryPage />} />
                  <Route path="/" element={<Navigate to="/dashboard" replace />} />
                </Routes>
              </Shell>
            </Protected>
          }
        />
      </Routes>
    </AuthProvider>
  )
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>
)
