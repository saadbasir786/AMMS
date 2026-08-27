<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartAuthorityInitiatedScheduling
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $this->assertActiveActor($actor);
            $context = $request->authorityContext;
            $officeId = (int) ($context?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-schedule.view') && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($request->status === MeetingRequestStatus::DIRECTORATES_ACKNOWLEDGED->value, 422, 'ALL_DIRECTORATE_ACKNOWLEDGEMENTS_REQUIRED');
            abort_unless($this->allCurrentAcknowledged($request), 422, 'ALL_DIRECTORATE_ACKNOWLEDGEMENTS_REQUIRED');
            $from = $this->transition06($request, MeetingRequestStatus::AUTHORITY_SCHEDULING);
            $this->record06($request, $actor, 'AUTHORITY_SCHEDULING_STARTED', $from, MeetingRequestStatus::AUTHORITY_SCHEDULING->value, null, ['directive_version' => $this->currentVersion($request)->version]);
            foreach ($this->flow06Units($request) as $unit) $this->notifyUnit((int) $unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'زمان‌بندی رسمی جلسه به ابتکار مقام', 'جلسه برای زمان‌بندی رسمی آماده شده است.', $request);
            return $this->response06($request);
        });
    }
}
