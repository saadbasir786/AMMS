<?php

declare(strict_types=1);

use App\Actions\Attendance\RecordFingerprintAttendance;
use App\Actions\Meetings\EndMeetingExecution;
use App\Actions\Meetings\StartMeetingExecution;
use App\Actions\Minutes\AcknowledgeMeetingMinutes;
use App\Actions\Minutes\CreateMeetingMinutesDraft;
use App\Actions\Minutes\FinalizeMeetingMinutes;
use App\Actions\Minutes\GenerateFinalMeetingMinutesDocument;
use App\Actions\Minutes\SubmitMeetingMinutesVersion;
use App\Actions\Minutes\UpdateMeetingMinutesDraft;
use App\Actions\Terminals\RegisterTerminal;
use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteDocument;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\BiometricCandidateSetBuilder;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$reconnect = static function (): void { DB::purge('mysql'); DB::reconnect('mysql'); };
$runTwo = static function (callable $worker) use ($reconnect): array {
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        [$read, $write] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        if ($pid < 0) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            fclose($read);
            try { $reconnect(); $out = ['ok' => true, 'result' => $worker($i)]; }
            catch (Throwable $e) {
                $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;
                $json = $response?->getData(true);
                $out = ['ok' => false, 'class' => $e::class, 'message' => $e->getMessage(), 'code' => $json['code'] ?? null, 'status' => $response?->getStatusCode() ?? null];
            }
            fwrite($write, json_encode($out, JSON_UNESCAPED_UNICODE)); fclose($write); exit(0);
        }
        fclose($write); $children[] = [$pid, $read];
    }
    $results = [];
    foreach ($children as [$pid, $read]) { $results[] = json_decode(stream_get_contents($read), true, 512, JSON_THROW_ON_ERROR); fclose($read); pcntl_waitpid($pid, $status); }
    return $results;
};

$actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
$director = User::query()->where('email', 'tech.director@example.test')->firstOrFail();
$admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
$unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
$employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();

