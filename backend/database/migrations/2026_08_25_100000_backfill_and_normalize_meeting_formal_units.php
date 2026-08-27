<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUPPORTED_FLOW_TYPES = [
        'INTERNAL_DIRECTORATE',
        'INTER_DIRECTORATE',
        'MULTI_DIRECTORATE',
        'DIRECTORATE_WITH_HIGH_AUTHORITY',
        'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY',
        'HIGH_AUTHORITY_INITIATED',
    ];

    public function up(): void
    {
        // TARGET is a request-side role. Meeting-side governance uses the
        // canonical REQUESTING/PARTICIPATING role set exclusively.
        DB::table('meeting_units')
            ->where('role', 'TARGET')
            ->whereIn('meeting_id', function ($query): void {
                $query->select('meetings.id')
                    ->from('meetings')
                    ->join('meeting_requests', 'meeting_requests.id', '=', 'meetings.meeting_request_id')
                    ->where('meeting_requests.flow_type', 'INTER_DIRECTORATE');
            })
            ->update(['role' => 'PARTICIPATING', 'updated_at' => now()]);

        DB::table('meetings')
            ->join('meeting_requests', 'meeting_requests.id', '=', 'meetings.meeting_request_id')
            ->select([
                'meetings.id',
                'meetings.meeting_request_id',
                'meeting_requests.flow_type',
                'meeting_requests.requesting_unit_id',
            ])
            ->whereIn('meeting_requests.flow_type', self::SUPPORTED_FLOW_TYPES)
            ->orderBy('meetings.id')
            ->eachById(function (object $meeting): void {
                $this->backfillFromRequestUnits($meeting);

                if (in_array($meeting->flow_type, ['INTERNAL_DIRECTORATE', 'DIRECTORATE_WITH_HIGH_AUTHORITY'], true)) {
                    $this->insertFormalUnit((int) $meeting->id, (int) $meeting->requesting_unit_id, 'REQUESTING');
                }

                $hasFormalUnit = DB::table('meeting_units')
                    ->where('meeting_id', $meeting->id)
                    ->whereIn('role', ['REQUESTING', 'PARTICIPATING'])
                    ->exists();

                if (!$hasFormalUnit) {
                    throw new \RuntimeException("MISSING_FORMAL_MEETING_UNITS: {$meeting->id}");
                }
            }, 1000, 'meetings.id', 'id');
    }

    public function down(): void
    {
        // Formal-unit repairs are retained because they define authorization scope and audit history.
    }

    private function backfillFromRequestUnits(object $meeting): void
    {
        $roles = [
            'REQUESTER' => 'REQUESTING',
            'REQUESTING' => 'REQUESTING',
            'TARGET' => 'PARTICIPATING',
            'PARTICIPATING' => 'PARTICIPATING',
        ];

        DB::table('meeting_request_units')
            ->where('meeting_request_id', $meeting->meeting_request_id)
            ->whereIn('role', array_keys($roles))
            ->orderBy('id')
            ->each(function (object $unit) use ($meeting, $roles): void {
                $this->insertFormalUnit(
                    (int) $meeting->id,
                    (int) $unit->organizational_unit_id,
                    $roles[$unit->role],
                );
            });
    }

    private function insertFormalUnit(int $meetingId, int $unitId, string $role): void
    {
        if ($unitId <= 0) {
            return;
        }

        DB::table('meeting_units')->insertOrIgnore([
            'meeting_id' => $meetingId,
            'organizational_unit_id' => $unitId,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
