import { fallbackAmmsMetadata } from './lib/amms-metadata'

export type OrganizationalUnitType =
  | 'GENERAL_PRESIDENCY'
  | 'DEPUTY_PRESIDENCY'
  | 'GENERAL_DIRECTORATE'
  | 'DIRECTORATE'
  | 'ATTACHED_AGENCY'
  | 'DEPUTY_DIRECTORATE'
  | 'AMIRIATE'
  | 'GENERAL_MANAGEMENT'
  | 'MANAGEMENT'
  | 'SECTION'
  | 'OTHER'
  | 'DEPARTMENT'
  | 'OFFICE'
  | 'HIGH_AUTHORITY'
  | 'AUTHORITY_OFFICE'

export const FORMAL_GOVERNANCE_UNIT_TYPES = fallbackAmmsMetadata.formal_governance_unit_types

export function isFormalGovernanceUnit(unit: { unit_type?: string } | null | undefined): boolean {
  return !!unit?.unit_type && (FORMAL_GOVERNANCE_UNIT_TYPES as readonly string[]).includes(unit.unit_type)
}

export type PositionType =
  | 'DIRECTOR'
  | 'DEPUTY_DIRECTOR'
  | 'AMIR'
  | 'GENERAL_MANAGER'
  | 'EXPERT'
  | 'MANAGER'
  | 'EMPLOYEE'
  | 'EXECUTIVE_MANAGEMENT'
  | 'HIGH_AUTHORITY'
  | 'AUTHORITY_OFFICE'
  | 'EXECUTIVE_SECRETARY'
  | 'OTHER'

export type OrganizationalUnit = {
  id: number
  organization_id?: number
  name: string
  code: string
  unit_type?: string
  status?: string
  parent_id?: number | null
  parent?: {
    id: number
    name: string
    code?: string
  } | null
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
  unit_scope_ids?: number[]
  unit?: {
    id: number
    name: string
    code?: string
  }
  position_id?: number | null
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

export type CapabilityScope = {
  id: number
  user_id: number
  capability: string
  organizational_unit_id: number
  unit?: { id: number; name: string } | null
  granted_at?: string
  expires_at?: string | null
  grant_reason?: string | null
}

export type AdminUser = {
  id: number
  name: string
  email: string
  status: 'ACTIVE' | 'INACTIVE'
  employee_id?: number | null
  employee?: Employee | null
  roles?: UserRoleScope[]
  capabilities?: CapabilityScope[]
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
  type?: string
  title: string
  message: string
  notification_type?: string
  data?: Record<string, any>
  read_at?: string | null
  created_at: string
  action_url?: string | null
  meta?: Record<string, any>
}

export type PaginationMeta = {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}
