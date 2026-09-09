<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;

class OrganizationStructureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Root Organization
        $organization = Organization::create([
            'name' => 'اداره نمونه',
            'short_name' => 'اداره نمونه',
            'type' => 'INDEPENDENT_ADMINISTRATION',
            'status' => 'ACTIVE',
        ]);

        // 2. Apex / Authority Root (Single authoritative root of the chart)
        $authoritySeat = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'مقام ریاست عمومی',
            'code' => 'AUTHORITY',
            'unit_type' => 'GENERAL_PRESIDENCY',
            'status' => 'ACTIVE',
        ]);

        // 3. Office of the Authority (Reporting to Authority Seat)
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $authoritySeat->id,
            'name' => 'ریاست دفتر مقام',
            'code' => 'AUTH-OFFICE',
            'unit_type' => 'AUTHORITY_OFFICE',
            'status' => 'ACTIVE',
        ]);

        // 4. Financial & Administrative Deputy Presidency (Reporting to Authority Seat)
        $deputyPresidency = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $authoritySeat->id,
            'name' => 'معاونیت مالی و اداری',
            'code' => 'DEP-PRES-FIN',
            'unit_type' => 'DEPUTY_PRESIDENCY',
            'status' => 'ACTIVE',
        ]);

        // -------------------------------------------------------------
        // 5. Technology Directorate (TECH)
        // -------------------------------------------------------------
        $tech = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $deputyPresidency->id,
            'name' => 'ریاست تکنالوژی معلوماتی',
            'code' => 'TECH',
            'unit_type' => 'DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        // 5a. Technology Deputy Directorate
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $tech->id,
            'name' => 'معاونیت ریاست تکنالوژی',
            'code' => 'TECH-DEP',
            'unit_type' => 'DEPUTY_DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        // 5b. Technology Executive General Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $tech->id,
            'name' => 'مدیریت عمومی اجرائیه تکنالوژی',
            'code' => 'TECH-EXEC',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        // 5c. Network & Systems Amirate
        $techAmirNet = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $tech->id,
            'name' => 'آمریت شبکه و سیستم‌ها',
            'code' => 'TECH-AMIR-NET',
            'unit_type' => 'AMIRIATE',
            'status' => 'ACTIVE',
        ]);

        // 5c-i. Network & Infrastructure General Management
        $techInfra = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techAmirNet->id,
            'name' => 'مدیریت عمومی شبکه و زیربنا',
            'code' => 'TECH-INFRA',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        // 5c-ii. Network Operational Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techInfra->id,
            'name' => 'مدیریت عملیاتی شبکه',
            'code' => 'TECH-MGT-NET',
            'unit_type' => 'MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        // 5d. Software Amirate
        $techAmirSw = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $tech->id,
            'name' => 'آمریت نرم‌افزار و سیستم‌ها',
            'code' => 'TECH-AMIR-SW',
            'unit_type' => 'AMIRIATE',
            'status' => 'ACTIVE',
        ]);

        // 5d-i. Software General Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techAmirSw->id,
            'name' => 'مدیریت عمومی انکشاف نرم‌افزار',
            'code' => 'TECH-SW',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        // -------------------------------------------------------------
        // 6. Finance Directorate (FIN)
        // -------------------------------------------------------------
        $finance = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $deputyPresidency->id,
            'name' => 'ریاست مالی و حسابی',
            'code' => 'FIN',
            'unit_type' => 'DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        // 6a. Finance Deputy Directorate
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $finance->id,
            'name' => 'معاونیت ریاست مالی',
            'code' => 'FIN-DEP',
            'unit_type' => 'DEPUTY_DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        // 6b. Finance Executive General Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $finance->id,
            'name' => 'مدیریت عمومی اجرائیه مالی',
            'code' => 'FIN-EXEC',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        // -------------------------------------------------------------
        // 7. Human Resources Directorate (HR)
        // -------------------------------------------------------------
        $hr = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $deputyPresidency->id,
            'name' => 'ریاست منابع بشری',
            'code' => 'HR',
            'unit_type' => 'DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        // 7a. HR Deputy Directorate
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $hr->id,
            'name' => 'معاونیت ریاست منابع بشری',
            'code' => 'HR-DEP',
            'unit_type' => 'DEPUTY_DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        // 7b. HR Executive General Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $hr->id,
            'name' => 'مدیریت عمومی اجرائیه منابع بشری',
            'code' => 'HR-EXEC',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);
    }
}
