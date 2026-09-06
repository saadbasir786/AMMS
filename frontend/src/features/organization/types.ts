export type OrganizationalUnitType = 'DIRECTORATE' | 'MANAGEMENT' | 'DEPARTMENT'

export type OrganizationalUnit = {
  id: number
  name: string
  code: string
  unit_type?: string
  status?: string
  parent_id?: number | null
  description?: string
  head_employee_id?: number | null
  head_employee?: {
    id: number
    full_name: string
    position?: string
  }
  children?: OrganizationalUnit[]
}

export type EmployeePosition = {
  id: number
  title: string
  code?: string
  position_type?: string | null
  status?: 'ACTIVE' | 'INACTIVE'
  organizational_unit_id?: number
  unit?: {
    id: number
    name: string
  } | null
}

export type EmployeeRole = {
  id: number
  role: string
  organizational_unit_id?: number
  unit?: {
    id: number
    name: string
  }
}

export type Employee = {
  id: number
  employee_number: string
  first_name?: string
  last_name?: string
  father_name?: string | null
  full_name: string
  email?: string
  phone?: string
  status?: string
  organizational_unit_id?: number
  unit?: {
    id: number
    name: string
    code?: string
  }
  position?: EmployeePosition | null
  roles?: EmployeeRole[]
  biometric_status?: string
}

export type UserRoleScope = {
  id: number
  role: string
  organizational_unit_id: number | null
  unit?: { id: number; name: string } | null
}

export type AdminUser = {
  id: number
  name: string
  email: string
  status: 'ACTIVE' | 'INACTIVE'
  employee_id?: number | null
  employee?: Employee | null
  roles?: UserRoleScope[]
}

export type UserAccessAudit = {
  id: number
  action: string
  role?: string | null
  organizational_unit_id?: number | null
  unit?: { id: number; name: string } | null
  actor?: { id: number; name: string; email: string } | null
  metadata?: Record<string, unknown> | null
  created_at: string
}

export type SystemNotification = {
  id: number
  title: string
  message: string
  notification_type?: string
  read_at?: string | null
  created_at: string
  action_url?: string
  meta?: Record<string, any>
}
