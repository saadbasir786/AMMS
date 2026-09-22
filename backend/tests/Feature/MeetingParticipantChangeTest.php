<?php

namespace Tests\Feature;

use App\Actions\Meetings\AddMeetingParticipantChangeItem;
use App\Actions\Meetings\ConfirmMeetingParticipantChange;
use App\Actions\Meetings\CreateMeetingParticipantChangeCase;
use App\Actions\Meetings\DeleteMeetingParticipantChangeItem;
use App\Actions\Meetings\RefreshParticipantChangeCaseBaseline;
use App\Actions\Meetings\RejectMeetingParticipantChange;
use App\Actions\Meetings\RemoveMeetingParticipantChangeItem;
use App\Actions\Meetings\ReturnMeetingParticipantChange;
use App\Actions\Meetings\SubmitMeetingParticipantChange;
use App\Actions\Meetings\UpdateMeetingParticipantChangeCase;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantVersionMember;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationAuthority;
use App\Models\User;
use App\Models\AmmsRoleScope;
use Spatie\Permission\Models\Role;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MeetingParticipantChangeTest extends TestCase
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

        $changeAction = $fresh->meeting->rescheduleActions()->where('action', 'MEETING_PARTICIPANTS_CHANGED')->firstOrFail();
        $this->assertSame($employees['techDirector']->full_name, $changeAction->metadata['operations'][0]['old_employee']['full_name']);
        $this->assertSame($employees['techEmployee']->full_name, $changeAction->metadata['operations'][0]['new_employee']['full_name']);
        $this->actingAs($users['techDirector'], 'sanctum')
            ->getJson("/api/meetings/{$meeting->id}/participant-change")
            ->assertOk()
            ->assertJsonPath('data.participant_actions.0.metadata.operations.0.old_employee.full_name', $employees['techDirector']->full_name)
            ->assertJsonPath('data.participant_actions.0.metadata.operations.0.new_employee.full_name', $employees['techEmployee']->full_name)
            ->assertJsonPath('data.participant_versions.0.source_change_case.items.0.old_employee.full_name', $employees['techDirector']->full_name)
            ->assertJsonPath('data.participant_versions.0.source_change_case.items.0.new_employee.full_name', $employees['techEmployee']->full_name)
            ->assertJsonPath('data.participant_versions.0.created_by.name', $users['techDirector']->name);
    }

    public function test_deleting_a_draft_item_keeps_the_affected_participant_in_audit_metadata(): void
    {
        [$meeting, $users, $employees] = $this->peerMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'ط¯ظ„غŒظ„ طھط؛غŒغŒط± ط¹ط¶ظˆ');
        $item = app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['techDirector']->id, $employees['techEmployee']->id);

        app(DeleteMeetingParticipantChangeItem::class)->execute($meeting, $case, $item, $users['techExec']);

        $deleteAction = $meeting->fresh()->rescheduleActions()->where('action', 'PARTICIPANT_CHANGE_ITEM_DELETED')->firstOrFail();
        $this->assertSame('REPLACE', $deleteAction->metadata['operation']);
        $this->assertSame($employees['techDirector']->full_name, $deleteAction->metadata['old_employee']['full_name']);
        $this->assertSame($employees['techEmployee']->full_name, $deleteAction->metadata['new_employee']['full_name']);
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

    public function test_internal_directorate_roles_and_self_approval_are_enforced(): void
    {
        [$meeting, $users, $employees] = $this->internalMeeting();
        try {
            app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techDirector'], 'دلیل تغییر');
            $this->fail('A Director must not create the internal draft.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'دلیل تغییر');
        $case = app(UpdateMeetingParticipantChangeCase::class)->execute($meeting, $case, $users['techExec'], 'دلیل به‌روزشده');
        $this->assertSame('دلیل به‌روزشده', $case->reason);
        try {
            app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techDirector'], 'REPLACE', $employees['oldInternal']->id, $employees['newInternal']->id);
            $this->fail('Director must not edit the preparer draft.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $employees['newInternal']->id);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);

        foreach ([$users['techExec'], $users['financeDirector']] as $denied) {
            try {
                app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $denied);
                $this->fail('Only a different Director of the same Directorate may confirm.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $techUnitId = (int) $meeting->request->requesting_unit_id;
        $directorRole = Role::findByName('DIRECTOR', 'sanctum');
        $users['techExec']->assignRole($directorRole);
        AmmsRoleScope::create(['user_id' => $users['techExec']->id, 'role_id' => $directorRole->id, 'organizational_unit_id' => $techUnitId]);
        try {
            app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
            $this->fail('The preparer must not self-approve even when also scoped as Director.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('PENDING_CONFIRMATION', $case->fresh()->status);
        $this->assertSame($employees['oldInternal']->id, $meeting->fresh()->participants()->where('employee_id', $employees['oldInternal']->id)->value('employee_id'));
    }

    public function test_internal_draft_rejects_contradictions_and_last_directorate_participant_removal(): void
    {
        [$meeting, $users, $employees] = $this->internalMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'دلیل تغییر');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $employees['newInternal']->id);

        foreach ([
            fn () => app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REMOVE', $employees['oldInternal']->id, null),
            fn () => app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'ADD', null, $employees['newInternal']->id),
        ] as $contradiction) {
            try {
                $contradiction();
                $this->fail('Contradictory operations should be rejected immediately.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }

        [$meeting2, $users2, $employees2] = $this->internalMeeting();
        $removeCase = app(CreateMeetingParticipantChangeCase::class)->execute($meeting2, $users2['techExec'], 'حذف آخر عضو');
        try {
            app(RemoveMeetingParticipantChangeItem::class)->execute($meeting2, $removeCase, $users2['techExec'], $employees2['oldInternal']->id);
            $this->fail('Removing the final valid Directorate participant must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(0, $removeCase->fresh()->items()->count());
        $this->assertSame(2, $meeting2->fresh()->participants()->count());
    }

    public function test_internal_workflow_notifications_and_mandatory_rejection_reason(): void
    {
        [$meeting, $users, $employees] = $this->internalMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'دلیل تغییر');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $employees['newInternal']->id);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        $this->assertDatabaseHas('amms_notifications', ['user_id' => $users['techDirector']->id, 'type' => 'PARTICIPANT_CHANGE_REVIEW_REQUIRED']);

        app(ReturnMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector'], 'لطفاً دلیل اصلاح را تکمیل کنید.');
        $this->assertDatabaseHas('amms_notifications', ['user_id' => $users['techExec']->id, 'type' => 'PARTICIPANT_CHANGE_RETURNED']);
        $this->assertSame('PARTICIPANT_CHANGE_RETURNED', $meeting->fresh()->rescheduleActions()->latest('id')->value('action'));
        $this->actingAs($users['techExec'], 'sanctum')->getJson("/api/meetings/{$meeting->id}/participant-change")
            ->assertOk()
            ->assertJsonPath('data.latest_director_review.action', 'PARTICIPANT_CHANGE_RETURNED')
            ->assertJsonPath('data.latest_director_review.comment', 'لطفاً دلیل اصلاح را تکمیل کنید.')
            ->assertJsonPath('data.latest_director_review.actor.id', $users['techDirector']->id);

        $this->actingAs($users['techExec'], 'sanctum')
            ->getJson("/api/meetings/{$meeting->id}/participant-change?participant_versions_page=2&actions_page=1&per_page=1")
            ->assertOk()
            ->assertJsonPath('data.participant_versions_meta.current_page', 2)
            ->assertJsonPath('data.participant_actions_meta.current_page', 1);

        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        try {
            app(RejectMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector'], '  ');
            $this->fail('Rejection requires a reason.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        app(RejectMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector'], 'درخواست به دلیل نبود ضرورت رد شد.');
        $this->assertDatabaseHas('amms_notifications', ['user_id' => $users['techExec']->id, 'type' => 'PARTICIPANT_CHANGE_REJECTED']);
        $this->assertNull($meeting->fresh()->active_participant_change_case_id);
        $this->assertSame($employees['oldInternal']->id, $meeting->fresh()->participants()->where('employee_id', $employees['oldInternal']->id)->value('employee_id'));
    }

    public function test_internal_candidates_must_be_active_and_in_the_same_directorate_and_chair_is_immutable(): void
    {
        [$meeting, $users, $employees] = $this->internalMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'دلیل تغییر');
        try {
            app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $this->employee('finance.exec@example.test')->id);
            $this->fail('Cross-Directorate participants must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame('EMPLOYEE_OUTSIDE_PARTICIPANT_DOMAIN', $exception->getMessage());
        }
        $inactive = Employee::create(['employee_number' => 'INTERNAL-INACTIVE-'.uniqid(), 'first_name' => 'Inactive', 'last_name' => 'Candidate', 'organizational_unit_id' => $this->unit('TECH')->id, 'status' => 'INACTIVE']);
        try {
            app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $inactive->id);
            $this->fail('Inactive participants must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame('EMPLOYEE_INACTIVE', $exception->getMessage());
        }

        [$chairMeeting, $chairUsers, $chairEmployees] = $this->internalMeeting();
        $chairMeeting->update(['chairperson_employee_id' => $chairEmployees['oldInternal']->id]);
        $chairCase = app(CreateMeetingParticipantChangeCase::class)->execute($chairMeeting, $chairUsers['techExec'], 'دلیل تغییر');
        try {
            app(RemoveMeetingParticipantChangeItem::class)->execute($chairMeeting, $chairCase, $chairUsers['techExec'], $chairEmployees['oldInternal']->id);
            $this->fail('The chairperson cannot be removed.');
        } catch (HttpException $exception) {
            $this->assertSame('CHAIRPERSON_PARTICIPANT_REQUIRED', $exception->getMessage());
        }
    }

    public function test_internal_submit_and_confirm_revalidate_central_schedule_conflicts(): void
    {
        [$meeting, $users, $employees] = $this->internalMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'دلیل تغییر');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $employees['newInternal']->id);

        $conflict = $this->conflictingMeetingFor($meeting, $users['techExec'], $employees['newInternal']);
        try {
            app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
            $this->fail('Submit must detect participant schedule conflicts.');
        } catch (HttpException $exception) {
            $this->assertSame('PARTICIPANT_SCHEDULE_CONFLICT', $exception->getMessage());
        }

        $conflict->update(['status' => 'CANCELLED']);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        $confirmConflict = $this->conflictingMeetingFor($meeting, $users['techExec'], $employees['newInternal']);
        try {
            app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
            $this->fail('Confirm must revalidate participant schedule conflicts.');
        } catch (HttpException $exception) {
            $this->assertSame('PARTICIPANT_SCHEDULE_CONFLICT', $exception->getMessage());
        }
        $this->assertSame('PENDING_CONFIRMATION', $case->fresh()->status);
        $this->assertSame($employees['oldInternal']->id, $meeting->fresh()->participants()->where('employee_id', $employees['oldInternal']->id)->value('employee_id'));
        $confirmConflict->update(['status' => 'CANCELLED']);
        app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
        $this->assertSame(2, $meeting->fresh('currentParticipantVersion')->currentParticipantVersion->version_number);
    }

    public function test_internal_schedule_version_change_requires_refresh_and_resubmission(): void
    {
        [$meeting, $users, $employees] = $this->internalMeeting();
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['techExec'], 'دلیل تغییر');
        app(AddMeetingParticipantChangeItem::class)->execute($meeting, $case, $users['techExec'], 'REPLACE', $employees['oldInternal']->id, $employees['newInternal']->id);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        $schedule = app(ScheduleVersionService::class)->createNext($meeting, [
            'meeting_date' => now()->addDays(25)->format('Y-m-d'),
            'start_time' => '13:00',
            'end_time' => '14:00',
            'location' => 'تالار جدید',
        ], $users['techExec']->id);
        app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
        try {
            app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
            $this->fail('Stale schedule baseline must block confirmation.');
        } catch (HttpException $exception) {
            $this->assertSame('MEETING_SCHEDULE_CHANGED_REVIEW_REQUIRED', $exception->getMessage());
        }
        $refreshed = app(RefreshParticipantChangeCaseBaseline::class)->execute($meeting, $case, $users['techExec']);
        $this->assertSame('DRAFT', $refreshed->status);
        app(SubmitMeetingParticipantChange::class)->execute($meeting, $case, $users['techExec']);
        app(ConfirmMeetingParticipantChange::class)->execute($meeting, $case, $users['techDirector']);
        $this->assertSame(2, $meeting->fresh('currentParticipantVersion')->currentParticipantVersion->version_number);
    }

    public function test_direct_participant_mutation_route_is_blocked(): void
    {
        [$meeting, $users] = $this->peerMeeting();
        $this->actingAs($users['techExec'], 'sanctum')->patchJson("/api/meetings/{$meeting->id}/participants/1", ['employee_id' => 99])->assertStatus(404);
    }

    public function test_authority_candidate_scope_includes_descendants_but_rejects_ancestors(): void
    {
        [$meeting, $users] = $this->authorityMeeting();
        $office = $this->unit('AUTH-OFFICE');
        $parent = OrganizationalUnit::create(['organization_id' => $office->organization_id, 'name' => 'Parent office', 'code' => 'TEST-PARENT', 'unit_type' => $office->unit_type, 'status' => 'ACTIVE']);
        $office->update(['parent_id' => $parent->id]);
        $child = OrganizationalUnit::create(['organization_id' => $office->organization_id, 'parent_id' => $office->id, 'name' => 'Child office', 'code' => 'TEST-CHILD', 'unit_type' => $office->unit_type, 'status' => 'ACTIVE']);
        $make = fn ($unit, $number) => Employee::create(['employee_number' => $number, 'first_name' => 'ScopeCandidate', 'last_name' => $number, 'organizational_unit_id' => $unit->id, 'status' => 'ACTIVE']);
        $allowed = $make($child, 'SCOPE-CHILD');
        $denied = $make($parent, 'SCOPE-PARENT');
        $url = "/api/meetings/{$meeting->id}/participant-change/employees?search=ScopeCandidate&per_page=1";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($users['authorityOffice'], 'sanctum')->getJson($url)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $allowed->id);
        $case = app(CreateMeetingParticipantChangeCase::class)->execute($meeting, $users['authorityOffice'], 'Add office representative');
        $this->actingAs($users['authorityOffice'], 'sanctum')->postJson("/api/meetings/{$meeting->id}/participant-change/{$case->id}/items", ['operation' => 'ADD', 'new_employee_id' => $denied->id])
            ->assertStatus(422);
        $this->assertSame(0, $case->items()->count());
        $this->actingAs($users['authorityOffice'], 'sanctum')->postJson("/api/meetings/{$meeting->id}/participant-change/{$case->id}/items", ['operation' => 'ADD', 'new_employee_id' => $allowed->id])
            ->assertCreated();
        $this->assertSame(1, $case->items()->count());
        $this->actingAs($this->user('system.admin@example.test'), 'sanctum')->getJson($url)->assertForbidden();
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
        foreach (['TECH', 'FIN', 'HR'] as $code) {
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $this->unit($code)->id, 'role' => 'PARTICIPATING']);
        }
        foreach ([$employees['techDirector'], $employees['financeDirector'], $employees['hrDirector']] as $employee) {
            MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        }
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
        foreach ($meeting->participantVersions as $version) {
            $version->members()->delete();
        }
        $meeting->participantVersions()->delete();
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting, $users['techExec']->id);
        $users['secretary'] = $this->user('executive.secretary@example.test');
        $users['authorityOffice'] = $this->user('authority.office@example.test');

        return [$meeting->fresh(['request', 'authorityContext', 'units.unit', 'participants.employee.unit', 'currentParticipantVersion.members.employee.unit', 'currentScheduleVersion']), $users, $employees];
    }

    private function internalMeeting(): array
    {
        [$meeting, $users] = $this->peerMeeting();
        $techUnit = $this->unit('TECH');
        $meeting->request->update(['flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $techUnit->id]);
        $meeting->units()->where('organizational_unit_id', '<>', $techUnit->id)->delete();
        $meeting->participants()->delete();
        $chairperson = $this->employee('finance.director@example.test');
        $meeting->update(['chairperson_employee_id' => $chairperson->id]);
        $meeting->participants()->create(['employee_id' => $chairperson->id, 'organizational_unit_id' => $chairperson->organizational_unit_id, 'role' => 'PARTICIPATING']);

        $oldEmployee = Employee::create(['employee_number' => 'INTERNAL-OLD-'.uniqid(), 'first_name' => 'Internal', 'last_name' => 'Old', 'organizational_unit_id' => $techUnit->id, 'status' => 'ACTIVE']);
        $newEmployee = Employee::create(['employee_number' => 'INTERNAL-NEW-'.uniqid(), 'first_name' => 'Internal', 'last_name' => 'New', 'organizational_unit_id' => $techUnit->id, 'status' => 'ACTIVE']);
        $meeting->participants()->create(['employee_id' => $oldEmployee->id, 'organizational_unit_id' => $techUnit->id, 'role' => 'PARTICIPATING']);

        $version = $meeting->currentParticipantVersion;
        $version->members()->delete();
        foreach ([$chairperson, $oldEmployee] as $employee) {
            MeetingParticipantVersionMember::create([
                'participant_version_id' => $version->id,
                'employee_id' => $employee->id,
                'organizational_unit_id' => $techUnit->id,
                'participant_role' => 'PARTICIPATING',
                'owner_type' => (int) $employee->organizational_unit_id === (int) $techUnit->id ? 'UNIT' : 'AUTHORITY',
                'owner_unit_id' => (int) $employee->organizational_unit_id === (int) $techUnit->id ? $techUnit->id : null,
                'is_mandatory' => false,
                'created_at' => now(),
            ]);
        }

        return [$meeting->fresh(['request', 'units.unit', 'participants.employee.unit', 'currentParticipantVersion.members.employee.unit', 'currentScheduleVersion']), $users, ['oldInternal' => $oldEmployee, 'newInternal' => $newEmployee]];
    }

    private function conflictingMeetingFor(Meeting $meeting, User $actor, Employee $participant): Meeting
    {
        $unitId = (int) $meeting->organizing_unit_id;
        $request = MeetingRequest::create([
            'request_number' => 'INTERNAL-CONFLICT-'.uniqid(),
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $unitId,
            'created_by' => $actor->id,
            'subject' => 'تعارض زمان‌بندی آزمایشی',
            'purpose' => 'COORDINATION',
            'status' => 'CONVERTED_TO_MEETING',
            'coordinator_id' => $actor->id,
        ]);
        $conflict = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => 'INTERNAL-CONFLICT-M-'.uniqid(),
            'title' => $request->subject,
            'organizing_unit_id' => $unitId,
            'coordinator_id' => $actor->id,
            'chairperson_employee_id' => $this->employee('tech.director@example.test')->id,
            'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
            'start_time' => $meeting->start_time,
            'end_time' => $meeting->end_time,
            'location' => 'محل دیگر',
            'status' => 'SCHEDULED',
        ]);
        MeetingParticipant::create([
            'meeting_id' => $conflict->id,
            'employee_id' => $participant->id,
            'organizational_unit_id' => $participant->organizational_unit_id,
            'role' => 'PARTICIPATING',
        ]);
        return $conflict;
    }
}
