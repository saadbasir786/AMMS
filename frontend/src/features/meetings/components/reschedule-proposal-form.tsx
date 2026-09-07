import React, { useState } from 'react'
import { CalendarDays, Clock, MapPin, Send } from 'lucide-react'
import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MultiCalendarDatePicker } from '@/components/ui/multi-calendar-date-picker'
import { TimePicker } from '@/components/ui/time-picker'

type RescheduleProposalFormProps = {
  initial?: {
    meeting_date?: string
    start_time?: string
    end_time?: string
    location?: string
  } | null
  label?: string
  busy: boolean
  onSubmit: (data: {
    meeting_date: string
    start_time: string
    end_time: string
    location: string
    comment?: string
  }) => Promise<boolean>
}

export function RescheduleProposalForm({
  initial,
  label = 'ثبت پیشنهاد زمان‌بندی جدید',
  busy,
  onSubmit,
}: RescheduleProposalFormProps) {
  const [meetingDate, setMeetingDate] = useState(
    initial?.meeting_date ? initial.meeting_date.slice(0, 10) : ''
  )
  const [startTime, setStartTime] = useState(
    initial?.start_time ? initial.start_time.slice(0, 5) : '09:00'
  )
  const [endTime, setEndTime] = useState(
    initial?.end_time ? initial.end_time.slice(0, 5) : '10:00'
  )
  const [location, setLocation] = useState(initial?.location || '')
  const [comment, setComment] = useState('')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!meetingDate) {
      setError('انتخاب تاریخ جلسه الزامی است.')
      return
    }
    if (!startTime || !endTime) {
      setError('تعیین ساعت آغاز و پایان جلسه الزامی است.')
      return
    }
    if (!location.trim()) {
      setError('درج محل برگزاری جلسه الزامی است.')
      return
    }
    setError(null)
    const saved = await onSubmit({
      meeting_date: meetingDate,
      start_time: startTime,
      end_time: endTime,
      location,
      comment: comment || undefined,
    })
    if (saved) setComment('')
  }

  return (
    <Card className="border border-border/70 shadow-xs" dir="rtl">
      <CardHeader className="p-4 sm:p-5 border-b border-border/60">
        <h3 className="text-xs sm:text-sm font-bold text-foreground">
          {label}
        </h3>
        <p className="text-[11px] text-muted-foreground">
          تنظیم تاریخ، ساعات رسمی و محل پیشنهادی با تقویم خورشیدی
        </p>
      </CardHeader>

      <CardContent className="p-4 sm:p-5 space-y-4">
        {error && (
          <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-700 dark:text-rose-300 font-medium">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          {/* Date Picker */}
          <div className="space-y-1.5 sm:col-span-2">
            <label className="text-xs font-bold text-foreground block">
              تاریخ جدید جلسه (هجری خورشیدی)
            </label>
            <MultiCalendarDatePicker
              value={meetingDate}
              onChange={setMeetingDate}
              placeholder="انتخاب تاریخ پیشنهادی جلسه..."
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          {/* Time Picker Start */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              ساعت آغاز
            </label>
            <TimePicker
              value={startTime}
              onChange={setStartTime}
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          {/* Time Picker End */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-foreground block">
              ساعت پایان
            </label>
            <TimePicker
              value={endTime}
              onChange={setEndTime}
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          {/* Location */}
          <div className="space-y-1.5 sm:col-span-2">
            <label className="text-xs font-bold text-foreground block">
              محل برگزاری جلسه
            </label>
            <Input
              value={location}
              onChange={(e) => setLocation(e.target.value)}
              placeholder="تالار جلسات، اتاق کنفرانس، یا لینک آنلاین..."
              className="h-9.5 text-xs rounded-xl"
            />
          </div>

          {/* Comment */}
          <div className="space-y-1.5 sm:col-span-2">
            <label className="text-xs font-bold text-foreground block">
              یادداشت یا توضیحات تکمیلی (اختیاری)
            </label>
            <Textarea
              rows={2}
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              placeholder="علت انتخاب این زمان یا توضیحات به سایر ریاست‌ها..."
              className="text-xs rounded-xl"
            />
          </div>
        </div>

        <div className="pt-2 border-t border-border/50">
          <Button
            disabled={busy}
            onClick={handleSubmit}
            className="h-9.5 text-xs font-semibold rounded-xl gap-2 bg-primary hover:bg-primary/90 text-white shadow-xs"
          >
            <Send className="size-3.5" />
            <span>{busy ? 'در حال ثبت…' : 'ثبت و ارسال پیشنهاد'}</span>
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
