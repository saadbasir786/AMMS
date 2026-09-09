<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestListingPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_legacy_request_lists_validate_pagination_and_return_standard_meta(): void
    {
        $techExecutive = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $financeExecutive = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $systemAdmin = User::where('email', 'system.admin@example.test')->firstOrFail();

        $this->actingAs($techExecutive, 'sanctum')
            ->getJson('/api/meeting-requests?page=1&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5);

        $this->actingAs($financeExecutive, 'sanctum')
            ->getJson('/api/incoming-meeting-requests?page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($systemAdmin, 'sanctum')
            ->getJson('/api/approvals?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }
}
