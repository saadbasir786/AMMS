import { api, apiData } from '@/lib/api-client'
import type { MeetingActionCapabilities } from '../types'

export async function fetchMeetingActionCapabilities(
  meetingId: number | string,
): Promise<MeetingActionCapabilities> {
  const response = await api.get(`/meetings/${meetingId}/action-capabilities`)
  return apiData(response)
}
