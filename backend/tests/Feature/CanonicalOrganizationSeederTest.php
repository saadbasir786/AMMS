<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalOrganizationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_seeded_organization_structure_has_single_authoritative_root(): void
    {
        $rootUnits = OrganizationalUnit::query()->whereNull('parent_id')->get();

        $this->assertCount(1, $rootUnits, 'There must be exactly one apex root organizational unit.');
        $root = $rootUnits->first();
        $this->assertSame('AUTHORITY', $root->code);
        $this->assertSame('GENERAL_PRESIDENCY', $root->unit_type);
        $this->assertSame('مقام ریاست عمومی', $root->name);
    }

    public function test_seeded_hierarchy_has_no_cycles_or_cross_organization_links(): void
    {
        $organization = Organization::query()->firstOrFail();
        $units = OrganizationalUnit::query()->get();

        foreach ($units as $unit) {
            $this->assertSame($organization->id, $unit->organization_id, "Unit {$unit->code} belongs to wrong organization.");
            $this->assertNotSame('DEPARTMENT', $unit->unit_type, "Unit {$unit->code} must not use legacy DEPARTMENT type.");

            // Verify cycle-free ancestor chain
            $visited = [$unit->id];
            $parent = $unit->parent;
            while ($parent) {
                $this->assertNotContains($parent->id, $visited, "Cycle detected at unit {$unit->code} -> parent {$parent->code}.");
                $visited[] = $parent->id;
                $parent = $parent->parent;
            }
        }
    }

    public function test_seeded_hierarchy_tree_depth_and_relationships(): void
    {
        $authority = OrganizationalUnit::query()->where('code', 'AUTHORITY')->firstOrFail();
        $authOffice = OrganizationalUnit::query()->where('code', 'AUTH-OFFICE')->firstOrFail();
        $deputyPresidency = OrganizationalUnit::query()->where('code', 'DEP-PRES-FIN')->firstOrFail();

        // Direct children of AUTHORITY
        $this->assertSame($authority->id, $authOffice->parent_id);
        $this->assertSame($authority->id, $deputyPresidency->parent_id);
        $this->assertSame('AUTHORITY_OFFICE', $authOffice->unit_type);
        $this->assertSame('DEPUTY_PRESIDENCY', $deputyPresidency->unit_type);

        // Directorates under Deputy Presidency
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::query()->where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::query()->where('code', 'HR')->firstOrFail();

        $this->assertSame($deputyPresidency->id, $tech->parent_id);
        $this->assertSame($deputyPresidency->id, $finance->parent_id);
        $this->assertSame($deputyPresidency->id, $hr->parent_id);

        // Technology Directorate -> Technology Deputy Directorate
        $techDep = OrganizationalUnit::query()->where('code', 'TECH-DEP')->firstOrFail();
        $this->assertSame($tech->id, $techDep->parent_id);
        $this->assertSame('DEPUTY_DIRECTORATE', $techDep->unit_type);

        // Subordinate operational units report through Deputy Directorate
        $techExec = OrganizationalUnit::query()->where('code', 'TECH-EXEC')->firstOrFail();
        $techAmirNet = OrganizationalUnit::query()->where('code', 'TECH-AMIR-NET')->firstOrFail();
        $techAmirSw = OrganizationalUnit::query()->where('code', 'TECH-AMIR-SW')->firstOrFail();

        $this->assertSame($techDep->id, $techExec->parent_id);
        $this->assertSame($techDep->id, $techAmirNet->parent_id);
        $this->assertSame($techDep->id, $techAmirSw->parent_id);

        $this->assertSame('GENERAL_MANAGEMENT', $techExec->unit_type);
        $this->assertSame('AMIRIATE', $techAmirNet->unit_type);
        $this->assertSame('AMIRIATE', $techAmirSw->unit_type);

        // Finance & HR subordinate units also report through their Deputy Directorates
        $finDep = OrganizationalUnit::query()->where('code', 'FIN-DEP')->firstOrFail();
        $finExec = OrganizationalUnit::query()->where('code', 'FIN-EXEC')->firstOrFail();
        $this->assertSame($finance->id, $finDep->parent_id);
        $this->assertSame($finDep->id, $finExec->parent_id);

        $hrDep = OrganizationalUnit::query()->where('code', 'HR-DEP')->firstOrFail();
        $hrExec = OrganizationalUnit::query()->where('code', 'HR-EXEC')->firstOrFail();
        $this->assertSame($hr->id, $hrDep->parent_id);
        $this->assertSame($hrDep->id, $hrExec->parent_id);

        // Network branch (depth 4 and 5)
        $techInfra = OrganizationalUnit::query()->where('code', 'TECH-INFRA')->firstOrFail();
        $techMgtNet = OrganizationalUnit::query()->where('code', 'TECH-MGT-NET')->firstOrFail();

        $this->assertSame($techAmirNet->id, $techInfra->parent_id);
        $this->assertSame($techInfra->id, $techMgtNet->parent_id);
        $this->assertSame('GENERAL_MANAGEMENT', $techInfra->unit_type);
        $this->assertSame('MANAGEMENT', $techMgtNet->unit_type);
        $this->assertSame('مدیریت عمومی شبکه', $techInfra->name);
        $this->assertSame('مدیریت شبکه', $techMgtNet->name);

        // Software branch (depth 4)
        $techSw = OrganizationalUnit::query()->where('code', 'TECH-SW')->firstOrFail();
        $this->assertSame($techAmirSw->id, $techSw->parent_id);
        $this->assertSame('GENERAL_MANAGEMENT', $techSw->unit_type);
        $this->assertSame('آمریت نرم‌افزار', $techAmirSw->name);
        $this->assertSame('مدیریت عمومی نرم‌افزار', $techSw->name);
    }

    public function test_deputy_director_position_and_employee_belong_to_deputy_directorate_unit(): void
    {
        $techDepUnit = OrganizationalUnit::query()->where('code', 'TECH-DEP')->firstOrFail();
        $deputyPosition = Position::query()->where('code', 'TECH-DEP-POS')->firstOrFail();
        $deputyEmployee = Employee::query()->where('employee_number', 'EMP-TECH-005')->firstOrFail();

        $this->assertSame($techDepUnit->id, $deputyPosition->organizational_unit_id);
        $this->assertSame('DEPUTY_DIRECTOR', $deputyPosition->position_type);

        $this->assertSame($techDepUnit->id, $deputyEmployee->organizational_unit_id);
        $this->assertSame($deputyPosition->id, $deputyEmployee->position_id);
        $this->assertSame('tech.deputy@example.test', $deputyEmployee->email);

        // Executive General Managers hold GENERAL_MANAGER position type
        $techExecPos = Position::query()->where('code', 'TECH-EXEC-POS')->firstOrFail();
        $this->assertSame('GENERAL_MANAGER', $techExecPos->position_type);
        $this->assertSame('مدیر عمومی اجرائیه', $techExecPos->title);

        $finExecPos = Position::query()->where('code', 'FIN-EXEC-POS')->firstOrFail();
        $this->assertSame('GENERAL_MANAGER', $finExecPos->position_type);

        $hrExecPos = Position::query()->where('code', 'HR-EXEC-POS')->firstOrFail();
        $this->assertSame('GENERAL_MANAGER', $hrExecPos->position_type);
    }

    public function test_scoped_roles_resolve_and_match_canonical_rules(): void
    {
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $techSw = OrganizationalUnit::query()->where('code', 'TECH-SW')->firstOrFail();
        $authOffice = OrganizationalUnit::query()->where('code', 'AUTH-OFFICE')->firstOrFail();

        // 1. Tech Exec
        $techExecUser = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $this->assertTrue($techExecUser->hasScopedRole('EXECUTIVE_MANAGEMENT', $tech->id));
        $this->assertEquals([$tech->id], $techExecUser->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'));

        // 2. Tech Director
        $techDirectorUser = User::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $this->assertTrue($techDirectorUser->hasScopedRole('DIRECTOR', $tech->id));
        $this->assertEquals([$tech->id], $techDirectorUser->scopedRoleUnitIds('DIRECTOR'));

        // 3. Tech Deputy
        $techDeputyUser = User::query()->where('email', 'tech.deputy@example.test')->firstOrFail();
        $this->assertTrue($techDeputyUser->hasScopedRole('DEPUTY_DIRECTOR', $tech->id));
        $this->assertEquals([$tech->id], $techDeputyUser->scopedRoleUnitIds('DEPUTY_DIRECTOR'));

        // 4. Employee A
        $employeeUser = User::query()->where('email', 'employee.a@example.test')->firstOrFail();
        $this->assertTrue($employeeUser->hasScopedRole('EMPLOYEE', $techSw->id));
        $this->assertEquals([$techSw->id], $employeeUser->scopedRoleUnitIds('EMPLOYEE'));

        // 5. System Admin
        $adminUser = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $this->assertTrue($adminUser->hasScopedRole('SYSTEM_ADMIN'));
        $this->assertNull($adminUser->employee_id);

        // 6. Organization Authority
        $authority = OrganizationAuthority::query()->where('status', 'ACTIVE')->firstOrFail();
        $this->assertSame('DIRECTOR_GENERAL', $authority->authority_type);
        $this->assertSame($authOffice->id, $authority->authority_office_unit_id);
    }
}
