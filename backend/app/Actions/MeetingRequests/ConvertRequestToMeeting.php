<?php
namespace App\Actions\MeetingRequests;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
use App\Services\MeetingRequestService;
class ConvertRequestToMeeting { public function __construct(private MeetingRequestService $service) {} public function __invoke(User $actor, MeetingRequest $request, array $data): Meeting { return $this->service->finalize($actor, $request, $data); } }