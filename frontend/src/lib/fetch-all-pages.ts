import { api, apiPage } from './api-client'

export async function fetchAllPages<T>(path: string, params: Record<string, unknown> = {}): Promise<T[]> {
  const items: T[] = []
  let page = 1
  let lastPage = 1

  do {
    const response = await api.get(path, { params: { ...params, page, per_page: 50 } })
    const payload = apiPage(response)
    items.push(...(payload?.data || []))
    lastPage = Number(payload?.meta?.last_page || 1)
    page += 1
  } while (page <= lastPage)

  return items
}
