<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestMultiHighAlternativeSchedule
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $unitId = (int) ($data['organizational_unit_id'] ?? 0);
            $unit = $this->formalUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($actor->can('authority-schedule.acknowledge') && $this->canParticipatingUnit($actor, $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless($locked->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless(filled(trim($data['reason'] ?? '')), 422, 'REASON_REQUIRED');
            $proposal = $locked->scheduleProposals()->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'])->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $proposal->update(['status' => 'ALTERNATIVE_REQUESTED']);
            $this->record($locked, $actor, 'DIRECTORATE_REQUESTED_ALTERNATIVE', $locked->status, $locked->status, $data['reason'], ['proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            $context = $locked->authorityContext;
            $this->notifyUnit($context->authority_office_unit_id, ['EXECUTIVE_SECRETARY', 'AUTHORITY_OFFICE'], 'درخواست زمان بدیل', 'یکی از ریاست‌ها برای زمان رسمی درخواست بدیل داده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
