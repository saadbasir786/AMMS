<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_can_manage_positions_but_cannot_deactivate_a_position_with_active_employees(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $executive = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $technologyUnitId = Position::query()->where('position_type', 'DIRECTOR')->value('organizational_unit_id');

        $this->actingAs($executive, 'sanctum')->getJson('/api/admin/positions')->assertForbidden();

        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/positions', [
            'organizational_unit_id' => $technologyUnitId,
            'title' => 'کارشناس پشتیبانی',
            'code' => 'TECH-SUPPORT',
            'position_type' => 'EMPLOYEE',
        ])->assertCreated()->assertJsonPath('data.title', 'کارشناس پشتیبانی');

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/positions/'.$created->json('data.id'), [
            'organizational_unit_id' => $technologyUnitId,
            'title' => 'کارشناس ارشد پشتیبانی',
            'code' => 'TECH-SUPPORT',
            'position_type' => 'EMPLOYEE',
            'status' => 'INACTIVE',
        ])->assertOk()->assertJsonPath('data.status', 'INACTIVE');

        $directorPosition = Position::query()->where('position_type', 'DIRECTOR')->firstOrFail();
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/positions/'.$directorPosition->id, [
            'organizational_unit_id' => $directorPosition->organizational_unit_id,
            'title' => $directorPosition->title,
            'position_type' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/positions/'.$directorPosition->id, [
            'organizational_unit_id' => $directorPosition->organizational_unit_id,
            'title' => $directorPosition->title,
            'position_type' => $directorPosition->position_type,
            'status' => 'INACTIVE',
        ])->assertStatus(422);
    }

    public function test_system_admin_can_filter_positions_and_view_employee_counts(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/positions?position_type=DIRECTOR')
            ->assertOk();

        $items = $response->json('data');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame('DIRECTOR', $item['position_type']);
            $this->assertArrayHasKey('employees_count', $item);
            $this->assertGreaterThanOrEqual(0, $item['employees_count']);
        }
    }
}
