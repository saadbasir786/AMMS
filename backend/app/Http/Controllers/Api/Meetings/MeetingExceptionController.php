<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Meetings;

use App\Actions\Exceptions\AcknowledgeMeetingExceptionReview;
use App\Actions\Exceptions\ApplyMeetingCancellation;
use App\Actions\Exceptions\ApplyMeetingPostponement;
use App\Actions\Exceptions\ApplyMeetingReopening;
use App\Actions\Exceptions\ApproveMeetingExceptionReview;
use App\Actions\Exceptions\CreateMeetingExceptionCase;
use App\Actions\Exceptions\RejectMeetingExceptionCase;
use App\Actions\Exceptions\RequestMeetingExceptionCorrection;
use App\Actions\Exceptions\ResumePostponedMeeting;
use App\Actions\Exceptions\SubmitMeetingExceptionCase;
use App\Actions\Exceptions\UpdateMeetingExceptionDraft;
use App\Actions\Exceptions\WithdrawMeetingExceptionCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\CreateMeetingExceptionCaseRequest;
use App\Http\Requests\Meetings\MeetingExceptionDecisionRequest;
use App\Http\Requests\Meetings\ResumePostponedMeetingRequest;
use App\Http\Requests\Meetings\UpdateMeetingExceptionDraftRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\MeetingExceptionCaseResource;
use App\Http\Resources\MeetingExceptionReviewResource;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MeetingExceptionController extends Controller
{
    public function index(PaginatedRequest $request, Meeting $meeting)
    {
        Gate::authorize('viewMeeting', [MeetingExceptionCase::class, $meeting]);
        $cases = $meeting->exceptionCases()->with(['meeting.request', 'meeting.units', 'meeting.authorityContext', 'currentVersion.reviews.organizationalUnit', 'currentVersion.reviews.version.exceptionCase.meeting'])
            ->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 50));
        $meeting->loadMissing(['request', 'currentScheduleVersion']);

        return MeetingExceptionCaseResource::collection($cases)->additional([
            'success' => true,
            'message' => 'پرونده‌های وضعیت استثنایی جلسه.',
            'meeting' => [
                'id' => $meeting->id, 'meeting_number' => $meeting->meeting_number,
                'title' => $meeting->title, 'status' => $meeting->status,
                'request_status' => $meeting->request?->status,
                'current_schedule_version' => $meeting->currentScheduleVersion,
            ],
        ]);
    }

    public function store(CreateMeetingExceptionCaseRequest $request, Meeting $meeting, CreateMeetingExceptionCase $action): JsonResponse
    {
        Gate::authorize('create', [MeetingExceptionCase::class, $meeting]);
        $case = $action->execute(
            $meeting,
            $request->user(),
            $request->string('case_type')->toString(),
            $request->string('reason')->toString(),
            (bool) $request->boolean('replacement_expected'),
            $request->input('proposed_resume_date'),
            $request->input('administrative_note'),
        );

        return (new MeetingExceptionCaseResource($case))->response()->setStatusCode(201);
    }

    public function show(MeetingExceptionCase $case): MeetingExceptionCaseResource
    {
        $case->load(['meeting', 'currentVersion.reviews', 'versions']);
        Gate::authorize('view', $case);

        return new MeetingExceptionCaseResource($case);
    }

    public function updateDraft(UpdateMeetingExceptionDraftRequest $request, MeetingExceptionCase $case, UpdateMeetingExceptionDraft $action): MeetingExceptionCaseResource
    {
        $case->load('meeting');
        Gate::authorize('updateDraft', $case);

        return new MeetingExceptionCaseResource($action->execute($case, $request->user(), $request->string('reason')->toString(), $request->input('administrative_note'), (bool) $request->boolean('replacement_expected'), $request->input('proposed_resume_date')));
    }

    public function submit(MeetingExceptionCase $case, SubmitMeetingExceptionCase $action): MeetingExceptionCaseResource
    {
        $case->load('meeting');
        Gate::authorize('submit', $case);

        return new MeetingExceptionCaseResource($action->execute($case, request()->user()));
    }

    public function acknowledge(MeetingExceptionReview $review, AcknowledgeMeetingExceptionReview $action): MeetingExceptionReviewResource
    {
        $review->load('version.exceptionCase.meeting');
        Gate::authorize('acknowledge', $review);

        return new MeetingExceptionReviewResource($action->execute($review, request()->user()));
    }

    public function approve(MeetingExceptionReview $review, ApproveMeetingExceptionReview $action): MeetingExceptionReviewResource
    {
        $review->load('version.exceptionCase.meeting');
        Gate::authorize('approve', $review);

        return new MeetingExceptionReviewResource($action->execute($review, request()->user()));
    }

    public function correction(MeetingExceptionDecisionRequest $request, MeetingExceptionReview $review, RequestMeetingExceptionCorrection $action): MeetingExceptionCaseResource
    {
        $review->load('version.exceptionCase.meeting');
        Gate::authorize('view', $review);

        return new MeetingExceptionCaseResource($action->execute($review, $request->user(), $request->string('reason')->toString()));
    }

    public function reject(MeetingExceptionDecisionRequest $request, MeetingExceptionCase $case, RejectMeetingExceptionCase $action): MeetingExceptionCaseResource
    {
        $case->load('meeting');
        Gate::authorize('apply', $case);

        return new MeetingExceptionCaseResource($action->execute($case, $request->user(), $request->string('reason')->toString()));
    }

    public function withdraw(MeetingExceptionDecisionRequest $request, MeetingExceptionCase $case, WithdrawMeetingExceptionCase $action): MeetingExceptionCaseResource
    {
        $case->load('meeting');
        Gate::authorize('withdraw', $case);

        return new MeetingExceptionCaseResource($action->execute($case, $request->user(), $request->string('reason')->toString()));
    }

    public function apply(MeetingExceptionCase $case, ApplyMeetingPostponement $postpone, ApplyMeetingCancellation $cancel, ApplyMeetingReopening $reopen): MeetingExceptionCaseResource
    {
        $case->load('meeting');
        Gate::authorize('apply', $case);
        $result = match ($case->case_type) {
            MeetingExceptionCase::POSTPONEMENT => $postpone->execute($case, request()->user()),
            MeetingExceptionCase::CANCELLATION => $cancel->execute($case, request()->user()),
            MeetingExceptionCase::REOPENING => $reopen->execute($case, request()->user()),
            default => abort(422, 'MEETING_EXCEPTION_NOT_ALLOWED'),
        };

        return new MeetingExceptionCaseResource($result);
    }

    public function resume(ResumePostponedMeetingRequest $request, MeetingExceptionCase $case, ResumePostponedMeeting $action): MeetingExceptionCaseResource
    {
        $case->load('meeting');
        Gate::authorize('apply', $case);

        return new MeetingExceptionCaseResource($action->execute($case, $request->user(), $request->validated()));
    }
}
