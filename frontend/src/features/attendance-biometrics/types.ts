export type TerminalStatus =
  | 'ACTIVE'
  | 'INACTIVE'
  | 'MAINTENANCE'
  | 'REVOKED'

export type TerminalAssignmentStatus =
  | 'ASSIGNED'
  | 'ACTIVE'
  | 'STALE'
  | 'REVOKED'
  | 'EXPIRED'

export type Terminal = {
  id: number
  uuid: string
  code: string
  name: string
  serial_number?: string
  manufacturer?: string
  model?: string
  platform?: string
  os_version?: string
  app_version?: string
  status: TerminalStatus
  last_seen_at?: string
  current_session_id?: string | null
  organization_id?: number
  organization?: {
    id: number
    name: string
  }
  assignments?: TerminalAssignment[]
}

export type TerminalAssignment = {
  id: number
  meeting_id: number
  terminal_id: number
  baseline_schedule_version_id: number
  baseline_participant_version_id: number
  valid_from: string
  valid_until: string
  status: TerminalAssignmentStatus
  is_stale?: boolean
  schedule_changed?: boolean
  participants_changed?: boolean
  terminal?: Terminal
  meeting?: {
    id: number
    title: string
    meeting_number?: string
  }
}

export type MeetingTerminalsData = {
  meeting: {
    id: number
    title: string
    meeting_number?: string
    status: string
    current_schedule_version_id?: number
    current_participant_version_id?: number
  }
  assignments: TerminalAssignment[]
  assignments_meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  stats?: {
    total: number
    active: number
    revoked: number
  }
  available_terminals: Terminal[]
}

export type AttendanceMethod = 'FINGERPRINT' | 'MANUAL' | 'CARD' | 'PASSWORD'

export type AttendanceRecord = {
  uuid: string
  meeting_id: number
  employee_id: number
  attendance_method: AttendanceMethod
  participant_version_id?: number
  recorded_at_server?: string
  manual_reason?: string
  employee?: {
    id: number
    full_name: string
    employee_number?: string
    unit?: {
      name: string
    }
  }
}

export type MeetingAttendanceData = {
  meeting?: {
    id: number
    title: string
    meeting_number?: string
    status: string
  }
  records: AttendanceRecord[]
  official_count?: number
  participants_count?: number
  records_meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  stats?: {
    total: number
    biometric: number
    manual: number
  }
}

export type BiometricTemplate = {
  uuid: string
  finger_position: string
  version_number: number
  status: 'ACTIVE' | 'REVOKED' | 'ARCHIVED'
  quality_score?: number
  enrolled_at?: string
}

export type EmployeeBiometricData = {
  biometric_status: 'ENROLLED' | 'NOT_ENROLLED' | 'INCOMPLETE'
  active_finger_count: number
  required_active_fingers: number
  employee?: {
    id: number
    full_name: string
    employee_number: string
    unit?: {
      name: string
    }
  }
  templates?: BiometricTemplate[]
  templates_meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
