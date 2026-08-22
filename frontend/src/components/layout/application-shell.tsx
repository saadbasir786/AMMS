import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import {
  Bell,
  ChevronDown,
  ChevronLeft,
  LogOut,
  Menu,
  ShieldCheck,
} from 'lucide-react'

import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Separator } from '@/components/ui/separator'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { cn } from '@/lib/utils'

export type ApplicationShellRole = {
  role: string
  unit?: { name?: string | null } | null
}

export type ApplicationShellUser = {
  name?: string | null
  employee?: {
    full_name?: string | null
    unit?: { name?: string | null } | null
  } | null
  roles?: ApplicationShellRole[] | null
}

export type ApplicationShellNavItem = {
  to: string
  label: string
  icon: LucideIcon
  role?: string
  roles?: string[]
}

type ApplicationShellProps = {
  children: ReactNode
  user: ApplicationShellUser | null
  navItems: ApplicationShellNavItem[]
  currentPath: string
  onNavigate: (path: string) => void
  onLogout: () => void | Promise<void>
}

const roleLabels: Record<string, string> = {
  SYSTEM_ADMIN: 'مدیر سیستم',
  EMPLOYEE: 'کارمند',
  DIRECTOR: 'رئیس ریاست',
  EXECUTIVE_MANAGEMENT: 'مدیریت اجرائیه',
  AUTHORITY_OFFICE: 'دفتر مقام',
  EXECUTIVE_SECRETARY: 'سکرتریت اجرائیه',
  HIGH_AUTHORITY: 'مقام عالی',
}

function userHasRole(user: ApplicationShellUser | null, role: string) {
  return Boolean(user?.roles?.some((item) => item.role === role))
}

function canSeeItem(user: ApplicationShellUser | null, item: ApplicationShellNavItem) {
  if (!item.role && !item.roles) return true
  if (item.role) return userHasRole(user, item.role)
  return Boolean(item.roles?.some((role) => userHasRole(user, role)))
}

function itemIsActive(path: string, target: string) {
  return path === target || path.startsWith(`${target}/`)
}

function displayName(user: ApplicationShellUser | null) {
  return user?.employee?.full_name || user?.name || 'کاربر سیستم'
}

function displayRole(user: ApplicationShellUser | null) {
  const role = user?.roles?.[0]?.role
  return role ? roleLabels[role] || role : 'کاربر سیستم'
}

function initials(user: ApplicationShellUser | null) {
  return displayName(user).trim().slice(0, 1) || 'ک'
}

function Navigation({
  user,
  items,
  currentPath,
  onNavigate,
  mobile = false,
}: {
  user: ApplicationShellUser | null
  items: ApplicationShellNavItem[]
  currentPath: string
  onNavigate: (path: string) => void
  mobile?: boolean
}) {
  const visibleItems = items.filter((item) => canSeeItem(user, item))

  return (
    <nav aria-label="ناوبری اصلی" className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-3">
      {visibleItems.map((item) => {
        const Icon = item.icon
        const active = itemIsActive(currentPath, item.to)
        const button = (
          <button
            key={item.to}
            type="button"
            aria-current={active ? 'page' : undefined}
            onClick={() => onNavigate(item.to)}
            className={cn(
              'group flex min-h-10 w-full items-center gap-3 rounded-xl px-3 py-2 text-start text-[13px] font-medium transition-colors',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
              active
                ? 'bg-primary/10 text-primary shadow-[inset_-3px_0_0_var(--amms-ui-primary)]'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            )}
          >
            <span
              className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                active ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground group-hover:text-foreground'
              )}
            >
              <Icon className="size-4" />
            </span>
            <span className="truncate">{item.label}</span>
          </button>
        )

        if (!mobile) return button

        return (
          <SheetClose asChild key={item.to}>
            {button}
          </SheetClose>
        )
      })}
    </nav>
  )
}

function Brand() {
  return (
    <div className="flex h-[76px] items-center gap-3 px-5">
      <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-lg font-extrabold text-primary-foreground shadow-sm">
        ا
      </div>
      <div className="min-w-0">
        <strong className="block truncate text-sm font-bold text-foreground">جلسات اداری</strong>
        <span className="mt-0.5 block truncate text-[11px] text-muted-foreground">AMMS · سیستم مدیریت جلسات</span>
      </div>
    </div>
  )
}

function UserCard({ user }: { user: ApplicationShellUser | null }) {
  return (
    <div className="mx-3 rounded-xl border border-border/70 bg-muted/45 p-3">
      <div className="flex items-center gap-3">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
          {initials(user)}
        </div>
        <div className="min-w-0">
          <strong className="block truncate text-xs font-semibold text-foreground">{displayName(user)}</strong>
          <span className="mt-0.5 block truncate text-[10px] text-muted-foreground">{displayRole(user)}</span>
        </div>
      </div>
    </div>
  )
}

