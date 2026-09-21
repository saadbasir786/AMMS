<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreMeetingVenueRequest;
use App\Http\Requests\Organization\UpdateMeetingVenueRequest;
use App\Http\Resources\MeetingVenueResource;
use App\Models\MeetingVenue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingVenueController extends Controller
{
    public function index(Request $request)
    {
        $this->assertAdmin($request);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'], 'organization_id' => ['sometimes', 'integer', 'exists:organizations,id']]);
        $query = MeetingVenue::query()->with('organization')->when($request->filled('organization_id'), fn ($q) => $q->where('organization_id', $request->integer('organization_id')))->orderBy('organization_id')->orderBy('name')->orderBy('id');
        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        return MeetingVenueResource::collection($query->paginate($perPage))->additional(['success' => true, 'message' => 'فهرست محل‌های داخلی.']);
    }

    public function store(StoreMeetingVenueRequest $request)
    {
        $data = $request->validated();
        $this->assertUniqueName((int) $data['organization_id'], $data['name']);
        $venue = DB::transaction(fn (): MeetingVenue => MeetingVenue::create([
            ...$data,
            'status' => $data['status'] ?? 'ACTIVE',
        ]));
        return (new MeetingVenueResource($venue))->additional(['success' => true, 'message' => 'محل داخلی ایجاد شد.']);
    }

    public function update(UpdateMeetingVenueRequest $request, MeetingVenue $meetingVenue)
    {
        $data = $request->validated();
        $this->assertUniqueName((int) $meetingVenue->organization_id, $data['name'], $meetingVenue->id);
        DB::transaction(fn (): bool => $meetingVenue->update($data));
        return (new MeetingVenueResource($meetingVenue->fresh()))->additional(['success' => true, 'message' => 'مشخصات محل داخلی اصلاح شد.']);
    }

    public function activate(Request $request, MeetingVenue $meetingVenue)
    {
        $this->assertAdmin($request);
        DB::transaction(fn (): bool => $meetingVenue->update(['status' => 'ACTIVE']));
        return (new MeetingVenueResource($meetingVenue->fresh()))->additional(['success' => true, 'message' => 'محل داخلی فعال شد.']);
    }

    public function deactivate(Request $request, MeetingVenue $meetingVenue)
    {
        $this->assertAdmin($request);
        DB::transaction(fn (): bool => $meetingVenue->update(['status' => 'INACTIVE']));
        return (new MeetingVenueResource($meetingVenue->fresh()))->additional(['success' => true, 'message' => 'محل داخلی غیرفعال شد.']);
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless($request->user()?->isActive() && $request->user()->hasScopedRole('SYSTEM_ADMIN') && $request->user()->can('organization.manage'), 403, 'FORBIDDEN');
    }

    private function assertUniqueName(int $organizationId, string $name, ?int $ignoreId = null): void
    {
        $query = MeetingVenue::query()->where('organization_id', $organizationId)->where('name', $name)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));
        abort_if($query->exists(), 422, 'MEETING_VENUE_NAME_TAKEN');
    }
}
