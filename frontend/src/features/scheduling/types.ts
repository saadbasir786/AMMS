export type SchedulingFormValues = {
  meeting_date: string
  start_time: string
  duration_minutes: number
  location: string
  venue_type?: 'INTERNAL_VENUE' | 'EXTERNAL_LOCATION' | 'ONLINE'
  venue_id?: number | string | null
  chairperson_employee_id: string | number
  participant_ids: number[]
  agenda: string
  agenda_items: Array<{ title: string; description: string }>
}

export type SchedulingStage = 'requested' | 'scheduling' | 'confirmed'
