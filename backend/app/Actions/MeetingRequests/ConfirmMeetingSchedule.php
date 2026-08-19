<?php
namespace App\Actions\MeetingRequests;
use App\Models\MeetingRequest;
use App\Models\User;
use App\Services\MeetingRequestService;
class ConfirmMeetingSchedule { public function __construct(private MeetingRequestService $service) {} public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest { return $this->service->confirmSchedule($actor, $request, $data); } }