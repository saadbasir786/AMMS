export type ActionItemStatus =
  | 'OPEN'
  | 'IN_PROGRESS'
  | 'BLOCKED'
  | 'PENDING_VERIFICATION'
  | 'COMPLETED'
  | 'CANCELLED'

export type ActionItemUpdateType =
  | 'STARTED'
  | 'PROGRESS'
  | 'BLOCKED_UPDATE'
  | 'UNBLOCKED'
  | 'COMPLETION_SUBMITTED'
  | 'COMPLETION_VERIFIED'
  | 'COMPLETION_RETURNED'
  | 'CANCELLED'

export type ActionItemUpdate = {
  id: number
  update_type: ActionItemUpdateType
  progress_percent?: number | null
  note?: string | null
  created_at: string
  actor?: {
    id: number
    name: string
  }
}

export type ActionItemEvidence = {
  id: number
  original_filename: string
  mime_type: string
  file_size: number
  download_url: string
  created_at: string
}

export type ActionItem = {
  id: number
  action_number: string
  title: string
  description?: string
  decision_text?: string
  decision_number?: string
  meeting_id?: number
  meeting_number?: string
  status: ActionItemStatus
  progress_percent: number
  due_date?: string
  is_overdue?: boolean
  days_overdue?: number
  responsible_unit_id?: number
  responsible_unit?: {
    id: number
    name: string
  }
  assigned_employee_id?: number
  assigned_employee?: {
    id: number
    full_name: string
    name?: string
  }
  updates?: ActionItemUpdate[]
  evidence?: ActionItemEvidence[]
  created_at: string
  updated_at: string
}

export type ActionItemFilters = {
  search: string
  status: string
  overdueOnly: boolean
}

export type FollowUpDecision = {
  id: number
  decision_number: string
  decision_text: string
  classification: 'UNCLASSIFIED' | 'NO_ACTION_REQUIRED' | 'ACTION_REQUIRED'
  status: 'ACTIVE' | 'CLOSED'
  responsible_unit_id?: number
  responsible_employee_id?: number
  target_date?: string
  reason?: string
  action_items?: ActionItem[]
}

export type MeetingFollowUpSummary = {
  total_decisions: number
  action_required: number
  no_action_required: number
  open: number
  in_progress: number
  blocked: number
  pending_verification: number
  completed: number
  overdue: number
}

export type MeetingFollowUpData = {
  meeting: {
    id: number
    meeting_number: string
    title: string
    status: string
  }
  minutes?: {
    status: string
    version_number: number
    finalized_at?: string
  }
  decision_count: number
  followup_initialized: boolean
  summary?: MeetingFollowUpSummary
  decisions?: FollowUpDecision[]
}
