<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Projects existing policy and action preconditions; never grants authority. */
class MeetingExceptionActionCapabilities
{
    public function forCase(MeetingExceptionCase $case, User $actor): array
    {
        $gate = Gate::forUser($actor);
        $draft = in_array($case->status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true);
        $ready = $case->status === 'READY_TO_APPLY';

        return [
            'edit' => $draft && $gate->allows('updateDraft', $case),
            'submit' => $case->status === 'DRAFT' && $gate->allows('submit', $case),
            'withdraw' => in_array($case->status, ['DRAFT', 'UNDER_REVIEW', 'RETURNED_FOR_CORRECTION'], true) && $gate->allows('withdraw', $case),
            'reject' => in_array($case->status, ['UNDER_REVIEW', 'READY_TO_APPLY'], true) && $gate->allows('apply', $case),
            'apply' => $ready && $case->case_type !== 'RESUMPTION' && $gate->allows('apply', $case),
            'resume' => $ready && $case->case_type === 'RESUMPTION' && $gate->allows('apply', $case),
        ];
    }

    public function forReview(MeetingExceptionReview $review, User $actor): array
    {
        $case = $review->version->exceptionCase;
        $pending = $review->status === 'PENDING' && $case->status === 'UNDER_REVIEW'
            && (int) $case->current_version_id === (int) $review->exception_case_version_id;
        $gate = Gate::forUser($actor);
        $ack = $pending && $gate->allows('acknowledge', $review);
        $approve = $pending && $gate->allows('approve', $review);

        return ['acknowledge' => $ack, 'approve' => $approve, 'correction' => $ack || $approve];
    }
}
