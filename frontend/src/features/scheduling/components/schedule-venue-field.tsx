import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { SchedulingFormValues } from '../types'

type VenueOption = { id: number; name: string; location_details?: string | null }

type ScheduleVenueFieldProps = {
  form: SchedulingFormValues
  venues: VenueOption[]
  onChange: (field: keyof SchedulingFormValues, value: any) => void
}

export function ScheduleVenueField({ form, venues, onChange }: ScheduleVenueFieldProps) {
  const selectedValue = form.venue_type === 'INTERNAL_VENUE' && form.venue_id
    ? `venue:${form.venue_id}`
    : form.venue_type || 'EXTERNAL_LOCATION'

  return (
    <div className="space-y-2">
      <Select
        value={selectedValue}
        onValueChange={(value) => {
          if (value.startsWith('venue:')) {
            onChange('venue_type', 'INTERNAL_VENUE')
            onChange('venue_id', Number(value.slice('venue:'.length)))
            onChange('location', '')
            return
          }
          onChange('venue_type', value)
          onChange('venue_id', null)
          if (form.venue_type === 'INTERNAL_VENUE') onChange('location', '')
        }}
      >
        <SelectTrigger className="h-9.5 rounded-xl text-xs">
          <SelectValue placeholder="انتخاب محل برگزاری" />
        </SelectTrigger>
        <SelectContent>
          {venues.map((venue) => (
            <SelectItem key={venue.id} value={`venue:${venue.id}`}>
              {venue.name}{venue.location_details ? ` — ${venue.location_details}` : ''}
            </SelectItem>
          ))}
          <SelectItem value="EXTERNAL_LOCATION">محل بیرونی</SelectItem>
          <SelectItem value="ONLINE">جلسه آنلاین</SelectItem>
        </SelectContent>
      </Select>
      {form.venue_type !== 'INTERNAL_VENUE' && (
        <Input
          value={form.location}
          onChange={(event) => onChange('location', event.target.value)}
          placeholder={form.venue_type === 'ONLINE' ? 'پیوند یا اطلاعات جلسه آنلاین' : 'نشانی محل بیرونی'}
          className="h-9.5 rounded-xl text-xs"
        />
      )}
    </div>
  )
}
