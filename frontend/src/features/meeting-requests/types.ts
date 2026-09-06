export type CreateMeetingMode =
  | 'internal'
  | 'inter-directorate'
  | 'multi-directorate'
  | 'high-authority'
  | 'multi-high-authority'
  | 'authority-initiated'

export type MeetingFormValues = {
  requesting_unit_id: string | number
  target_unit_id?: string | number
  target_unit_ids?: number[]
  subject: string
  purpose: string
  priority: string
  description: string
  meeting_justification?: string
  requested_outcome?: string
  preferred_date: string
  preferred_start_time: string
  estimated_duration_minutes: number
  proposed_location: string
  participant_ids: number[]
}

export type { EmployeeItem, OrganizationalUnit } from '@/shared/types'

export type RequestRowItem = {
  id: number | string
  request_number?: string | null
  subject?: string | null
  purpose?: string | null
  priority?: string | null
  status?: string | null
  created_at?: string | null
  flow_type?: string | null
  creator?: { id?: number; name?: string } | null
  requesting_unit?: { name?: string | null } | null
  target_unit?: { name?: string | null } | null
  units?: Array<{ role: string; unit?: { name?: string | null } }>
}

export type PaginationMeta = {
  current_page: number
  last_page: number
  total: number
  from?: number | null
  to?: number | null
  per_page?: number
}

export type RequestStats = {
  total: number
  pending: number
  approved: number
  draft: number
}
