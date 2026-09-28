<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Meetings;

use App\Models\Meeting;
use App\Http\Resources\MeetingVersionInspectionSnapshotResource;
use App\Support\MeetingVersionInspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingVersionInspectionController
{
    public function index(Request $request, Meeting $meeting, string $domain, MeetingVersionInspection $inspection): JsonResponse
    {
        $this->authorizeDomain($meeting, $domain);
        $this->prepareCurrentPointer($meeting, $domain);
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);
        $paginator = $inspection->query($meeting, $domain)->with($this->summaryRelations($domain))->orderByDesc('version_number')->orderByDesc('id')->paginate(min(max((int) ($data['per_page'] ?? 15), 1), 50));
        $items = collect($paginator->items())->map(fn ($version) => $this->summary($meeting, $domain, $version, $inspection));
        return response()->json(['data' => $items, 'meta' => [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
        ]]);
    }

    public function show(Request $request, Meeting $meeting, string $domain, int $version, MeetingVersionInspection $inspection): JsonResponse
    {
        $this->authorizeDomain($meeting, $domain);
        $this->prepareCurrentPointer($meeting, $domain);
        $snapshot = $inspection->load($meeting, $domain, $version);
        return response()->json(['data' => [
            'domain' => $domain,
            'current' => $inspection->isCurrent($meeting, $domain, $snapshot),
            'metadata' => $this->summary($meeting, $domain, $snapshot, $inspection),
            'version' => (new MeetingVersionInspectionSnapshotResource(['domain' => $domain, 'version' => $snapshot]))->resolve($request),
        ]]);
    }

    public function compare(Request $request, Meeting $meeting, string $domain, MeetingVersionInspection $inspection): JsonResponse
    {
        $this->authorizeDomain($meeting, $domain);
        $this->prepareCurrentPointer($meeting, $domain);
        $ids = $request->validate(['from_id' => ['required', 'integer', 'different:to_id'], 'to_id' => ['required', 'integer', 'different:from_id']]);
        $from = $inspection->load($meeting, $domain, (int) $ids['from_id']);
        $to = $inspection->load($meeting, $domain, (int) $ids['to_id']);
        return response()->json(['data' => [
            'domain' => $domain,
            'from' => $this->summary($meeting, $domain, $from, $inspection),
            'to' => $this->summary($meeting, $domain, $to, $inspection),
            'comparison' => $inspection->compare($domain, $from, $to),
        ]]);
    }

    private function authorizeDomain(Meeting $meeting, string $domain): void
    {
        abort_unless(in_array($domain, MeetingVersionInspection::MEETING_DOMAINS, true), 404);
        match ($domain) {
            'schedule' => Gate::authorize('meetingRescheduleViewHistory', $meeting),
            'participants' => Gate::authorize('participantChangeViewHistory', $meeting),
            'minutes' => Gate::authorize('minutesView', $meeting),
        };
    }

    private function summaryRelations(string $domain): array
    {
        return match ($domain) {
            'schedule' => ['createdBy', 'sourceRescheduleCase'],
            'participants' => ['createdBy', 'sourceChangeCase'],
            'minutes' => ['minutes.finalizedBy', 'preparedBy'],
        };
    }

    private function summary(Meeting $meeting, string $domain, object $version, MeetingVersionInspection $inspection): array
    {
        $actor = match ($domain) {
            'minutes' => $version->preparedBy?->name,
            default => $version->createdBy?->name,
        };
        return [
            'id' => $version->id, 'version_number' => $version->version_number,
            'current' => $inspection->isCurrent($meeting, $domain, $version),
            'created_at' => $version->created_at ?? $version->submitted_at,
            'finalized_at' => $domain === 'minutes' && $version->minutes?->status === 'FINALIZED' ? $version->minutes?->finalized_at : null,
            'actor' => $actor,
            'finalized_by' => $domain === 'minutes' ? $version->minutes?->finalizedBy?->name : null,
            'source' => $domain === 'schedule' ? $version->sourceRescheduleCase?->uuid : ($domain === 'participants' ? $version->sourceChangeCase?->uuid : null),
            'label' => match ($domain) {
                'schedule' => $version->meeting_date?->toDateString().' '.substr((string) $version->start_time, 0, 5),
                'participants' => $version->members_count ?? null,
                'minutes' => mb_substr((string) $version->summary, 0, 120),
            },
        ];
    }

    private function prepareCurrentPointer(Meeting $meeting, string $domain): void
    {
        if ($domain === 'minutes') {
            $meeting->setAttribute('minutes_current_version_id', $meeting->minutes()->value('current_version_id'));
        }
    }
}
