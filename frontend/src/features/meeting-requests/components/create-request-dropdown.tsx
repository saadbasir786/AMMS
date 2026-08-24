import React from 'react'
import {
  FilePlus2,
  Users2,
  Layers,
  ShieldCheck,
  Sparkles,
  ChevronDown,
  Building
} from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'

type CreateOption = {
  to: string
  label: string
  description: string
  icon: React.ElementType
}

const createOptions: CreateOption[] = [
  {
    to: '/meeting-requests/create',
    label: 'جلسه داخلی ریاست',
    description: 'درخواست جلسه اداری درون‌واحدی',
    icon: Building,
  },
  {
    to: '/meeting-requests/create-inter-directorate',
    label: 'جلسه بین دو ریاست',
    description: 'هماهنگی جلسه مشترک میان دو ریاست',
    icon: Users2,
  },
  {
    to: '/meeting-requests/create-multi-directorate',
    label: 'جلسه چندریاستی',
    description: 'تشکیل جلسه میان حداقل سه ریاست',
    icon: Layers,
  },
  {
    to: '/meeting-requests/create-high-authority',
    label: 'جلسه با مقام عالی',
    description: 'درخواست جلسه رسمی با حضور مقام عالی',
    icon: ShieldCheck,
  },
  {
    to: '/meeting-requests/create-multi-high-authority',
    label: 'جلسه چندریاستی با مقام عالی',
    description: 'نشست چند ریاست با حضور مقام عالی',
    icon: ShieldCheck,
  },
  {
    to: '/meeting-requests/create-authority-initiated',
    label: 'جلسه به ابتکار مقام عالی',
    description: 'جلسه صادره بر اساس هدایت مقام عالی',
    icon: Sparkles,
  },
]

export function CreateRequestDropdown() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button size="sm" className="gap-1.5 text-xs text-white !text-white">
          <FilePlus2 className="size-3.5 text-white" />
          <span>ثبت درخواست جدید</span>
          <ChevronDown className="size-3.5 text-white/80" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64 p-1.5">
        {createOptions.map((opt) => {
          const Icon = opt.icon
          return (
            <DropdownMenuItem key={opt.to} asChild className="p-2 cursor-pointer rounded-lg">
              <a href={opt.to} className="flex items-start gap-2.5 w-full">
                <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                  <Icon className="size-3.5" />
                </div>
                <div className="space-y-0.5 min-w-0">
                  <strong className="text-xs font-bold text-foreground block truncate">
                    {opt.label}
                  </strong>
                  <span className="text-[10px] text-muted-foreground block truncate">
                    {opt.description}
                  </span>
                </div>
              </a>
            </DropdownMenuItem>
          )
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