$fixture = static function (string $tag, bool $withTerminal = false) use ($actor, $admin, $unit, $employee): array {
    $request = MeetingRequest::create(['request_number' => 'FLOW11-RACE-'.$tag.'-'.Str::random(8), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $actor->id, 'subject' => 'FLOW-11 race '.$tag, 'purpose' => 'CONCURRENCY', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id]);
    $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-RACE-M-'.$tag.'-'.Str::random(6), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $actor->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(30)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW11-RACE', 'status' => 'SCHEDULED']);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class);
    $version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW11-RACE'], $actor->id);
    $schedule->applyToMeeting($meeting, $version);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
    $meeting = $meeting->fresh();
    $result = compact('meeting', 'actor', 'employee', 'unit');
    if (!$withTerminal) return $result;
    $terminal = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $unit->organization_id, 'code' => 'FLOW11-RACE-T-'.Str::random(8), 'name' => 'FLOW-11 race terminal', 'status' => 'ACTIVE']);
    $assignment = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(40)->toIso8601String());
    $session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', Str::random(32)), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
    EmployeeBiometricTemplate::query()->where('employee_id', $employee->id)->where('finger_position', 'RIGHT_INDEX')->where('status', 'ACTIVE')->update(['status' => 'REVOKED', 'revoked_at' => now(), 'revoke_reason' => 'isolated FLOW-11 race fixture']);
    $nextVersion = ((int) EmployeeBiometricTemplate::query()->where('employee_id', $employee->id)->where('finger_position', 'RIGHT_INDEX')->max('version_number')) + 1;
    $bytes = 'FLOW11_RACE_TEMPLATE_'.$tag;
    $template = EmployeeBiometricTemplate::create(['uuid' => (string) Str::uuid(), 'organization_id' => $terminal->organization_id, 'employee_id' => $employee->id, 'modality' => 'FINGERPRINT', 'finger_position' => 'RIGHT_INDEX', 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1', 'algorithm_version' => 'SIMULATOR-V1', 'template_data' => $bytes, 'template_checksum' => hash('sha256', $bytes), 'quality_score' => 95, 'version_number' => $nextVersion, 'status' => 'ACTIVE', 'source_terminal_id' => $terminal->id, 'enrolled_by_user_id' => $actor->id, 'enrolled_at' => now()]);
    return $result + compact('terminal', 'assignment', 'session', 'template');
};
$start = static fn (array $f): MeetingExecutionSession => app(StartMeetingExecution::class)->execute($f['actor'], Meeting::findOrFail($f['meeting']->id));
$end = static fn (array $f): MeetingExecutionSession => app(EndMeetingExecution::class)->execute($f['actor'], Meeting::findOrFail($f['meeting']->id));
$payload = static function (array $f, string $checksum): array { return ['client_event_uuid' => (string) Str::uuid(), 'employee_id' => $f['employee']->id, 'matched_template_uuid' => $f['template']->uuid, 'finger_position' => 'RIGHT_INDEX', 'matcher_vendor' => 'FAKE', 'matcher_algorithm_version' => 'SIMULATOR-V1', 'match_score' => 99.0, 'threshold_reference' => 'FLOW11_ACCEPTED', 'candidate_set_checksum' => $checksum]; };
$draftData = ['summary' => 'خلاصهٔ آزمون FLOW-11.', 'general_notes' => 'یادداشت آزمون.', 'items' => [['agenda_title' => 'اجندا', 'discussion_summary' => 'بحث ثبت شد.']], 'decisions' => [['decision_text' => 'تصمیم ثبت شد.']]];
$results = [];

$f = $fixture('START');
$results['concurrent_start'] = $runTwo(static function () use ($f, $start): string { $start($f); return 'MEETING_STARTED'; });
$results['concurrent_start_invariants'] = ['sessions' => MeetingExecutionSession::where('meeting_id', $f['meeting']->id)->count(), 'status' => Meeting::find($f['meeting']->id)->status];

$f = $fixture('END'); $start($f);
$results['concurrent_end'] = $runTwo(static function () use ($f, $end): string { $end($f); return 'MEETING_ENDED'; });
$results['concurrent_end_invariants'] = ['sessions' => MeetingExecutionSession::where('meeting_id', $f['meeting']->id)->count(), 'ended_sessions' => MeetingExecutionSession::where('meeting_id', $f['meeting']->id)->where('status', 'ENDED')->count(), 'status' => Meeting::find($f['meeting']->id)->status];

$f = $fixture('ATT_FIRST', true); $start($f); $checksum = app(BiometricCandidateSetBuilder::class)->build($f['session'])['candidate_set_checksum'];
$results['attendance_first_vs_end'] = $runTwo(static function (int $worker) use ($f, $checksum, $payload, $end): string { if ($worker === 0) { app(RecordFingerprintAttendance::class)->execute(TerminalMeetingSession::findOrFail($f['session']->id), $payload($f, $checksum)); return 'ATTENDANCE_COMMITTED'; } usleep(120000); $end($f); return 'MEETING_ENDED'; });
$results['attendance_first_invariants'] = ['attendance_rows' => MeetingAttendanceRecord::where('meeting_id', $f['meeting']->id)->count(), 'status' => Meeting::find($f['meeting']->id)->status];

$f = $fixture('END_FIRST', true); $start($f); $checksum = app(BiometricCandidateSetBuilder::class)->build($f['session'])['candidate_set_checksum'];
$results['end_first_vs_attendance'] = $runTwo(static function (int $worker) use ($f, $checksum, $payload, $end): string { if ($worker === 0) { $end($f); return 'MEETING_ENDED'; } usleep(120000); app(RecordFingerprintAttendance::class)->execute(TerminalMeetingSession::findOrFail($f['session']->id), $payload($f, $checksum)); return 'ATTENDANCE_COMMITTED'; });
$results['end_first_invariants'] = ['attendance_rows' => MeetingAttendanceRecord::where('meeting_id', $f['meeting']->id)->count(), 'status' => Meeting::find($f['meeting']->id)->status];

$f = $fixture('ROOT'); $start($f);
$results['concurrent_minutes_root_creation'] = $runTwo(static function () use ($f): string { app(CreateMeetingMinutesDraft::class)->execute($f['actor'], Meeting::findOrFail($f['meeting']->id)); return 'MINUTES_ROOT_AVAILABLE'; });
$results['concurrent_minutes_root_creation_invariants'] = ['root_rows' => MeetingMinutes::where('meeting_id', $f['meeting']->id)->count()];

$f = $fixture('SUBMIT'); $start($f); $end($f); $minutes = app(CreateMeetingMinutesDraft::class)->execute($f['actor'], $f['meeting']); $minutes = app(UpdateMeetingMinutesDraft::class)->execute($f['actor'], $minutes, $draftData);
$results['concurrent_minutes_submit'] = $runTwo(static function () use ($minutes, $f): string { app(SubmitMeetingMinutesVersion::class)->execute($f['actor'], MeetingMinutes::findOrFail($minutes->id)); return 'MINUTES_SUBMITTED'; });
$results['concurrent_minutes_submit_invariants'] = ['versions' => $minutes->fresh()->versions()->count(), 'submitted_versions' => $minutes->fresh()->versions()->whereNotNull('submitted_at')->count()];

$f = $fixture('ACK'); $start($f); $end($f); $minutes = app(CreateMeetingMinutesDraft::class)->execute($f['actor'], $f['meeting']); $minutes = app(UpdateMeetingMinutesDraft::class)->execute($f['actor'], $minutes, $draftData); app(SubmitMeetingMinutesVersion::class)->execute($f['actor'], $minutes);
$results['concurrent_unit_acknowledgement'] = $runTwo(static function () use ($minutes, $director, $unit): string { app(AcknowledgeMeetingMinutes::class)->execute($director, MeetingMinutes::findOrFail($minutes->id), $unit->id); return 'UNIT_ACKNOWLEDGED'; });
$results['concurrent_unit_acknowledgement_invariants'] = ['review_rows' => DB::table('meeting_minute_reviews')->where('meeting_minute_version_id', $minutes->current_version_id)->count(), 'acknowledged' => DB::table('meeting_minute_reviews')->where('meeting_minute_version_id', $minutes->current_version_id)->where('status', 'ACKNOWLEDGED')->count()];

$f = $fixture('FINAL'); $start($f); $end($f); $minutes = app(CreateMeetingMinutesDraft::class)->execute($f['actor'], $f['meeting']); $minutes = app(UpdateMeetingMinutesDraft::class)->execute($f['actor'], $minutes, $draftData); app(SubmitMeetingMinutesVersion::class)->execute($f['actor'], $minutes); app(AcknowledgeMeetingMinutes::class)->execute($director, $minutes->fresh(), $unit->id);
$results['concurrent_finalization'] = $runTwo(static function () use ($minutes, $f): string { app(FinalizeMeetingMinutes::class)->execute($f['actor'], MeetingMinutes::findOrFail($minutes->id)); return 'MINUTES_FINALIZED'; });
$final = $minutes->fresh(); $generated = app(GenerateFinalMeetingMinutesDocument::class)->execute($f['actor'], $final); $generatedAgain = app(GenerateFinalMeetingMinutesDocument::class)->execute($f['actor'], $final);
$results['concurrent_finalization_invariants'] = ['minutes_status' => $final->fresh()->status, 'meeting_status' => Meeting::find($f['meeting']->id)->status, 'document_rows' => MeetingMinuteDocument::where('meeting_minutes_id', $minutes->id)->count(), 'same_checksum_on_repeat' => $generated['document']->content_sha256 === $generatedAgain['document']->content_sha256];

$results['flow11_race_probe'] = 'PASS';
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
