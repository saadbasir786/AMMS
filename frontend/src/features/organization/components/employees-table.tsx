import React from 'react'
import { Link } from 'react-router-dom'
import { Fingerprint, Pencil, UserCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { StatusBadge } from '@/components/amms/status-badge'
import type { Employee } from '../types'

type EmployeesTableProps = {
  employees: Employee[]
  onEdit?: (employee: Employee) => void
}

export function EmployeesTable({ employees, onEdit }: EmployeesTableProps) {
  if (employees.length === 0) {
    return (
      <div
        className="text-center py-12 px-4 rounded-2xl border border-dashed border-border/80 bg-card/50"
        dir="rtl"
      >
        <UserCheck className="size-9 mx-auto text-muted-foreground/40 mb-2.5" />
        <h4 className="text-xs sm:text-sm font-bold text-foreground mb-1">
          کارمندی با این مشخصات یافت نشد
        </h4>
        <p className="text-[11px] text-muted-foreground max-w-sm mx-auto leading-relaxed">
          عبارت جستجو یا فیلترهای واحد سازمانی را تغییر دهید.
        </p>
      </div>
    )
  }

  return (
    <div
      className="rounded-2xl border border-border/70 bg-card overflow-hidden shadow-xs"
      dir="rtl"
    >
      <div className="overflow-x-auto">
        <table className="w-full text-xs text-right border-collapse">
          <thead>
            <tr className="border-b border-border/60 bg-slate-50/70 dark:bg-slate-900/40 text-muted-foreground">
              <th className="py-3.5 px-4 font-bold">شماره کارمندی</th>
              <th className="py-3.5 px-4 font-bold">نام و تخلص کارمند</th>
              <th className="py-3.5 px-4 font-bold">ریاست / واحد سازمانی</th>
              <th className="py-3.5 px-4 font-bold">سمت اداری</th>
              <th className="py-3.5 px-4 font-bold">وضعیت</th>
              <th className="py-3.5 px-4 font-bold text-center">بایومتریک</th>
              {onEdit && <th className="py-3.5 px-4 font-bold text-center">عملیات</th>}
            </tr>
          </thead>
          <tbody className="divide-y divide-border/50">
            {employees.map((emp) => (
              <tr
                key={emp.id}
                className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors"
              >
                {/* Employee Number */}
                <td className="py-3.5 px-4 font-mono font-bold text-foreground">
                  <Badge variant="outline" className="font-mono text-[11px]">
                    {emp.employee_number || emp.id}
                  </Badge>
                </td>

                {/* Name */}
                <td className="py-3.5 px-4 whitespace-nowrap">
                  <div className="flex items-center gap-2.5">
                    <div className="size-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                      {emp.full_name?.charAt(0) || 'ک'}
                    </div>
                    <div>
                      <strong className="text-xs font-semibold text-foreground block">
                        {emp.full_name}
                      </strong>
                      {emp.email && (
                        <span className="text-[10px] text-muted-foreground font-mono block">
                          {emp.email}
                        </span>
                      )}
                    </div>
                  </div>
                </td>

                {/* Unit */}
                <td className="py-3.5 px-4 text-muted-foreground">
                  <span className="font-medium text-foreground">
                    {emp.unit?.name || '—'}
                  </span>
                </td>

                {/* Position */}
                <td className="py-3.5 px-4 text-muted-foreground">
                  {emp.position?.title || 'کارشناس اداری'}
                </td>

                {/* Status */}
                <td className="py-3.5 px-4 whitespace-nowrap">
                  <StatusBadge status={emp.status || 'ACTIVE'} />
                </td>

                {/* Biometrics Action */}
                <td className="py-3.5 px-4 text-center whitespace-nowrap">
                  <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="h-8 text-xs font-semibold rounded-xl gap-1 text-primary hover:bg-primary/5"
                  >
                    <Link to={`/employees/${emp.id}/biometrics`}>
                      <Fingerprint className="size-3.5" />
                      <span>پروفایل بایومتریک</span>
                    </Link>
                  </Button>
                </td>
                {onEdit && (
                  <td className="py-3.5 px-4 text-center whitespace-nowrap">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-8 rounded-xl text-xs gap-1.5 hover:bg-primary/5 hover:text-primary"
                      onClick={() => onEdit(emp)}
                    >
                      <Pencil className="size-3.5" />
                      <span>اصلاح</span>
                    </Button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
