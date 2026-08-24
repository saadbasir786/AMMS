export type ReportType =
  | 'MEETING_REGISTER'
  | 'MEETING_REQUEST_REGISTER'
  | 'ATTENDANCE_REGISTER'
  | 'DECISION_REGISTER'
  | 'ACTION_ITEM_REGISTER'
  | 'EXCEPTION_LIFECYCLE_REGISTER'
  | 'HIGH_AUTHORITY_MEETING_REGISTER'

export type ReportTypeOption = {
  id: ReportType
  label: string
  endpoint: string
  description?: string
}

export type MetricCard = {
  metric_id: string
  label: string
  value: number | string | null
  unit?: string
}

export type ChartSeries = {
  metric_id: string
  title?: string
  labels: string[]
  series: number[]
}

export type DashboardReportData = {
  scope?: {
    organization_wide?: boolean
    [key: string]: any
  }
  attribution_mode?: string
  period?: {
    from: string
    to: string
  }
  as_of?: string
  cards: MetricCard[]
  charts: ChartSeries[]
}

export type ExportJob = {
  uuid: string
  report_type: string
  format: 'PDF' | 'XLSX'
  status: 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED'
  requested_at: string
  completed_at?: string
  download_url?: string
}

export type ReportsFilterParams = {
  from: string
  to: string
  search?: string
  meeting_status?: string
  per_page?: number
}
