import React, { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowRight, RotateCw, AlertCircle, Loader2 } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { api, apiData } from '@/lib/api-client'
import { fetchAllPages } from '@/lib/fetch-all-pages'
import { MeetingRequestForm } from '../components/meeting-request-form'
import type { ActiveVenueOption } from '../components/proposed-location-field'
import { FormSideGuide } from '../components/form-side-guide'
import { SchedulingStageBar } from '@/features/scheduling/components/scheduling-stage-bar'
import type {
  CreateMeetingMode,
  MeetingFormValues,
  OrganizationalUnit,
  EmployeeItem
} from '../types'

type CreateRequestPageProps = {
  mode?: CreateMeetingMode
  purposeLabels: Record<string, string>
  priorityLabels: Record<string, string>
}

export function CreateRequestPage({
  mode = 'internal',
  purposeLabels,
  priorityLabels,
}: CreateRequestPageProps) {
  const navigate = useNavigate()
  const [units, setUnits] = useState<OrganizationalUnit[]>([])
  const [employees, setEmployees] = useState<EmployeeItem[]>([])
  const [venues, setVenues] = useState<ActiveVenueOption[]>([])
  const [currentUser, setCurrentUser] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [fetchError, setFetchError] = useState('')

  const fetchData = async () => {
    setLoading(true)
    setFetchError('')
    try {
      const [resUnits, resEmployees, resMe, resVenues] = await Promise.all([
        api.get('/organizational-units'),
        fetchAllPages<EmployeeItem>('/employees'),
        api.get('/me').catch(() => ({ data: { data: null } })),
        mode === 'internal'
          ? api.get('/meeting-venues', { params: { per_page: 50 } }).catch(() => null)
          : Promise.resolve(null),
      ])
      setUnits(apiData(resUnits) || [])
      setEmployees(resEmployees)
      setCurrentUser(apiData(resMe) || null)
      setVenues(apiData(resVenues) || [])
    } catch (err: any) {
      console.error('Error loading form metadata:', err)
      setFetchError(
        err?.response?.data?.message || 'بارگذاری اطلاعات پایه با خطا مواجه شد. لطفاً دوباره تلاش نمایید.'
      )
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchData()
  }, [])

  const handleSubmit = async (values: MeetingFormValues, submitForApproval: boolean) => {
    let endpoint = '/meeting-requests'
    let submitEndpoint = ''
    let payload: Record<string, any> = {
      requesting_unit_id: Number(values.requesting_unit_id),
      subject: values.subject,
      purpose: values.purpose,
      priority: values.priority,
      description: values.description,
      preferred_date: values.preferred_date || null,
      preferred_start_time: values.preferred_start_time || null,
      estimated_duration_minutes: values.estimated_duration_minutes,
      proposed_location: values.proposed_location,
      participant_ids: values.participant_ids,
    }

    if (mode === 'inter-directorate') {
      endpoint = '/meeting-requests/inter-directorate'
      payload.target_unit_id = Number(values.target_unit_id)
      submitEndpoint = 'submit-inter-directorate'
    } else if (mode === 'multi-directorate') {
      endpoint = '/meeting-requests/multi-directorate'
      payload.target_unit_ids = (values.target_unit_ids || []).map(Number)
      submitEndpoint = 'submit-multi'
    } else if (mode === 'high-authority') {
      endpoint = '/meeting-requests/high-authority'
      payload.meeting_justification = values.meeting_justification || ''
      payload.requested_outcome = values.requested_outcome || ''
      submitEndpoint = 'submit-high-authority'
    } else if (mode === 'multi-high-authority') {
      endpoint = '/meeting-requests/multi-high-authority'
      payload.target_unit_ids = (values.target_unit_ids || []).map(Number)
      payload.meeting_justification = values.meeting_justification || ''
      payload.requested_outcome = values.requested_outcome || ''
      submitEndpoint = 'submit-multi-high'
    } else if (mode === 'authority-initiated') {
      endpoint = '/meeting-requests/authority-initiated'
      payload.meeting_justification = values.meeting_justification || ''
      payload.requested_outcome = values.requested_outcome || ''
      submitEndpoint = 'submit-authority-initiated'
    } else {
      submitEndpoint = 'submit'
    }

    const res = await api.post(endpoint, payload)
    const item = apiData(res)

    if (submitForApproval && item?.id && submitEndpoint) {
      await api.post(`/meeting-requests/${item.id}/${submitEndpoint}`)
    }
    navigate(`/meeting-requests/${item.id}`)
  }

  const getPageInfo = () => {
    switch (mode) {
      case 'inter-directorate':
        return {
          title: 'ثبت درخواست جلسه بین دو ریاست',
          description: 'ثبت و ارسال پیشنهاد جلسه مشترک میان دو ریاست فعال در سازمان',
        }
      case 'multi-directorate':
        return {
          title: 'ثبت درخواست جلسه چندریاستی',
          description: 'ثبت و هماهنگی جلسه اداری مشترک میان چند ریاست فعال در سازمان',
        }
      case 'high-authority':
        return {
          title: 'ثبت درخواست جلسه با مقام عالی',
          description: 'ثبت و ارسال پیشنهاد برگزاری جلسه رسمی با حضور مقام عالی سازمان',
        }
      case 'multi-high-authority':
        return {
          title: 'ثبت درخواست چندریاستی با مقام عالی',
          description: 'ثبت و هماهنگی نشست چند ریاست با حضور و هدایت مقام عالی',
        }
      case 'authority-initiated':
        return {
          title: 'ثبت جلسه به ابتکار مقام عالی',
          description: 'ثبت جلسه بر اساس دستور و هدایت مستقیم صادره از مقام عالی',
        }
      default:
        return {
          title: 'ثبت درخواست جلسه داخلی',
          description: 'ثبت و ارسال پیشنهاد جلسه اداری درون‌واحدی جهت بررسی و تأیید رئیس ریاست',
        }
    }
  }

  const { title: pageTitle, description: pageDescription } = getPageInfo()

  return (
    <div className="space-y-6">
      {/* Header with back button */}
      <PageHeader
        title={pageTitle}
        description={pageDescription}
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={() => navigate('/meeting-requests')}
            className="text-xs gap-1.5"
          >
            <ArrowRight className="size-3.5" />
            بازگشت به فهرست
          </Button>
        }
      />

      {mode === 'internal' && <SchedulingStageBar status="DRAFT" />}

      {/* Grid: Form on the Right / Main, Guide Card on the Left */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div className="lg:col-span-2">
          {loading ? (
            <div className="space-y-6">
              <Card className="border border-border/70 p-6 space-y-4">
                <div className="flex items-center gap-2">
                  <Loader2 className="size-4 animate-spin text-primary" />
                  <span className="text-xs text-muted-foreground font-medium">
                    در حال بارگذاری اطلاعات ساختار سازمانی و کارمندان...
                  </span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                  <div className="h-10 rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                  <div className="h-10 rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
                </div>
                <div className="h-24 rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
              </Card>
            </div>
          ) : fetchError ? (
            <div className="p-8 text-center bg-destructive/5 border border-destructive/20 rounded-2xl space-y-3">
              <AlertCircle className="size-6 text-destructive mx-auto" />
              <p className="text-xs text-destructive font-semibold">{fetchError}</p>
              <Button
                variant="outline"
                size="sm"
                onClick={fetchData}
                className="text-xs gap-1.5 mx-auto"
              >
                <RotateCw className="size-3.5" />
                تلاش مجدد
              </Button>
            </div>
          ) : (
            <MeetingRequestForm
              mode={mode}
              units={units}
              employees={employees}
              venues={venues}
              currentUser={currentUser}
              purposeLabels={purposeLabels}
              priorityLabels={priorityLabels}
              onSubmit={handleSubmit}
              onCancel={() => navigate('/meeting-requests')}
            />
          )}
        </div>

        <div className="lg:col-span-1">
          <FormSideGuide
            mode={
              mode === 'inter-directorate'
                ? 'inter-directorate'
                : mode === 'multi-directorate' || mode === 'multi-high-authority'
                ? 'multi'
                : mode === 'high-authority' || mode === 'authority-initiated'
                ? 'authority'
                : 'internal'
            }
          />
        </div>
      </div>
    </div>
  )
}
