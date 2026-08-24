export type MeetingStatus =
  | 'SCHEDULED'
  | 'IN_PROGRESS'
  | 'HELD'
  | 'COMPLETED'
  | 'POSTPONED'
  | 'CANCELLED'

export type MeetingItem = {
  id: number
  meeting_number: string
  title: string
  description?: string
  agenda?: string
  meeting_date: string
  start_time: string
  end_time: string
  location: string
  status: MeetingStatus
  reschedule_pending?: boolean
  request_id?: number
  request?: {
    id: number
    request_number?: string
    subject: string
    flow_type?: string
    requesting_unit?: {
      id: number
      name: string
    }
    actions?: any[]
  }
  chairperson?: {
    id: number
    full_name: string
    unit?: {
      name: string
    }
  }
  participants?: {
    id: number
    employee_id: number
    role?: string
    participant_type?: string
    attendance_status?: string
    employee?: {
      id: number
      full_name: string
      unit?: {
        name: string
      }
    }
  }[]
  created_at: string
  updated_at: string
}

export type MeetingFilters = {
  search: string
  status: string
  unit_id: string
}

export type ExecutionSession = {
  id: number
  meeting_id: number
  status: 'NOT_STARTED' | 'IN_PROGRESS' | 'ENDED'
  started_at?: string | null
  ended_at?: string | null
  start_notes?: string | null
  end_notes?: string | null
}

export type MeetingExecutionState = {
  meeting_id: number
  status: MeetingStatus
  schedule_version_id?: number | string
  participant_version_id?: number | string
  execution_session?: ExecutionSession | null
}

export type MinutesItem = {
  id?: number
  sequence_number?: number
  agenda_title: string
  discussion_summary: string
}

export type MinutesDecision = {
  id?: number
  decision_number?: string
  decision_text: string
  responsible_unit_id?: number | string
  responsible_employee_id?: number | string
  target_date?: string
  notes?: string
}

export type MinutesReview = {
  id: number
  reviewer_type: string
  organizational_unit_id: number
  status: 'PENDING' | 'ACKNOWLEDGED' | 'CORRECTION_REQUESTED' | 'APPROVED'
  reason?: string | null
  unit?: {
    id: number
    name: string
  }
}

export type MinutesVersion = {
  id: number
  version_number: number
  summary?: string | null
  general_notes?: string | null
  submitted_at?: string | null
  items?: MinutesItem[]
  decisions?: MinutesDecision[]
  reviews?: MinutesReview[]
}

export type MeetingMinutes = {
  id: number
  meeting_id: number
  status: 'DRAFT' | 'UNDER_REVIEW' | 'RETURNED_FOR_CORRECTION' | 'READY_TO_FINALIZE' | 'FINALIZED'
  current_version_id?: number
  current_version?: MinutesVersion | null
}
