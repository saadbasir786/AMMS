import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, apiData } from '@/lib/api-client'

export type AuthenticatedUser = {
  id: number
  name: string
  email: string
  employee?: { full_name: string; unit?: { name: string } }
  roles?: { role: string; unit?: { name: string } }[]
}

type AuthState = {
  user: AuthenticatedUser | null
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

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthenticatedUser | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!sessionStorage.getItem('amms_token')) {
      setLoading(false)
      return
    }

    api
      .get('/me')
      .then((response) => setUser(apiData(response)))
      .catch(() => sessionStorage.removeItem('amms_token'))
      .finally(() => setLoading(false))
  }, [])

  const login = async (email: string, password: string) => {
    const data = apiData(await api.post('/login', { email, password }))
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

  return <AuthContext.Provider value={{ user, loading, login, logout }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  return useContext(AuthContext)
}
