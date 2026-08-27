<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposeAuthorityInitiatedSchedule
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $this->assertActiveActor($actor);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-schedule.propose') && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($request->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless((string) $data['end_time'] > (string) $data['start_time'], 422, 'INVALID_SCHEDULE_TIME');
            $request->scheduleProposals()->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED', 'ALTERNATIVE_REQUESTED'])->update(['status' => 'SUPERSEDED']);
            $proposal = $request->scheduleProposals()->create([
                'proposed_by_unit_id' => $officeId,
                'proposed_by_user_id' => $actor->id,
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'location' => $data['location'],
                'comment' => $data['comment'] ?? null,
                'status' => 'PROPOSED',
                'proposal_type' => 'AUTHORITY_OFFICIAL',
            ]);
            $this->record06($request, $actor, 'AUTHORITY_SCHEDULE_PROPOSED', $request->status, $request->status, $data['comment'] ?? null, ['proposal_id' => $proposal->id, 'directive_version' => $this->currentVersion($request)->version]);
            foreach ($this->flow06Units($request) as $unit) $this->notifyUnit((int) $unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'پیشنهاد رسمی زمان‌بندی', 'پیشنهاد رسمی جدید برای جلسهٔ مقام ثبت شد.', $request);
            return $this->response06($request);
        });
    }
}
