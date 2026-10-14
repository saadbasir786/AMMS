<?php

namespace Tests\Feature;

use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\IssueTerminalCredential;
use App\Actions\Terminals\IssueTerminalPairingCode;
use App\Actions\Terminals\RegisterTerminal;
use App\Actions\Terminals\RevokeTerminal;
use App\Actions\Terminals\RotateTerminalCredential;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingParticipantVersionMember;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleVersion;
use App\Models\MeetingUnit;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Terminal;
use App\Models\TerminalPairingCode;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use App\Support\TerminalAssignmentConflictGuard;
use App\Support\TerminalCredentialManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MeetingTerminalAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_terminal_credential_is_hashed_rotated_and_revoked(): void
    {
        $terminal = $this->registerTerminal();
        $admin = $this->admin();
        $first = app(IssueTerminalCredential::class)->execute($admin, $terminal);
        $this->assertNotSame($first['token'], $first['credential']->token_hash);
        $this->assertSame(hash('sha256', $first['token']), $first['credential']->token_hash);
        $this->assertNotNull(app(TerminalCredentialManager::class)->resolve($first['token']));

        $second = app(RotateTerminalCredential::class)->execute($admin, $terminal);
        $this->assertNull(app(TerminalCredentialManager::class)->resolve($first['token']));
        $this->assertNotNull(app(TerminalCredentialManager::class)->resolve($second['token']));

        app(RevokeTerminal::class)->execute($admin, $terminal, 'آزمایش لغو ترمینال');
        $this->assertNull(app(TerminalCredentialManager::class)->resolve($second['token']));
        $this->assertSame('REVOKED', $terminal->fresh()->status);
    }

    public function test_terminal_pairing_code_is_short_lived_one_time_and_returns_a_credential(): void
    {
        $terminal = $this->registerTerminal();
        $admin = $this->admin();

        $issued = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/terminals/'.$terminal->id.'/pairing-code')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->json('data');

        $code = $issued['pairing_code'];
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
        $this->assertEquals(now()->addMinutes(3)->timestamp, CarbonImmutable::parse($issued['expires_at'])->timestamp);

        $pairingCode = TerminalPairingCode::query()->where('terminal_id', $terminal->id)->sole();
        $this->assertSame(hash('sha256', $code), $pairingCode->code_hash);
        $this->assertNotSame($code, $pairingCode->code_hash);

        $paired = $this->postJson('/api/terminal/v1/pair', ['pairing_code' => $code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.terminal.uuid', $terminal->uuid);

        $token = $paired->json('data.credential');
        $this->assertNotEmpty($token);
        $this->assertNotNull(app(TerminalCredentialManager::class)->resolve($token));
        $this->assertNotNull($pairingCode->fresh()->consumed_at);

        $this->postJson('/api/terminal/v1/pair', ['pairing_code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TERMINAL_PAIRING_CODE_INVALID');
    }

    public function test_issuing_a_new_terminal_pairing_code_invalidates_the_previous_code(): void
    {
        $terminal = $this->registerTerminal();
        $action = app(IssueTerminalPairingCode::class);
        $first = $action->execute($this->admin(), $terminal);
        $second = $action->execute($this->admin(), $terminal);

        $this->assertNotSame($first['code'], $second['code']);
        $this->postJson('/api/terminal/v1/pair', ['pairing_code' => $first['code']])->assertUnauthorized();
        $this->postJson('/api/terminal/v1/pair', ['pairing_code' => '123'])->assertUnprocessable();
        $this->postJson('/api/terminal/v1/pair', ['pairing_code' => $second['code']])->assertOk();
    }

    public function test_issuing_a_pairing_code_requires_terminal_management_authority(): void
    {
        $terminal = $this->registerTerminal();

        $this->postJson('/api/terminals/'.$terminal->id.'/pairing-code')->assertUnauthorized();

        $this->actingAs($this->user('tech.exec@example.test'), 'sanctum')
            ->postJson('/api/terminals/'.$terminal->id.'/pairing-code')
            ->assertForbidden();
    }

    public function test_unauthorized_human_cannot_register_terminal(): void
    {
        $meeting = $this->futureMeeting();
        $unit = $meeting->units()->with('unit')->first()->unit;
        $response = $this->actingAs($this->user('tech.exec@example.test'), 'sanctum')->postJson('/api/terminals', [
            'organization_id' => $unit->organization_id,
            'code' => 'UNAUTHORIZED-01',
            'name' => 'دستگاه غیرمجاز',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseCount('terminals', 0);
    }

    public function test_assignment_captures_current_schedule_and_participant_versions(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $this->assertSame($meeting->current_schedule_version_id, $assignment->baseline_schedule_version_id);
        $this->assertSame($meeting->current_participant_version_id, $assignment->baseline_participant_version_id);
        $this->assertSame('SCHEDULED', $meeting->fresh()->status);
        $this->assertSame('CONVERTED_TO_MEETING', $meeting->fresh()->request->status);
    }

    public function test_meeting_terminal_workspace_paginates_assignment_history_and_exposes_total_stats(): void
    {
        $meeting = $this->futureMeeting();
        $actor = $this->user('tech.exec@example.test');
        $window = $this->window($meeting);

        foreach (range(1, 2) as $index) {
            $terminal = $this->registerTerminal($meeting);
            app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, ...$window);
        }

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/meetings/'.$meeting->id.'/terminals?page=1&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.assignments')
            ->assertJsonPath('data.assignments_meta.per_page', 1)
            ->assertJsonPath('data.assignments_meta.total', 2)
            ->assertJsonPath('data.stats.total', 2);
    }

    public function test_terminal_api_isolated_and_activation_returns_one_time_session_token(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));

        $this->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])->getJson('/api/terminal/v1/me')
            ->assertOk()->assertJsonPath('data.terminal_uuid', $terminal->uuid)->assertJsonMissingPath('data.email');
        $activation = $this->withHeader('Authorization', 'Bearer '.$issued['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate');
        $activation->assertStatus(201)->assertJsonPath('data.session.status', 'ACTIVE');
        $sessionToken = $activation->json('data.session_token');
        $sessionUuid = $activation->json('data.session.uuid');
        $this->assertNotEmpty($sessionToken);
        $this->assertNotSame($sessionToken, $terminal->fresh()->sessions()->first()->session_token_hash);

        $manifest = $this->withHeader('Authorization', 'Bearer '.$sessionToken)->getJson('/api/terminal/v1/sessions/'.$sessionUuid.'/manifest');
        $manifest->assertOk()->assertJsonPath('data.schedule_version_id', $assignment->baseline_schedule_version_id)->assertJsonPath('data.participant_version_id', $assignment->baseline_participant_version_id);
        $data = $manifest->json('data');
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('phone', $data);
        foreach ($data['participants'] as $participant) {
            $this->assertArrayNotHasKey('email', $participant);
            $this->assertArrayNotHasKey('phone', $participant);
        }

        $this->withHeader('Authorization', 'Bearer '.$sessionToken)->postJson('/api/terminal/v1/sessions/'.$sessionUuid.'/close', ['reason' => 'پایان آزمایش'])->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->withHeader('Authorization', 'Bearer '.$sessionToken)->getJson('/api/terminal/v1/sessions/'.$sessionUuid.'/manifest')->assertStatus(401);
    }

    public function test_terminal_activation_accepts_in_progress_meeting_and_assignment_api_reports_actual_meeting_status(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $credential = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $otherTerminal = $this->registerTerminal($meeting);
        $otherCredential = app(IssueTerminalCredential::class)->execute($this->admin(), $otherTerminal);
        app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $otherTerminal, ...$this->window($meeting));
        $meeting->forceFill(['status' => 'IN_PROGRESS'])->saveQuietly();

        $this->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$credential['token'])
            ->getJson('/api/terminal/v1/assignments/current')
            ->assertOk()
            ->assertJsonPath('data.0.meeting_status', 'IN_PROGRESS');

        $this->withHeader('Authorization', 'Bearer '.$otherCredential['token'])
            ->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')
            ->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$credential['token'])
            ->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')
            ->assertCreated()
            ->assertJsonPath('data.session.status', 'ACTIVE');
    }

    public function test_terminal_activation_is_rejected_after_meeting_attendance_lifecycle_closes(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $credential = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $meeting->forceFill(['status' => 'HELD'])->saveQuietly();

        $this->withHeader('Authorization', 'Bearer '.$credential['token'])
            ->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MEETING_NOT_ATTENDANCE_ELIGIBLE')
            ->assertJsonPath('message', 'ثبت حضور فقط برای جلسهٔ برنامه‌ریزی‌شده یا در حال برگزاری ممکن است.');

        $this->assertDatabaseCount('terminal_meeting_sessions', 0);
        $this->assertNull($terminal->fresh()->current_session_id);
    }

    public function test_terminal_can_resume_its_active_assignment_with_a_rotated_session_token(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $first = $this->withHeader('Authorization', 'Bearer '.$issued['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertStatus(201);
        $firstToken = $first->json('data.session_token');
        $sessionUuid = $first->json('data.session.uuid');

        $resumed = $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')
            ->assertOk()
            ->assertJsonPath('data.session.uuid', $sessionUuid);
        $resumedToken = $resumed->json('data.session_token');

        $this->assertNotSame($firstToken, $resumedToken);
        $this->assertSame(hash('sha256', $resumedToken), $terminal->fresh()->sessions()->sole()->session_token_hash);
        $this->assertDatabaseHas('terminal_audit_events', [
            'action' => 'TERMINAL_SESSION_RESUMED',
            'session_id' => $resumed->json('data.session.id'),
        ]);
        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/terminal/v1/sessions/'.$sessionUuid.'/manifest')
            ->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$resumedToken)
            ->getJson('/api/terminal/v1/sessions/'.$sessionUuid.'/manifest')
            ->assertOk();
    }

    public function test_stale_participant_binding_blocks_activation_until_refresh(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $old = $meeting->currentParticipantVersion;
        $new = MeetingParticipantVersion::create(['meeting_id' => $meeting->id, 'version_number' => 2, 'source_change_case_id' => null, 'created_by_user_id' => $this->admin()->id, 'created_at' => now()]);
        foreach ($old->members as $member) {
            MeetingParticipantVersionMember::create(['participant_version_id' => $new->id, 'employee_id' => $member->employee_id, 'organizational_unit_id' => $member->organizational_unit_id, 'participant_role' => $member->participant_role, 'owner_type' => $member->owner_type, 'owner_unit_id' => $member->owner_unit_id, 'is_mandatory' => $member->is_mandatory, 'created_at' => now()]);
        }
        $meeting->forceFill(['current_participant_version_id' => $new->id])->save();
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/participants')
            ->assertStatus(422)
            ->assertJsonPath('code', 'TERMINAL_ASSIGNMENT_STALE');
        $this->assertSame('ASSIGNED', $assignment->fresh()->status);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertStatus(422)->assertJsonPath('code', 'TERMINAL_ASSIGNMENT_STALE')->assertJsonPath('errors.participants_changed', true);
        $freshAssignment = $assignment->fresh('meeting');
        $this->assertTrue($freshAssignment->isStale($meeting->fresh()));
    }

    public function test_duplicate_active_terminal_assignment_is_rejected_without_revoke(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $window = $this->window($meeting);
        app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$window);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('TERMINAL_ASSIGNMENT_EXISTS');
        app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$window);
    }

    public function test_cross_organization_and_unavailable_terminal_are_rejected(): void
    {
        $meeting = $this->futureMeeting();
        $other = Organization::create(['name' => 'سازمان جداگانه', 'short_name' => 'OTHER', 'type' => 'GOVERNMENT', 'status' => 'ACTIVE']);
        $foreign = app(RegisterTerminal::class)->execute($this->admin(), ['organization_id' => $other->id, 'code' => 'FOREIGN-'.uniqid(), 'name' => 'ترمینال بیرونی', 'status' => 'ACTIVE']);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('TERMINAL_OUTSIDE_MEETING_ORGANIZATION');
        app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $foreign, ...$this->window($meeting));
    }

    public function test_schedule_version_change_returns_safe_stale_metadata(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $current = $meeting->currentScheduleVersion;
        $next = MeetingScheduleVersion::create(['meeting_id' => $meeting->id, 'version_number' => 2, 'meeting_date' => $current->meeting_date, 'start_time' => $current->start_time, 'end_time' => $current->end_time, 'location' => $current->location, 'created_by_user_id' => $this->admin()->id, 'created_at' => now()]);
        $meeting->forceFill(['current_schedule_version_id' => $next->id])->save();
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertStatus(422)->assertJsonPath('code', 'TERMINAL_ASSIGNMENT_STALE')->assertJsonPath('errors.schedule_changed', true)->assertJsonPath('errors.participants_changed', false);
    }

    public function test_heartbeat_updates_operational_telemetry_without_exposing_rbac(): void
    {
        $terminal = $this->registerTerminal();
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])->postJson('/api/terminal/v1/heartbeat', ['app_version' => '2.4.1', 'health' => ['battery' => 82]])->assertOk()->assertJsonPath('data.terminal_uuid', $terminal->uuid)->assertJsonMissingPath('data.roles')->assertJsonMissingPath('data.users');
        $fresh = $terminal->fresh();
        $this->assertSame('2.4.1', $fresh->app_version);
        $this->assertNotNull($fresh->last_seen_at);
    }

    public function test_current_assignments_are_limited_to_authenticated_terminal(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $response = $this->withHeader('Authorization', 'Bearer '.$issued['token'])->getJson('/api/terminal/v1/assignments/current')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($assignment->uuid, $response->json('data.0.assignment_uuid'));
    }

    public function test_terminal_can_read_its_assignment_participants_without_activating_a_session(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $issued = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $participantCount = $meeting->currentParticipantVersion->members()->count();
        $firstMember = $meeting->currentParticipantVersion->members()->with('employee')->orderBy('id')->firstOrFail();
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/participants?page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment_uuid', $assignment->uuid)
            ->assertJsonPath('participant_version_id', $assignment->baseline_participant_version_id)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', $participantCount)
            ->assertJsonPath('data.0.attendance_status', 'NOT_CHECKED_IN')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.phone');

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/participants?page=2&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(1, 'data');

        $search = $firstMember->employee->first_name;
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/participants?search='.urlencode($search))
            ->assertOk()
            ->assertJsonPath('data.0.employee_id', $firstMember->employee_id);

        $this->assertDatabaseCount('terminal_meeting_sessions', 0);
        $this->assertNull($terminal->fresh()->current_session_id);
        $this->assertNull($meeting->fresh()->current_terminal_session_id);
    }

    public function test_terminal_participant_list_is_scoped_and_hidden_until_assignment_window_opens(): void
    {
        $meeting = $this->futureMeeting();
        $firstTerminal = $this->registerTerminal($meeting);
        $secondTerminal = $this->registerTerminal($meeting);
        $firstCredential = app(IssueTerminalCredential::class)->execute($this->admin(), $firstTerminal);
        $secondCredential = app(IssueTerminalCredential::class)->execute($this->admin(), $secondTerminal);
        $window = $this->window($meeting);
        $firstAssignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $firstTerminal, ...$window);
        $secondAssignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $secondTerminal, ...$window);

        $this->getJson('/api/terminal/v1/assignments/'.$firstAssignment->uuid.'/participants')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$firstCredential['token'])
            ->getJson('/api/terminal/v1/assignments/'.$secondAssignment->uuid.'/participants')
            ->assertNotFound();

        $firstAssignment->forceFill(['valid_from' => now()->addMinute()])->save();
        $this->withHeader('Authorization', 'Bearer '.$firstCredential['token'])
            ->getJson('/api/terminal/v1/assignments/'.$firstAssignment->uuid.'/participants')
            ->assertForbidden()
            ->assertJsonPath('code', 'TERMINAL_ASSIGNMENT_WINDOW_NOT_OPEN');

        $this->assertDatabaseCount('terminal_meeting_sessions', 0);
    }

    public function test_assignment_revoke_invalidates_active_session_and_preserves_history(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $credential = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $activation = $this->withHeader('Authorization', 'Bearer '.$credential['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertStatus(201);
        $sessionToken = $activation->json('data.session_token');
        $sessionUuid = $activation->json('data.session.uuid');
        $this->actingAs($this->user('tech.exec@example.test'), 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/terminal-assignments/'.$assignment->id.'/revoke', ['reason' => 'لغو assignment برای آزمایش امنیت'])->assertOk();
        $this->assertSame('REVOKED', $assignment->fresh()->status);
        $this->assertSame('REVOKED', $terminal->fresh()->sessions()->where('uuid', $sessionUuid)->value('status'));
        $this->withHeader('Authorization', 'Bearer '.$sessionToken)->getJson('/api/terminal/v1/sessions/'.$sessionUuid.'/manifest')->assertStatus(401);
    }

    public function test_expired_session_is_rejected_even_before_cleanup(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $credential = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $activation = $this->withHeader('Authorization', 'Bearer '.$credential['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertStatus(201);
        $session = $terminal->fresh()->sessions()->where('uuid', $activation->json('data.session.uuid'))->firstOrFail();
        $session->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->withHeader('Authorization', 'Bearer '.$activation->json('data.session_token'))->getJson('/api/terminal/v1/sessions/'.$session->uuid.'/manifest')->assertStatus(401);
        $this->assertSame('EXPIRED', $session->fresh()->status);
    }

    public function test_active_session_becomes_stale_after_participant_version_changes(): void
    {
        $meeting = $this->futureMeeting();
        $terminal = $this->registerTerminal($meeting);
        $credential = app(IssueTerminalCredential::class)->execute($this->admin(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($this->user('tech.exec@example.test'), $meeting, $terminal, ...$this->window($meeting));
        $activation = $this->withHeader('Authorization', 'Bearer '.$credential['token'])->postJson('/api/terminal/v1/assignments/'.$assignment->uuid.'/activate')->assertStatus(201);
        $sessionUuid = $activation->json('data.session.uuid');
        $old = $meeting->fresh('currentParticipantVersion.members')->currentParticipantVersion;
        $new = MeetingParticipantVersion::create(['meeting_id' => $meeting->id, 'version_number' => 2, 'source_change_case_id' => null, 'created_by_user_id' => $this->admin()->id, 'created_at' => now()]);
        foreach ($old->members as $member) {
            MeetingParticipantVersionMember::create(['participant_version_id' => $new->id, 'employee_id' => $member->employee_id, 'organizational_unit_id' => $member->organizational_unit_id, 'participant_role' => $member->participant_role, 'owner_type' => $member->owner_type, 'owner_unit_id' => $member->owner_unit_id, 'is_mandatory' => $member->is_mandatory, 'created_at' => now()]);
        }
        $meeting->forceFill(['current_participant_version_id' => $new->id])->save();
        $this->withHeader('Authorization', 'Bearer '.$activation->json('data.session_token'))->getJson('/api/terminal/v1/sessions/'.$sessionUuid.'/manifest')->assertStatus(422)->assertJsonPath('code', 'TERMINAL_SESSION_STALE');
        $this->assertSame('REVOKED', $terminal->fresh()->sessions()->where('uuid', $sessionUuid)->value('status'));
    }

    private function admin()
    {
        return $this->user('system.admin@example.test');
    }

    private function user(string $email)
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function futureMeeting(): Meeting
    {
        $existing = Meeting::query()->with(['request', 'units.unit', 'currentScheduleVersion', 'currentParticipantVersion.members'])->where('status', 'SCHEDULED')->whereDate('meeting_date', '>=', today())->whereNotNull('current_schedule_version_id')->whereNotNull('current_participant_version_id')->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        $tech = $this->user('tech.exec@example.test');
        $request = MeetingRequest::create(['request_number' => 'FLOW09-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $this->unit('TECH')->id, 'created_by' => $tech->id, 'subject' => 'جلسهٔ آزمایشی FLOW-09', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $tech->id]);
        $employees = collect(['tech.director@example.test', 'finance.director@example.test', 'hr.director@example.test'])->map(fn ($email) => Employee::where('email', $email)->firstOrFail());
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW09-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $this->unit('TECH')->id, 'coordinator_id' => $tech->id, 'chairperson_employee_id' => $employees->get(1)->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار آزمایش FLOW-09', 'status' => 'SCHEDULED']);
        foreach (['TECH', 'FIN', 'HR'] as $code) {
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $this->unit($code)->id, 'role' => 'PARTICIPATING']);
        }
        foreach ($employees as $employee) {
            MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        }
        $schedule = app(ScheduleVersionService::class);
        $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $tech->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $tech->id);

        return $meeting->fresh(['request', 'units.unit', 'currentScheduleVersion', 'currentParticipantVersion.members.employee.unit']);
    }

    private function unit(string $code)
    {
        return OrganizationalUnit::where('code', $code)->firstOrFail();
    }

    private function registerTerminal(?Meeting $meeting = null): Terminal
    {
        $meeting ??= $this->futureMeeting();
        $unit = $meeting->units->first(fn ($item) => in_array($item->role, ['REQUESTING', 'PARTICIPATING'], true))->unit;

        return app(RegisterTerminal::class)->execute($this->admin(), [
            'organization_id' => $unit->organization_id,
            'code' => 'FLOW09-'.uniqid(),
            'name' => 'ترمینال آزمایشی FLOW-09',
            'manufacturer' => 'DERMALOG',
            'model' => 'SVT3000 SE',
            'platform' => 'Android',
            'os_version' => '7.1.2',
            'status' => 'ACTIVE',
        ]);
    }

    private function window(Meeting $meeting): array
    {
        $schedule = $meeting->currentScheduleVersion;
        $date = $schedule->meeting_date->format('Y-m-d');
        $from = now('UTC')->subMinutes(5);
        $until = CarbonImmutable::parse($date.' '.$schedule->end_time, TerminalAssignmentConflictGuard::TIMEZONE)->addDay();

        return [$from->toIso8601String(), $until->toIso8601String()];
    }
}
