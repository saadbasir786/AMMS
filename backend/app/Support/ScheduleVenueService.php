<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingVenue;
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
