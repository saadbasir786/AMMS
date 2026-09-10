<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use App\Models\UserRole;
use App\Support\AmmsRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserAccessSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrFail();
        $authorityPosition = Position::query()->where('code', 'HIGH-AUTHORITY')->firstOrFail();
        $authorityOffice = OrganizationalUnit::query()->where('code', 'AUTH-OFFICE')->firstOrFail();
        $authoritySeat = OrganizationalUnit::query()->where('code', 'AUTHORITY')->firstOrFail();

        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $techSw = OrganizationalUnit::query()->where('code', 'TECH-SW')->firstOrFail();
        $finance = OrganizationalUnit::query()->where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::query()->where('code', 'HR')->firstOrFail();

        $employeesByNum = Employee::query()->get()->keyBy('employee_number');

        // 1. High Authority Primary Record
        OrganizationAuthority::create([
            'organization_id' => $organization->id,
            'authority_type' => 'DIRECTOR_GENERAL',
            'authority_position_id' => $authorityPosition->id,
            'authority_office_unit_id' => $authorityOffice->id,
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        // Helper to provision user, UserRole, Spatie Role, and AmmsRoleScope
        $makeUser = function (string $name, string $email, Employee $employee, string $role, ?int $unitId = null): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('ChangeMe!2026'),
                'employee_id' => $employee->id,
                'status' => 'ACTIVE',
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role' => $role,
                'organizational_unit_id' => $unitId,
            ]);

            $spatieRole = Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);
            $user->assignRole($spatieRole);

            AmmsRoleScope::create([
                'user_id' => $user->id,
                'role_id' => $spatieRole->id,
                'organizational_unit_id' => $unitId,
            ]);

            return $user;
        };

        // 2. System Administrator (Unscoped, no employee profile)
        $system = User::create([
            'name' => 'مدیر سیستم',
            'email' => 'system.admin@example.test',
            'password' => Hash::make('ChangeMe!2026'),
            'status' => 'ACTIVE',
        ]);

        UserRole::create([
            'user_id' => $system->id,
            'role' => AmmsRole::SYSTEM_ADMIN,
            'organizational_unit_id' => null,
        ]);

        $systemRole = Role::firstOrCreate(['name' => AmmsRole::SYSTEM_ADMIN, 'guard_name' => 'sanctum']);
        $system->assignRole($systemRole);

        AmmsRoleScope::create([
            'user_id' => $system->id,
            'role_id' => $systemRole->id,
            'organizational_unit_id' => null,
        ]);

        // 3. Technology Users
        $makeUser('مدیریت اجرائیه تکنالوژی', 'tech.exec@example.test', $employeesByNum['EMP-TECH-002'], AmmsRole::EXECUTIVE_MANAGEMENT, $tech->id);
        $makeUser('رئیس ریاست تکنالوژی', 'tech.director@example.test', $employeesByNum['EMP-TECH-001'], AmmsRole::DIRECTOR, $tech->id);
        $makeUser('معاون ریاست تکنالوژی', 'tech.deputy@example.test', $employeesByNum['EMP-TECH-005'], AmmsRole::DEPUTY_DIRECTOR, $tech->id);

        // 4. Finance Users
        $makeUser('مدیریت اجرائیه مالی', 'finance.exec@example.test', $employeesByNum['EMP-FIN-002'], AmmsRole::EXECUTIVE_MANAGEMENT, $finance->id);
        $makeUser('رئیس ریاست مالی', 'finance.director@example.test', $employeesByNum['EMP-FIN-001'], AmmsRole::DIRECTOR, $finance->id);

        // 5. Human Resources Users
        $makeUser('مدیریت اجرائیه منابع بشری', 'hr.exec@example.test', $employeesByNum['EMP-HR-002'], AmmsRole::EXECUTIVE_MANAGEMENT, $hr->id);
        $makeUser('رئیس ریاست منابع بشری', 'hr.director@example.test', $employeesByNum['EMP-HR-101'], AmmsRole::DIRECTOR, $hr->id);

        // 6. Regular Employee
        $makeUser('کارمند تکنالوژی', 'employee.a@example.test', $employeesByNum['EMP-TECH-003'], AmmsRole::EMPLOYEE, $techSw->id);

        // 7. Authority Office & High Authority
        $makeUser('بررسی‌کننده دفتر مقام', 'authority.office@example.test', $employeesByNum['EMP-AUTH-OFFICE-001'], AmmsRole::AUTHORITY_OFFICE, $authorityOffice->id);
        $makeUser('سکرتر اجرائیوی مقام', 'executive.secretary@example.test', $employeesByNum['EMP-AUTH-SEC-001'], AmmsRole::EXECUTIVE_SECRETARY, $authorityOffice->id);
        $makeUser('رئیس عمومی', 'high.authority@example.test', $employeesByNum['EMP-AUTH-001'], AmmsRole::HIGH_AUTHORITY, $authoritySeat->id);
    }
}
