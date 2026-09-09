<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DelegationIndexRequest;
use App\Http\Requests\Meetings\RevokeMinutesApprovalDelegationRequest;
use App\Http\Requests\Meetings\StoreMinutesApprovalDelegationRequest;
use App\Http\Resources\CapabilityScopeResource;
use App\Models\AmmsCapabilityScope;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\ScopedCapabilityManager;
use App\Support\SpecialCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MinutesApprovalDelegationController extends Controller
{
    public function index(DelegationIndexRequest $request): JsonResponse
    {
        $unitIds = $this->directorUnitIds($request->user());

        $baseQuery = AmmsCapabilityScope::query()
            ->with(['user.employee.unit', 'unit', 'grantedBy', 'revokedBy'])
            ->where('capability', SpecialCapability::DIRECTORATE_MINUTES_APPROVAL)
            ->whereIn('organizational_unit_id', $unitIds);

        if ($request->filled('organizational_unit_id')) {
            $baseQuery->where('organizational_unit_id', $request->integer('organizational_unit_id'));
        }

        $query = clone $baseQuery;

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'ACTIVE') {
                $query->active();
            } elseif ($status === 'REVOKED') {
                $query->whereNotNull('revoked_at');
            } elseif ($status === 'EXPIRED') {
                $query->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now());
            }
        }

        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')->toString()).'%';
            $query->where(function ($searchQuery) use ($term): void {
                $searchQuery
                    ->where('grant_reason', 'like', $term)
                    ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('user', function ($user) use ($term): void {
                        $user->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhereHas('employee', fn ($employee) => $employee->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
                    });
            });
        }

        $delegations = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(min(max($request->integer('per_page', 15), 1), 50));
        $delegationsPayload = CapabilityScopeResource::collection($delegations)->response()->getData(true);
        $statsBase = clone $baseQuery;
        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => (clone $statsBase)->active()->count(),
            'revoked' => (clone $statsBase)->whereNotNull('revoked_at')->count(),
            'expired' => (clone $statsBase)->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
        ];

        $delegates = User::query()
            ->with('employee.unit')
            ->where(fn ($query) => $query->where('status', 'ACTIVE')->orWhereNull('status'))
            ->whereHas('roleScopes', fn ($query) => $query
                ->whereIn('organizational_unit_id', $unitIds)
                ->whereHas('role', fn ($role) => $role->where('name', 'DEPUTY_DIRECTOR')->where('guard_name', 'sanctum')))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'name', 'email', 'employee_id', 'status']);

        $units = OrganizationalUnit::query()->whereIn('id', $unitIds)->orderBy('name')->orderBy('id')->limit(50)->get(['id', 'name', 'code']);

        return response()->json(['success' => true, 'data' => [
            'delegations' => $delegationsPayload['data'],
            'delegations_meta' => $delegationsPayload['meta'],
            'delegations_links' => $delegationsPayload['links'],
            'stats' => $stats,
            'delegates' => $delegates->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee' => $user->employee ? [
                    'full_name' => $user->employee->full_name,
                    'unit' => $user->employee->unit ? ['id' => $user->employee->unit->id, 'name' => $user->employee->unit->name] : null,
                ] : null,
            ]),
            'units' => $units,
        ]]);
    }

    public function store(StoreMinutesApprovalDelegationRequest $request, ScopedCapabilityManager $manager): JsonResponse
    {
        $actor = $request->user();
        $unit = OrganizationalUnit::query()->findOrFail($request->integer('organizational_unit_id'));
        $this->assertDirectorForUnit($actor, $unit->id);

        $delegate = User::query()->with('employee.unit')->findOrFail($request->integer('delegate_user_id'));
        abort_unless(
            $delegate->isActive() && $delegate->hasScopedRole('DEPUTY_DIRECTOR', $unit->id),
            422,
            'DELEGATE_MUST_BE_DEPUTY_DIRECTOR_OF_UNIT',
        );

        $scope = DB::transaction(function () use ($actor, $delegate, $manager, $request, $unit) {
            OrganizationalUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $hasAnotherActiveDelegate = AmmsCapabilityScope::query()
                ->active()
                ->where('capability', SpecialCapability::DIRECTORATE_MINUTES_APPROVAL)
                ->where('organizational_unit_id', $unit->id)
                ->where('user_id', '!=', $delegate->id)
                ->exists();
            abort_if($hasAnotherActiveDelegate, 422, 'ACTIVE_MINUTES_APPROVAL_DELEGATION_EXISTS');

            return $manager->grant(
                $delegate,
                SpecialCapability::DIRECTORATE_MINUTES_APPROVAL,
                $unit,
                $actor,
                [
                    'expires_at' => $request->date('expires_at')->endOfDay(),
                    'grant_reason' => $request->string('reason')->trim()->toString(),
                ],
            );
        });

        return (new CapabilityScopeResource($scope->load(['user.employee.unit', 'unit', 'grantedBy'])))
            ->additional(['success' => true, 'message' => 'صلاحیت تأیید صورت‌جلسه تفویض شد.'])
            ->response()
            ->setStatusCode($scope->wasRecentlyCreated ? 201 : 200);
    }

    public function revoke(RevokeMinutesApprovalDelegationRequest $request, AmmsCapabilityScope $capabilityScope, ScopedCapabilityManager $manager): CapabilityScopeResource
    {
        abort_unless($capabilityScope->capability === SpecialCapability::DIRECTORATE_MINUTES_APPROVAL, 404);
        $this->assertDirectorForUnit($request->user(), (int) $capabilityScope->organizational_unit_id);

        return new CapabilityScopeResource($manager->revoke(
            $capabilityScope,
            $request->user(),
            $request->string('reason')->trim()->toString(),
        ));
    }

    /** @return array<int, int> */
    private function directorUnitIds(User $actor): array
    {
        abort_unless($actor->isActive() && $actor->can('meeting.minutes.review'), 403, 'FORBIDDEN');
        $unitIds = $actor->scopedRoleUnitIds('DIRECTOR');
        abort_unless($unitIds !== [], 403, 'FORBIDDEN');

        return array_map('intval', $unitIds);
    }

    private function assertDirectorForUnit(User $actor, int $unitId): void
    {
        abort_unless(
            $actor->isActive()
            && $actor->can('meeting.minutes.review')
            && $actor->hasScopedRole('DIRECTOR', $unitId),
            403,
            'FORBIDDEN',
        );
    }
}
