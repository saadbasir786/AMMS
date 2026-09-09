<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignCapabilityRequest;
use App\Http\Requests\Organization\AssignRoleRequest;
use App\Http\Requests\Organization\StoreEmployeeRequest;
use App\Http\Requests\Organization\StorePositionRequest;
use App\Http\Requests\Organization\StoreUserRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\CapabilityScopeResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\OrganizationalUnitResource;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\PositionResource;
use App\Http\Resources\UserAccessAuditResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserRoleResource;
use App\Models\AmmsCapabilityScope;
use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User as UserModel;
use App\Models\UserAccessAudit;
use App\Models\UserRole;
use App\Services\ScopedCapabilityManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    private const BUSINESS_ROLES = ['EMPLOYEE', 'DIRECTOR', 'DEPUTY_DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY'];

    private function admin(Request $request): void
    {
        abort_unless($request->user()->isActive() && $request->user()->hasScopedRole('SYSTEM_ADMIN') && ($request->user()->can('organization.manage') || $request->user()->can('user.manage') || $request->user()->can('role.manage')), 403, 'FORBIDDEN');
    }

    private function organizationAdmin(Request $request): void
    {
        abort_unless($request->user()->isActive() && $request->user()->hasScopedRole('SYSTEM_ADMIN') && $request->user()->can('organization.manage'), 403, 'FORBIDDEN');
    }

    public function users(PaginatedRequest $request)
    {
        $this->authorize('viewAny', UserModel::class);
        $query = UserModel::with(['employee.unit', 'employee.position', 'roleScopes.role', 'roleScopes.unit', 'capabilityScopes.unit'])
            ->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roleScopes.role', fn ($rq) => $rq->where('name', $role));
        }

        return UserResource::collection($query->paginate($this->perPage($request, 20)))->additional(['success' => true, 'message' => 'فهرست کاربران.']);
    }

    public function storeUser(StoreUserRequest $request)
    {
        $this->authorize('create', UserModel::class);
        $user = DB::transaction(function () use ($request): UserModel {
            $user = UserModel::create(array_merge($request->safe()->except('password'), ['password' => Hash::make($request->validated()['password'])]));
            $this->recordAccessAudit($user, $request->user(), 'ACCOUNT_CREATED', null, null, ['employee_id' => $user->employee_id, 'status' => $user->status ?: 'ACTIVE']);

            return $user;
        });

        return (new UserResource($user->load(['employee.unit', 'employee.position', 'roleScopes.role', 'roleScopes.unit', 'capabilityScopes.unit'])))->additional(['success' => true, 'message' => 'کاربر ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function updateUser(StoreUserRequest $request, UserModel $user, ScopedCapabilityManager $manager)
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        $passwordChanged = filled($data['password'] ?? null);
        if (! $passwordChanged) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $isBeingDeactivated = ($data['status'] ?? $user->status) === 'INACTIVE' && $user->status !== 'INACTIVE';
        $this->assertNotDeactivatingLastSystemAdmin($user, $isBeingDeactivated);
        DB::transaction(function () use ($user, $request, $data, $passwordChanged, $isBeingDeactivated, $manager): void {
            $original = $user->getOriginal();
            $user->update($data);
            $changedFields = array_values(array_diff(array_keys($user->getChanges()), ['password', 'updated_at']));
            if ($changedFields !== []) {
                $this->recordAccessAudit($user, $request->user(), $isBeingDeactivated ? 'ACCOUNT_DEACTIVATED' : (($original['status'] ?? null) === 'INACTIVE' && ($user->status ?? null) === 'ACTIVE' ? 'ACCOUNT_ACTIVATED' : 'ACCOUNT_UPDATED'), null, null, ['changed_fields' => $changedFields]);
            }
            if ($passwordChanged) {
                $this->recordAccessAudit($user, $request->user(), 'PASSWORD_CHANGED');
            }
            if ($isBeingDeactivated) {
                $user->tokens()->delete();
            }
            if ($isBeingDeactivated || in_array('employee_id', $changedFields, true)) {
                $freshUser = $user->fresh(['employee.unit', 'employee.position']);
                $this->reconcileUserAccess($freshUser, $freshUser->employee, $request->user(), $manager);
            }
        });

        return (new UserResource($user->fresh(['employee.unit', 'employee.position', 'roleScopes.role', 'roleScopes.unit', 'capabilityScopes.unit'])))->additional(['success' => true, 'message' => 'اطلاعات کاربر اصلاح شد.']);
    }

    public function accessAudits(PaginatedRequest $request, UserModel $user)
    {
        $this->authorize('view', $user);

        return UserAccessAuditResource::collection($user->accessAudits()->with(['actor', 'unit'])->orderByDesc('created_at')->orderByDesc('id')->paginate(min(max($request->integer('per_page', 30), 1), 50)))->additional(['success' => true]);
    }

    public function assignRole(AssignRoleRequest $request, UserModel $user)
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        $roleName = $data['role'];
        $unitId = $data['organizational_unit_id'] ?? null;
        $unit = $unitId ? OrganizationalUnit::query()->findOrFail($unitId) : null;
        $this->assertRoleAssignmentIsValid($user, $roleName, $unit);

        $scope = DB::transaction(function () use ($user, $request, $roleName, $unitId) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $user->assignRole($role);
            UserRole::updateOrCreate(['user_id' => $user->id, 'role' => $roleName, 'organizational_unit_id' => $unitId]);
            $scope = AmmsRoleScope::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'organizational_unit_id' => $unitId]);
            if ($scope->wasRecentlyCreated) {
                $this->recordAccessAudit($user, $request->user(), 'ROLE_ASSIGNED', $roleName, $unitId);
            }

            return $scope;
        });

        return (new UserRoleResource($scope->load(['role', 'unit'])))->additional(['success' => true, 'message' => 'صلاحیت کاربر ثبت شد.']);
    }

    public function revokeRole(Request $request, UserModel $user, AmmsRoleScope $roleScope)
    {
        $this->authorize('update', $user);
        abort_unless($request->user()->isActive() && $request->user()->hasScopedRole('SYSTEM_ADMIN') && $request->user()->can('role.manage'), 403, 'FORBIDDEN');
        abort_unless((int) $roleScope->user_id === (int) $user->id, 404);
        $roleScope->load('role');
        $roleName = (string) $roleScope->role->name;
        abort_if($roleName === 'SYSTEM_ADMIN' && AmmsRoleScope::query()->whereHas('role', fn ($query) => $query->where('name', 'SYSTEM_ADMIN'))->whereHas('user', fn ($query) => $query->where('status', 'ACTIVE'))->count() <= 1, 422, 'LAST_SYSTEM_ADMIN_ROLE');

        DB::transaction(function () use ($user, $request, $roleScope, $roleName): void {
            UserRole::query()->where('user_id', $user->id)->where('role', $roleName)->where('organizational_unit_id', $roleScope->organizational_unit_id)->delete();
            $roleScope->delete();
            if (! $user->roleScopes()->where('role_id', $roleScope->role_id)->exists()) {
                $user->removeRole($roleScope->role);
            }
            $this->recordAccessAudit($user, $request->user(), 'ROLE_REVOKED', $roleName, $roleScope->organizational_unit_id);
        });

        return response()->json(['success' => true, 'message' => 'نقش و حوزهٔ کاربر لغو شد.']);
    }

    public function grantCapability(AssignCapabilityRequest $request, UserModel $user, ScopedCapabilityManager $manager)
    {
        $this->authorize('update', $user);
        $unit = OrganizationalUnit::query()->findOrFail($request->integer('organizational_unit_id'));
        $scope = $manager->grant($user, $request->string('capability')->toString(), $unit, $request->user());
        if ($scope->wasRecentlyCreated) {
            $this->recordAccessAudit($user, $request->user(), 'CAPABILITY_GRANTED', null, $unit->id, ['capability' => $scope->capability]);
        }
        $response = (new CapabilityScopeResource($scope))
            ->additional(['success' => true, 'message' => 'صلاحیت ویژه کاربر در محدوده تعیین‌شده ثبت شد.'])
            ->response();

        return $response->setStatusCode($scope->wasRecentlyCreated ? 201 : 200);
    }

    public function revokeCapability(Request $request, UserModel $user, AmmsCapabilityScope $capabilityScope, ScopedCapabilityManager $manager)
    {
        $this->authorize('update', $user);
        abort_unless($request->user()->isActive() && $request->user()->hasScopedRole('SYSTEM_ADMIN') && $request->user()->can('role.manage'), 403, 'FORBIDDEN');
        abort_unless((int) $capabilityScope->user_id === (int) $user->id, 404);
        $scope = $manager->revoke($capabilityScope, $request->user());
        $this->recordAccessAudit($user, $request->user(), 'CAPABILITY_REVOKED', null, $scope->organizational_unit_id, ['capability' => $scope->capability]);

        return (new CapabilityScopeResource($scope))->additional(['success' => true, 'message' => 'صلاحیت ویژه کاربر لغو شد.']);
    }

    public function employees(PaginatedRequest $request)
    {
        abort_unless($request->user()->hasScopedRole('SYSTEM_ADMIN') && $request->user()->can('employee.manage'), 403, 'FORBIDDEN');
        $this->authorize('viewAny', Employee::class);
        $query = Employee::with(['unit', 'position'])->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('organizational_unit_id')) {
            $query->where('organizational_unit_id', $request->integer('organizational_unit_id'));
        }

        return EmployeeResource::collection($query->paginate($this->perPage($request, 20)))->additional(['success' => true, 'message' => 'فهرست همهٔ کارمندان.']);
    }

    public function storeEmployee(StoreEmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);
        $employee = Employee::create($request->validated());

        return (new EmployeeResource($employee->load(['unit', 'position'])))->additional(['success' => true, 'message' => 'کارمند ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function updateEmployee(StoreEmployeeRequest $request, Employee $employee, ScopedCapabilityManager $manager)
    {
        $this->authorize('update', $employee);
        DB::transaction(function () use ($request, $employee, $manager): void {
            $employee->update($request->validated());
            $this->reconcileEmployeeAccess($employee->fresh(['unit', 'position', 'user']), $request->user(), $manager);
        });

        return (new EmployeeResource($employee->fresh(['unit', 'position'])))->additional(['success' => true, 'message' => 'اطلاعات کارمند اصلاح شد.']);
    }

    public function deactivateEmployee(Request $request, Employee $employee, ScopedCapabilityManager $manager)
    {
        $this->authorize('update', $employee);
        DB::transaction(function () use ($request, $employee, $manager): void {
            $employee->update(['status' => 'INACTIVE']);
            $this->reconcileEmployeeAccess($employee->fresh(['unit', 'position', 'user']), $request->user(), $manager);
        });

        return (new EmployeeResource($employee))->additional(['success' => true, 'message' => 'کارمند غیرفعال شد.']);
    }

    public function units(PaginatedRequest $request)
    {
        $this->admin($request);

        $units = OrganizationalUnit::with(['parent', 'children'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 50));

        return OrganizationalUnitResource::collection($units)->additional(['success' => true, 'message' => 'مدیریت واحدهای تشکیلاتی.']);
    }

    public const ALLOWED_UNIT_TYPES = [
        'GENERAL_PRESIDENCY',
        'DEPUTY_PRESIDENCY',
        'GENERAL_DIRECTORATE',
        'DIRECTORATE',
        'ATTACHED_AGENCY',
        'DEPUTY_DIRECTORATE',
        'AMIRIATE',
        'GENERAL_MANAGEMENT',
        'MANAGEMENT',
        'SECTION',
        'OTHER',
        // Backwards compatibility
        'DEPARTMENT',
        'OFFICE',
        'HIGH_AUTHORITY',
        'AUTHORITY_OFFICE',
    ];

    public function storeUnit(Request $request)
    {
        $this->admin($request);

        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;

        $orgId = (int) ($request->input('organization_id')
            ?? ($parentId ? OrganizationalUnit::query()->where('id', $parentId)->value('organization_id') : null)
            ?? OrganizationalUnit::query()->value('organization_id')
            ?? Organization::query()->value('id')
            ?? 1);

        $nameRule = Rule::unique('organizational_units', 'name')
            ->where('organization_id', $orgId);
        if ($parentId !== null) {
            $nameRule->where('parent_id', $parentId);
        } else {
            $nameRule->whereNull('parent_id');
        }

        $codeRule = Rule::unique('organizational_units', 'code')
            ->where('organization_id', $orgId);

        $data = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
            'parent_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => ['nullable', 'string', 'max:50', $codeRule],
            'unit_type' => ['required', 'string', Rule::in(self::ALLOWED_UNIT_TYPES)],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
        ], [
            'name.unique' => 'عنوان واحد سازمانی در میان واحدهای هم‌ردهٔ این بخش تکراری است. لطفاً عنوان دیگری انتخاب نمایید.',
            'code.unique' => 'کد اداری واردشده در این سازمان تکراری است. لطفاً کد دیگری وارد فرمایید.',
        ]);

        $data['organization_id'] = $orgId;
        $data['parent_id'] = $parentId;

        $unit = DB::transaction(function () use ($data, $parentId, $orgId) {
            // Concurrency protection: lock and verify sibling uniqueness
            $query = OrganizationalUnit::query()
                ->where('organization_id', $orgId)
                ->where('name', $data['name']);
            if ($parentId !== null) {
                $query->where('parent_id', $parentId);
            } else {
                $query->whereNull('parent_id');
            }
            abort_if(
                $query->lockForUpdate()->exists(),
                422,
                'عنوان واحد سازمانی در میان واحدهای هم‌ردهٔ این بخش تکراری است. لطفاً عنوان دیگری انتخاب نمایید.'
            );

            // Same Organization Guard
            if ($parentId !== null) {
                $parent = OrganizationalUnit::query()->lockForUpdate()->findOrFail($parentId);
                abort_unless(
                    (int) $parent->organization_id === (int) $orgId,
                    422,
                    'واحد مافوق باید متعلق به همین سازمان باشد.'
                );
            }

            return OrganizationalUnit::create($data);
        });

        return (new OrganizationalUnitResource($unit->load(['parent', 'children'])))
            ->additional(['success' => true, 'message' => 'واحد تشکیلاتی با موفقیت ایجاد شد.'])
            ->response()
            ->setStatusCode(201);
    }

    public function updateUnit(Request $request, OrganizationalUnit $unit)
    {
        $this->admin($request);

        $orgId = (int) ($request->input('organization_id', $unit->organization_id));
        $newParentId = array_key_exists('parent_id', $request->all())
            ? ($request->filled('parent_id') ? (int) $request->input('parent_id') : null)
            : $unit->parent_id;

        $nameRule = Rule::unique('organizational_units', 'name')
            ->where('organization_id', $orgId);
        if ($newParentId !== null) {
            $nameRule->where('parent_id', $newParentId);
        } else {
            $nameRule->whereNull('parent_id');
        }
        $nameRule->ignore($unit->id);

        $codeRule = Rule::unique('organizational_units', 'code')
            ->where('organization_id', $orgId)
            ->ignore($unit->id);

        $data = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
            'parent_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => ['nullable', 'string', 'max:50', $codeRule],
            'unit_type' => ['required', 'string', Rule::in(self::ALLOWED_UNIT_TYPES)],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
        ], [
            'name.unique' => 'عنوان واحد سازمانی در میان واحدهای هم‌ردهٔ این بخش تکراری است. لطفاً عنوان دیگری انتخاب نمایید.',
            'code.unique' => 'کد اداری واردشده در این سازمان تکراری است. لطفاً کد دیگری وارد فرمایید.',
        ]);

        $data['organization_id'] = $orgId;
        if (array_key_exists('parent_id', $request->all())) {
            $data['parent_id'] = $newParentId;
        }

        $freshUnit = DB::transaction(function () use ($unit, $data, $newParentId, $orgId) {
            // Concurrency protection: lock and verify sibling uniqueness
            $query = OrganizationalUnit::query()
                ->where('organization_id', $orgId)
                ->where('id', '!=', $unit->id)
                ->where('name', $data['name']);
            if ($newParentId !== null) {
                $query->where('parent_id', $newParentId);
            } else {
                $query->whereNull('parent_id');
            }
            abort_if(
                $query->lockForUpdate()->exists(),
                422,
                'عنوان واحد سازمانی در میان واحدهای هم‌ردهٔ این بخش تکراری است. لطفاً عنوان دیگری انتخاب نمایید.'
            );

            // Hierarchy cycle prevention
            if ($newParentId !== null) {
                abort_if(
                    $newParentId === (int) $unit->id,
                    422,
                    'واحد سازمانی نمی‌تواند مافوق خودش باشد.'
                );

                abort_if(
                    $unit->isWithinScope($newParentId),
                    422,
                    'نمی‌توان یک واحد زیرمجموعه را به عنوان مافوق تعیین نمود.'
                );

                // Same Organization Guard
                $parent = OrganizationalUnit::query()->lockForUpdate()->findOrFail($newParentId);
                abort_unless(
                    (int) $parent->organization_id === (int) $orgId,
                    422,
                    'واحد مافوق باید متعلق به همین سازمان باشد.'
                );
            }

            // Deactivation checks
            if (isset($data['status']) && $data['status'] === 'INACTIVE' && $unit->status === 'ACTIVE') {
                abort_if(
                    $unit->children()->where('status', 'ACTIVE')->exists(),
                    422,
                    'این واحد دارای واحدهای تابعه فعال می‌باشد. لطفاً ابتدا وضعیت واحدهای زیرمجموعه را مشخص نمایید.'
                );

                abort_if(
                    $unit->employees()->where('status', 'ACTIVE')->exists(),
                    422,
                    'این واحد دارای کارمندان فعال می‌باشد. لطفاً ابتدا وضعیت کارمندان را تعیین تکلیف فرمایید.'
                );
            }

            $unit->update($data);

            return $unit->fresh(['parent', 'children']);
        });

        return (new OrganizationalUnitResource($freshUnit))
            ->additional(['success' => true, 'message' => 'واحد تشکیلاتی با موفقیت اصلاح شد.']);
    }

    public function positions(PaginatedRequest $request)
    {
        $this->organizationAdmin($request);

        $query = Position::query()->with('unit')->withCount('employees')->orderBy('title')->orderBy('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('organizational_unit_id')) {
            $query->where('organizational_unit_id', $request->integer('organizational_unit_id'));
        }

        if ($request->filled('position_type')) {
            $query->where('position_type', $request->input('position_type'));
        }

        return PositionResource::collection($query->paginate($this->perPage($request, 30)))
            ->additional(['success' => true, 'message' => 'فهرست سمت‌های سازمانی.']);
    }

    public function storePosition(StorePositionRequest $request)
    {
        $position = Position::create($request->validated());

        return (new PositionResource($position->load('unit')->loadCount('employees')))
            ->additional(['success' => true, 'message' => 'سمت سازمانی ایجاد شد.'])
            ->response()
            ->setStatusCode(201);
    }

    public function updatePosition(StorePositionRequest $request, Position $position)
    {
        $data = $request->validated();
        $isBeingDeactivated = ($data['status'] ?? $position->status) === 'INACTIVE' && $position->status === 'ACTIVE';
        $structureChanged = (int) $data['organizational_unit_id'] !== (int) $position->organizational_unit_id || ($data['position_type'] ?? null) !== $position->position_type;
        abort_if(($isBeingDeactivated || $structureChanged) && Employee::query()->where('position_id', $position->id)->where('status', 'ACTIVE')->exists(), 422, 'POSITION_HAS_ACTIVE_EMPLOYEES');

        $position->update($data);

        return (new PositionResource($position->fresh('unit')->loadCount('employees')))
            ->additional(['success' => true, 'message' => 'سمت سازمانی اصلاح شد.']);
    }

    public function organizations(PaginatedRequest $request)
    {
        $this->admin($request);

        $organizations = Organization::withCount('units')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 50), 1), 50));

        return OrganizationResource::collection($organizations)->additional(['success' => true, 'message' => 'فهرست سازمان‌ها.']);
    }

    private function assertRoleAssignmentIsValid(UserModel $user, string $roleName, ?OrganizationalUnit $unit): void
    {
        $user->loadMissing('employee.position');
        $hasSystemAdminRole = $user->hasRole('SYSTEM_ADMIN', 'sanctum');

        if ($roleName === 'SYSTEM_ADMIN') {
            abort_if($unit !== null, 422, 'SYSTEM_ADMIN_MUST_BE_UNSCOPED');
            abort_if($user->roleScopes()->whereHas('role', fn ($query) => $query->whereIn('name', self::BUSINESS_ROLES))->exists(), 422, 'SYSTEM_ADMIN_CANNOT_HAVE_BUSINESS_ROLE');

            return;
        }

        abort_if($hasSystemAdminRole, 422, 'SYSTEM_ADMIN_CANNOT_HAVE_BUSINESS_ROLE');
        abort_unless($unit?->status === 'ACTIVE', 422, 'ROLE_SCOPE_UNIT_MUST_BE_ACTIVE');
        abort_unless($user->isActive() && $user->employee && $user->employee->position?->status === 'ACTIVE', 422, 'BUSINESS_ROLE_REQUIRES_ACTIVE_EMPLOYEE');
        abort_unless($unit->isWithinScope((int) $user->employee->organizational_unit_id), 422, 'ROLE_SCOPE_OUTSIDE_EMPLOYEE_ORGANIZATION');

        $requiredPositionType = $roleName === 'EMPLOYEE' ? null : $roleName;
        abort_unless($requiredPositionType === null || $user->employee->position?->position_type === $requiredPositionType, 422, 'ROLE_DOES_NOT_MATCH_EMPLOYEE_POSITION');
    }

    private function assertNotDeactivatingLastSystemAdmin(UserModel $user, bool $isBeingDeactivated): void
    {
        if (! $isBeingDeactivated || ! $user->hasRole('SYSTEM_ADMIN', 'sanctum')) {
            return;
        }

        $activeSystemAdminCount = UserModel::query()
            ->where('status', 'ACTIVE')
            ->whereHas('roleScopes.role', fn ($query) => $query->where('name', 'SYSTEM_ADMIN'))
            ->count();
        abort_if($activeSystemAdminCount <= 1, 422, 'LAST_SYSTEM_ADMIN_ACCOUNT');
    }

    private function recordAccessAudit(UserModel $user, ?UserModel $actor, string $action, ?string $role = null, ?int $unitId = null, array $metadata = []): void
    {
        UserAccessAudit::create([
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'role' => $role,
            'organizational_unit_id' => $unitId,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function reconcileEmployeeAccess(Employee $employee, UserModel $actor, ScopedCapabilityManager $manager): void
    {
        $user = $employee->user;
        if (! $user) {
            return;
        }

        $this->reconcileUserAccess($user, $employee, $actor, $manager);
    }

    private function reconcileUserAccess(UserModel $user, ?Employee $employee, UserModel $actor, ScopedCapabilityManager $manager): void
    {
        $employee?->loadMissing(['unit', 'position']);

        $user->load(['roleScopes.role', 'roleScopes.unit', 'capabilityScopes.unit']);
        if (! $user->isActive()) {
            $user->tokens()->delete();
        }

        foreach ($user->roleScopes as $scope) {
            $roleName = (string) $scope->role->name;
            if ($roleName === 'SYSTEM_ADMIN') {
                continue;
            }
            $positionMatches = $roleName === 'EMPLOYEE' || $employee?->position?->position_type === $roleName;
            $scopeMatches = $employee && $scope->unit?->status === 'ACTIVE' && $scope->unit->isWithinScope((int) $employee->organizational_unit_id);
            if ($user->isActive() && $employee?->position?->status === 'ACTIVE' && $positionMatches && $scopeMatches) {
                continue;
            }

            $unitId = $scope->organizational_unit_id;
            $roleId = $scope->role_id;
            UserRole::query()->where('user_id', $user->id)->where('role', $roleName)->where('organizational_unit_id', $unitId)->delete();
            $scope->delete();
            if (! $user->roleScopes()->where('role_id', $roleId)->exists()) {
                $user->removeRole($roleName);
            }
            $this->recordAccessAudit($user, $actor, 'ROLE_REVOKED', $roleName, $unitId, ['reason' => 'EMPLOYEE_PROFILE_CHANGED']);
        }

        foreach ($user->capabilityScopes->filter->isActive() as $scope) {
            $scopeMatches = $employee && $scope->unit?->status === 'ACTIVE' && $scope->unit->isWithinScope((int) $employee->organizational_unit_id);
            if ($user->isActive() && $scopeMatches) {
                continue;
            }
            $revoked = $manager->revoke($scope, $actor, 'EMPLOYEE_PROFILE_CHANGED');
            $this->recordAccessAudit($user, $actor, 'CAPABILITY_REVOKED', null, $revoked->organizational_unit_id, ['capability' => $revoked->capability, 'reason' => 'EMPLOYEE_PROFILE_CHANGED']);
        }
    }

    private function perPage(Request $request, int $default): int
    {
        return min(max($request->integer('per_page', $default), 1), 50);
    }
}
