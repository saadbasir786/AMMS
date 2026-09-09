<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmmsCapabilityScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationalUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_can_create_directorate_and_sub_unit(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();

        // 1. Create a root directorate (independent, no parent)
        $directorateRes = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'name' => 'ریاست تضمین کیفیت و تفتیش',
            'code' => 'QA',
            'unit_type' => 'DIRECTORATE',
            'status' => 'ACTIVE',
        ])->assertCreated()
          ->assertJsonPath('data.name', 'ریاست تضمین کیفیت و تفتیش')
          ->assertJsonPath('data.code', 'QA')
          ->assertJsonPath('data.unit_type', 'DIRECTORATE')
          ->assertJsonPath('data.parent_id', null);

        $directorateId = $directorateRes->json('data.id');

        // 2. Create a child sub-unit under this directorate
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'parent_id' => $directorateId,
            'name' => 'مدیریت نظارت و تفتیش',
            'code' => 'QA-AUDIT',
            'unit_type' => 'MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()
          ->assertJsonPath('data.name', 'مدیریت نظارت و تفتیش')
          ->assertJsonPath('data.parent_id', $directorateId);
    }

    public function test_system_admin_can_update_unit(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $technology = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/organizational-units/{$technology->id}", [
            'name' => 'ریاست تکنالوژی معلوماتی و سیستم‌ها',
            'code' => 'TECH-SYS',
            'unit_type' => 'DIRECTORATE',
            'status' => 'ACTIVE',
        ])->assertOk()
          ->assertJsonPath('data.name', 'ریاست تکنالوژی معلوماتی و سیستم‌ها')
          ->assertJsonPath('data.code', 'TECH-SYS');

        $this->assertDatabaseHas('organizational_units', [
            'id' => $technology->id,
            'name' => 'ریاست تکنالوژی معلوماتی و سیستم‌ها',
            'code' => 'TECH-SYS',
        ]);
    }

    public function test_sibling_units_can_have_same_name_under_different_parents(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::query()->where('code', 'FIN')->firstOrFail();

        // 1. Create "مدیریت اجرائیه" under TECH
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'مدیریت اجرائیه مشترک',
            'code' => 'TECH-EXEC-COMMON',
            'unit_type' => 'MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()
          ->assertJsonPath('data.name', 'مدیریت اجرائیه مشترک')
          ->assertJsonPath('data.parent_id', $tech->id);

        // 2. Create another "مدیریت اجرائیه" with same name under FINANCE -> Must Succeed!
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $finance->organization_id,
            'parent_id' => $finance->id,
            'name' => 'مدیریت اجرائیه مشترک',
            'code' => 'FIN-EXEC-COMMON',
            'unit_type' => 'MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()
          ->assertJsonPath('data.name', 'مدیریت اجرائیه مشترک')
          ->assertJsonPath('data.parent_id', $finance->id);
    }

    public function test_duplicate_name_under_same_parent_is_prevented(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        // Create initial unit
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'مدیریت اسناد و ارتباطات',
            'code' => 'TECH-DOCS',
            'unit_type' => 'MANAGEMENT',
        ])->assertCreated();

        // Attempt duplicate under same parent -> Must be rejected with 422
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'مدیریت اسناد و ارتباطات',
            'code' => 'TECH-DOCS-2',
            'unit_type' => 'MANAGEMENT',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['name']);
    }

    public function test_cross_organization_parent_assignment_is_prevented(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();

        // Create second organization
        $org2 = Organization::create([
            'name' => 'وزارت مخابرات و تکنالوژی',
            'code' => 'MCIT',
            'status' => 'ACTIVE',
        ]);

        $parentInOrg2 = OrganizationalUnit::create([
            'organization_id' => $org2->id,
            'name' => 'ریاست عمومی مخابرات',
            'code' => 'MCIT-ROOT',
            'unit_type' => 'GENERAL_DIRECTORATE',
            'status' => 'ACTIVE',
        ]);

        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        // Try to assign $parentInOrg2 as parent to a unit in $tech->organization_id
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $parentInOrg2->id,
            'name' => 'واحد بین سازمانی غیرمجاز',
            'code' => 'CROSS-ORG-FAIL',
            'unit_type' => 'MANAGEMENT',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'واحد مافوق باید متعلق به همین سازمان باشد.');
    }

    public function test_charter_cases_1_to_4_technology_directorate_substructures(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        // CASE 1: Technology Directorate -> Executive General Management (without an Amirate parent)
        $case1 = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'مدیریت عمومی اجرائیه تکنالوژی',
            'code' => 'TECH-GM-EXEC',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('organizational_units', [
            'id' => $case1,
            'parent_id' => $tech->id,
            'unit_type' => 'GENERAL_MANAGEMENT',
        ]);

        // CASE 2: Technology Directorate -> Network and Systems Amirate -> Network General Management -> Network Management
        $case2Amir = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'آمریت شبکه و سیستم‌ها',
            'code' => 'TECH-AMIR-NET',
            'unit_type' => 'AMIRIATE',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $case2GM = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $case2Amir,
            'name' => 'مدیریت عمومی شبکه',
            'code' => 'TECH-GM-NET-CASE2',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $case2Mgt = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $case2GM,
            'name' => 'مدیریت عملیاتی شبکه',
            'code' => 'TECH-MGT-NET',
            'unit_type' => 'MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('organizational_units', [
            'id' => $case2Mgt,
            'parent_id' => $case2GM,
            'unit_type' => 'MANAGEMENT',
        ]);

        // CASE 3: Technology Directorate -> Software Amirate -> Software General Management
        $case3Amir = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'آمریت نرم‌افزار',
            'code' => 'TECH-AMIR-SW-C3',
            'unit_type' => 'AMIRIATE',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $case3GM = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $case3Amir,
            'name' => 'مدیریت عمومی انکشاف نرم‌افزار',
            'code' => 'TECH-GM-SW-C3',
            'unit_type' => 'GENERAL_MANAGEMENT',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('organizational_units', [
            'id' => $case3GM,
            'parent_id' => $case3Amir,
            'unit_type' => 'GENERAL_MANAGEMENT',
        ]);

        // CASE 4: Technology Directorate -> Technology Deputy Directorate
        $case4Deputy = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $tech->organization_id,
            'parent_id' => $tech->id,
            'name' => 'معاونیت ریاست تکنالوژی',
            'code' => 'TECH-DEP-DIR-C4',
            'unit_type' => 'DEPUTY_DIRECTORATE',
            'status' => 'ACTIVE',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('organizational_units', [
            'id' => $case4Deputy,
            'parent_id' => $tech->id,
            'unit_type' => 'DEPUTY_DIRECTORATE',
        ]);
    }

    public function test_expert_and_general_manager_have_equal_seniority_without_permissions(): void
    {
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        $generalManagerPos = Position::create([
            'organizational_unit_id' => $tech->id,
            'title' => 'مدیر عمومی شبکه',
            'code' => 'TECH-GM-NET',
            'position_type' => 'GENERAL_MANAGER',
            'status' => 'ACTIVE',
        ]);

        $expertPos = Position::create([
            'organizational_unit_id' => $tech->id,
            'title' => 'کارشناس ارشد زیرساخت',
            'code' => 'TECH-EXP-INFRA',
            'position_type' => 'EXPERT',
            'status' => 'ACTIVE',
        ]);

        // Both must have identical seniority level of 70
        $this->assertSame(70, $generalManagerPos->seniority_level);
        $this->assertSame(70, $expertPos->seniority_level);

        // Seniority does NOT grant RBAC permissions
        $user = User::factory()->create(['status' => 'ACTIVE']);
        $employee = Employee::create([
            'organizational_unit_id' => $tech->id,
            'position_id' => $expertPos->id,
            'first_name' => 'احمد',
            'last_name' => 'کارشناس',
            'employee_number' => 'EMP-EXP-999',
            'status' => 'ACTIVE',
        ]);
        $user->update(['employee_id' => $employee->id]);

        $this->assertFalse($user->hasPermissionTo('meeting-request.approve'));
        $this->assertFalse($user->hasRole('DIRECTOR'));
    }

    public function test_deputy_director_exercises_delegated_authority_without_permanent_director_role(): void
    {
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $directorUser = User::query()->where('email', 'tech.director@example.test')->firstOrFail();

        // Create deputy user
        $deputyUser = User::factory()->create([
            'name' => 'معاون ریاست تکنالوژی',
            'email' => 'tech.deputy.test@example.test',
            'status' => 'ACTIVE',
        ]);
        $deputyUser->assignRole('DEPUTY_DIRECTOR');

        // Deputy does NOT have DIRECTOR role
        $this->assertFalse($deputyUser->hasRole('DIRECTOR'));
        $this->assertTrue($deputyUser->hasRole('DEPUTY_DIRECTOR'));

        // Director delegates internal meeting approval to deputy
        AmmsCapabilityScope::create([
            'user_id' => $deputyUser->id,
            'capability' => 'INTERNAL_MEETING_REQUEST_APPROVAL',
            'organizational_unit_id' => $tech->id,
            'granted_by_user_id' => $directorUser->id,
            'granted_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(7),
            'grant_reason' => 'تفویض موقت به دلیل سفر کاری رئیس',
        ]);

        $this->assertTrue(
            $deputyUser->hasScopedCapability('INTERNAL_MEETING_REQUEST_APPROVAL', $tech->id)
        );

        // Deputy can act through delegation while remaining in DEPUTY_DIRECTOR role
        $this->assertFalse($deputyUser->hasRole('DIRECTOR'));
    }

    public function test_system_admin_cannot_create_hierarchy_cycle(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $technology = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $software = OrganizationalUnit::query()->where('code', 'TECH-SW')->firstOrFail();

        // 1. Cannot set unit as its own parent
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/organizational-units/{$software->id}", [
            'parent_id' => $software->id,
            'name' => $software->name,
            'code' => $software->code,
            'unit_type' => 'DEPARTMENT',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'واحد سازمانی نمی‌تواند مافوق خودش باشد.');

        // 2. Cannot set a descendant unit as parent
        $subSoftware = OrganizationalUnit::create([
            'organization_id' => $technology->organization_id,
            'parent_id' => $software->id,
            'name' => 'مدیریت توسعه وب',
            'code' => 'TECH-WEB',
            'unit_type' => 'MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/organizational-units/{$software->id}", [
            'parent_id' => $subSoftware->id,
            'name' => $software->name,
            'code' => $software->code,
            'unit_type' => 'DEPARTMENT',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'نمی‌توان یک واحد زیرمجموعه را به عنوان مافوق تعیین نمود.');
    }

    public function test_cannot_deactivate_unit_with_active_employees(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $technology = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $this->assertTrue($technology->employees()->where('status', 'ACTIVE')->exists());

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/organizational-units/{$technology->id}", [
            'name' => $technology->name,
            'code' => $technology->code,
            'unit_type' => $technology->unit_type,
            'status' => 'INACTIVE',
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_manage_units(): void
    {
        $executive = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();

        $this->actingAs($executive, 'sanctum')->postJson('/api/admin/organizational-units', [
            'name' => 'ریاست غیرمجاز',
            'code' => 'UNAUTH',
            'unit_type' => 'DIRECTORATE',
        ])->assertForbidden();

        $technology = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $this->actingAs($executive, 'sanctum')->putJson("/api/admin/organizational-units/{$technology->id}", [
            'name' => 'تغییر غیرمجاز',
            'code' => 'UNAUTH',
            'unit_type' => 'DIRECTORATE',
        ])->assertForbidden();
    }

    public function test_duplicate_root_name_in_same_organization_is_prevented(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();

        // 1. Create first root unit
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'name' => 'ریاست عمومی تفتیش و نظارت',
            'code' => 'ROOT-AUDIT-1',
            'unit_type' => 'GENERAL_DIRECTORATE',
            'parent_id' => null,
            'status' => 'ACTIVE',
        ])->assertCreated();

        // 2. Attempt duplicate root name in same organization -> Must be rejected with 422
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'name' => 'ریاست عمومی تفتیش و نظارت',
            'code' => 'ROOT-AUDIT-2',
            'unit_type' => 'GENERAL_DIRECTORATE',
            'parent_id' => null,
            'status' => 'ACTIVE',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['name']);
    }

    public function test_same_root_name_in_different_organizations_is_allowed(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();

        $org2 = Organization::create([
            'name' => 'اداره امور امارت اسلامی',
            'code' => 'AOP',
            'status' => 'ACTIVE',
        ]);

        // 1. Create root in default organization
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'name' => 'ریاست عمومی اداری و منابع',
            'code' => 'ADMIN-ORG1',
            'unit_type' => 'GENERAL_DIRECTORATE',
            'parent_id' => null,
            'status' => 'ACTIVE',
        ])->assertCreated();

        // 2. Create same root name in organization 2 -> Must Succeed
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/organizational-units', [
            'organization_id' => $org2->id,
            'name' => 'ریاست عمومی اداری و منابع',
            'code' => 'ADMIN-ORG2',
            'unit_type' => 'GENERAL_DIRECTORATE',
            'parent_id' => null,
            'status' => 'ACTIVE',
        ])->assertCreated()
          ->assertJsonPath('data.organization_id', $org2->id)
          ->assertJsonPath('data.name', 'ریاست عمومی اداری و منابع');
    }

    public function test_admin_and_directory_tree_endpoints_return_unpaginated_complete_tree(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $exec = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();

        // Admin tree endpoint
        $adminRes = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/organizational-units/tree')
            ->assertOk()
            ->assertJsonPath('success', true);

        $totalUnits = OrganizationalUnit::count();
        $this->assertSame($totalUnits, count($adminRes->json('data')));
        $this->assertSame($totalUnits, $adminRes->json('total_count'));

        // Directory tree endpoint for regular authenticated users
        $dirRes = $this->actingAs($exec, 'sanctum')->getJson('/api/organizational-units/tree')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($totalUnits, count($dirRes->json('data')));
    }
}
