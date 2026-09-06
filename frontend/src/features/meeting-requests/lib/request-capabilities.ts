import { api, apiData } from '@/lib/api-client'

export type MeetingRequestActionCapabilities = {
  edit: boolean
  submit: boolean
  approve: boolean
  approve_with_delegation: boolean
  return_for_revision: boolean
  reject: boolean
  start_scheduling: boolean
  open_scheduling: boolean
  finalize: boolean
}

export async function fetchMeetingRequestActionCapabilities(
  requestId: string | number,
): Promise<MeetingRequestActionCapabilities> {
  const response = await api.get(`/meeting-requests/${requestId}/action-capabilities`)
  return apiData(response)
}
