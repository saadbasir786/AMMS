<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\ExceptionWorkflowSupport;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class WithdrawMeetingExceptionCase
{
    use ExceptionWorkflowSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor, string $reason): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($this->governance->mayWithdraw($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        $reason = trim($reason);
        abort_unless($reason !== '', 422, 'MEETING_EXCEPTION_REASON_REQUIRED');
        return DB::transaction(function () use ($case, $actor, $reason): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with(['meeting', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            abort_unless(in_array($locked->status, [MeetingExceptionCase::DRAFT, MeetingExceptionCase::UNDER_REVIEW, MeetingExceptionCase::RETURNED_FOR_CORRECTION], true), 409, 'MEETING_EXCEPTION_NOT_ALLOWED');
            $locked->forceFill(['status' => MeetingExceptionCase::WITHDRAWN, 'active_marker' => null, 'withdrawn_by_user_id' => $actor->id, 'withdrawn_at' => now(), 'updated_at' => now()])->save();
            $this->audit($locked->meeting, $actor, 'MEETING_EXCEPTION_CASE_WITHDRAWN', $reason, $this->exceptionMetadata($locked));
            return $locked->fresh(['meeting', 'currentVersion']);
        });
    }
}
