export type SchedulingFormValues = {
  meeting_date: string
  start_time: string
  duration_minutes: number
  location: string
  chairperson_employee_id: string | number
  participant_ids: number[]
  agenda: string
}

export type SchedulingStage = 'requested' | 'scheduling' | 'confirmed'
