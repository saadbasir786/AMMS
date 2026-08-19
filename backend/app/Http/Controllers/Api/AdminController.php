<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\OrganizationalUnitResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserRoleResource;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\User as UserModel;
use App\Models\UserRole;
use App\Models\AmmsRoleScope;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    private function admin(Request $request): void { abort_unless($request->user()->isActive() && $request->user()->hasScopedRole('SYSTEM_ADMIN') && ($request->user()->can('organization.manage') || $request->user()->can('user.manage') || $request->user()->can('role.manage')), 403, 'FORBIDDEN'); }

    public function users(Request $request)
    {
        $this->authorize('viewAny', UserModel::class);
        return UserResource::collection(UserModel::with(['employee.unit','roleScopes.role','roleScopes.unit'])->latest()->paginate(20))->additional(['success'=>true,'message'=>'فهرست کاربران.']);
    }

    public function storeUser(StoreUserRequest $request)
    {
        $this->authorize('create', UserModel::class);
        $user = UserModel::create(array_merge($request->safe()->except('password'), ['password'=>Hash::make($request->validated()['password'])]));
        return (new UserResource($user->load(['employee.unit','roleScopes.role','roleScopes.unit'])))->additional(['success'=>true,'message'=>'کاربر ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function updateUser(StoreUserRequest $request, UserModel $user)
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        if (blank($data['password'] ?? null)) unset($data['password']); else $data['password'] = Hash::make($data['password']);
        $user->update($data);
        return (new UserResource($user->fresh(['employee.unit','roleScopes.role','roleScopes.unit'])))->additional(['success'=>true,'message'=>'کاربر اصلاح شد.']);
    }

    public function assignRole(AssignRoleRequest $request, UserModel $user)
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        $roleName = $data['role'];
        $unitId = $data['organizational_unit_id'] ?? null;
        $role = Role::firstOrCreate(['name'=>$roleName,'guard_name'=>'sanctum']);
        $user->assignRole($role);
        UserRole::updateOrCreate(['user_id'=>$user->id,'role'=>$roleName,'organizational_unit_id'=>$unitId]);
        $scope = AmmsRoleScope::firstOrCreate(['user_id'=>$user->id,'role_id'=>$role->id,'organizational_unit_id'=>$unitId]);
        return (new UserRoleResource($scope->load(['role','unit'])))->additional(['success'=>true,'message'=>'صلاحیت کاربر ثبت شد.']);
    }

    public function employees(Request $request)
    {
        $this->authorize('viewAny', Employee::class);
        return EmployeeResource::collection(Employee::with(['unit','position'])->latest()->paginate(20))->additional(['success'=>true,'message'=>'فهرست همهٔ کارمندان.']);
    }

    public function storeEmployee(StoreEmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);
        $employee = Employee::create($request->validated());
        return (new EmployeeResource($employee->load(['unit','position'])))->additional(['success'=>true,'message'=>'کارمند ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function updateEmployee(StoreEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $employee->update($request->validated());
        return (new EmployeeResource($employee->fresh(['unit','position'])))->additional(['success'=>true,'message'=>'اطلاعات کارمند اصلاح شد.']);
    }

    public function deactivateEmployee(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $employee->update(['status'=>'INACTIVE']);
        return (new EmployeeResource($employee))->additional(['success'=>true,'message'=>'کارمند غیرفعال شد.']);
    }

    public function units(Request $request)
    {
        $this->admin($request);
        return OrganizationalUnitResource::collection(OrganizationalUnit::with(['parent','children'])->orderBy('name')->get())->additional(['success'=>true,'message'=>'مدیریت واحدهای تشکیلاتی.']);
    }

    public function storeUnit(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['organization_id'=>['required','integer','exists:organizations,id'],'parent_id'=>['nullable','integer','exists:organizational_units,id'],'name'=>['required','string','max:255'],'code'=>['nullable','string','max:50'],'unit_type'=>['required','in:DIRECTORATE,DEPARTMENT,MANAGEMENT,OFFICE,OTHER'],'status'=>['sometimes','in:ACTIVE,INACTIVE']]);
        return (new OrganizationalUnitResource(OrganizationalUnit::create($data)))->additional(['success'=>true,'message'=>'واحد تشکیلاتی ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function organizations(Request $request)
    {
        $this->admin($request);
        return response()->json(['success'=>true,'data'=>Organization::withCount('units')->orderBy('name')->get(),'message'=>'فهرست سازمان‌ها.']);
    }
}
