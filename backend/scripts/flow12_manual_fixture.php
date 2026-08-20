<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
$employee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$tag = 'F12-HTTP-'.Str::upper(Str::random(8));
$request = MeetingRequest::create(['request_number' => $tag.'-REQ', 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'جلسهٔ آزمایشی پیگیری تصمیمات', 'purpose' => 'MANUAL_FLOW12', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
$meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $tag.'-MEETING', 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'اتاق آزمایش', 'status' => 'COMPLETED']);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class);
$scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $meeting->location], $owner->id);
$schedule->applyToMeeting($meeting, $scheduleVersion);
$participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
$session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $owner->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $owner->id]);
$minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $owner->id, 'finalized_at' => now()->subMinutes(10), 'finalized_by_user_id' => $owner->id]);
$version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'خلاصهٔ نهایی آزمایشی', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subMinutes(20)]);
$decision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => 'HTTP-DEC-001', 'decision_text' => 'اقدام آزمایشی اجرا گردد.', 'responsible_unit_id' => $unit->id, 'responsible_employee_id' => $employee->id, 'target_date' => now()->subDay()->format('Y-m-d'), 'notes' => 'منبع immutable']);
$noActionDecision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 2, 'decision_number' => 'HTTP-DEC-002', 'decision_text' => 'اقدام جداگانه لازم نیست.', 'responsible_unit_id' => $unit->id, 'target_date' => now()->addDays(7)->format('Y-m-d'), 'notes' => 'دلیل عدم اقدام در manual gate']);
$minutes->update(['current_version_id' => $version->id]);
echo json_encode(['meeting_id' => $meeting->id, 'meeting_request_id' => $request->id, 'decision_id' => $decision->id, 'no_action_decision_id' => $noActionDecision->id, 'unit_id' => $unit->id, 'finance_unit_id' => OrganizationalUnit::where('code', 'FIN')->value('id'), 'employee_id' => $employee->id, 'owner_email' => $owner->email, 'employee_email' => $employee->email, 'tag' => $tag, 'before_source_snapshot' => ['request_status' => $request->status, 'meeting_status' => $meeting->status, 'minutes_status' => $minutes->status, 'meeting_minute_version_id' => $version->id, 'decision_text' => $decision->decision_text, 'decision_target_date' => $decision->target_date?->format('Y-m-d'), 'decision_responsible_unit_id' => $decision->responsible_unit_id, 'decision_responsible_employee_id' => $decision->responsible_employee_id]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
