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
  agenda_items?: MeetingAgendaItem[]
  meeting_date: string
  start_time: string
  end_time: string
  location: string
  venue_type?: 'INTERNAL_VENUE' | 'EXTERNAL_LOCATION' | 'ONLINE'
  venue_id?: number | null
  venue_snapshot?: Record<string, unknown> | null
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
  action_capabilities?: MeetingActionCapabilities
}

export type MeetingActionCapabilities = {
  execution: { view: boolean; start: boolean; end: boolean }
  minutes: {
    view: boolean
    prepare: boolean
    submit: boolean
    review: boolean
    reviewable_unit_ids: number[]
    delegated_review_unit_ids: number[]
    approve_authority: boolean
    finalize: boolean
    document: boolean
  }
  terminals: { view: boolean; assign: boolean; refresh: boolean; revoke: boolean }
  reschedule: {
    view: boolean
    create: boolean
    propose: boolean
    submit: boolean
    acknowledge: boolean
    return_for_correction: boolean
    request_alternative: boolean
    confirm: boolean
    resolve_alternative: boolean
  }
  participant_change: { view: boolean; create: boolean; manage: boolean; confirm: boolean; approve: boolean; finalize: boolean; return: boolean; reject: boolean }
  exceptions: {
    view: boolean
    create: boolean
    submit: boolean
    withdraw: boolean
    apply: boolean
    acknowledge: boolean
    approve: boolean
  }
  follow_up: { view: boolean; initialize: boolean }
}

export type MeetingFilters = {
  search: string
  status: string
  unit_id: string
}

export type PaginationMeta = {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export type MeetingStats = {
  total: number
  upcoming: number
  completed: number
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

// Reschedule Types
export type ScheduleVersion = {
  id: number
  version_number: number
  meeting_date: string
  start_time: string
  end_time: string
  location: string
  venue_type?: 'INTERNAL_VENUE' | 'EXTERNAL_LOCATION' | 'ONLINE'
  venue_id?: number | null
  venue_snapshot?: Record<string, unknown> | null
  created_at?: string
}

export type RescheduleAcknowledgement = {
  id: number
  organizational_unit_id: number
  acknowledged_at?: string
}

export type RescheduleAlternative = {
  id: number
  organizational_unit_id: number
  reason: string
  status: 'OPEN' | 'RESOLVED'
  unit?: {
    id: number
    name: string
  }
}

export type RescheduleProposal = {
  id: number
  status: 'ACTIVE' | 'DRAFT' | 'SUBMITTED' | 'RETURNED_FOR_CORRECTION' | 'APPROVED' | 'ACCEPTED' | 'SUPERSEDED'
  meeting_date: string
  start_time: string
  end_time: string
  location: string
  venue_type?: 'INTERNAL_VENUE' | 'EXTERNAL_LOCATION' | 'ONLINE'
  venue_id?: number | null
  venue_snapshot?: Record<string, unknown> | null
  comment?: string
  acknowledgements?: RescheduleAcknowledgement[]
}

export type RescheduleCase = {
  id: number
  status: string
  governance_mode: string
  reason: string
  baseline_schedule_version_id: number
  proposals?: RescheduleProposal[]
  alternative_requests?: RescheduleAlternative[]
}

export type MeetingRescheduleData = {
  actions?: WorkspaceAuditItem[]
  actions_meta?: PaginationMeta
  meeting: MeetingItem & {
    request?: any
    authority_context?: any
    units?: any[]
    current_schedule_version_id?: number
  }
  current_schedule_version?: ScheduleVersion | null
  schedule_versions?: ScheduleVersion[]
  schedule_versions_meta?: PaginationMeta
  active_reschedule_case?: RescheduleCase | null
}

// Participant Change Types
export type ParticipantChangeItem = {
  id: number
  operation: 'ADD' | 'REMOVE' | 'REPLACE'
  old_employee_id?: number | null
  new_employee_id?: number | null
  participant_role?: string
  old_employee?: {
    id: number
    full_name: string
  }
  new_employee?: {
    id: number
    full_name: string
  }
}

export type ParticipantChangeCase = {
  id: number
  status: 'DRAFT' | 'RETURNED_FOR_CORRECTION' | 'PENDING_CONFIRMATION' | 'APPROVED' | 'CONFIRMED' | 'REJECTED'
  reason: string
  owner_type: 'UNIT' | 'AUTHORITY'
  owner_unit_id?: number
  baseline_participant_version_id: number
  baseline_schedule_version_id: number
  submitted_at?: string | null
  submitted_by_user_id?: number | null
  submitted_by?: { id: number; name: string } | null
  review_cycle?: number
  approved_by_user_id?: number | null
  approved_by?: { id: number; name: string } | null
  approved_at?: string | null
  owner_unit?: {
    id: number
    name: string
  }
  items?: ParticipantChangeItem[]
}

export type ParticipantVersion = {
  id: number
  version_number: number
  created_at?: string
  created_by?: { id: number; name?: string | null } | null
  members?: any[]
  source_change_case?: {
    id: number
    items?: ParticipantChangeItem[]
  } | null
}

export type MeetingParticipantChangeData = {
  participant_actions?: WorkspaceAuditItem[]
  participant_actions_meta?: PaginationMeta
  latest_director_review?: WorkspaceAuditItem | null
  meeting: MeetingItem & {
    request?: { flow_type?: string }
    current_schedule_version_id?: number
    current_participant_version_id?: number
    authority_context?: any
    units?: any[]
  }
  current_participant_version?: ParticipantVersion | null
  participant_versions?: ParticipantVersion[]
  participant_versions_meta?: PaginationMeta
  current_participants?: any[]
  employee_options?: any[]
  active_participant_change_case?: ParticipantChangeCase | null
}

// Meeting Exceptions Types
export type ExceptionCaseType =
  | 'POSTPONEMENT'
  | 'CANCELLATION'
  | 'REOPENING'
  | 'RESUMPTION'

export type ExceptionReview = {
  reason?: string | null
  action_capabilities?: { acknowledge: boolean; approve: boolean; correction: boolean }
  unit_name?: string | null
  id: number
  reviewer_type: string
  review_requirement: string
  organizational_unit_id?: number
  authority_employee_id?: number
  status: 'PENDING' | 'ACKNOWLEDGED' | 'APPROVED' | 'CORRECTION_REQUESTED'
}

export type ExceptionVersion = {
  administrative_note?: string | null
  replacement_expected?: boolean
  proposed_resume_date?: string | null
  id: number
  version_number: number
  reason: string
  submitted_at?: string | null
}

export type ExceptionCase = {
  action_capabilities?: { edit: boolean; submit: boolean; withdraw: boolean; reject: boolean; apply: boolean; resume: boolean }
  id: number
  case_type: ExceptionCaseType
  status: string
  governance_mode: string
  created_at: string
  baseline?: {
    meeting_status: string
  }
  current_version?: ExceptionVersion | null
  reviews?: ExceptionReview[]
}

export type MeetingExceptionData = {
  meeting: {
    id: number
    meeting_number: string
    title: string
    status: string
    current_schedule_version?: ScheduleVersion | null
    request_status?: string
  }
  data?: ExceptionCase[]
  meta?: PaginationMeta
}

export type MeetingAgendaItem = {
  id: number
  sequence_number: number
  title: string
  description?: string | null
}

export type WorkspaceAuditItem = {
  id: number
  action: string
  comment?: string | null
  metadata?: Record<string, unknown> | null
  created_at?: string
  actor?: { name?: string } | null
}
