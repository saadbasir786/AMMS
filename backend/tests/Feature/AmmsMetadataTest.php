<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\AmmsRole;
use App\Support\OrganizationalUnitType;
use App\Support\PositionType;
use App\Support\SpecialCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AmmsMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_requires_authentication(): void
    {
        $this->getJson('/api/meta/amms')->assertUnauthorized();
    }

    public function test_metadata_exposes_safe_current_taxonomy(): void
    {
        $user = User::create([
            'name' => 'Metadata user',
            'email' => 'metadata@example.test',
            'password' => Hash::make('ChangeMe!2026'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/meta/amms')->assertOk();

        $response->assertJsonPath('data.organizational_unit_types', OrganizationalUnitType::all());
        $response->assertJsonPath('data.formal_governance_unit_types', OrganizationalUnitType::formalGovernance());
        $response->assertJsonPath('data.position_types', PositionType::all());
        $response->assertJsonPath('data.roles', AmmsRole::all());
        $response->assertJsonPath('data.special_capabilities', SpecialCapability::all());
        $this->assertArrayNotHasKey('permissions', $response->json('data'));
    }
}
