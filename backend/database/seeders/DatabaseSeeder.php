<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use App\Models\UserRole;
use App\Models\AmmsRoleScope;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::create(['name'=>'اداره نمونه','short_name'=>'اداره نمونه','type'=>'INDEPENDENT_ADMINISTRATION','status'=>'ACTIVE']);
        $tech = OrganizationalUnit::create(['organization_id'=>$organization->id,'name'=>'ریاست تکنالوژی','code'=>'TECH','unit_type'=>'DIRECTORATE','status'=>'ACTIVE']);
        $finance = OrganizationalUnit::create(['organization_id'=>$organization->id,'name'=>'ریاست مالی','code'=>'FIN','unit_type'=>'DIRECTORATE','status'=>'ACTIVE']);
        $hr = OrganizationalUnit::create(['organization_id'=>$organization->id,'name'=>'ریاست منابع بشری','code'=>'HR','unit_type'=>'DIRECTORATE','status'=>'ACTIVE']);
        $techExec = OrganizationalUnit::create(['organization_id'=>$organization->id,'parent_id'=>$tech->id,'name'=>'مدیریت اجرائیه تکنالوژی','code'=>'TECH-EXEC','unit_type'=>'MANAGEMENT','status'=>'ACTIVE']);
        $software = OrganizationalUnit::create(['organization_id'=>$organization->id,'parent_id'=>$tech->id,'name'=>'دیپارتمنت نرم‌افزار','code'=>'TECH-SW','unit_type'=>'DEPARTMENT','status'=>'ACTIVE']);
        $infra = OrganizationalUnit::create(['organization_id'=>$organization->id,'parent_id'=>$tech->id,'name'=>'دیپارتمنت زیربنا','code'=>'TECH-INFRA','unit_type'=>'DEPARTMENT','status'=>'ACTIVE']);
        $financeExec = OrganizationalUnit::create(['organization_id'=>$organization->id,'parent_id'=>$finance->id,'name'=>'مدیریت اجرائیه مالی','code'=>'FIN-EXEC','unit_type'=>'MANAGEMENT','status'=>'ACTIVE']);
        $hrExec = OrganizationalUnit::create(['organization_id'=>$organization->id,'parent_id'=>$hr->id,'name'=>'مدیریت اجرائیه منابع بشری','code'=>'HR-EXEC','unit_type'=>'MANAGEMENT','status'=>'ACTIVE']);
        $authorityOffice = OrganizationalUnit::create(['organization_id'=>$organization->id,'name'=>'ریاست دفتر مقام','code'=>'AUTH-OFFICE','unit_type'=>'AUTHORITY_OFFICE','status'=>'ACTIVE']);
        $authoritySeat = OrganizationalUnit::create(['organization_id'=>$organization->id,'name'=>'مقام ریاست عمومی','code'=>'AUTHORITY','unit_type'=>'HIGH_AUTHORITY','status'=>'ACTIVE']);

        $director = Position::create(['organizational_unit_id'=>$tech->id,'title'=>'رئیس ریاست','position_type'=>'DIRECTOR','status'=>'ACTIVE']);
        $executive = Position::create(['organizational_unit_id'=>$techExec->id,'title'=>'مدیر اجرائیه','position_type'=>'EXECUTIVE_MANAGEMENT','status'=>'ACTIVE']);
        $engineer = Position::create(['organizational_unit_id'=>$software->id,'title'=>'انجنیر نرم‌افزار','position_type'=>'EMPLOYEE','status'=>'ACTIVE']);
        $admin = Position::create(['organizational_unit_id'=>$techExec->id,'title'=>'مسؤول اداری','position_type'=>'EMPLOYEE','status'=>'ACTIVE']);
        $financeDirector = Position::create(['organizational_unit_id'=>$finance->id,'title'=>'رئیس ریاست','position_type'=>'DIRECTOR','status'=>'ACTIVE']);
        $hrDirector = Position::create(['organizational_unit_id'=>$hr->id,'title'=>'رئیس ریاست','position_type'=>'DIRECTOR','status'=>'ACTIVE']);
        $hrExecutive = Position::create(['organizational_unit_id'=>$hrExec->id,'title'=>'مدیر اجرائیه','position_type'=>'EXECUTIVE_MANAGEMENT','status'=>'ACTIVE']);
        $authorityPosition = Position::create(['organizational_unit_id'=>$authoritySeat->id,'title'=>'رئیس عمومی','code'=>'HIGH-AUTHORITY','position_type'=>'HIGH_AUTHORITY','status'=>'ACTIVE']);
        $officeReviewerPosition = Position::create(['organizational_unit_id'=>$authorityOffice->id,'title'=>'رئیس دفتر','code'=>'AUTH-OFFICE-DIRECTOR','position_type'=>'AUTHORITY_OFFICE','status'=>'ACTIVE']);
        $secretaryPosition = Position::create(['organizational_unit_id'=>$authorityOffice->id,'title'=>'سکرتر اجرائیوی','code'=>'EXEC-SECRETARY','position_type'=>'EXECUTIVE_SECRETARY','status'=>'ACTIVE']);

        $techDirectorEmployee = Employee::create(['employee_number'=>'EMP-TECH-001','first_name'=>'احمد','last_name'=>'احمدی','father_name'=>'محمد','organizational_unit_id'=>$tech->id,'position_id'=>$director->id,'email'=>'tech.director@example.test','phone'=>'0700000001','status'=>'ACTIVE']);
        $techExecEmployee = Employee::create(['employee_number'=>'EMP-TECH-002','first_name'=>'فاطمه','last_name'=>'رحیمی','father_name'=>'علی','organizational_unit_id'=>$techExec->id,'position_id'=>$executive->id,'email'=>'tech.exec@example.test','phone'=>'0700000002','status'=>'ACTIVE']);
        $employeeA = Employee::create(['employee_number'=>'EMP-TECH-003','first_name'=>'حامد','last_name'=>'کریمی','father_name'=>'عبدالله','organizational_unit_id'=>$software->id,'position_id'=>$engineer->id,'email'=>'employee.a@example.test','phone'=>'0700000003','status'=>'ACTIVE']);
        $employeeB = Employee::create(['employee_number'=>'EMP-TECH-004','first_name'=>'مریم','last_name'=>'نوری','father_name'=>'حسین','organizational_unit_id'=>$infra->id,'position_id'=>$admin->id,'email'=>'employee.b@example.test','phone'=>'0700000004','status'=>'ACTIVE']);
        $financeDirectorEmployee = Employee::create(['employee_number'=>'EMP-FIN-001','first_name'=>'زلمی','last_name'=>'حکیمی','father_name'=>'عبدالکریم','organizational_unit_id'=>$finance->id,'position_id'=>$financeDirector->id,'email'=>'finance.director@example.test','phone'=>'0700000010','status'=>'ACTIVE']);
        $financeExecEmployee = Employee::create(['employee_number'=>'EMP-FIN-002','first_name'=>'سمیرا','last_name'=>'صادقی','father_name'=>'حبیب','organizational_unit_id'=>$financeExec->id,'position_id'=>$executive->id,'email'=>'finance.exec@example.test','phone'=>'0700000011','status'=>'ACTIVE']);
        $hrDirectorEmployee = Employee::create(['employee_number'=>'EMP-HR-101','first_name'=>'حسن','last_name'=>'احمدی','father_name'=>'کریم','organizational_unit_id'=>$hr->id,'position_id'=>$hrDirector->id,'email'=>'hr.director@example.test','phone'=>'0700000020','status'=>'ACTIVE']);
        $hrExecEmployee = Employee::create(['employee_number'=>'EMP-HR-002','first_name'=>'لیلا','last_name'=>'رحیمی','father_name'=>'نصیر','organizational_unit_id'=>$hrExec->id,'position_id'=>$hrExecutive->id,'email'=>'hr.exec@example.test','phone'=>'0700000021','status'=>'ACTIVE']);
        $authorityEmployee = Employee::create(['employee_number'=>'EMP-AUTH-001','first_name'=>'عبدالقیوم','last_name'=>'احمدزی','father_name'=>'عبدالرحمن','organizational_unit_id'=>$authoritySeat->id,'position_id'=>$authorityPosition->id,'email'=>'high.authority@example.test','phone'=>'0700000030','status'=>'ACTIVE']);
        $officeReviewerEmployee = Employee::create(['employee_number'=>'EMP-AUTH-OFFICE-001','first_name'=>'مریم','last_name'=>'نورستانی','father_name'=>'حبیب','organizational_unit_id'=>$authorityOffice->id,'position_id'=>$officeReviewerPosition->id,'email'=>'authority.office@example.test','phone'=>'0700000031','status'=>'ACTIVE']);
        $secretaryEmployee = Employee::create(['employee_number'=>'EMP-AUTH-SEC-001','first_name'=>'جمال','last_name'=>'صدیقی','father_name'=>'نعمت','organizational_unit_id'=>$authorityOffice->id,'position_id'=>$secretaryPosition->id,'email'=>'executive.secretary@example.test','phone'=>'0700000032','status'=>'ACTIVE']);

        OrganizationAuthority::create(['organization_id'=>$organization->id,'authority_type'=>'DIRECTOR_GENERAL','authority_position_id'=>$authorityPosition->id,'authority_office_unit_id'=>$authorityOffice->id,'status'=>'ACTIVE','is_primary'=>true]);

        $makeUser = function (string $name, string $email, Employee $employee, string $role, ?int $unitId = null): User {
            $user = User::create(['name'=>$name,'email'=>$email,'password'=>Hash::make('ChangeMe!2026'),'employee_id'=>$employee->id]);
            UserRole::create(['user_id'=>$user->id,'role'=>$role,'organizational_unit_id'=>$unitId]);
            $spatieRole = Role::firstOrCreate(['name'=>$role,'guard_name'=>'sanctum']);
            $user->assignRole($spatieRole);
            AmmsRoleScope::create(['user_id'=>$user->id,'role_id'=>$spatieRole->id,'organizational_unit_id'=>$unitId]);
            return $user;
        };
        $system = User::create(['name'=>'مدیر سیستم','email'=>'system.admin@example.test','password'=>Hash::make('ChangeMe!2026')]);
        UserRole::create(['user_id'=>$system->id,'role'=>'SYSTEM_ADMIN','organizational_unit_id'=>null]);
        $systemRole = Role::firstOrCreate(['name'=>'SYSTEM_ADMIN','guard_name'=>'sanctum']);
        $system->assignRole($systemRole);
        AmmsRoleScope::create(['user_id'=>$system->id,'role_id'=>$systemRole->id,'organizational_unit_id'=>null]);
        $makeUser('مدیریت اجرائیه تکنالوژی','tech.exec@example.test',$techExecEmployee,'EXECUTIVE_MANAGEMENT',$tech->id);
        $makeUser('رئیس ریاست تکنالوژی','tech.director@example.test',$techDirectorEmployee,'DIRECTOR',$tech->id);
        $makeUser('مدیریت اجرائیه مالی','finance.exec@example.test',$financeExecEmployee,'EXECUTIVE_MANAGEMENT',$finance->id);
        $makeUser('رئیس ریاست مالی','finance.director@example.test',$financeDirectorEmployee,'DIRECTOR',$finance->id);
        $makeUser('مدیریت اجرائیه منابع بشری','hr.exec@example.test',$hrExecEmployee,'EXECUTIVE_MANAGEMENT',$hr->id);
        $makeUser('رئیس ریاست منابع بشری','hr.director@example.test',$hrDirectorEmployee,'DIRECTOR',$hr->id);
        $makeUser('هماهنگ‌کننده جلسات تکنالوژی','tech.coordinator@example.test',$techExecEmployee,'MEETING_COORDINATOR',$tech->id);
        $makeUser('کارمند تکنالوژی','employee.a@example.test',$employeeA,'EMPLOYEE',$software->id);
        $makeUser('بررسی‌کننده دفتر مقام','authority.office@example.test',$officeReviewerEmployee,'AUTHORITY_OFFICE',$authorityOffice->id);
        $makeUser('سکرتر اجرائیوی مقام','executive.secretary@example.test',$secretaryEmployee,'EXECUTIVE_SECRETARY',$authorityOffice->id);
        $makeUser('رئیس عمومی','high.authority@example.test',$authorityEmployee,'HIGH_AUTHORITY',$authoritySeat->id);
    }
}
