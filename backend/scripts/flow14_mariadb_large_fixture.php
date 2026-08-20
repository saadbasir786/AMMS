<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('This fixture must run against MariaDB/MySQL.');
}
$prefix = 'FLOW14-PERF-';
$unitId = (int) DB::table('organizational_units')->where('code', 'FLOW14-REPORTING')->value('id');
$owner = DB::table('users')->where('email', 'tech.exec@example.test')->first();
$employeeIds = DB::table('employees')->orderBy('id')->limit(4)->pluck('id')->all();
if (!$unitId || !$owner || count($employeeIds) < 2) throw new RuntimeException('Seed prerequisites missing.');
if (DB::table('meetings')->where('meeting_number', 'like', $prefix.'%')->exists()) {
    throw new RuntimeException('Performance fixture already exists; reset disposable MariaDB database before rerunning.');
}
$now = now();
$counts = ['meetings' => 0, 'requests' => 0, 'participants' => 0, 'attendance' => 0, 'decisions' => 0, 'followups' => 0, 'action_items' => 0];
$meetingIds = [];
DB::transaction(function () use (&$counts, &$meetingIds, $prefix, $unitId, $owner, $employeeIds, $now): void {
    for ($i = 1; $i <= 120; $i++) {
        $date = now()->addDays(30 + $i)->toDateString();
        $requestId = DB::table('meeting_requests')->insertGetId([
            'request_number' => $prefix.'R-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $unitId,
            'created_by' => $owner->id,
            'subject' => $prefix.'request '.$i,
            'purpose' => 'PERFORMANCE_FIXTURE',
            'description' => 'Deterministic MariaDB performance fixture.',
            'priority' => 'NORMAL',
            'preferred_date' => $date,
            'preferred_start_time' => '10:00:00',
            'estimated_duration_minutes' => 60,
            'proposed_location' => 'Performance Hall',
            'status' => 'CONVERTED_TO_MEETING',
            'coordinator_id' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $meetingId = DB::table('meetings')->insertGetId([
            'meeting_request_id' => $requestId,
            'meeting_number' => $prefix.'M-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'title' => $prefix.'meeting '.$i,
            'description' => 'Deterministic MariaDB performance fixture.',
            'organizing_unit_id' => $unitId,
            'coordinator_id' => $owner->id,
            'chairperson_employee_id' => $employeeIds[0],
            'meeting_date' => $date,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'location' => 'Performance Hall',
            'agenda' => 'Performance and query-plan validation',
            'status' => 'SCHEDULED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $meetingIds[] = $meetingId;
        $scheduleId = DB::table('meeting_schedule_versions')->insertGetId([
            'meeting_id' => $meetingId, 'version_number' => 1, 'meeting_date' => $date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'location' => 'Performance Hall',
            'source_reschedule_case_id' => null, 'created_by_user_id' => $owner->id, 'created_at' => $now,
        ]);
        $participantVersionId = DB::table('meeting_participant_versions')->insertGetId([
            'meeting_id' => $meetingId, 'version_number' => 1, 'source_change_case_id' => null,
            'created_by_user_id' => $owner->id, 'created_at' => $now,
        ]);
        DB::table('meetings')->where('id', $meetingId)->update(['current_schedule_version_id' => $scheduleId, 'current_participant_version_id' => $participantVersionId]);
        DB::table('meeting_units')->insert(['meeting_id' => $meetingId, 'organizational_unit_id' => $unitId, 'role' => 'PARTICIPATING', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([$employeeIds[0], $employeeIds[1]] as $employeeId) {
            DB::table('meeting_participants')->insert(['meeting_id' => $meetingId, 'employee_id' => $employeeId, 'organizational_unit_id' => $unitId, 'role' => 'PARTICIPANT', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('meeting_participant_version_members')->insert(['participant_version_id' => $participantVersionId, 'employee_id' => $employeeId, 'organizational_unit_id' => $unitId, 'participant_role' => 'PARTICIPANT', 'owner_type' => 'UNIT', 'owner_unit_id' => $unitId, 'is_mandatory' => true, 'created_at' => $now]);
            $counts['participants']++;
        }
        DB::table('meeting_attendance_records')->insert([
            'uuid' => (string) Str::uuid(), 'meeting_id' => $meetingId, 'employee_id' => $employeeIds[0], 'participant_version_id' => $participantVersionId,
            'terminal_id' => null, 'terminal_session_id' => null, 'attendance_method' => 'MANUAL', 'finger_position' => null,
            'matched_template_id' => null, 'matcher_vendor' => null, 'matcher_algorithm_version' => null, 'match_score' => null,
            'threshold_reference' => null, 'candidate_set_checksum' => null, 'client_event_uuid' => (string) Str::uuid(),
            'recorded_at_server' => $now, 'device_captured_at' => null, 'recorded_by_user_id' => $owner->id, 'manual_reason' => 'Performance fixture',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $counts['attendance']++;
        if ($i <= 60) {
            $sessionId = DB::table('meeting_execution_sessions')->insertGetId([
                'uuid' => (string) Str::uuid(), 'meeting_id' => $meetingId, 'schedule_version_id' => $scheduleId,
                'participant_version_id' => $participantVersionId, 'status' => 'ENDED', 'started_at' => $now->copy()->subHour(),
                'started_by_user_id' => $owner->id, 'ended_at' => $now, 'ended_by_user_id' => $owner->id,
                'start_notes' => null, 'end_notes' => 'Performance fixture', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $minutesId = DB::table('meeting_minutes')->insertGetId([
                'uuid' => (string) Str::uuid(), 'meeting_id' => $meetingId, 'status' => 'FINALIZED', 'current_version_id' => null,
                'created_by_user_id' => $owner->id, 'finalized_at' => $now, 'finalized_by_user_id' => $owner->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $versionId = DB::table('meeting_minute_versions')->insertGetId([
                'uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutesId, 'version_number' => 1,
                'execution_session_id' => $sessionId, 'schedule_version_id' => $scheduleId, 'participant_version_id' => $participantVersionId,
                'summary' => 'Performance fixture minutes', 'general_notes' => null, 'prepared_by_user_id' => $owner->id,
                'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('meeting_minutes')->where('id', $minutesId)->update(['current_version_id' => $versionId]);
            $decisionId = DB::table('meeting_minute_version_decisions')->insertGetId([
                'uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $versionId, 'sequence_number' => 1,
                'decision_number' => $prefix.'DEC-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'decision_text' => 'Performance fixture decision',
                'responsible_unit_id' => $unitId, 'responsible_employee_id' => $employeeIds[0], 'target_date' => $date,
                'notes' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $counts['decisions']++;
            $followupId = DB::table('meeting_decision_followups')->insertGetId([
                'uuid' => (string) Str::uuid(), 'meeting_id' => $meetingId, 'meeting_minutes_id' => $minutesId,
                'meeting_minute_version_id' => $versionId, 'meeting_decision_id' => $decisionId,
                'decision_number' => $prefix.'DEC-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'classification' => 'ACTION_REQUIRED',
                'status' => 'OPEN', 'classified_by_user_id' => $owner->id, 'classified_at' => $now,
                'no_action_reason' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $counts['followups']++;
            for ($sequence = 1; $sequence <= 2; $sequence++) {
                $status = $sequence === 1 ? 'OPEN' : 'COMPLETED';
                DB::table('meeting_action_items')->insert([
                    'uuid' => (string) Str::uuid(), 'decision_followup_id' => $followupId, 'sequence_number' => $sequence,
                    'title' => $prefix.'action '.$i.'-'.$sequence, 'description' => 'Performance fixture action item.',
                    'responsible_unit_id' => $unitId, 'assigned_employee_id' => $employeeIds[0], 'status' => $status,
                    'progress_percent' => $status === 'COMPLETED' ? 100 : 0, 'due_date' => $status === 'OPEN' ? now()->subDay()->toDateString() : now()->addDays(8)->toDateString(),
                    'started_at' => $status === 'COMPLETED' ? $now->copy()->subDays(2) : null,
                    'submitted_for_verification_at' => null, 'submitted_by_user_id' => null, 'completed_at' => $status === 'COMPLETED' ? $now : null,
                    'completed_by_user_id' => $status === 'COMPLETED' ? $owner->id : null, 'cancelled_at' => null, 'cancelled_by_user_id' => null,
                    'cancel_reason' => null, 'created_by_user_id' => $owner->id, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $counts['action_items']++;
            }
        }
        $counts['requests']++;
        $counts['meetings']++;
    }
});
$artifact = ['fixture' => $prefix, 'database_driver' => DB::connection()->getDriverName(), 'counts' => $counts, 'meeting_ids' => $meetingIds];
$root = dirname(__DIR__, 2);
file_put_contents($root.'/flow14_mariadb_large_fixture.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
