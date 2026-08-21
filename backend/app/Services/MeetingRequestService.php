<?php

namespace App\Services;

use App\Enums\MeetingRequestStatus;
use App\Models\AmmsNotification;
use App\Models\AmmsSequence;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestAction;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MeetingRequestService
{
    public function create(User $actor, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $data) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            $unit = OrganizationalUnit::findOrFail($data['requesting_unit_id']);
            abort_unless($this->canManageUnit($actor, $unit->id), 403, 'FORBIDDEN');
            $request = MeetingRequest::create(array_merge($data, ['created_by' => $actor->id, 'flow_type' => 'INTERNAL_DIRECTORATE', 'status' => 'DRAFT']));
            $this->action($request, $actor, 'CREATED', null, 'DRAFT');
            $this->syncParticipants($request, $actor, $data['participant_ids'] ?? []);
            return $request->fresh($this->withRelations());
        });
    }

    public function update(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            abort_unless($this->editableBy($actor, $request), 403, 'REQUEST_NOT_EDITABLE');
            $participantIds = $data['participant_ids'] ?? null;
            unset($data['participant_ids']);
            $request->update($data);
            if ($participantIds !== null) $this->syncParticipants($request, $actor, $participantIds);
            $this->action($request, $actor, 'UPDATED', $request->status, $request->status);
            return $request->fresh($this->withRelations());
        });
    }

    public function submit(User $actor, int $id): MeetingRequest
    {
        return $this->submitFromStatus($actor, $id, MeetingRequestStatus::DRAFT, 'SUBMITTED');
    }

    public function resubmit(User $actor, int $id): MeetingRequest
    {
        return $this->submitFromStatus($actor, $id, MeetingRequestStatus::RETURNED_FOR_REVISION, 'RESUBMITTED');
    }

    private function submitFromStatus(User $actor, int $id, MeetingRequestStatus $expectedStatus, string $auditAction): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $id, $expectedStatus, $auditAction) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            $request = MeetingRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($this->editableBy($actor, $request), 403, 'FORBIDDEN');
            $current = MeetingRequestStatus::tryFrom($request->status);
            abort_unless($current === $expectedStatus && $current->canTransitionTo(MeetingRequestStatus::PENDING_INTERNAL_APPROVAL), 422, 'REQUEST_ALREADY_SUBMITTED');
            abort_if(blank($request->subject) || blank($request->purpose), 422, 'VALIDATION_ERROR');
            $unit = $request->requestingUnit;
            abort_if(!$unit || $unit->status !== 'ACTIVE', 422, 'VALIDATION_ERROR');
            abort_if(!User::whereHas('roleScopes', fn($q) => $q->where('organizational_unit_id', $unit->id)->whereHas('role', fn($role) => $role->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');
            $from = $request->status;
            $request->update(['status' => MeetingRequestStatus::PENDING_INTERNAL_APPROVAL->value, 'request_number' => $request->request_number ?: $this->nextNumber('meeting_requests', 'MR')]);
            $this->action($request, $actor, $auditAction, $from, MeetingRequestStatus::PENDING_INTERNAL_APPROVAL->value);
            $this->notifyDirectors($unit->id, 'درخواست جدید برای تأیید', 'یک درخواست داخلی جدید منتظر تأیید شما است.', $request);
            return $request->fresh($this->withRelations());
        });
    }

    public function review(User $actor, MeetingRequest $request, string $decision, ?string $reason = null): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $decision, $reason) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($request->id);
            $current = MeetingRequestStatus::tryFrom($locked->status);
            abort_unless($current?->isReviewable(), 422, 'REQUEST_NOT_PENDING_APPROVAL');
            abort_unless($actor->hasScopedRole('DIRECTOR', $locked->requesting_unit_id), 403, 'UNAUTHORIZED_APPROVER');
            if (in_array($decision, ['return', 'reject'], true) && blank($reason)) abort(422, $decision === 'return' ? 'RETURN_REASON_REQUIRED' : 'REJECTION_REASON_REQUIRED');
            $next = ['approve' => MeetingRequestStatus::APPROVED, 'return' => MeetingRequestStatus::RETURNED_FOR_REVISION, 'reject' => MeetingRequestStatus::REJECTED][$decision] ?? null;
            abort_unless($next && $current->canTransitionTo($next), 422, 'INVALID_STATUS_TRANSITION');
            $action = ['approve' => 'APPROVED', 'return' => 'RETURNED', 'reject' => 'REJECTED'][$decision];
            $locked->update(['status' => $next->value, 'approved_by' => $decision === 'approve' ? $actor->id : $locked->approved_by, 'approved_at' => $decision === 'approve' ? now() : $locked->approved_at]);
            $this->action($locked, $actor, $action, $current->value, $next->value, $reason);
            $this->notify($locked->created_by, $decision === 'approve' ? 'درخواست تأیید شد' : ($decision === 'return' ? 'درخواست جهت اصلاح برگشت داده شد' : 'درخواست رد شد'), $reason ?: 'درخواست شما بررسی شد.', $locked);
            if ($decision === 'approve' && $locked->coordinator_id) $this->notify($locked->coordinator_id, 'درخواست آماده زمان‌بندی است', 'یک درخواست تأییدشده برای زمان‌بندی آماده است.', $locked);
            return $locked->fresh($this->withRelations());
        });
    }

    public function startScheduling(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($request->id);
            $current = MeetingRequestStatus::tryFrom($locked->status);
            abort_unless($current === MeetingRequestStatus::APPROVED && $current->canTransitionTo(MeetingRequestStatus::SCHEDULING), 422, 'REQUEST_NOT_APPROVED');
            abort_unless($actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $locked->requesting_unit_id), 403, 'FORBIDDEN');
            $locked->update(['status' => MeetingRequestStatus::SCHEDULING->value, 'coordinator_id' => $locked->coordinator_id ?: $actor->id]);
            $this->action($locked, $actor, 'SCHEDULING_STARTED', $current->value, MeetingRequestStatus::SCHEDULING->value);
            return $locked->fresh($this->withRelations());
        });
    }

    public function confirmSchedule(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($request->id);
            $current = MeetingRequestStatus::tryFrom($locked->status);
            abort_unless($current === MeetingRequestStatus::SCHEDULING && $current->canTransitionTo(MeetingRequestStatus::SCHEDULE_CONFIRMED), 422, 'SCHEDULING_NOT_STARTED');
            abort_unless($actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $locked->requesting_unit_id), 403, 'FORBIDDEN');
            $this->validatePeople($locked->requesting_unit_id, [$data['chairperson_employee_id'], ...($data['participant_ids'] ?? [])]);
            $locked->update([
                'preferred_date' => $data['meeting_date'],
                'preferred_start_time' => $data['start_time'],
                'estimated_duration_minutes' => $data['duration_minutes'],
                'proposed_location' => $data['location'],
                'confirmed_chairperson_employee_id' => $data['chairperson_employee_id'],
                'coordinator_id' => $locked->coordinator_id ?: $actor->id,
            ]);
            $this->syncParticipants($locked, $actor, $data['participant_ids'] ?? [], 'FINAL');
            $this->action($locked, $actor, 'SCHEDULE_CONFIRMED', $current->value, MeetingRequestStatus::SCHEDULE_CONFIRMED->value);
            $locked->update(['status' => MeetingRequestStatus::SCHEDULE_CONFIRMED->value]);
            return $locked->fresh($this->withRelations());
        });
    }

    public function finalize(User $actor, MeetingRequest $request, array $data): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data) {
            abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
            $locked = MeetingRequest::query()->lockForUpdate()->with(['participants.employee', 'units.unit', 'authorityContext'])->findOrFail($request->id);
            $current = MeetingRequestStatus::tryFrom($locked->status);
            abort_unless($current === MeetingRequestStatus::SCHEDULE_CONFIRMED && $current->canTransitionTo(MeetingRequestStatus::CONVERTED_TO_MEETING), 422, 'SCHEDULE_INCOMPLETE');
            abort_unless(!$locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');
            abort_unless($actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless($locked->preferred_date && $locked->preferred_start_time && $locked->estimated_duration_minutes && $locked->proposed_location && $locked->confirmed_chairperson_employee_id, 422, 'SCHEDULE_INCOMPLETE');

            $confirmedDate = $locked->preferred_date->format('Y-m-d');
            $confirmedStart = substr((string) $locked->preferred_start_time, 0, 5);
            $confirmedEnd = Carbon::createFromFormat('H:i', $confirmedStart)->addMinutes((int) $locked->estimated_duration_minutes)->format('H:i');
            abort_unless(
                $data['meeting_date'] === $confirmedDate
                && $data['start_time'] === $confirmedStart
                && $data['end_time'] === $confirmedEnd
                && $data['location'] === $locked->proposed_location
                && (int) $data['chairperson_employee_id'] === (int) $locked->confirmed_chairperson_employee_id,
                422,
                'CONFIRMED_SCHEDULE_MISMATCH'
            );

            $this->validatePeople($locked->requesting_unit_id, [(int) $locked->confirmed_chairperson_employee_id, ...$locked->participants->where('participant_type', 'FINAL')->pluck('employee_id')->all()]);
            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id,
                'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject,
                'description' => $locked->description,
                'organizing_unit_id' => $locked->requesting_unit_id,
                'coordinator_id' => $locked->coordinator_id ?: $actor->id,
                'chairperson_employee_id' => $locked->confirmed_chairperson_employee_id,
                'meeting_date' => $confirmedDate,
                'start_time' => $confirmedStart,
                'end_time' => $confirmedEnd,
                'location' => $locked->proposed_location,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'SCHEDULED',
            ]);
            foreach ($locked->participants->where('participant_type', 'FINAL') as $participant) $meeting->participants()->create(['employee_id' => $participant->employee_id, 'organizational_unit_id' => $participant->employee->organizational_unit_id]);
            app(\App\Support\ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
            $from = $current->value;
            $locked->update(['status' => MeetingRequestStatus::CONVERTED_TO_MEETING->value]);
            $this->action($locked, $actor, 'MEETING_CREATED', $from, MeetingRequestStatus::CONVERTED_TO_MEETING->value);
            $recipientEmployeeIds = array_merge([$meeting->chairperson_employee_id], $locked->participants->where('participant_type', 'FINAL')->pluck('employee_id')->all());
            $recipientUserIds = User::whereIn('employee_id', array_unique($recipientEmployeeIds))->pluck('id')->all();
            if ($meeting->coordinator_id) $recipientUserIds[] = $meeting->coordinator_id;
            foreach (array_unique($recipientUserIds) as $recipientUserId) $this->notify($recipientUserId, 'جلسه ایجاد شد', 'جلسه رسمی درخواست شما ایجاد و زمان‌بندی شد.', $locked);
            return $meeting->fresh(['participants.employee', 'chairperson', 'request']);
        });
    }

    private function canManageUnit(User $actor, int $unitId): bool
    {
        return $actor->isActive() && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    private function editableBy(User $actor, MeetingRequest $request): bool
    {
        return $actor->isActive() && $request->created_by === $actor->id && (MeetingRequestStatus::tryFrom($request->status)?->isEditable() ?? false);
    }

    private function validatePeople(int $unitId, array $employeeIds): void
    {
        $scope = OrganizationalUnit::findOrFail($unitId);
        foreach (array_unique(array_filter($employeeIds)) as $employeeId) {
            $employee = Employee::find($employeeId);
            abort_if(!$employee || $employee->status !== 'ACTIVE', 422, 'INVALID_PARTICIPANT');
            abort_unless($scope->isWithinScope($employee->organizational_unit_id), 422, 'PARTICIPANT_OUTSIDE_DIRECTORATE');
        }
    }

    private function syncParticipants(MeetingRequest $request, User $actor, array $ids, string $type = 'PROPOSED'): void
    {
        $this->validatePeople($request->requesting_unit_id, $ids);
        $request->participants()->where('participant_type', $type)->delete();
        foreach (array_unique($ids) as $employeeId) $request->participants()->create(['employee_id' => $employeeId, 'participant_type' => $type, 'created_by' => $actor->id]);
    }

    private function nextNumber(string $name, string $prefix): string
    {
        $sequence = AmmsSequence::query()->lockForUpdate()->find($name);
        if (!$sequence) {
            AmmsSequence::create(['name'=>$name,'value'=>0]);
            $sequence = AmmsSequence::query()->lockForUpdate()->findOrFail($name);
        }
        $sequence->increment('value');
        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $sequence->fresh()->value, 5, '0', STR_PAD_LEFT);
    }

    private function action(MeetingRequest $request, User $actor, string $action, ?string $from, ?string $to, ?string $comment = null): void
    {
        MeetingRequestAction::create(['meeting_request_id'=>$request->id,'actor_id'=>$actor->id,'action'=>$action,'from_status'=>$from,'to_status'=>$to,'comment'=>$comment]);
    }

    private function notify(int $userId, string $title, string $message, MeetingRequest $request): void
    {
        AmmsNotification::create(['user_id'=>$userId,'type'=>'MEETING_REQUEST','title'=>$title,'message'=>$message,'data'=>['meeting_request_id'=>$request->id]]);
    }

    private function notifyDirectors(int $unitId, string $title, string $message, MeetingRequest $request): void
    {
        User::whereHas('roleScopes', fn($q)=>$q->where('organizational_unit_id',$unitId)->whereHas('role', fn($role)=>$role->where('name','DIRECTOR')->where('guard_name','sanctum')))->pluck('id')->each(fn($id)=>$this->notify($id,$title,$message,$request));
    }

    private function withRelations(): array
    {
        return ['requestingUnit','creator.employee','participants.employee','actions.actor','meeting'];
    }
}
