<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$r = App\Models\MeetingRequest::findOrFail(5);
$meeting = $r->meeting;
$meetingId = $meeting?->id;
$unitRows = Illuminate\Support\Facades\DB::table('meeting_units')
    ->where('meeting_id', $meetingId)
    ->orderBy('organizational_unit_id')
    ->get();
$contextRows = Illuminate\Support\Facades\DB::table('meeting_authority_contexts')
    ->where('meeting_id', $meetingId)
    ->get();
$participantRows = Illuminate\Support\Facades\DB::table('meeting_participants')
    ->where('meeting_id', $meetingId)
    ->orderBy('id')
    ->get();

$payload = [
    'request_id' => $r->id,
    'request_status' => (string) $r->status,
    'requesting_unit_id' => $r->requesting_unit_id,
    'meeting_id' => $meetingId,
    'meeting_count_for_request' => Illuminate\Support\Facades\DB::table('meetings')
        ->where('meeting_request_id', $r->id)
        ->count(),
    'meeting_units_count_for_meeting' => $unitRows->count(),
    'meeting_unit_roles' => $unitRows->pluck('role')->values(),
    'meeting_unit_ids' => $unitRows->pluck('organizational_unit_id')->values(),
    'requesting_unit_count_for_meeting' => $unitRows->where('role', 'REQUESTING')->count(),
    'participating_unit_count_for_meeting' => $unitRows->where('role', 'PARTICIPATING')->count(),
    'meeting_authority_context_count_for_meeting' => $contextRows->count(),
    'authority_contexts' => $contextRows->map(static fn ($row) => [
        'id' => $row->id,
        'authority_employee_id' => $row->authority_employee_id,
        'authority_type' => $row->authority_type,
        'authority_office_unit_id' => $row->authority_office_unit_id,
    ])->values(),
    'chairperson_employee_id' => $meeting->chairperson_employee_id,
    'meeting_participant_count_for_meeting' => $participantRows->count(),
    'participants' => $participantRows->map(static fn ($row) => [
        'employee_id' => $row->employee_id,
        'organizational_unit_id' => $row->organizational_unit_id,
        'role' => $row->role,
    ])->values(),
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
