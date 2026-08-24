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

export const apiData = (res: any) => res.data?.data ?? res.data

export const apiPage = (res: any) => {
  const body = res.data
  const payload = body?.data ?? body
  return Array.isArray(payload)
    ? { data: payload, meta: body?.meta || null, links: body?.links || null }
    : payload
}

export const apiError = (err: any) =>
  err?.response?.data?.message ||
  err?.response?.data?.error?.message ||
  'خطایی در برقراری ارتباط با سرور رخ داد.'
