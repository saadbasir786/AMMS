<?php
namespace App\Actions\MeetingRequests;
use App\Models\MeetingRequest;
use App\Models\User;
use App\Services\MeetingRequestService;
use App\Support\InternalMeetingApprovalDelegation;

class ApproveMeetingRequest
{
    public function __construct(
        private MeetingRequestService $service,
        private InternalMeetingApprovalDelegation $delegations,
    ) {}

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        $delegation = $actor->hasScopedRole('DIRECTOR', (int) $request->requesting_unit_id)
            ? null
            : $this->delegations->scopeFor($actor, $request);

        return $this->service->review($actor, $request, 'approve', null, $delegation ? [
            'delegated_capability_scope_id' => $delegation->id,
            'delegated_capability' => $delegation->capability,
            'delegated_by_user_id' => $delegation->granted_by_user_id,
            'delegation_expires_at' => $delegation->expires_at?->toISOString(),
        ] : []);
    }
}