function Sidebar({
  user,
  navItems,
  currentPath,
  onNavigate,
  onLogout,
}: Omit<ApplicationShellProps, 'children'>) {
  return (
    <aside className="sticky top-0 hidden h-dvh min-h-0 flex-col border-e border-border bg-card lg:flex">
      <Brand />
      <Separator />
      <div className="py-4">
        <UserCard user={user} />
      </div>
      <Navigation user={user} items={navItems} currentPath={currentPath} onNavigate={onNavigate} />
      <div className="mt-auto border-t border-border p-3">
        <div className="mb-2 flex items-center gap-2 rounded-lg px-2 py-2 text-[10px] text-muted-foreground">
          <ShieldCheck className="size-4 text-primary" />
          <span>محیط داخلی مصئون</span>
        </div>
        <Button variant="ghost" className="w-full justify-start gap-2 text-muted-foreground hover:text-destructive" onClick={() => void onLogout()}>
          <LogOut className="size-4" />
          خروج از سیستم
        </Button>
      </div>
    </aside>
  )
}

function MobileNavigation({
  user,
  navItems,
  currentPath,
  onNavigate,
  onLogout,
}: Omit<ApplicationShellProps, 'children'>) {
  return (
    <Sheet>
      <SheetTrigger asChild>
        <Button variant="ghost" size="icon" className="lg:hidden" aria-label="بازکردن منوی اصلی">
          <Menu className="size-5" />
        </Button>
      </SheetTrigger>
      <SheetContent side="right" className="w-[88vw] max-w-[320px] gap-0 p-0" dir="rtl">
        <SheetHeader className="sr-only">
          <SheetTitle>منوی اصلی</SheetTitle>
          <SheetDescription>دسترسی به بخش‌های سیستم مدیریت جلسات اداری</SheetDescription>
        </SheetHeader>
        <Brand />
        <Separator />
        <div className="py-4">
          <UserCard user={user} />
        </div>
        <Navigation user={user} items={navItems} currentPath={currentPath} onNavigate={onNavigate} mobile />
        <div className="border-t border-border p-3">
          <SheetClose asChild>
            <Button variant="ghost" className="w-full justify-start gap-2 text-muted-foreground hover:text-destructive" onClick={() => void onLogout()}>
              <LogOut className="size-4" />
              خروج از سیستم
            </Button>
          </SheetClose>
        </div>
      </SheetContent>
    </Sheet>
  )
}

function Header({
  user,
  navItems,
  currentPath,
  onNavigate,
  onLogout,
}: Omit<ApplicationShellProps, 'children'>) {
  const currentItem = [...navItems]
    .filter((item) => canSeeItem(user, item) && itemIsActive(currentPath, item.to))
    .sort((a, b) => b.to.length - a.to.length)[0]

  return (
    <header className="sticky top-0 z-40 flex h-16 items-center border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/85 md:px-6">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <MobileNavigation
          user={user}
          navItems={navItems}
          currentPath={currentPath}
          onNavigate={onNavigate}
          onLogout={onLogout}
        />
        <div className="hidden min-w-0 items-center gap-2 text-xs text-muted-foreground sm:flex">
          <span className="shrink-0">اداره نمونه</span>
          <ChevronLeft className="size-3.5 shrink-0" />
          <span className="truncate font-medium text-foreground">{currentItem?.label || 'مدیریت جلسات'}</span>
        </div>
        <div className="min-w-0 sm:hidden">
          <span className="block truncate text-sm font-semibold text-foreground">{currentItem?.label || 'مدیریت جلسات'}</span>
        </div>
      </div>

      <div className="flex items-center gap-1.5">
        <Button variant="ghost" size="icon" className="relative" aria-label="اعلان‌ها" onClick={() => onNavigate('/notifications')}>
          <Bell className="size-[18px]" />
          <span className="absolute end-1.5 top-1.5 size-1.5 rounded-full bg-destructive ring-2 ring-background" />
        </Button>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-10 gap-2 px-2" aria-label="منوی کاربر">
              <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">
                {initials(user)}
              </div>
              <div className="hidden max-w-36 text-start md:block">
                <span className="block truncate text-xs font-semibold text-foreground">{displayName(user)}</span>
                <span className="block truncate text-[10px] text-muted-foreground">{displayRole(user)}</span>
              </div>
              <ChevronDown className="hidden size-3.5 text-muted-foreground md:block" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel className="space-y-0.5 px-2 py-2">
              <span className="block text-xs font-semibold text-foreground">{displayName(user)}</span>
              <span className="block text-[10px] font-normal text-muted-foreground">{displayRole(user)}</span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" onSelect={() => void onLogout()}>
              <LogOut className="size-4" />
              خروج از سیستم
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  )
}

export function ApplicationShell({
  children,
  user,
  navItems,
  currentPath,
  onNavigate,
  onLogout,
}: ApplicationShellProps) {
  return (
    <div className="min-h-dvh bg-background text-foreground lg:grid lg:grid-cols-[280px_minmax(0,1fr)]" dir="rtl">
      <Sidebar user={user} navItems={navItems} currentPath={currentPath} onNavigate={onNavigate} onLogout={onLogout} />
      <div className="min-w-0">
        <Header user={user} navItems={navItems} currentPath={currentPath} onNavigate={onNavigate} onLogout={onLogout} />
        <main className="mx-auto w-full max-w-[1600px] p-4 md:p-6 xl:p-8">{children}</main>
      </div>
    </div>
  )
}
