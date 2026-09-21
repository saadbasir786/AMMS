<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingVenue;
use Carbon\CarbonImmutable;

class ScheduleVenueService
{
    public const INTERNAL = 'INTERNAL_VENUE';
    public const EXTERNAL = 'EXTERNAL_LOCATION';
    public const ONLINE = 'ONLINE';

    public function normalize(array $data, Meeting|MeetingRequest|null $context = null): array
    {
        $type = strtoupper((string) ($data['venue_type'] ?? self::EXTERNAL));
        abort_unless(in_array($type, [self::INTERNAL, self::EXTERNAL, self::ONLINE], true), 422, 'INVALID_VENUE_TYPE');

        $venueId = isset($data['venue_id']) && $data['venue_id'] !== '' ? (int) $data['venue_id'] : null;
        $location = trim((string) ($data['location'] ?? ''));
        $snapshot = null;

        if ($type === self::INTERNAL) {
            abort_unless($venueId, 422, 'INTERNAL_VENUE_REQUIRED');

            // A venue that was already formally confirmed remains valid for that exact
            // stored schedule even if an administrator later deactivates or renames it.
            // This preserves the reservation and immutable historical snapshot while
            // still preventing the inactive venue from being selected for any new time.
            $stored = $this->storedConfirmedVenue($context, $venueId, $data);
            if ($stored !== null) return $stored;

            $venue = MeetingVenue::query()->whereKey($venueId)->where('status', 'ACTIVE')->first();
            abort_unless($venue, 422, 'INTERNAL_VENUE_UNAVAILABLE');
            $this->assertSameOrganization($venue, $context);
            $location = trim(implode(' — ', array_filter([$venue->name, $venue->location_details])));
            $snapshot = [
                'type' => self::INTERNAL,
                'venue_id' => $venue->id,
                'name' => $venue->name,
                'description' => $venue->description,
                'location_details' => $venue->location_details,
                'capacity' => $venue->capacity,
            ];
        } else {
            abort_if($venueId, 422, 'VENUE_NOT_ALLOWED_FOR_LOCATION_TYPE');
            abort_unless($location !== '', 422, $type === self::ONLINE ? 'ONLINE_LOCATION_REQUIRED' : 'INVALID_SCHEDULE_LOCATION');
            $snapshot = ['type' => $type, 'location' => $location];
        }

        return ['venue_type' => $type, 'venue_id' => $venueId, 'venue_snapshot' => $snapshot, 'location' => $location];
    }

    public function fromStored(?string $type, ?int $venueId, ?array $snapshot, ?string $location): array
    {
        $type = $type ?: self::EXTERNAL;
        return [
            'venue_type' => $type,
            'venue_id' => $venueId,
            'venue_snapshot' => $snapshot ?: ['type' => $type, 'location' => $location],
            'location' => trim((string) $location),
        ];
    }

    private function storedConfirmedVenue(Meeting|MeetingRequest|null $context, int $venueId, array $data): ?array
    {
        if ($context instanceof MeetingRequest) {
            if ($context->status !== 'SCHEDULE_CONFIRMED'
                || (string) $context->scheduled_venue_type !== self::INTERNAL
                || (int) $context->scheduled_venue_id !== $venueId
                || !$context->scheduled_date
                || !$context->scheduled_start_time
                || !$context->scheduled_duration_minutes) {
                return null;
            }

            $start = substr((string) $context->scheduled_start_time, 0, 5);
            $end = CarbonImmutable::createFromFormat('H:i', $start)
                ->addMinutes((int) $context->scheduled_duration_minutes)
                ->format('H:i');
            if (!$this->sameStoredSlot($data, $context->scheduled_date->toDateString(), $start, $end)) return null;

            $venue = MeetingVenue::query()->find($venueId);
            abort_unless($venue, 422, 'INTERNAL_VENUE_UNAVAILABLE');
            $this->assertSameOrganization($venue, $context);

            return $this->fromStored(
                $context->scheduled_venue_type,
                $context->scheduled_venue_id,
                $context->scheduled_venue_snapshot,
                $context->scheduled_location,
            );
        }

        if ($context instanceof Meeting) {
            if ((string) $context->venue_type !== self::INTERNAL
                || (int) $context->venue_id !== $venueId
                || !$context->meeting_date
                || !$this->sameStoredSlot(
                    $data,
                    $context->meeting_date->toDateString(),
                    substr((string) $context->start_time, 0, 5),
                    substr((string) $context->end_time, 0, 5),
                )) {
                return null;
            }

            $venue = MeetingVenue::query()->find($venueId);
            abort_unless($venue, 422, 'INTERNAL_VENUE_UNAVAILABLE');
            $this->assertSameOrganization($venue, $context);

            return $this->fromStored($context->venue_type, $context->venue_id, $context->venue_snapshot, $context->location);
        }

        return null;
    }

    private function sameStoredSlot(array $data, string $date, string $start, string $end): bool
    {
        if (!isset($data['meeting_date'], $data['start_time'], $data['end_time'])) return false;

        return (string) $data['meeting_date'] === $date
            && substr((string) $data['start_time'], 0, 5) === $start
            && substr((string) $data['end_time'], 0, 5) === $end;
    }

    private function assertSameOrganization(MeetingVenue $venue, Meeting|MeetingRequest|null $context): void
    {
        if (!$context) return;
        $organizationId = $context instanceof Meeting
            ? $context->organizingUnit?->organization_id
            : ($context->requestingUnit?->organization_id
                ?? $context->authorityContext?->authorityOfficeUnit?->organization_id);
        abort_unless($organizationId && (int) $organizationId === (int) $venue->organization_id, 403, 'VENUE_OUTSIDE_SCOPE');
    }
}
