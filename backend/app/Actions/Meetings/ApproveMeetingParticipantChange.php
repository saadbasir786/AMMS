<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApproveMeetingParticipantChange
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor): MeetingParticipantChangeCase
    {
        return DB::transaction(function () use ($meeting, $case, $actor): MeetingParticipantChangeCase {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            if (! $this->governance()->isInternalDirectorate($meeting)) {
                throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');
            }

            $this->assertConfirm($actor, $case, $this->governance());
            $this->assertState($case, ['PENDING_CONFIRMATION']);
            abort_unless((int) $meeting->active_participant_change_case_id === (int) $case->id, 422, 'PARTICIPANT_CHANGE_NOT_ACTIVE');
            $this->validator()->assertCaseReady($case);
            $this->lockEmployeesForItems($case);
            foreach ($case->items as $item) {
                $this->validator()->assertItem($case, $item->operation, $item->old_employee_id, $item->new_employee_id);
            }
            $this->validator()->assertInternalDraftConsistent($case, $case->items->map(fn ($item): array => [
                'operation' => $item->operation,
                'old_employee_id' => $item->old_employee_id,
                'new_employee_id' => $item->new_employee_id,
                'participant_role' => $item->participant_role,
            ])->all());
            $members = $this->validator()->applyDelta($case, $case->baselineParticipantVersion->members->map(fn ($member): array => [
                'employee_id' => (int) $member->employee_id,
                'organizational_unit_id' => (int) $member->organizational_unit_id,
                'participant_role' => $member->participant_role,
                'owner_type' => $member->owner_type,
                'owner_unit_id' => $member->owner_unit_id,
                'is_mandatory' => (bool) $member->is_mandatory,
            ])->all());
            $candidateIds = $case->items->whereIn('operation', ['ADD', 'REPLACE'])->pluck('new_employee_id')->filter()->all();
            $this->validator()->assertFinalConflicts($meeting, $members, $candidateIds);

            $approvedAt = now();
            $draftHash = $this->draftFingerprint($case);
            $case->forceFill([
                'status' => 'APPROVED',
                'approved_by_user_id' => $actor->id,
                'approved_at' => $approvedAt,
                'approved_participant_version_id' => $meeting->current_participant_version_id,
                'approved_schedule_version_id' => $meeting->current_schedule_version_id,
                'approved_draft_hash' => $draftHash,
            ])->save();

            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_APPROVED', $case->reason, [
                'change_case_id' => $case->id,
                'review_cycle' => (int) $case->review_cycle,
                'submitted_by_user_id' => (int) $case->submitted_by_user_id,
                'approved_by_user_id' => (int) $actor->id,
                'approved_by_name' => $actor->name,
                'approved_at' => $approvedAt->toIso8601String(),
                'approved_participant_version_id' => $case->approved_participant_version_id,
                'approved_schedule_version_id' => $case->approved_schedule_version_id,
                'approved_draft_hash' => $draftHash,
                'operations' => $case->items->map(fn ($item): array => [
                    'operation' => $item->operation,
                    'old_employee_id' => $item->old_employee_id,
                    'new_employee_id' => $item->new_employee_id,
                ])->values()->all(),
            ]);
            $this->notifyScopedRoleAndUsers(
                (int) $case->owner_unit_id,
                'EXECUTIVE_MANAGEMENT',
                [(int) $case->initiated_by_user_id, (int) $case->submitted_by_user_id],
                'PARTICIPANT_CHANGE_APPROVED',
                'پرونده تغییر اعضا تأیید شد',
                'رئیس پرونده را تأیید کرد؛ برای اعمال تغییرات آن را نهایی کنید.',
                $meeting,
            );

            return $case->fresh(['items.oldEmployee.unit', 'items.newEmployee.unit', 'submittedBy', 'approvedBy']);
        });
    }
}
