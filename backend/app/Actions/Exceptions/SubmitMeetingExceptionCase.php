<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\ExceptionWorkflowSupport;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitMeetingExceptionCase
{
    use ExceptionWorkflowSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($this->governance->maySubmit($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');

        return DB::transaction(function () use ($case, $actor): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with(['meeting.request', 'meeting.units', 'meeting.authorityContext', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            abort_unless($locked->status === MeetingExceptionCase::DRAFT, 409, 'MEETING_EXCEPTION_NOT_ALLOWED');
            $version = $locked->currentVersion;
            abort_unless($version && trim((string) $version->reason) !== '', 422, 'MEETING_EXCEPTION_REASON_REQUIRED');
            abort_if($version->isSubmitted(), 409, 'MEETING_EXCEPTION_NOT_ALLOWED');
            if ($locked->case_type === MeetingExceptionCase::POSTPONEMENT) {
                abort_unless(! $version->replacement_expected, 422, 'POSTPONEMENT_REPLACEMENT_SCHEDULE_REQUIRED');
            }
            $version->forceFill(['submitted_at' => now()])->save();
            foreach ($this->governance->reviewRequirements($locked->meeting, $locked->case_type) as $requirement) {
                MeetingExceptionReview::create(array_merge($requirement, ['uuid' => (string) Str::uuid(), 'exception_case_version_id' => $version->id, 'status' => MeetingExceptionReview::PENDING, 'created_at' => now()]));
            }
            $locked->forceFill(['status' => MeetingExceptionCase::UNDER_REVIEW, 'updated_at' => now()])->save();
            $this->audit($locked->meeting, $actor, 'MEETING_EXCEPTION_CASE_SUBMITTED', $version->reason, $this->exceptionMetadata($locked));

            return $locked->fresh(['meeting', 'currentVersion.reviews', 'versions']);
        });
    }
}
