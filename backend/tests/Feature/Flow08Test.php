<?php

namespace Tests\Feature;

use App\Actions\Meetings\AddMeetingParticipantChangeItem;
use App\Actions\Meetings\ConfirmMeetingParticipantChange;
use App\Actions\Meetings\CreateMeetingParticipantChangeCase;
use App\Actions\Meetings\RejectMeetingParticipantChange;
use App\Actions\Meetings\RefreshParticipantChangeCaseBaseline;
use App\Actions\Meetings\RemoveMeetingParticipantChangeItem;
use App\Actions\Meetings\ReturnMeetingParticipantChange;
use App\Actions\Meetings\SubmitMeetingParticipantChange;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Flow08Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_backfill_creates_version_one_without_mutating_projection(): void
    {
        [$meeting] = $this->peerMeeting();
        $before = $meeting->participants()->orderBy('employee_id')->pluck('employee_id')->all();
        $version = $meeting->fresh('currentParticipantVersion.members')->currentParticipantVersion;
        $this->assertSame(1, $version->version_number);
        $this->assertSame($before, $version->members->sortBy('employee_id')->pluck('employee_id')->values()->all());
        $this->assertSame($before, $meeting->fresh()->participants()->orderBy('employee_id')->pluck('employee_id')->all());
    }

    public function test_replace_is_atomic_and_preserves_identity_and_closed_request(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'به دلیل مأموریت رسمی نماینده قبلی، نماینده جدید معرفی می‌گردد.');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['techEmployee']->id, 'PARTICIPATING');
        $this->assertSame([$employees['techDirector']->id, $employees['financeDirector']->id, $employees['hrDirector']->id], $meeting->fresh()->participants()->orderBy('employee_id')->pluck('employee_id')->all());
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        $confirmed = app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
        $fresh = $confirmed->fresh(['meeting.request', 'meeting.currentParticipantVersion.members', 'meeting.participants']);
        $this->assertSame('CONFIRMED', $fresh->status);
        $this->assertSame('SCHEDULED', $fresh->meeting->status);
        $this->assertSame('CONVERTED_TO_MEETING', $fresh->meeting->request->status);
        $this->assertSame($meeting->id, $fresh->meeting->id);
        $this->assertSame(2, $fresh->meeting->currentParticipantVersion->version_number);
        $this->assertSame(collect([$employees['financeDirector']->id, $employees['hrDirector']->id, $employees['techEmployee']->id])->sort()->values()->all(), $fresh->meeting->participants->sortBy('employee_id')->pluck('employee_id')->values()->all());
        $this->assertSame(2, $fresh->meeting->participantVersions()->count());
        $this->assertSame(1, $fresh->meeting->rescheduleActions()->where('action', 'MEETING_PARTICIPANTS_CHANGED')->count());
    }

    public function test_draft_does_not_mutate_current_projection_and_one_active_case_is_enforced(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'تغییر نماینده ریاست تکنالوژی');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['techEmployee']->id);
        $this->assertSame($employees['techDirector']->id, $meeting->fresh()->participants()->where('organizational_unit_id', $this->unit('TECH')->id)->value('employee_id'));
        $this->expectException(HttpException::class);
        app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'پروندهٔ دوم نباید ایجاد شود');
    }

    public function test_authority_office_cannot_prepare_peer_case(): void
    {
        [$meeting, $users] = $this->peerMeeting();
        try {
            app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['authorityOffice'], 'Authority Office نباید Directorate را تغییر دهد');
            $this->fail('Authority Office must not prepare a Directorate-owned participant change.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('UNAUTHORIZED_PARTICIPANT_CHANGE', $exception->getMessage());
        }
    }

    public function test_authority_participant_ownership_is_authority_office_only(): void
    {
        [$meeting, $users] = $this->authorityMeeting();
        try {
            app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['secretary'], 'سکرتر نباید مالک participant مقام باشد');
            $this->fail('Executive Secretary must not own Authority participant changes.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('UNAUTHORIZED_PARTICIPANT_CHANGE', $exception->getMessage());
        }
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['authorityOffice'], 'Authority Office مالک رسمی است');
        try {
            app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'ADD', null, $this->employee('employee.a@example.test')->id);
            $this->fail('Technology must not manage an Authority-owned case.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('UNAUTHORIZED_PARTICIPANT_CHANGE', $exception->getMessage());
        }
    }

    public function test_remove_last_participant_of_formal_unit_is_rejected(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'حفاظت حداقل participant ریاست تکنالوژی');
        app(RemoveMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], $employees['techDirector']->id);
        try {
            app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
            $this->fail('The last unit participant must not be submitted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('UNIT_PARTICIPANT_REQUIRED', $exception->getMessage());
        }
    }

    public function test_cross_unit_confirmation_is_rejected_and_owner_director_can_confirm(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'تغییر نماینده ریاست تکنالوژی');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['techEmployee']->id);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        try {
            app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['financeDirector']);
            $this->fail('Finance Director must not confirm Technology-owned participant changes.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('UNAUTHORIZED_PARTICIPANT_CONFIRMATION', $exception->getMessage());
        }
        $confirmed = app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
        $this->assertSame('CONFIRMED', $confirmed->status);
    }

    public function test_cross_unit_candidate_is_rejected(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'تغییر نماینده');
        try {
            app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['financeExec']->id);
            $this->fail('Cross-unit candidate should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('EMPLOYEE_OUTSIDE_PARTICIPANT_DOMAIN', $exception->getMessage());
        }
    }

    public function test_chairperson_and_high_authority_are_protected(): void
    {
        [$meeting, $users, $employees] = $this->authorityMeeting();
        $authorityCase = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['authorityOffice'], 'تغییر نماینده دفتر مقام');
        try {
            app(RemoveMeetingParticipantChangeItem::class)->execute($meeting, $authorityCase, $users['authorityOffice'], $employees['authority']->id);
            $this->fail('High Authority must remain immutable.');
        } catch (HttpException $exception) {
            $this->assertSame('HIGH_AUTHORITY_PARTICIPANT_IMMUTABLE', $exception->getMessage());
        }

        [$peer, $peerUsers, $peerEmployees] = $this->peerMeeting();
        $financeCase = app(CreateMeetingParticipantChangeCase::class)->execute($peer, $peerUsers['financeExec'], 'حفاظت رئیس جلسه');
        try {
            app(RemoveMeetingParticipantChangeItem::class)->execute($peer, $financeCase, $peerUsers['financeExec'], $peerEmployees['financeDirector']->id);
            $this->fail('Chairperson must remain a participant.');
        } catch (HttpException $exception) {
            $this->assertSame('CHAIRPERSON_PARTICIPANT_REQUIRED', $exception->getMessage());
        }
    }

    public function test_return_resubmit_and_reject_keep_projection_unchanged(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'اصلاح نماینده');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['techEmployee']->id);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        app(ReturnMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector'], 'لطفاً دلیل و نقش نماینده را تکمیل کنید.');
        $this->assertSame('RETURNED_FOR_CORRECTION', $case->fresh()->status);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        app(RejectMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector'], 'تغییر فعلاً لازم نیست.');
        $this->assertSame('REJECTED', $case->fresh()->status);
        $this->assertNull($meeting->fresh()->active_participant_change_case_id);
        $this->assertSame($employees['techDirector']->id, $meeting->fresh()->participants()->where('organizational_unit_id', $this->unit('TECH')->id)->value('employee_id'));
    }

    public function test_schedule_baseline_change_requires_refresh_before_confirmation(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'تغییر نماینده');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['techEmployee']->id);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        $newSchedule = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => now()->addDays(15)->format('Y-m-d'), 'start_time' => '13:00', 'end_time' => '14:00', 'location' => 'تالار جدید'], $users['techExec']->id);
        app(ScheduleVersionService::class)->applyToMeeting($meeting, $newSchedule);
        try {
            app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
            $this->fail('Stale schedule baseline must block confirmation.');
        } catch (HttpException $exception) {
            $this->assertSame('MEETING_SCHEDULE_CHANGED_REVIEW_REQUIRED', $exception->getMessage());
        }
        app(RefreshParticipantChangeCaseBaseline::class)->execute($meeting, $case, $users['techExec']);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
        $this->assertSame(2, $meeting->fresh('currentParticipantVersion')->currentParticipantVersion->version_number);
    }

    public function test_direct_participant_mutation_route_is_blocked(): void
    {
        [$meeting, $users] = $this->peerMeeting();
        $this->actingAs($users['techExec'], 'sanctum')->patchJson("/api/meetings/{$meeting->id}/participants/1", ['employee_id' => 99])->assertStatus(404);
    }

    private function unit(string $code): OrganizationalUnit
    {
        return OrganizationalUnit::where('code', $code)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function employee(string $email): Employee
    {
        return Employee::where('email', $email)->firstOrFail();
    }

    private function peerMeeting(): array
    {
        $users = ['techExec' => $this->user('tech.exec@example.test'), 'financeExec' => $this->user('finance.exec@example.test'), 'techDirector' => $this->user('tech.director@example.test'), 'financeDirector' => $this->user('finance.director@example.test'), 'hrDirector' => $this->user('hr.director@example.test'), 'authorityOffice' => $this->user('authority.office@example.test')];
        $employees = ['techDirector' => $this->employee('tech.director@example.test'), 'techEmployee' => $this->employee('employee.a@example.test'), 'financeDirector' => $this->employee('finance.director@example.test'), 'financeExec' => $this->employee('finance.exec@example.test'), 'hrDirector' => $this->employee('hr.director@example.test')];
        $request = MeetingRequest::create(['request_number' => 'FLOW08-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $this->unit('TECH')->id, 'created_by' => $users['techExec']->id, 'subject' => 'جلسهٔ آزمایشی FLOW-08', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $users['techExec']->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW08-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $this->unit('TECH')->id, 'coordinator_id' => $users['techExec']->id, 'chairperson_employee_id' => $employees['financeDirector']->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار آزمایش', 'status' => 'SCHEDULED']);
        foreach (['TECH', 'FIN', 'HR'] as $code) MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $this->unit($code)->id, 'role' => 'PARTICIPATING']);
        foreach ([$employees['techDirector'], $employees['financeDirector'], $employees['hrDirector']] as $employee) MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        app(ScheduleVersionService::class)->applyToMeeting($meeting, app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $users['techExec']->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $users['techExec']->id);
        return [$meeting->fresh(['request', 'units.unit', 'participants.employee.unit', 'currentParticipantVersion.members.employee.unit', 'currentScheduleVersion']), $users, $employees];
    }

    private function authorityMeeting(): array
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $meeting->request->update(['flow_type' => 'HIGH_AUTHORITY_INITIATED']);
        $authority = OrganizationAuthority::where('authority_office_unit_id', $this->unit('AUTH-OFFICE')->id)->firstOrFail();
        $employees['authority'] = $this->employee('high.authority@example.test');
        $employees['secretary'] = $this->employee('executive.secretary@example.test');
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employees['authority']->id, 'organizational_unit_id' => $employees['authority']->organizational_unit_id, 'role' => 'HIGH_AUTHORITY']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employees['secretary']->id, 'organizational_unit_id' => $employees['secretary']->organizational_unit_id, 'role' => 'AUTHORITY_SUPPORT']);
        MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => $authority->id, 'authority_type' => $authority->authority_type, 'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $employees['authority']->id, 'authority_office_unit_id' => $authority->authority_office_unit_id]);
        $meeting->update(['chairperson_employee_id' => $employees['authority']->id]);
        $meeting = $meeting->fresh(['request.authorityContext', 'authorityContext', 'units.unit', 'participants.employee.unit']);
        $meeting->update(['current_participant_version_id' => null]);
        foreach ($meeting->participantVersions as $version) $version->members()->delete();
        $meeting->participantVersions()->delete();
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting, $users['techExec']->id);
        $users['secretary'] = $this->user('executive.secretary@example.test');
        $users['authorityOffice'] = $this->user('authority.office@example.test');
        return [$meeting->fresh(['request', 'authorityContext', 'units.unit', 'participants.employee.unit', 'currentParticipantVersion.members.employee.unit', 'currentScheduleVersion']), $users, $employees];
    }
}
