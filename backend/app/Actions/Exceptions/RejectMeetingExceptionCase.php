<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\Flow13ActionSupport;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class RejectMeetingExceptionCase
{
    use Flow13ActionSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor, string $reason): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($this->governance->mayApply($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        $reason = trim($reason);
        abort_unless($reason !== '', 422, 'MEETING_EXCEPTION_REASON_REQUIRED');
        return DB::transaction(function () use ($case, $actor, $reason): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with(['meeting', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            abort_unless(in_array($locked->status, [MeetingExceptionCase::UNDER_REVIEW, MeetingExceptionCase::READY_TO_APPLY], true), 409, 'MEETING_EXCEPTION_ALREADY_REJECTED');
            $locked->forceFill(['status' => MeetingExceptionCase::REJECTED, 'active_marker' => null, 'updated_at' => now()])->save();
            $this->audit($locked->meeting, $actor, 'MEETING_EXCEPTION_CASE_REJECTED', $reason, $this->exceptionMetadata($locked));
            return $locked->fresh(['meeting', 'currentVersion']);
        });
    }
}
