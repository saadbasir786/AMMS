import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'

export type ActiveVenueOption = {
  id: number
  name: string
  location_details?: string | null
}

type ProposedLocationFieldProps = {
  value: string
  venues: ActiveVenueOption[]
  onChange: (value: string) => void
}

const OTHER_LOCATION = 'OTHER_LOCATION'

export function ProposedLocationField({ value, venues, onChange }: ProposedLocationFieldProps) {
  const matchingVenue = venues.find((venue) => venue.name === value)
  const selectedValue = matchingVenue ? String(matchingVenue.id) : OTHER_LOCATION

  return (
    <div className="space-y-2">
      <Select
        value={selectedValue}
        onValueChange={(selection) => {
          if (selection === OTHER_LOCATION) {
            if (matchingVenue) onChange('')
            return
          }
          const venue = venues.find((item) => String(item.id) === selection)
          if (venue) onChange(venue.name)
        }}
      >
        <SelectTrigger className="h-9.5 rounded-xl text-xs">
          <SelectValue placeholder="انتخاب محل پیشنهادی" />
        </SelectTrigger>
        <SelectContent>
          {venues.map((venue) => (
            <SelectItem key={venue.id} value={String(venue.id)}>
              {venue.name}
              {venue.location_details ? ` — ${venue.location_details}` : ''}
            </SelectItem>
          ))}
          <SelectItem value={OTHER_LOCATION}>سایر محل‌ها، بیرونی یا آنلاین</SelectItem>
        </SelectContent>
      </Select>
      {selectedValue === OTHER_LOCATION && (
        <Input
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder="نام محل بیرونی یا اطلاعات جلسه آنلاین"
          className="h-9.5 rounded-xl text-xs"
        />
      )}
      <p className="text-[11px] text-muted-foreground">
        این مورد پیشنهاد اولیه است؛ محل نهایی هنگام تنظیم زمان‌بندی رسمی تأیید می‌شود.
      </p>
    </div>
  )
}
