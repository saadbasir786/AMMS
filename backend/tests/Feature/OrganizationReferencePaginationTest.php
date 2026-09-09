<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationReferencePaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_organization_and_unit_lists_return_paginated_metadata(): void
    {
        $admin = User::where('email', 'system.admin@example.test')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/organizational-units?page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/organizations?page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_admin_reference_lists_reject_invalid_page_size(): void
    {
        $admin = User::where('email', 'system.admin@example.test')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/organizations?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }
}
