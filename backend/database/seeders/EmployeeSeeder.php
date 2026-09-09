<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $unitByCode = OrganizationalUnit::query()->pluck('id', 'code')->all();
        $positionByCode = Position::query()->pluck('id', 'code')->all();

        // 1. Technology Employees
        Employee::create([
            'employee_number' => 'EMP-TECH-001',
            'first_name' => 'احمد',
            'last_name' => 'احمدی',
            'father_name' => 'محمد',
            'organizational_unit_id' => $unitByCode['TECH'],
            'position_id' => $positionByCode['TECH-DIR-POS'],
            'email' => 'tech.director@example.test',
            'phone' => '0700000001',
            'status' => 'ACTIVE',
        ]);

        // Deputy Director placed in DEPUTY_DIRECTORATE unit (TECH-DEP)
        Employee::create([
            'employee_number' => 'EMP-TECH-005',
            'first_name' => 'فرید',
            'last_name' => 'صادقی',
            'father_name' => 'محمد',
            'organizational_unit_id' => $unitByCode['TECH-DEP'],
            'position_id' => $positionByCode['TECH-DEP-POS'],
            'email' => 'tech.deputy@example.test',
            'phone' => '0700000005',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-TECH-002',
            'first_name' => 'عبدالباقی',
            'last_name' => 'رحیمی',
            'father_name' => 'علی',
            'organizational_unit_id' => $unitByCode['TECH-EXEC'],
            'position_id' => $positionByCode['TECH-EXEC-POS'],
            'email' => 'tech.exec@example.test',
            'phone' => '0700000002',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-TECH-003',
            'first_name' => 'حامد',
            'last_name' => 'کریمی',
            'father_name' => 'عبدالله',
            'organizational_unit_id' => $unitByCode['TECH-SW'],
            'position_id' => $positionByCode['TECH-SW-ENG-POS'],
            'email' => 'employee.a@example.test',
            'phone' => '0700000003',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-TECH-004',
            'first_name' => 'مریم',
            'last_name' => 'نوری',
            'father_name' => 'حسین',
            'organizational_unit_id' => $unitByCode['TECH-EXEC'],
            'position_id' => $positionByCode['TECH-ADMIN-POS'],
            'email' => 'employee.b@example.test',
            'phone' => '0700000004',
            'status' => 'ACTIVE',
        ]);

        // 2. Finance Employees
        Employee::create([
            'employee_number' => 'EMP-FIN-001',
            'first_name' => 'زلمی',
            'last_name' => 'حکیمی',
            'father_name' => 'عبدالکریم',
            'organizational_unit_id' => $unitByCode['FIN'],
            'position_id' => $positionByCode['FIN-DIR-POS'],
            'email' => 'finance.director@example.test',
            'phone' => '0700000010',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-FIN-002',
            'first_name' => 'سمیرا',
            'last_name' => 'صادقی',
            'father_name' => 'حبیب',
            'organizational_unit_id' => $unitByCode['FIN-EXEC'],
            'position_id' => $positionByCode['FIN-EXEC-POS'],
            'email' => 'finance.exec@example.test',
            'phone' => '0700000011',
            'status' => 'ACTIVE',
        ]);

        // 3. Human Resources Employees
        Employee::create([
            'employee_number' => 'EMP-HR-101',
            'first_name' => 'حسن',
            'last_name' => 'احمدی',
            'father_name' => 'کریم',
            'organizational_unit_id' => $unitByCode['HR'],
            'position_id' => $positionByCode['HR-DIR-POS'],
            'email' => 'hr.director@example.test',
            'phone' => '0700000020',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-HR-002',
            'first_name' => 'لیلا',
            'last_name' => 'رحیمی',
            'father_name' => 'نصیر',
            'organizational_unit_id' => $unitByCode['HR-EXEC'],
            'position_id' => $positionByCode['HR-EXEC-POS'],
            'email' => 'hr.exec@example.test',
            'phone' => '0700000021',
            'status' => 'ACTIVE',
        ]);

        // 4. High Authority & Office Employees
        Employee::create([
            'employee_number' => 'EMP-AUTH-001',
            'first_name' => 'عبدالقیوم',
            'last_name' => 'احمدزی',
            'father_name' => 'عبدالرحمن',
            'organizational_unit_id' => $unitByCode['AUTHORITY'],
            'position_id' => $positionByCode['HIGH-AUTHORITY'],
            'email' => 'high.authority@example.test',
            'phone' => '0700000030',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-AUTH-OFFICE-001',
            'first_name' => 'مریم',
            'last_name' => 'نورستانی',
            'father_name' => 'حبیب',
            'organizational_unit_id' => $unitByCode['AUTH-OFFICE'],
            'position_id' => $positionByCode['AUTH-OFFICE-DIRECTOR'],
            'email' => 'authority.office@example.test',
            'phone' => '0700000031',
            'status' => 'ACTIVE',
        ]);

        Employee::create([
            'employee_number' => 'EMP-AUTH-SEC-001',
            'first_name' => 'جمال',
            'last_name' => 'صدیقی',
            'father_name' => 'نعمت',
            'organizational_unit_id' => $unitByCode['AUTH-OFFICE'],
            'position_id' => $positionByCode['EXEC-SECRETARY'],
            'email' => 'executive.secretary@example.test',
            'phone' => '0700000032',
            'status' => 'ACTIVE',
        ]);
    }
}
