<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreAmmsRoleRequest;
use App\Http\Requests\Organization\UpdateAmmsRolePermissionsRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\AmmsRoleResource;
use App\Http\Resources\AmmsRoleAuditResource;
use App\Models\AmmsRoleAudit;
use App\Models\AmmsRoleDefinition;
use App\Models\UserRole;
use App\Support\AmmsPermissionRegistry;
use App\Support\AmmsRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleManagementController extends Controller
{
    public function permissions(Request $request)
    {
        $this->guard($request);
        $metadata = AmmsPermissionRegistry::metadata();
        $grouped = collect($metadata)->groupBy('category')->map(static fn ($items): array => array_values($items->all()))->all();

        return response()->json(['success' => true, 'data' => array_values($metadata), 'grouped' => $grouped]);
    }

    public function index(PaginatedRequest $request)
    {
        $this->guard($request);
        $query = AmmsRoleDefinition::query()->with(['role.permissions'])->orderBy('role_kind')->orderBy('label')->orderBy('id');

        if ($request->filled('role_kind')) $query->where('role_kind', $request->string('role_kind')->toString());
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());

        return AmmsRoleResource::collection($query->paginate($this->perPage($request, 30)))
            ->additional(['success' => true]);
    }

    public function show(Request $request, Role $role)
    {
        $this->guard($request);
        $definition = $this->definition($role);
        $definition->load('role.permissions');

        return (new AmmsRoleResource($definition))->additional(['success' => true]);
    }

    public function audits(PaginatedRequest $request, Role $role)
    {
        $this->guard($request);
        $this->definition($role);

        return AmmsRoleAuditResource::collection(
            AmmsRoleAudit::query()->where('role_id', $role->id)->with('actor')->orderByDesc('created_at')->orderByDesc('id')->paginate($this->perPage($request, 30))
        )->additional(['success' => true]);
    }

    public function store(StoreAmmsRoleRequest $request)
    {
        $this->guard($request);
        $data = $request->validated();
        $name = strtoupper((string) $data['name']);
        $this->assertCustomNameAvailable($name);

        $definition = DB::transaction(function () use ($data, $name, $request): AmmsRoleDefinition {
            $role = Role::create(['name' => $name, 'guard_name' => 'sanctum']);
            $definition = AmmsRoleDefinition::create([
                'role_id' => $role->id,
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'role_kind' => AmmsRoleDefinition::CUSTOM,
                'status' => AmmsRoleDefinition::ACTIVE,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->audit($role, $request->user(), 'CUSTOM_ROLE_CREATED', ['name' => $name, 'label' => $data['label']]);
            return $definition;
        });

        return (new AmmsRoleResource($definition->load('role.permissions')))->additional(['success' => true])->response()->setStatusCode(201);
    }

    public function update(StoreAmmsRoleRequest $request, Role $role)
    {
        $this->guard($request);
        $definition = $this->definition($role);
        abort_if($definition->isSystem(), 422, 'SYSTEM_ROLE_PROTECTED');
        $data = $request->validated();
        $name = isset($data['name']) ? strtoupper((string) $data['name']) : $role->name;
        if ($name !== $role->name) $this->assertCustomNameAvailable($name, $role->id);

        DB::transaction(function () use ($data, $name, $role, $definition, $request): void {
            $before = ['name' => $role->name, 'label' => $definition->label, 'description' => $definition->description];
            $role->update(['name' => $name]);
            UserRole::query()->where('role', $before['name'])->update(['role' => $name]);
            $definition->update([
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            $this->audit($role, $request->user(), 'CUSTOM_ROLE_UPDATED', ['before' => $before, 'after' => ['name' => $name, 'label' => $definition->label, 'description' => $definition->description]]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new AmmsRoleResource($definition->fresh('role.permissions')))->additional(['success' => true]);
    }

    public function permissionsUpdate(UpdateAmmsRolePermissionsRequest $request, Role $role)
    {
        $this->guard($request);
        $definition = $this->definition($role);
        abort_if($definition->isSystem(), 422, 'SYSTEM_ROLE_PROTECTED');

        $requested = array_values(array_unique($request->validated()['permissions']));
        $metadata = AmmsPermissionRegistry::metadata();
        $invalid = array_values(array_filter($requested, static fn (string $permission): bool => !isset($metadata[$permission]) || !$metadata[$permission]['custom_role_assignable']));
        if ($invalid !== []) {
            throw ValidationException::withMessages(['permissions' => ['این permissionها برای نقش سفارشی قابل واگذاری نیستند: '.implode(', ', $invalid)]]);
        }

        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        DB::transaction(function () use ($role, $requested, $definition, $request, $before): void {
            $role->syncPermissions($requested);
            $definition->update(['updated_by' => $request->user()->id]);
            $this->audit($role, $request->user(), 'CUSTOM_ROLE_PERMISSIONS_CHANGED', [
                'added' => array_values(array_diff($requested, $before)),
                'removed' => array_values(array_diff($before, $requested)),
                'permissions' => $requested,
            ]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new AmmsRoleResource($definition->fresh('role.permissions')))->additional(['success' => true]);
    }

    public function activate(Request $request, Role $role)
    {
        return $this->setStatus($request, $role, AmmsRoleDefinition::ACTIVE, 'CUSTOM_ROLE_ACTIVATED');
    }

    public function deactivate(Request $request, Role $role)
    {
        return $this->setStatus($request, $role, AmmsRoleDefinition::INACTIVE, 'CUSTOM_ROLE_DEACTIVATED');
    }

    private function setStatus(Request $request, Role $role, string $status, string $action)
    {
        $this->guard($request);
        $definition = $this->definition($role);
        abort_if($definition->isSystem(), 422, 'SYSTEM_ROLE_PROTECTED');

        DB::transaction(function () use ($definition, $role, $status, $action, $request): void {
            $definition->update(['status' => $status, 'updated_by' => $request->user()->id]);
            $this->audit($role, $request->user(), $action, ['status' => $status]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new AmmsRoleResource($definition->fresh('role.permissions')))->additional(['success' => true]);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isActive() === true && $request->user()->hasScopedRole(AmmsRole::SYSTEM_ADMIN) && $request->user()->can('role.manage'), 403, 'FORBIDDEN');
    }

    private function definition(Role $role): AmmsRoleDefinition
    {
        return AmmsRoleDefinition::query()->where('role_id', $role->id)->whereHas('role', fn ($query) => $query->where('guard_name', 'sanctum'))->firstOrFail();
    }

    private function assertCustomNameAvailable(string $name, ?int $ignoreRoleId = null): void
    {
        $reserved = array_merge(AmmsRole::all(), ['MEETING_COORDINATOR', 'BIOMETRIC_ENROLLMENT_OFFICER']);
        abort_if(in_array($name, $reserved, true), 422, 'ROLE_NAME_RESERVED');
        $exists = Role::query()->where('name', $name)->where('guard_name', 'sanctum')->when($ignoreRoleId, fn ($query) => $query->where('id', '<>', $ignoreRoleId))->exists();
        abort_if($exists, 422, 'ROLE_NAME_ALREADY_EXISTS');
    }

    private function audit(Role $role, ?\App\Models\User $actor, string $action, array $metadata = []): void
    {
        AmmsRoleAudit::create(['role_id' => $role->id, 'actor_id' => $actor?->id, 'action' => $action, 'metadata' => $metadata ?: null]);
    }

    private function perPage(Request $request, int $default): int
    {
        return min(max($request->integer('per_page', $default), 1), 50);
    }
}
