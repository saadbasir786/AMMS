<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AmmsPermissionRegistry;
use App\Support\AmmsRole;
use App\Support\OrganizationalUnitType;
use App\Support\PositionType;
use App\Support\RolePositionCompatibility;
use App\Support\SpecialCapability;
use PHPUnit\Framework\TestCase;

final class AmmsRegistryTest extends TestCase
{
    public function test_unit_taxonomy_and_formal_governance_are_consistent(): void
    {
        $this->assertSame(
            ['GENERAL_PRESIDENCY', 'DEPUTY_PRESIDENCY', 'GENERAL_DIRECTORATE', 'DIRECTORATE', 'ATTACHED_AGENCY', 'DEPUTY_DIRECTORATE'],
            OrganizationalUnitType::formalGovernance(),
        );

        foreach (OrganizationalUnitType::formalGovernance() as $type) {
            $this->assertContains($type, OrganizationalUnitType::all());
            $this->assertTrue(OrganizationalUnitType::isFormalGovernance($type));
        }
        $this->assertTrue(OrganizationalUnitType::isValid('DEPARTMENT'));
    }

    public function test_position_taxonomy_and_seniority_are_stable(): void
    {
        $this->assertSame(100, PositionType::seniority()['DIRECTOR']);
        $this->assertSame(90, PositionType::seniority()['DEPUTY_DIRECTOR']);
        $this->assertSame(70, PositionType::seniority()['GENERAL_MANAGER']);
        $this->assertSame(70, PositionType::seniority()['EXPERT']);
        $this->assertSame(50, PositionType::seniority()['EMPLOYEE']);
        $this->assertContains('EXECUTIVE_SECRETARY', PositionType::all());
    }

    public function test_roles_and_role_position_compatibility_are_canonical(): void
    {
        $this->assertSame([AmmsRole::SYSTEM_ADMIN], AmmsRole::unscopedRoles());
        $this->assertNotContains('MEETING_COORDINATOR', AmmsRole::all());
        $this->assertTrue(RolePositionCompatibility::matches(AmmsRole::EXECUTIVE_MANAGEMENT, PositionType::GENERAL_MANAGER));
        $this->assertFalse(RolePositionCompatibility::matches(AmmsRole::DIRECTOR, PositionType::GENERAL_MANAGER));
        $this->assertTrue(RolePositionCompatibility::matches(AmmsRole::EMPLOYEE, PositionType::EXPERT));
    }

    public function test_all_role_grants_and_special_capability_permissions_are_canonical(): void
    {
        $permissions = AmmsPermissionRegistry::allPermissions();
        $this->assertSame($permissions, array_values(array_unique($permissions)));

        foreach (AmmsPermissionRegistry::rolePermissions() as $role => $grants) {
            $this->assertContains($role, AmmsRole::all());
            foreach ($grants as $permission) {
                $this->assertContains($permission, $permissions);
            }
        }

        foreach (SpecialCapability::all() as $capability) {
            foreach (SpecialCapability::permissions($capability) as $permission) {
                $this->assertContains($permission, $permissions);
            }
        }
    }
}
