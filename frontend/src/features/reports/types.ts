export type ReportType =
  | 'MEETING_REGISTER'
  | 'MEETING_REQUEST_REGISTER'
  | 'ATTENDANCE_REGISTER'
  | 'DECISION_REGISTER'
  | 'ACTION_ITEM_REGISTER'
  | 'EXCEPTION_LIFECYCLE_REGISTER'
  | 'HIGH_AUTHORITY_MEETING_REGISTER'

export type ReportTypeConfig = {
  id: ReportType
  title: string
  subtitle: string
  endpoint: string
  icon: string
  category: 'meetings' | 'requests' | 'followup' | 'attendance' | 'governance'
}

export type MetricCard = {
  metric_id: string
  label: string
  value: number | string | null
  unit?: string
  change_rate?: number
  trend?: 'up' | 'down' | 'neutral'
}

export type ChartSeries = {
  metric_id: string
  title?: string
  labels: string[]
  series: number[]
  unit?: string
}

export type DashboardReportData = {
  scope?: {
    organization_wide?: boolean
    involved_unit_id?: number
    user_id?: number
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
  involved_unit_id?: number
  sort?: string
  page?: number
  per_page?: number
}
