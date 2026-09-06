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
      <div className="flex items-center gap-2 p-1.5 rounded-2xl bg-muted/70 border border-border/80 overflow-x-auto w-full sm:w-fit shadow-xs">
        <button
          type="button"
          onClick={() => handleTabChange('internal')}
          className={cn(
            'flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap',
            activeTab === 'internal'
              ? 'bg-primary text-primary-foreground shadow-md ring-2 ring-primary/25'
              : 'text-muted-foreground hover:text-foreground hover:bg-background/60'
          )}
        >
          <ShieldCheck
            className={cn(
              'size-4 shrink-0',
              activeTab === 'internal' ? 'text-primary-foreground' : 'text-muted-foreground'
            )}
          />
          <span>تأیید جلسات داخلی</span>
          <span
            className={cn(
              'text-[10px] px-2 py-0.5 rounded-md font-medium transition-colors',
              activeTab === 'internal'
                ? 'bg-primary-foreground/20 text-primary-foreground'
                : 'bg-muted-foreground/10 text-muted-foreground'
            )}
          >
            مدیریت اجرائیه
          </span>
        </button>

        <button
          type="button"
          onClick={() => handleTabChange('minutes')}
          className={cn(
            'flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap',
            activeTab === 'minutes'
              ? 'bg-primary text-primary-foreground shadow-md ring-2 ring-primary/25'
              : 'text-muted-foreground hover:text-foreground hover:bg-background/60'
          )}
        >
          <FileCheck
            className={cn(
              'size-4 shrink-0',
              activeTab === 'minutes' ? 'text-primary-foreground' : 'text-muted-foreground'
            )}
          />
          <span>بررسی و تأیید صورت‌جلسات</span>
          <span
            className={cn(
              'text-[10px] px-2 py-0.5 rounded-md font-medium transition-colors',
              activeTab === 'minutes'
                ? 'bg-primary-foreground/20 text-primary-foreground'
                : 'bg-muted-foreground/10 text-muted-foreground'
            )}
          >
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
