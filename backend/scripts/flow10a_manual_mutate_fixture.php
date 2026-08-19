<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$type = $argv[1] ?? '';
$fixture = json_decode(file_get_contents('/tmp/flow10a_manual_fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$actor = User::query()->where('email', $fixture['actor_email'])->firstOrFail();
$meeting = Meeting::query()->findOrFail($fixture['meeting_id']);

if ($type === 'schedule') {
    $next = app(ScheduleVersionService::class)->createNext($meeting, [
        'meeting_date' => now()->addDays(31)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'location' => 'FLOW10A-MANUAL-SCHEDULE-CHANGE',
    ], $actor->id);
    app(ScheduleVersionService::class)->applyToMeeting($meeting, $next);
    echo json_encode(['mutation' => 'schedule', 'schedule_version_id' => $next->id]).PHP_EOL;
    exit(0);
}

if ($type === 'participants') {
    $case = MeetingParticipantChangeCase::create([
        'meeting_id' => $meeting->id,
        'owner_type' => 'UNIT',
        'owner_unit_id' => $meeting->organizing_unit_id,
        'baseline_participant_version_id' => $meeting->current_participant_version_id,
        'baseline_schedule_version_id' => $meeting->current_schedule_version_id,
        'initiated_by_user_id' => $actor->id,
        'reason' => 'FLOW10A manual stale-session evidence',
        'status' => 'CONFIRMED',
        'confirmed_at' => now(),
    ]);
    $meeting->load('currentParticipantVersion.members');
    $members = $meeting->currentParticipantVersion->members->map(fn ($member): array => [
        'employee_id' => $member->employee_id,
        'organizational_unit_id' => $member->organizational_unit_id,
        'participant_role' => $member->participant_role,
        'owner_type' => $member->owner_type,
        'owner_unit_id' => $member->owner_unit_id,
        'is_mandatory' => $member->is_mandatory,
    ])->all();
    $version = app(ParticipantVersionService::class)->createConfirmedVersion($meeting, $case, $members, $actor->id);
    echo json_encode(['mutation' => 'participants', 'participant_version_id' => $version->id]).PHP_EOL;
    exit(0);
}

fwrite(STDERR, "Usage: php flow10a_manual_mutate_fixture.php schedule|participants\n");
exit(2);
