<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Support\OrganizationalUnitType;
use Illuminate\Database\Seeder;

class OrganizationStructureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Root Organization
        $organization = Organization::create([
            'name' => 'ریاست عمومی اداره امور ا.ا.ا',
            'short_name' => 'اداره امور',
            'type' => 'INDEPENDENT_ADMINISTRATION',
            'status' => 'ACTIVE',
        ]);

        // 2. Apex / Authority Root (Single authoritative root of the chart)
        $authoritySeat = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'مقام ریاست عمومی',
            'code' => 'AUTHORITY',
            'unit_type' => OrganizationalUnitType::GENERAL_PRESIDENCY,
            'status' => 'ACTIVE',
        ]);

        // 3. Office of the Authority (Reporting to Authority Seat)
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $authoritySeat->id,
            'name' => 'ریاست دفتر مقام',
            'code' => 'AUTH-OFFICE',
            'unit_type' => OrganizationalUnitType::AUTHORITY_OFFICE,
            'status' => 'ACTIVE',
        ]);

        // 4. Financial & Administrative Deputy Presidency (Reporting to Authority Seat)
        $deputyPresidency = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $authoritySeat->id,
            'name' => 'معاونیت مالی و اداری',
            'code' => 'DEP-PRES-FIN',
            'unit_type' => OrganizationalUnitType::DEPUTY_PRESIDENCY,
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
            'unit_type' => OrganizationalUnitType::DIRECTORATE,
            'status' => 'ACTIVE',
        ]);

        // 5a. Technology Deputy Directorate (Subordinate operational units report through Deputy Directorate)
        $techDep = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $tech->id,
            'name' => 'معاونیت ریاست تکنالوژی',
            'code' => 'TECH-DEP',
            'unit_type' => OrganizationalUnitType::DEPUTY_DIRECTORATE,
            'status' => 'ACTIVE',
        ]);

        // 5b. Technology Executive General Management (Reporting to Deputy Directorate)
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techDep->id,
            'name' => 'مدیریت عمومی اجرائیه تکنالوژی',
            'code' => 'TECH-EXEC',
            'unit_type' => OrganizationalUnitType::GENERAL_MANAGEMENT,
            'status' => 'ACTIVE',
        ]);

        // 5c. Network & Systems Amirate (Reporting to Deputy Directorate)
        $techAmirNet = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techDep->id,
            'name' => 'آمریت شبکه و سیستم‌ها',
            'code' => 'TECH-AMIR-NET',
            'unit_type' => OrganizationalUnitType::AMIRIATE,
            'status' => 'ACTIVE',
        ]);

        // 5c-i. Network General Management
        $techInfra = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techAmirNet->id,
            'name' => 'مدیریت عمومی شبکه',
            'code' => 'TECH-INFRA',
            'unit_type' => OrganizationalUnitType::GENERAL_MANAGEMENT,
            'status' => 'ACTIVE',
        ]);

        // 5c-ii. Network Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techInfra->id,
            'name' => 'مدیریت شبکه',
            'code' => 'TECH-MGT-NET',
            'unit_type' => OrganizationalUnitType::MANAGEMENT,
            'status' => 'ACTIVE',
        ]);

        // 5d. Software Amirate (Reporting to Deputy Directorate)
        $techAmirSw = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techDep->id,
            'name' => 'آمریت نرم‌افزار',
            'code' => 'TECH-AMIR-SW',
            'unit_type' => OrganizationalUnitType::AMIRIATE,
            'status' => 'ACTIVE',
        ]);

        // 5d-i. Software General Management
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $techAmirSw->id,
            'name' => 'مدیریت عمومی نرم‌افزار',
            'code' => 'TECH-SW',
            'unit_type' => OrganizationalUnitType::GENERAL_MANAGEMENT,
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
            'unit_type' => OrganizationalUnitType::DIRECTORATE,
            'status' => 'ACTIVE',
        ]);

        // 6a. Finance Deputy Directorate
        $financeDep = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $finance->id,
            'name' => 'معاونیت ریاست مالی',
            'code' => 'FIN-DEP',
            'unit_type' => OrganizationalUnitType::DEPUTY_DIRECTORATE,
            'status' => 'ACTIVE',
        ]);

        // 6b. Finance Executive General Management (Reporting to Deputy Directorate)
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $financeDep->id,
            'name' => 'مدیریت عمومی اجرائیه مالی',
            'code' => 'FIN-EXEC',
            'unit_type' => OrganizationalUnitType::GENERAL_MANAGEMENT,
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
            'unit_type' => OrganizationalUnitType::DIRECTORATE,
            'status' => 'ACTIVE',
        ]);

        // 7a. HR Deputy Directorate
        $hrDep = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $hr->id,
            'name' => 'معاونیت ریاست منابع بشری',
            'code' => 'HR-DEP',
            'unit_type' => OrganizationalUnitType::DEPUTY_DIRECTORATE,
            'status' => 'ACTIVE',
        ]);

        // 7b. HR Executive General Management (Reporting to Deputy Directorate)
        OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $hrDep->id,
            'name' => 'مدیریت عمومی اجرائیه منابع بشری',
            'code' => 'HR-EXEC',
            'unit_type' => OrganizationalUnitType::GENERAL_MANAGEMENT,
            'status' => 'ACTIVE',
        ]);
    }
}
