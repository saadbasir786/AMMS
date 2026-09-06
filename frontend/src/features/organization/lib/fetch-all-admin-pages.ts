import { api, apiPage } from '@/lib/api-client'

export async function fetchAllAdminPages<T>(path: string): Promise<T[]> {
  const items: T[] = []
  let page = 1
  let lastPage = 1

  do {
    const response = await api.get(path, { params: { page, per_page: 100 } })
    const payload = apiPage(response)
    items.push(...(payload?.data || []))
    lastPage = Number(payload?.meta?.last_page || 1)
    page += 1
  } while (page <= lastPage)

  return items
}
