<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DistributeAuthorityMeetingDirective
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-initiated-meeting.distribute') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeId)), 403, 'FORBIDDEN');
            abort_unless($request->status === MeetingRequestStatus::ISSUED_BY_HIGH_AUTHORITY->value, 422, 'DIRECTIVE_NOT_ISSUED');
            $version = $this->currentVersion($request);
            abort_unless($version->version_status === 'ISSUED', 422, 'DIRECTIVE_NOT_ISSUED');
            foreach ($this->authorityInitiatedUnits($request) as $unit) {
                $request->unitResponses()->firstOrCreate([
                    'meeting_request_directive_version_id' => $version->id,
                    'organizational_unit_id' => $unit->id,
                ], ['response_status' => 'PENDING_ACKNOWLEDGEMENT']);
                $this->notifyUnit((int) $unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'دستور جلسهٔ مقام توزیع شد', 'دستور رسمی مقام عالی برای هماهنگی ریاست شما توزیع شده است.', $request);
            }
            $from = $this->transitionAuthorityInitiated($request, MeetingRequestStatus::DISTRIBUTED_TO_DIRECTORATES);
            $this->recordAuthorityInitiated($request, $actor, 'DIRECTIVE_DISTRIBUTED', $from, MeetingRequestStatus::DISTRIBUTED_TO_DIRECTORATES->value, null, ['directive_version' => $version->version, 'unit_count' => $this->authorityInitiatedUnits($request)->count()]);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
