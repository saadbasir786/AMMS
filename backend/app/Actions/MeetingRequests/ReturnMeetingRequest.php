<?php
namespace App\Actions\MeetingRequests;
use App\Models\MeetingRequest;
use App\Models\User;
use App\Services\MeetingRequestService;
class ReturnMeetingRequest { public function __construct(private MeetingRequestService $service) {} public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest { return $this->service->review($actor, $request, 'return', $reason); } }