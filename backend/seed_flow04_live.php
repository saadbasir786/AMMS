<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

$organization = Organization::query()->firstOrFail();
$unit = static function (string $code, string $name, string $type, ?int $parentId = null) use ($organization): OrganizationalUnit {
    return OrganizationalUnit::firstOrCreate(['code' => $code], [
        'organization_id' => $organization->id,
        'parent_id' => $parentId,
        'name' => $name,
        'unit_type' => $type,
        'status' => 'ACTIVE',
    ]);
};
$office = $unit('AUTH-OFFICE', 'ریاست دفتر مقام', 'AUTHORITY_OFFICE');
$seat = $unit('AUTHORITY', 'مقام ریاست عمومی', 'HIGH_AUTHORITY');
$position = static function (string $code, int $unitId, string $title, string $type): Position {
    return Position::firstOrCreate(['code' => $code], [
        'organizational_unit_id' => $unitId,
        'title' => $title,
        'position_type' => $type,
        'status' => 'ACTIVE',
    ]);
};
$authorityPosition = $position('HIGH-AUTHORITY', $seat->id, 'رئیس عمومی', 'HIGH_AUTHORITY');
$officePosition = $position('AUTH-OFFICE-DIRECTOR', $office->id, 'رئیس دفتر', 'AUTHORITY_OFFICE');
$secretaryPosition = $position('EXEC-SECRETARY', $office->id, 'سکرتر اجرائیوی', 'EXECUTIVE_SECRETARY');
$employee = static function (string $number, string $first, string $last, int $unitId, int $positionId, string $email): Employee {
    return Employee::firstOrCreate(['employee_number' => $number], [
        'first_name' => $first,
        'last_name' => $last,
        'father_name' => '—',
        'organizational_unit_id' => $unitId,
        'position_id' => $positionId,
        'email' => $email,
        'phone' => null,
        'status' => 'ACTIVE',
    ]);
};
$authorityEmployee = $employee('EMP-AUTH-001', 'عبدالقیوم', 'احمدزی', $seat->id, $authorityPosition->id, 'high.authority@example.test');
$officeEmployee = $employee('EMP-AUTH-OFFICE-001', 'مریم', 'نورستانی', $office->id, $officePosition->id, 'authority.office@example.test');
$secretaryEmployee = $employee('EMP-AUTH-SEC-001', 'جمال', 'صدیقی', $office->id, $secretaryPosition->id, 'executive.secretary@example.test');
$authority = OrganizationAuthority::updateOrCreate(
    ['organization_id' => $organization->id, 'is_primary' => true, 'status' => 'ACTIVE'],
    ['authority_type' => 'DIRECTOR_GENERAL', 'authority_position_id' => $authorityPosition->id, 'authority_office_unit_id' => $office->id]
);
$role = static function (string $name): Role { return Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']); };
$account = static function (string $email, string $name, Employee $employee, string $roleName, int $unitId) use ($role): User {
    $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('ChangeMe!2026'), 'employee_id' => $employee->id]);
    $user->update(['employee_id' => $employee->id, 'status' => 'ACTIVE']);
    $spatieRole = $role($roleName);
    if (!$user->hasRole($spatieRole, 'sanctum')) $user->assignRole($spatieRole);
    UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $roleName, 'organizational_unit_id' => $unitId]);
    AmmsRoleScope::firstOrCreate(['user_id' => $user->id, 'role_id' => $spatieRole->id, 'organizational_unit_id' => $unitId]);
    return $user;
};
$account('high.authority@example.test', 'رئیس عمومی', $authorityEmployee, 'HIGH_AUTHORITY', $seat->id);
$account('authority.office@example.test', 'بررسی‌کننده دفتر مقام', $officeEmployee, 'AUTHORITY_OFFICE', $office->id);
$account('executive.secretary@example.test', 'سکرتر اجرائیوی مقام', $secretaryEmployee, 'EXECUTIVE_SECRETARY', $office->id);
echo json_encode(['organization_id' => $organization->id, 'authority_id' => $authority->id, 'office_unit_id' => $office->id, 'authority_employee_id' => $authorityEmployee->id], JSON_UNESCAPED_UNICODE).PHP_EOL;
