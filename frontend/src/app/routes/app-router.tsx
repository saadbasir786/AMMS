import { lazy, Suspense } from 'react'
import { Navigate, Outlet, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { ApplicationShell } from '@/components/layout/application-shell'
import { useAuth, AuthProvider, ProtectedRoute } from '../auth'
import { navigationItems } from '../config/navigation'
import { priorityLabels, purposeLabels, statusLabels } from '../config/labels'
import { RouteFallback } from './route-fallback'

const LoginPage = lazy(() => import('@/features/auth/pages/login-page').then(({ LoginPage: Page }) => ({ default: Page })))
const DashboardPage = lazy(() => import('@/features/dashboard/pages/dashboard-page').then(({ DashboardPage: Page }) => ({ default: Page })))
const RequestsPage = lazy(() => import('@/features/meeting-requests/pages/requests-page').then(({ RequestsPage: Page }) => ({ default: Page })))
const IncomingRequestsPage = lazy(() => import('@/features/meeting-requests/pages/incoming-requests-page').then(({ IncomingRequestsPage: Page }) => ({ default: Page })))
const CreateRequestPage = lazy(() => import('@/features/meeting-requests/pages/create-request-page').then(({ CreateRequestPage: Page }) => ({ default: Page })))
const RequestDetailPage = lazy(() => import('@/features/meeting-requests/pages/request-detail-page').then(({ RequestDetailPage: Page }) => ({ default: Page })))
const EditRequestPage = lazy(() => import('@/features/meeting-requests/pages/edit-request-page').then(({ EditRequestPage: Page }) => ({ default: Page })))
const ApprovalsPage = lazy(() => import('@/features/meeting-requests/pages/approvals-page').then(({ ApprovalsPage: Page }) => ({ default: Page })))
const InternalApprovalDelegationsPage = lazy(() => import('@/features/meeting-requests/pages/internal-approval-delegations-page').then(({ InternalApprovalDelegationsPage: Page }) => ({ default: Page })))
const MinutesApprovalDelegationsPage = lazy(() => import('@/features/meetings/pages/minutes-approval-delegations-page').then(({ MinutesApprovalDelegationsPage: Page }) => ({ default: Page })))
const SchedulingPage = lazy(() => import('@/features/scheduling/pages/scheduling-page').then(({ SchedulingPage: Page }) => ({ default: Page })))
const MeetingsPage = lazy(() => import('@/features/meetings/pages/meetings-page').then(({ MeetingsPage: Page }) => ({ default: Page })))
const MeetingDetailPage = lazy(() => import('@/features/meetings/pages/meeting-detail-page').then(({ MeetingDetailPage: Page }) => ({ default: Page })))
const MeetingExecutionPage = lazy(() => import('@/features/meetings/pages/meeting-execution-page').then(({ MeetingExecutionPage: Page }) => ({ default: Page })))
const MeetingMinutesPage = lazy(() => import('@/features/meetings/pages/meeting-minutes-page').then(({ MeetingMinutesPage: Page }) => ({ default: Page })))
const MeetingReschedulePage = lazy(() => import('@/features/meetings/pages/meeting-reschedule-page').then(({ MeetingReschedulePage: Page }) => ({ default: Page })))
const MeetingParticipantChangePage = lazy(() => import('@/features/meetings/pages/meeting-participant-change-page').then(({ MeetingParticipantChangePage: Page }) => ({ default: Page })))
const MeetingExceptionsPage = lazy(() => import('@/features/meetings/pages/meeting-exceptions-page').then(({ MeetingExceptionsPage: Page }) => ({ default: Page })))
const ActionItemsPage = lazy(() => import('@/features/action-items/pages/action-items-page').then(({ ActionItemsPage: Page }) => ({ default: Page })))
const ActionItemDetailPage = lazy(() => import('@/features/action-items/pages/action-item-detail-page').then(({ ActionItemDetailPage: Page }) => ({ default: Page })))
const MeetingFollowUpPage = lazy(() => import('@/features/action-items/pages/meeting-followup-page').then(({ MeetingFollowUpPage: Page }) => ({ default: Page })))
const MeetingAttendancePage = lazy(() => import('@/features/attendance-biometrics/pages/meeting-attendance-page').then(({ MeetingAttendancePage: Page }) => ({ default: Page })))
const MeetingTerminalsPage = lazy(() => import('@/features/attendance-biometrics/pages/meeting-terminals-page').then(({ MeetingTerminalsPage: Page }) => ({ default: Page })))
const TerminalsRegistryPage = lazy(() => import('@/features/attendance-biometrics/pages/terminals-registry-page').then(({ TerminalsRegistryPage: Page }) => ({ default: Page })))
const TerminalDetailPage = lazy(() => import('@/features/attendance-biometrics/pages/terminal-detail-page').then(({ TerminalDetailPage: Page }) => ({ default: Page })))
const BiometricsRegistryPage = lazy(() => import('@/features/attendance-biometrics/pages/biometrics-registry-page').then(({ BiometricsRegistryPage: Page }) => ({ default: Page })))
const EmployeeBiometricsPage = lazy(() => import('@/features/attendance-biometrics/pages/employee-biometrics-page').then(({ EmployeeBiometricsPage: Page }) => ({ default: Page })))
const ReportsPage = lazy(() => import('@/features/reports/pages/reports-page').then(({ ReportsPage: Page }) => ({ default: Page })))
const OrganizationPage = lazy(() => import('@/features/organization/pages/organization-page').then(({ OrganizationPage: Page }) => ({ default: Page })))
const EmployeesPage = lazy(() => import('@/features/organization/pages/employees-page').then(({ EmployeesPage: Page }) => ({ default: Page })))
const NotificationsPage = lazy(() => import('@/features/organization/pages/notifications-page').then(({ NotificationsPage: Page }) => ({ default: Page })))

function AppShellLayout() {
  const { user, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  return (
    <ApplicationShell user={user} navItems={navigationItems} currentPath={location.pathname} onNavigate={navigate} onLogout={logout}>
      <Outlet />
    </ApplicationShell>
  )
}

function LoginRoute() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  if (user) return <Navigate to="/dashboard" replace />
  return <LoginPage onLogin={async (email, password) => { await login(email, password); navigate('/dashboard') }} />
}

function DashboardRoute() {
  const { user } = useAuth()
  return <DashboardPage user={user} statusLabels={statusLabels} />
}

function RequestDetailRoute() {
  const { user } = useAuth()
  return <RequestDetailPage purposeLabels={purposeLabels} priorityLabels={priorityLabels} currentUser={user} />
}

function SchedulingRoute() {
  const { user } = useAuth()
  return <SchedulingPage currentUser={user} />
}

function ReportsRoute({ exportsOnly = false }: { exportsOnly?: boolean }) {
  const { user } = useAuth()
  return <ReportsPage user={user} exportsOnly={exportsOnly} />
}

export function AppRouter() {
  return (
    <AuthProvider>
      <Suspense fallback={<RouteFallback />}>
        <Routes>
          <Route path="/login" element={<LoginRoute />} />
          <Route element={<ProtectedRoute />}>
            <Route element={<AppShellLayout />}>
              <Route path="/dashboard" element={<DashboardRoute />} />
              <Route path="/meeting-requests" element={<RequestsPage statusLabels={statusLabels} priorityLabels={priorityLabels} purposeLabels={purposeLabels} />} />
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
              <Route path="/meeting-requests/:id" element={<RequestDetailRoute />} />
              <Route path="/meeting-requests/:id/edit" element={<EditRequestPage purposeLabels={purposeLabels} priorityLabels={priorityLabels} />} />
              <Route path="/meeting-requests/:id/scheduling" element={<SchedulingRoute />} />
              <Route path="/meeting-requests/:id/multi-scheduling" element={<SchedulingRoute />} />
              <Route path="/meeting-requests/:id/high-authority-scheduling" element={<SchedulingRoute />} />
              <Route path="/meeting-requests/:id/multi-high-scheduling" element={<SchedulingRoute />} />
              <Route path="/meeting-requests/:id/authority-initiated-scheduling" element={<SchedulingRoute />} />
              <Route path="/approvals" element={<ApprovalsPage priorityLabels={priorityLabels} purposeLabels={purposeLabels} />} />
              <Route path="/internal-meeting-approval-delegations" element={<InternalApprovalDelegationsPage />} />
              <Route path="/minutes-approval-delegations" element={<MinutesApprovalDelegationsPage />} />
              <Route path="/meetings" element={<MeetingsPage />} />
              <Route path="/meetings/:id/reschedule" element={<MeetingReschedulePage />} />
              <Route path="/meetings/:id/participants/change" element={<MeetingParticipantChangePage />} />
              <Route path="/meetings/:id/terminals" element={<MeetingTerminalsPage />} />
              <Route path="/meetings/:id/attendance" element={<MeetingAttendancePage />} />
              <Route path="/meetings/:id/execution" element={<MeetingExecutionPage />} />
              <Route path="/meetings/:id/minutes" element={<MeetingMinutesPage />} />
              <Route path="/meetings/:id/follow-up" element={<MeetingFollowUpPage />} />
              <Route path="/meetings/:id/exceptions" element={<MeetingExceptionsPage />} />
              <Route path="/meetings/:id" element={<MeetingDetailPage />} />
              <Route path="/action-items" element={<ActionItemsPage />} />
              <Route path="/action-items/:id" element={<ActionItemDetailPage />} />
              <Route path="/employees/:id/biometrics" element={<EmployeeBiometricsPage />} />
              <Route path="/notifications" element={<NotificationsPage />} />
              <Route path="/reports" element={<ReportsRoute />} />
              <Route path="/reports/exports" element={<ReportsRoute exportsOnly />} />
              <Route path="/admin/organization" element={<OrganizationPage />} />
              <Route path="/admin/employees" element={<EmployeesPage />} />
              <Route path="/admin/roles" element={<EmployeesPage isRolesView />} />
              <Route path="/admin/terminals" element={<TerminalsRegistryPage />} />
              <Route path="/admin/terminals/:id" element={<TerminalDetailPage />} />
              <Route path="/admin/biometrics" element={<BiometricsRegistryPage />} />
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Route>
          </Route>
        </Routes>
      </Suspense>
    </AuthProvider>
  )
}
