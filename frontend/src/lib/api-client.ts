import axios from 'axios'

export const api = axios.create({
  baseURL: '/api',
})

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('amms_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export const apiData = (res: any) => {
  const body = res?.data

  // Preserve an explicit `data: null` payload (for example, a meeting with
  // no minutes draft yet) instead of falling back to the whole response body.
  return body && Object.prototype.hasOwnProperty.call(body, 'data')
    ? body.data
    : body
}

export const apiPage = (res: any) => {
  const body = res.data
  const payload = body?.data ?? body
  return Array.isArray(payload)
    ? { data: payload, meta: body?.meta || null, links: body?.links || null, stats: body?.stats || null }
    : payload
}

export const apiError = (err: any) =>
  err?.response?.data?.message ||
  err?.response?.data?.error?.message ||
  'خطایی در برقراری ارتباط با سرور رخ داد.'
