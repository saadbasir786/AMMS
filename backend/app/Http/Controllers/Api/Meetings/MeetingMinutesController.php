<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Meetings;

use App\Actions\Minutes\AcknowledgeMeetingMinutes;
use App\Actions\Minutes\ApproveHighAuthorityMeetingMinutes;
use App\Actions\Minutes\CreateMeetingMinutesDraft;
use App\Actions\Minutes\FinalizeMeetingMinutes;
use App\Actions\Minutes\GenerateFinalMeetingMinutesDocument;
use App\Actions\Minutes\RequestMeetingMinutesCorrection;
use App\Actions\Minutes\SubmitMeetingMinutesVersion;
use App\Actions\Minutes\UpdateMeetingMinutesDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\RequestMeetingMinutesCorrectionRequest;
use App\Http\Requests\Meetings\StoreMeetingMinutesDraftRequest;
use App\Http\Resources\MeetingMinutesResource;
use App\Models\Meeting;
use App\Models\MeetingMinutes;
use Illuminate\Http\JsonResponse;

class MeetingMinutesController extends Controller
{
    public function show(Meeting $meeting): MeetingMinutesResource|JsonResponse
    {
        $this->authorize('minutesView', $meeting);
        $minutes = $meeting->minutes()->with([
            'currentVersion.items', 'currentVersion.decisions', 'currentVersion.attendanceSnapshots', 'currentVersion.reviews.organizationalUnit',
            'document',
        ])->first();
        if (!$minutes) return response()->json(['success' => true, 'data' => null]);
        return new MeetingMinutesResource($minutes);
    }

    public function create(Meeting $meeting, CreateMeetingMinutesDraft $action): MeetingMinutesResource
    {
        $this->authorize('minutesPrepare', $meeting);
        return new MeetingMinutesResource($action->execute(request()->user(), $meeting));
    }

    public function update(StoreMeetingMinutesDraftRequest $request, Meeting $meeting, UpdateMeetingMinutesDraft $action): MeetingMinutesResource
    {
        $this->authorize('minutesPrepare', $meeting);
        $minutes = $meeting->minutes()->firstOrFail();
        return new MeetingMinutesResource($action->execute($request->user(), $minutes, $request->validated()));
    }

    public function submit(Meeting $meeting, SubmitMeetingMinutesVersion $action): MeetingMinutesResource
    {
        $this->authorize('minutesSubmit', $meeting);
        $minutes = $meeting->minutes()->firstOrFail();
        return new MeetingMinutesResource($action->execute(request()->user(), $minutes));
    }

    public function acknowledge(Meeting $meeting, int $unit, AcknowledgeMeetingMinutes $action): MeetingMinutesResource
    {
        $this->authorize('minutesReviewUnit', [$meeting, $unit]);
        $minutes = $meeting->minutes()->firstOrFail();
        return new MeetingMinutesResource($action->execute(request()->user(), $minutes, $unit));
    }

    public function correction(RequestMeetingMinutesCorrectionRequest $request, Meeting $meeting, RequestMeetingMinutesCorrection $action): MeetingMinutesResource
    {
        $type = strtoupper((string) $request->validated('reviewer_type'));
        if ($type === 'UNIT') {
            $this->authorize('minutesReviewUnit', [$meeting, (int) ($request->validated('organizational_unit_id') ?? 0)]);
        } else {
            $this->authorize('minutesApproveAuthority', $meeting);
        }
        $minutes = $meeting->minutes()->firstOrFail();
        return new MeetingMinutesResource($action->execute(request()->user(), $minutes, $type, $request->validated('organizational_unit_id'), $request->validated('reason')));
    }

    public function authorityApprove(Meeting $meeting, ApproveHighAuthorityMeetingMinutes $action): MeetingMinutesResource
    {
        $this->authorize('minutesApproveAuthority', $meeting);
        $minutes = $meeting->minutes()->firstOrFail();
        return new MeetingMinutesResource($action->execute(request()->user(), $minutes));
    }

    public function finalize(Meeting $meeting, FinalizeMeetingMinutes $action): MeetingMinutesResource
    {
        $this->authorize('minutesFinalize', $meeting);
        $minutes = $meeting->minutes()->firstOrFail();
        return new MeetingMinutesResource($action->execute(request()->user(), $minutes));
    }

    public function finalPdf(Meeting $meeting, GenerateFinalMeetingMinutesDocument $action)
    {
        $this->authorize('minutesDocumentView', $meeting);
        $minutes = $meeting->minutes()->firstOrFail();
        $result = $action->execute(request()->user(), $minutes);
        return response($result['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$result['document']->file_name.'"',
            'X-AMMS-Content-SHA256' => $result['document']->content_sha256,
        ]);
    }
}
