import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './auth-provider'

export function ProtectedRoute() {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="screen-center" aria-label="در حال بررسی نشست کاربر">
        <div className="spinner" />
      </div>
    )
  }

  return user ? <Outlet /> : <Navigate to="/login" replace />
}
