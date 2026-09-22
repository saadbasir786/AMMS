<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use App\Support\ParticipantVersionService;
use Illuminate\Support\Facades\DB;

class ConfirmMeetingParticipantChange
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor): MeetingParticipantChangeCase
    {
        return DB::transaction(function () use ($meeting, $case, $actor): MeetingParticipantChangeCase {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertConfirm($actor, $case, $this->governance());
            $this->assertState($case, ['PENDING_CONFIRMATION']);
            $this->validator()->assertCaseReady($case);
            $this->lockEmployeesForItems($case);
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

            $baselineMembers = $case->baselineParticipantVersion->members->map(fn ($member): array => [
                'employee_id' => (int) $member->employee_id,
                'organizational_unit_id' => (int) $member->organizational_unit_id,
                'participant_role' => $member->participant_role,
                'owner_type' => $member->owner_type,
                'owner_unit_id' => $member->owner_unit_id,
                'is_mandatory' => (bool) $member->is_mandatory,
            ])->all();
            $members = $this->validator()->applyDelta($case, $baselineMembers);
            $this->validator()->assertFinalConflicts($meeting, $members, $case->items->whereIn('operation', ['ADD', 'REPLACE'])->pluck('new_employee_id')->filter()->all());

            $version = app(ParticipantVersionService::class)->createConfirmedVersion($meeting, $case, $members, $actor->id);
            $case->forceFill(['status' => 'CONFIRMED', 'confirmed_at' => now()])->save();

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
