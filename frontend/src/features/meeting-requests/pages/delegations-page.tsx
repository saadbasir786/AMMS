import React from 'react'
import { useSearchParams } from 'react-router-dom'
import { ShieldCheck, FileCheck } from 'lucide-react'
import { PageHeader } from '@/components/amms/page-header'
import { cn } from '@/lib/utils'
import { InternalApprovalDelegationsPage } from './internal-approval-delegations-page'
import { MinutesApprovalDelegationsPage } from '@/features/meetings/pages/minutes-approval-delegations-page'

type TabType = 'internal' | 'minutes'

type DelegationsPageProps = {
  initialTab?: TabType
}

export function DelegationsPage({ initialTab }: DelegationsPageProps) {
  const [searchParams, setSearchParams] = useSearchParams()
  const urlTab = searchParams.get('tab') as TabType | null
  const activeTab: TabType = urlTab || initialTab || 'internal'

  const handleTabChange = (tab: TabType) => {
    setSearchParams({ tab })
  }

  return (
    <div className="space-y-6" dir="rtl">
      {/* Unified Hub Header */}
      <PageHeader
        title="مدیریت تفویض صلاحیت‌ها"
        description="مرکز یکپارچه تفویض اختیارات سازمانی ریاست به مدیریت اجرائیه (جلسات داخلی) و معاونت اداره (صورت‌جلسات رسمی)"
      />

      {/* Hub Navigation Tabs */}
      <div className="flex items-center gap-1.5 p-1.5 rounded-2xl bg-muted/50 border border-border/70 overflow-x-auto w-full sm:w-fit shadow-xs">
        <button
          type="button"
          onClick={() => handleTabChange('internal')}
          className={cn(
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
            activeTab === 'internal'
              ? 'bg-background text-foreground shadow-xs'
              : 'text-muted-foreground hover:text-foreground hover:bg-background/50'
          )}
        >
          <ShieldCheck className={cn('size-4', activeTab === 'internal' ? 'text-primary' : 'text-muted-foreground')} />
          <span>تأیید جلسات داخلی</span>
          <span className="text-[10px] px-1.5 py-0.5 rounded-md bg-primary/10 text-primary font-normal">
            مدیریت اجرائیه
          </span>
        </button>

        <button
          type="button"
          onClick={() => handleTabChange('minutes')}
          className={cn(
            'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
            activeTab === 'minutes'
              ? 'bg-background text-foreground shadow-xs'
              : 'text-muted-foreground hover:text-foreground hover:bg-background/50'
          )}
        >
          <FileCheck className={cn('size-4', activeTab === 'minutes' ? 'text-primary' : 'text-muted-foreground')} />
          <span>بررسی و تأیید صورت‌جلسات</span>
          <span className="text-[10px] px-1.5 py-0.5 rounded-md bg-primary/10 text-primary font-normal">
            معاونت اداره
          </span>
        </button>
      </div>

      {/* Active Tab Content */}
      <div className="mt-2">
        {activeTab === 'internal' && <InternalApprovalDelegationsPage embedded={true} />}
        {activeTab === 'minutes' && <MinutesApprovalDelegationsPage embedded={true} />}
      </div>
    </div>
  )
}
