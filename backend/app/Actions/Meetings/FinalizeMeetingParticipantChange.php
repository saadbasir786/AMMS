<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use App\Support\ParticipantVersionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FinalizeMeetingParticipantChange
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
            $this->assertActive($actor);
            $this->assertState($case, ['APPROVED']);
            if ((int) $case->approved_by_user_id === (int) $actor->id) {
                throw new HttpException(403, 'PARTICIPANT_CHANGE_APPROVER_CANNOT_FINALIZE');
            }
            if (! $this->governance()->mayFinalize(
                $meeting,
                $actor,
                $case->owner_type,
                $case->owner_unit_id,
                $case->approved_by_user_id ? (int) $case->approved_by_user_id : null,
            )) {
                throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CHANGE_FINALIZATION');
            }
            abort_unless((int) $meeting->active_participant_change_case_id === (int) $case->id, 422, 'PARTICIPANT_CHANGE_NOT_ACTIVE');
            if (! $case->approved_by_user_id || ! $case->approved_at || ! $case->approved_draft_hash) {
                throw new HttpException(422, 'PARTICIPANT_CHANGE_APPROVAL_INVALIDATED');
            }
            if ((int) $case->approved_participant_version_id !== (int) $meeting->current_participant_version_id
                || (int) $case->approved_participant_version_id !== (int) $case->baseline_participant_version_id) {
                throw new HttpException(422, 'PARTICIPANT_VERSION_CHANGED_REVIEW_REQUIRED');
            }
            if ((int) ($case->approved_schedule_version_id ?? 0) !== (int) ($meeting->current_schedule_version_id ?? 0)
                || (int) ($case->approved_schedule_version_id ?? 0) !== (int) ($case->baseline_schedule_version_id ?? 0)) {
                throw new HttpException(422, 'MEETING_SCHEDULE_CHANGED_REVIEW_REQUIRED');
            }
            if (! hash_equals((string) $case->approved_draft_hash, $this->draftFingerprint($case))) {
                throw new HttpException(422, 'PARTICIPANT_CHANGE_APPROVAL_INVALIDATED');
            }

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

            $version = app(ParticipantVersionService::class)->createConfirmedVersion($meeting, $case, $members, (int) $actor->id);
            $case->forceFill(['status' => 'CONFIRMED', 'confirmed_at' => now()])->save();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_FINALIZED', $case->reason, [
                'change_case_id' => $case->id,
                'review_cycle' => (int) $case->review_cycle,
                'approved_by_user_id' => (int) $case->approved_by_user_id,
                'approved_at' => $case->approved_at?->toIso8601String(),
                'finalized_by_user_id' => (int) $actor->id,
                'finalized_by_name' => $actor->name,
                'participant_version_id' => $version->id,
                'participant_version_number' => $version->version_number,
                'baseline_participant_version_id' => $case->approved_participant_version_id,
                'baseline_schedule_version_id' => $case->approved_schedule_version_id,
            ]);

            foreach ($case->items as $item) {
                if ($item->operation === 'ADD' && $item->new_employee_id) {
                    $this->notifyEmployee((int) $item->new_employee_id, 'PARTICIPANT_ADDED', 'افزوده‌شدن به جلسه', 'شما به فهرست اشتراک‌کنندگان این جلسه افزوده شدید.', $meeting);
                } elseif ($item->operation === 'REMOVE' && $item->old_employee_id) {
                    $this->notifyEmployee((int) $item->old_employee_id, 'PARTICIPANT_REMOVED', 'لغو اشتراک در جلسه', 'اشتراک شما در این جلسه لغو گردید.', $meeting);
                } elseif ($item->operation === 'REPLACE') {
                    if ($item->old_employee_id) $this->notifyEmployee((int) $item->old_employee_id, 'PARTICIPANT_REPLACED', 'تغییر نماینده جلسه', 'اشتراک شما در این جلسه با نماینده جدید جایگزین گردید.', $meeting);
                    if ($item->new_employee_id) $this->notifyEmployee((int) $item->new_employee_id, 'PARTICIPANT_REPLACED', 'افزوده‌شدن به جلسه', 'شما به‌عنوان نماینده جدید به این جلسه افزوده شدید.', $meeting);
                }
            }

            return $case->fresh(['items.oldEmployee.unit', 'items.newEmployee.unit', 'baselineParticipantVersion', 'meeting.currentParticipantVersion.members.employee.unit']);
        });
    }
}
