<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\MeetingExceptionReview;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingTerminalAssignment;
use App\Models\MeetingUnit;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\OfficialAttendanceResolver;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_postponement_requires_versioned_director_review_and_invalidates_attendance_without_mutating_source(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $oldSchedule = $meeting->currentScheduleVersion()->firstOrFail()->toArray();
        $attendance = MeetingAttendanceRecord::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'employee_id' => $director->employee_id, 'participant_version_id' => $meeting->current_participant_version_id, 'attendance_method' => 'MANUAL_OVERRIDE', 'client_event_uuid' => (string) Str::uuid(), 'recorded_at_server' => now(), 'recorded_by_user_id' => $owner->id, 'manual_reason' => 'ثبت پیش از تعویق']);

        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'POSTPONEMENT', 'reason' => 'به دلیل وضعیت اضطراری جلسه در تاریخ فعلی برگزار نمی‌شود.'])->assertCreated()->json('data');
        $caseId = $created['id'];
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$caseId.'/submit')->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');
        $review = MeetingExceptionReview::firstOrFail();
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/approve')->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$caseId.'/apply')->assertOk()->assertJsonPath('data.status', 'APPLIED');

        $this->assertSame('POSTPONED', $meeting->fresh()->status);
        $this->assertSame($oldSchedule['id'], $meeting->fresh()->current_schedule_version_id);
        $this->assertSame('CONVERTED_TO_MEETING', $meeting->fresh()->request->status);
        $this->assertDatabaseHas('meeting_attendance_invalidations', ['meeting_attendance_record_id' => $attendance->id, 'reason' => 'MEETING_POSTPONED']);
        $this->assertSame(0, app(OfficialAttendanceResolver::class)->count($meeting->fresh()));
    }

    public function test_resumption_creates_new_schedule_version_and_old_schedule_remains_immutable(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $case = $this->applyCase($meeting, $owner, $director, 'POSTPONEMENT', 'تعویق جلسه برای بررسی شرایط.');
        $old = $meeting->fresh()->currentScheduleVersion()->firstOrFail();
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'RESUMPTION', 'reason' => 'زمان جدید برای برگزاری جلسه تعیین شد.', 'replacement_expected' => true])->assertCreated()->json('data');
        $resumeId = $created['id'];
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$resumeId.'/submit')->assertOk();
        $review = MeetingExceptionReview::whereHas('version', fn ($query) => $query->where('exception_case_id', $resumeId))->firstOrFail();
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/approve')->assertOk();
        $future = now()->addDays(14)->format('Y-m-d');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$resumeId.'/resume', ['meeting_date' => $future, 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار جدید'])->assertOk()->assertJsonPath('data.status', 'APPLIED');
        $meeting = $meeting->fresh(['currentScheduleVersion', 'scheduleVersions']);
        $this->assertSame('SCHEDULED', $meeting->status);
        $this->assertCount(2, $meeting->scheduleVersions);
        $this->assertNotSame((int) $old->id, (int) $meeting->current_schedule_version_id);
        $this->assertSame($old->location, $meeting->scheduleVersions->firstWhere('id', $old->id)->location);
    }

    public function test_only_one_active_case_and_completed_reopen_is_denied(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'POSTPONEMENT', 'reason' => 'تعویق نخست.'])->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'CANCELLATION', 'reason' => 'لغو دوم.'])->assertStatus(409)->assertJsonPath('code', 'MEETING_EXCEPTION_ACTIVE_CASE_EXISTS');
        $meeting->forceFill(['status' => 'COMPLETED'])->save();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'REOPENING', 'reason' => 'بازگشایی نباید مجاز باشد.'])->assertStatus(409)->assertJsonPath('code', 'COMPLETED_MEETING_REOPEN_NOT_ALLOWED');
    }

    public function test_cancellation_revokes_active_terminal_assignment_and_session(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $terminal = Terminal::create(['uuid' => (string) Str::uuid(), 'organization_id' => Organization::firstOrFail()->id, 'code' => 'FLOW13-TERM-'.uniqid(), 'name' => 'FLOW-13 terminal', 'serial_number' => 'FLOW13-SERIAL-'.uniqid(), 'manufacturer' => 'TEST', 'model' => 'FLOW13', 'platform' => 'TEST', 'os_version' => '1', 'app_version' => '1', 'status' => 'ACTIVE', 'registered_by_user_id' => $owner->id, 'registered_at' => now()]);
        $assignment = MeetingTerminalAssignment::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'terminal_id' => $terminal->id, 'baseline_schedule_version_id' => $meeting->current_schedule_version_id, 'baseline_participant_version_id' => $meeting->current_participant_version_id, 'valid_from' => now()->subMinute(), 'valid_until' => now()->addHour(), 'status' => 'ASSIGNED', 'assigned_by_user_id' => $owner->id, 'assigned_at' => now()]);
        $session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', 'flow13-terminal-token'), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
        $terminal->forceFill(['current_session_id' => $session->id])->save();
        $meeting->forceFill(['current_terminal_session_id' => $session->id])->save();
        $this->applyCase($meeting, $owner, $director, 'CANCELLATION', 'لغو رسمی جلسه به دلیل موجه.');
        $this->assertSame('CANCELLED', $meeting->fresh()->status);
        $this->assertSame('REVOKED', $assignment->fresh()->status);
        $this->assertSame('REVOKED', $session->fresh()->status);
        $this->assertNull($terminal->fresh()->current_session_id);
        $this->assertNull($meeting->fresh()->current_terminal_session_id);
    }

    public function test_correction_creates_new_version_and_old_review_does_not_transfer(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'POSTPONEMENT', 'reason' => 'نسخهٔ اول دلیل.'])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/submit')->assertOk();
        $review = MeetingExceptionReview::firstOrFail();
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/correction', ['reason' => 'دلیل نیاز به اصلاح دارد.'])->assertOk()->assertJsonPath('data.status', 'RETURNED_FOR_CORRECTION');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/submit')->assertStatus(409);
        $this->assertDatabaseHas('meeting_exception_reviews', ['id' => $review->id, 'status' => 'CORRECTION_REQUESTED']);
        $updated = $this->actingAs($owner, 'sanctum')->putJson('/api/meeting-exceptions/'.$created['id'].'/draft', ['reason' => 'نسخهٔ دوم دلیل اصلاح‌شده.'])->assertOk()->json('data');
        $this->assertSame(2, MeetingExceptionCaseVersion::where('exception_case_id', $created['id'])->count());
        $this->assertNotSame($created['current_version']['id'], $updated['current_version']['id']);
        $this->assertSame('CORRECTION_REQUESTED', $review->fresh()->status);
        $this->assertSame('DRAFT', $updated['status']);
    }

    public function test_duplicate_application_returns_terminal_already_applied_error(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'CANCELLATION', 'reason' => 'لغو برای آزمون تکرار عملیات.'])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/submit')->assertOk();
        $review = MeetingExceptionReview::where('exception_case_version_id', $created['current_version']['id'])->firstOrFail();
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/approve')->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/apply')->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/apply')->assertStatus(409)->assertJsonPath('code', 'MEETING_EXCEPTION_ALREADY_APPLIED');
    }

    public function test_non_owner_business_actors_cannot_create_or_apply_exception(): void
    {
        [$meeting, $owner] = $this->scheduledFixture();
        foreach (['authority.office@example.test', 'high.authority@example.test', 'system.admin@example.test'] as $email) {
            $actor = User::where('email', $email)->firstOrFail();
            $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'POSTPONEMENT', 'reason' => 'تلاش غیرمجاز.'])->assertStatus(403);
        }
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'POSTPONEMENT', 'reason' => 'تعویق مجاز.'])->assertCreated();
    }

    public function test_exception_workspace_returns_meeting_pagination_and_row_scoped_actions(): void
    {
        [$meeting, $owner, $director] = $this->scheduledFixture();
        $url = '/api/meetings/'.$meeting->id.'/exceptions';
        $this->getJson($url)->assertUnauthorized();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING']);
        $created = $this->actingAs($owner, 'sanctum')->postJson($url, ['case_type' => 'POSTPONEMENT', 'reason' => 'Review both units'])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->getJson($url)->assertOk()
            ->assertJsonPath('meeting.id', $meeting->id)->assertJsonPath('meeting.current_schedule_version.id', $meeting->current_schedule_version_id)
            ->assertJsonPath('data.0.action_capabilities.edit', true)->assertJsonPath('meta.total', 1);
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/submit')->assertOk();
        $rows = $this->actingAs($director, 'sanctum')->getJson($url)->assertOk()->json('data.0.reviews');
        foreach ($rows as $row) {
            $own = (int) $row['organizational_unit_id'] === (int) $meeting->organizing_unit_id;
            $this->assertSame($own, $row['action_capabilities']['approve']);
            $this->assertSame($own, $row['action_capabilities']['correction']);
        }
        $foreign = collect($rows)->firstWhere('organizational_unit_id', $finance->id);
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$foreign['id'].'/approve')->assertForbidden();
        $this->actingAs($owner, 'sanctum')->getJson($url.'?page=2&per_page=1')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->userForTest('system.admin@example.test'), 'sanctum')->getJson($url)->assertForbidden();
    }

    private function userForTest(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function applyCase(Meeting $meeting, User $owner, User $director, string $type, string $reason): MeetingExceptionCase
    {
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => $type, 'reason' => $reason])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/submit')->assertOk();
        $review = MeetingExceptionReview::where('exception_case_version_id', $created['current_version']['id'])->firstOrFail();
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/approve')->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/apply')->assertOk();

        return MeetingExceptionCase::findOrFail($created['id']);
    }

    private function scheduledFixture(): array
    {
        $owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employee = Employee::findOrFail($director->employee_id);
        $request = MeetingRequest::create(['request_number' => 'FLOW13-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'جلسهٔ استثنایی FLOW-13', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW13-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(8)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار FLOW-13', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
        app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);

        return [$meeting->fresh(['currentScheduleVersion', 'currentParticipantVersion']), $owner, $director];
    }
}
