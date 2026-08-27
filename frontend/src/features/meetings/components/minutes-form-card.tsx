import React from 'react'
import {
  FileText,
  Plus,
  Trash2,
  Save,
  Send,
  Calendar,
  CheckCircle2,
  BookmarkCheck,
  Building2,
  UserCheck
} from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { formatSolarDate } from '@/lib/calendar-utils'
import type { MeetingMinutes, MinutesItem, MinutesDecision } from '../types'
import type { Employee, OrganizationalUnit } from '@/features/organization/types'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type MinutesFormCardProps = {
  minutes: MeetingMinutes | null
  form: {
    summary: string
    general_notes: string
    items: MinutesItem[]
    decisions: MinutesDecision[]
  }
  onChange: (form: any) => void
  organizationalUnits: OrganizationalUnit[]
  employees: Employee[]
  busy: boolean
  onSaveDraft: () => Promise<void>
  onSubmit: () => Promise<void>
  canPrepare?: boolean
  canSubmit?: boolean
}

export function MinutesFormCard({
  minutes,
  form,
  onChange,
  organizationalUnits,
  employees,
  busy,
  onSaveDraft,
  onSubmit,
  canPrepare = false,
  canSubmit = false,
}: MinutesFormCardProps) {
  const version = minutes?.current_version
  const editable = canPrepare && (!minutes || ['DRAFT', 'RETURNED_FOR_CORRECTION'].includes(minutes.status))

  const addItem = () => {
    onChange({
      ...form,
      items: [...form.items, { agenda_title: '', discussion_summary: '' }],
    })
  }

  const removeItem = (index: number) => {
    onChange({
      ...form,
      items: form.items.filter((_, idx) => idx !== index),
    })
  }

  const updateItem = (index: number, field: keyof MinutesItem, value: string) => {
    const updated = [...form.items]
    updated[index] = { ...updated[index], [field]: value }
    onChange({ ...form, items: updated })
  }

  const addDecision = () => {
    onChange({
      ...form,
      decisions: [
        ...form.decisions,
        {
          decision_text: '',
          responsible_unit_id: '',
          responsible_employee_id: '',
          target_date: '',
          notes: '',
        },
      ],
    })
  }

  const removeDecision = (index: number) => {
    onChange({
      ...form,
      decisions: form.decisions.filter((_, idx) => idx !== index),
    })
  }

  const updateDecision = (index: number, field: keyof MinutesDecision, value: any) => {
    const updated = [...form.decisions]
    updated[index] = { ...updated[index], [field]: value }
    onChange({ ...form, decisions: updated })
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <FileText className="size-4" />
            </div>
            <div>
              <h3 className="text-xs sm:text-sm font-bold text-foreground">
                محتوا و متن صورت‌جلسه رسمی
              </h3>
              <p className="text-[11px] text-muted-foreground">
                ثبت خلاصه مذاکرات، بررسی بندهای آجندا و تعیین مصوبات با مهلت اقدام
              </p>
            </div>
          </div>

          <Badge variant="outline" className="text-[11px] font-mono px-2 py-0.5">
            نسخه {version?.version_number || 1}
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-6">
        {editable ? (
          <>
            {/* General Summary */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground flex items-center gap-1.5">
                <FileText className="size-3.5 text-primary" />
                خلاصه عمومی مذاکرات و مباحث جلسه
              </label>
              <Textarea
                rows={4}
                value={form.summary}
                onChange={(e) => onChange({ ...form, summary: e.target.value })}
                placeholder="خلاصه کلی مباحث مطرح شده در جلسه..."
                className="text-xs rounded-xl border-border/70"
              />
            </div>

            {/* General Notes */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground flex items-center gap-1.5">
                <BookmarkCheck className="size-3.5 text-primary" />
                یادداشت‌ها و توضیحات تکمیلی
              </label>
              <Textarea
                rows={2}
                value={form.general_notes}
                onChange={(e) => onChange({ ...form, general_notes: e.target.value })}
                placeholder="یادداشت‌های اضافی اختیاری..."
                className="text-xs rounded-xl border-border/70"
              />
            </div>

            {/* Agenda & Discussion Items */}
            <div className="space-y-3 pt-3 border-t border-border/50">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold text-foreground flex items-center gap-1.5">
                  <BookmarkCheck className="size-3.5 text-primary" />
                  بررسی بندهای آجندا و مباحث تخصصی
                </h4>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={addItem}
                  className="h-8 text-xs font-semibold rounded-xl gap-1.5"
                >
                  <Plus className="size-3.5" />
                  <span>افزودن بند آجندا</span>
                </Button>
              </div>

              <div className="space-y-3">
                {form.items.map((item, idx) => (
                  <div
                    key={idx}
                    className="p-3.5 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/60 space-y-3"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-[11px] font-bold text-muted-foreground">
                        بند شماره {idx + 1}
                      </span>
                      {form.items.length > 1 && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => removeItem(idx)}
                          className="size-7 p-0 text-muted-foreground hover:text-destructive rounded-lg"
                        >
                          <Trash2 className="size-3.5" />
                        </Button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 gap-3 text-xs">
                      <Input
                        value={item.agenda_title}
                        onChange={(e) => updateItem(idx, 'agenda_title', e.target.value)}
                        placeholder="عنوان بند آجندا یا موضوع مورد بحث..."
                        className="h-9 text-xs rounded-xl"
                      />
                      <Textarea
                        rows={2}
                        value={item.discussion_summary}
                        onChange={(e) => updateItem(idx, 'discussion_summary', e.target.value)}
                        placeholder="خلاصه مباحث و نظرات ارائه شده پیرامون این بند..."
                        className="text-xs rounded-xl"
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Decisions & Action Items */}
            <div className="space-y-3 pt-3 border-t border-border/50">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold text-foreground flex items-center gap-1.5">
                  <CheckCircle2 className="size-3.5 text-emerald-600" />
                  تصمیمات و مصوبات رسمی جلسه
                </h4>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={addDecision}
                  className="h-8 text-xs font-semibold rounded-xl gap-1.5"
                >
                  <Plus className="size-3.5" />
                  <span>افزودن مصوبه</span>
                </Button>
              </div>

              <div className="space-y-3">
                {form.decisions.map((dec, idx) => (
                  <div
                    key={idx}
                    className="p-3.5 rounded-2xl bg-emerald-50/30 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40 space-y-3"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-[11px] font-bold text-emerald-800 dark:text-emerald-300">
                        مصوبه شماره {idx + 1}
                      </span>
                      {form.decisions.length > 1 && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => removeDecision(idx)}
                          className="size-7 p-0 text-muted-foreground hover:text-destructive rounded-lg"
                        >
                          <Trash2 className="size-3.5" />
                        </Button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 gap-3 text-xs">
                      <Textarea
                        rows={2}
                        value={dec.decision_text}
                        onChange={(e) => updateDecision(idx, 'decision_text', e.target.value)}
                        placeholder="متن کامل مصوبه یا اقدام محوله..."
                        className="text-xs rounded-xl bg-card"
                      />

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                          <label className="text-[10px] font-bold text-muted-foreground flex items-center gap-1">
                            <Building2 className="size-3 text-primary" />
                            واحد مسئول
                          </label>
                          <Select
                            value={dec.responsible_unit_id ? String(dec.responsible_unit_id) : undefined}
                            onValueChange={(value) => {
                              const updated = [...form.decisions]
                              updated[idx] = {
                                ...updated[idx],
                                responsible_unit_id: value === 'none' ? '' : value,
                                responsible_employee_id: '',
                              }
                              onChange({ ...form, decisions: updated })
                            }}
                          >
                            <SelectTrigger className="h-9.5 text-xs rounded-xl bg-card">
                              <SelectValue placeholder="انتخاب واحد مسئول" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="none">بدون تعیین واحد</SelectItem>
                              {organizationalUnits.map((unit) => (
                                <SelectItem key={unit.id} value={String(unit.id)}>
                                  {unit.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>

                        <div className="space-y-1.5">
                          <label className="text-[10px] font-bold text-muted-foreground flex items-center gap-1">
                            <UserCheck className="size-3 text-primary" />
                            کارمند مسئول
                          </label>
                          <Select
                            value={dec.responsible_employee_id ? String(dec.responsible_employee_id) : undefined}
                            onValueChange={(value) =>
                              updateDecision(idx, 'responsible_employee_id', value === 'none' ? '' : value)
                            }
                            disabled={!dec.responsible_unit_id}
                          >
                            <SelectTrigger className="h-9.5 text-xs rounded-xl bg-card">
                              <SelectValue placeholder="انتخاب کارمند مسئول" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="none">بدون تعیین کارمند مشخص</SelectItem>
                              {employees
                                .filter(
                                  (employee) =>
                                    Number(employee.organizational_unit_id || employee.unit?.id) ===
                                    Number(dec.responsible_unit_id)
                                )
                                .map((employee) => (
                                  <SelectItem key={employee.id} value={String(employee.id)}>
                                    {employee.full_name}
                                    {employee.position?.title ? ` — ${employee.position.title}` : ''}
                                  </SelectItem>
                                ))}
                            </SelectContent>
                          </Select>
                        </div>
                      </div>

                      <div className="space-y-1.5">
                        <label className="text-[10px] font-bold text-muted-foreground block">
                          مهلت انجام مصوبه
                        </label>
                        <MultiCalendarDatePicker
                          value={dec.target_date || ''}
                          onChange={(iso) => updateDecision(idx, 'target_date', iso)}
                          placeholder="انتخاب مهلت (خورشیدی)..."
                          className="h-9.5 text-xs rounded-xl"
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Bottom Actions Bar */}
            <div className="flex flex-wrap items-center gap-2 pt-3 border-t border-border/50">
              {canPrepare && <Button
                variant="outline"
                disabled={busy}
                onClick={onSaveDraft}
                className="h-9.5 text-xs font-semibold rounded-xl gap-2"
              >
                <Save className="size-3.5" />
                <span>{busy ? 'در حال ذخیره…' : 'ذخیره پیش‌نویس صورت‌جلسه'}</span>
              </Button>}

              {canSubmit && <Button
                disabled={busy}
                onClick={onSubmit}
                className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
              >
                <Send className="size-3.5" />
                <span>
                  {minutes?.status === 'RETURNED_FOR_CORRECTION'
                    ? 'ارسال مجدد نسخه اصلاح‌شده'
                    : 'ارسال صورت‌جلسه برای بررسی و تایید'}
                </span>
              </Button>}
            </div>
          </>
        ) : (
          /* Read-only View */
          <div className="space-y-5 text-xs">
            <div className="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-900/40 border border-border/60 space-y-2">
              <span className="text-[10px] font-bold text-muted-foreground block">
                خلاصه عمومی مذاکرات
              </span>
              <p className="text-xs text-foreground leading-relaxed whitespace-pre-wrap">
                {version?.summary || '—'}
              </p>
              {version?.general_notes && (
                <p className="text-xs text-muted-foreground mt-2 pt-2 border-t border-border/40">
                  {version.general_notes}
                </p>
              )}
            </div>

            {/* Items */}
            <div className="space-y-2.5">
              <h4 className="text-xs font-bold text-foreground">بندهای بررسی‌شده آجندا</h4>
              <div className="divide-y divide-border/60 rounded-2xl border border-border/60 bg-card overflow-hidden">
                {(version?.items || []).map((item, idx) => (
                  <div key={item.id || idx} className="p-3.5 space-y-1">
                    <strong className="text-xs font-bold text-foreground block">
                      {idx + 1}. {item.agenda_title}
                    </strong>
                    <p className="text-xs text-muted-foreground leading-relaxed">
                      {item.discussion_summary}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* Decisions */}
            <div className="space-y-2.5">
              <h4 className="text-xs font-bold text-foreground">مصوبات رسمی و اقدامات</h4>
              <div className="divide-y divide-border/60 rounded-2xl border border-emerald-200/60 dark:border-emerald-900/40 bg-emerald-50/20 dark:bg-emerald-950/10 overflow-hidden">
                {(version?.decisions || []).map((dec, idx) => (
                  <div key={dec.id || idx} className="p-3.5 space-y-1.5">
                    <div className="flex items-center justify-between gap-2">
                      <strong className="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                        {dec.decision_number || `مصوبه ${idx + 1}`}
                      </strong>
                      {dec.target_date && (
                        <span className="text-[11px] font-mono text-muted-foreground flex items-center gap-1">
                          <Calendar className="size-3" />
                          مهلت: {formatSolarDate(dec.target_date)} هـ.ش
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-foreground leading-relaxed">
                      {dec.decision_text}
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px] text-muted-foreground">
                      <span>
                        <strong className="text-foreground">واحد مسئول:</strong>{' '}
                        {organizationalUnits.find((unit) => Number(unit.id) === Number(dec.responsible_unit_id))?.name || '—'}
                      </span>
                      <span>
                        <strong className="text-foreground">کارمند مسئول:</strong>{' '}
                        {employees.find((employee) => Number(employee.id) === Number(dec.responsible_employee_id))?.full_name || '—'}
                      </span>
                      <span>
                        <strong className="text-foreground">مهلت:</strong>{' '}
                        {dec.target_date ? `${formatSolarDate(dec.target_date)} هـ.ش` : '—'}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
