import { api, apiData } from '@/lib/api-client'
import type { ActionItemCapabilities } from '../types'

export async function fetchActionItemCapabilities(
  actionItemId: number | string,
): Promise<ActionItemCapabilities> {
  const response = await api.get(`/action-items/${actionItemId}/action-capabilities`)
  return apiData(response)
}
