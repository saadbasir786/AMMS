<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitMultiHighRequestToAuthorityOffice
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            abort_unless(
                $actor->id === $locked->lead_coordinator_user_id
                    && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $locked->requesting_unit_id),
                403,
                'FORBIDDEN'
            );
            abort_unless($locked->status === MeetingRequestStatus::DIRECTORATES_APPROVED->value && $this->allParticipatingApproved($locked), 422, 'REQUEST_NOT_READY_FOR_AUTHORITY_OFFICE');
            $from = $this->transition($locked, MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE);
            $this->record($locked, $actor, 'REQUEST_SENT_TO_AUTHORITY_OFFICE', $from, $locked->status, null, ['unit_ids' => $this->formalUnits($locked)->pluck('id')->all()]);
            $context = $locked->authorityContext;
            $this->notifyUnit($context->authority_office_unit_id, ['AUTHORITY_OFFICE'], 'درخواست چندریاستی جدید', 'درخواست برای بررسی دفتر مقام ارسال شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
