<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubmitMeetingParticipantChange
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor): MeetingParticipantChangeCase
    {
        return DB::transaction(function () use ($meeting, $case, $actor): MeetingParticipantChangeCase {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertManage($actor, $case, $this->governance());
            if ($this->governance()->isInternalDirectorate($meeting) && !$actor->can('meeting-participant-change.submit')) {
                throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CHANGE');
            }
            $this->assertState($case, ['DRAFT', 'RETURNED_FOR_CORRECTION']);
            $this->validator()->assertMeetingEditable($meeting);
            if ($case->items()->count() === 0) throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');
            foreach ($case->items as $item) {
                $this->validator()->assertItem($case, $item->operation, $item->old_employee_id, $item->new_employee_id);
            }
            if ($this->governance()->isInternalDirectorate($meeting)) {
                $this->validator()->assertInternalDraftConsistent($case, $case->items->map(fn ($item): array => [
                    'operation' => $item->operation,
                    'old_employee_id' => $item->old_employee_id,
                    'new_employee_id' => $item->new_employee_id,
                    'participant_role' => $item->participant_role,
                ])->all());
            }
            $members = $this->validator()->applyDelta($case, $case->baselineParticipantVersion->members->map(fn ($member): array => [
                'employee_id' => (int) $member->employee_id,
                'organizational_unit_id' => (int) $member->organizational_unit_id,
                'participant_role' => $member->participant_role,
                'owner_type' => $member->owner_type,
                'owner_unit_id' => $member->owner_unit_id,
                'is_mandatory' => (bool) $member->is_mandatory,
            ])->all());
            $this->validator()->assertFinalConflicts($meeting, $members, $case->items->whereIn('operation', ['ADD', 'REPLACE'])->pluck('new_employee_id')->filter()->all());

            $case->forceFill(['status' => 'PENDING_CONFIRMATION', 'submitted_at' => now()])->save();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_SUBMITTED', $case->reason, [
                'change_case_id' => $case->id,
                'baseline_participant_version_id' => $case->baseline_participant_version_id,
                'baseline_schedule_version_id' => $case->baseline_schedule_version_id,
            ]);
            if ($this->governance()->isInternalDirectorate($meeting)) {
                $this->notifyScopedRole((int) $case->owner_unit_id, 'DIRECTOR', 'PARTICIPANT_CHANGE_REVIEW_REQUIRED', 'تغییر اعضای جلسه در انتظار بررسی', 'پرونده تغییر اعضای جلسه برای بررسی و تصمیم‌گیری ارسال شده است.', $meeting, (int) $actor->id);
            }
            return $case->fresh(['items.oldEmployee.unit', 'items.newEmployee.unit', 'ownerUnit', 'baselineScheduleVersion']);
        });
    }
}
