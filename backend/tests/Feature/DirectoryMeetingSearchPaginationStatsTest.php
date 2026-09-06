<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryMeetingSearchPaginationStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_meetings_endpoint_returns_authoritative_stats_and_supports_server_pagination_and_search(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();

        $req1 = MeetingRequest::create([
            'request_number' => 'MR-TEST-001',
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $techExec->id,
            'subject' => 'جلسه تست فیلترها و آمار ۱',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
        ]);
        $req2 = MeetingRequest::create([
            'request_number' => 'MR-TEST-002',
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $techExec->id,
            'subject' => 'جلسه تست فیلترها و آمار ۲',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
        ]);
        $req3 = MeetingRequest::create([
            'request_number' => 'MR-TEST-003',
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $techExec->id,
            'subject' => 'جلسه تست فیلترها و آمار ۳',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
        ]);

        // 1. Upcoming Scheduled meeting
        $m1 = Meeting::create([
            'meeting_request_id' => $req1->id,
            'meeting_number' => 'MEET-TEST-UPCOMING-001',
            'title' => 'جلسه توسعه سیستم اداری',
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $techExec->id,
            'chairperson_employee_id' => $techDirector->employee_id,
            'meeting_date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => 'تالار جلسات تکنالوژی',
            'status' => 'SCHEDULED',
        ]);
        MeetingUnit::create(['meeting_id' => $m1->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);

        // 2. Completed meeting
        $m2 = Meeting::create([
            'meeting_request_id' => $req2->id,
            'meeting_number' => 'MEET-TEST-COMPLETED-002',
            'title' => 'جلسه بررسی بودجه مالی',
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $techExec->id,
            'chairperson_employee_id' => $techDirector->employee_id,
            'meeting_date' => now()->subDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'اتاق کنفرانس عمومی',
            'status' => 'COMPLETED',
        ]);
        MeetingUnit::create(['meeting_id' => $m2->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);

        // 3. Postponed meeting
        $m3 = Meeting::create([
            'meeting_request_id' => $req3->id,
            'meeting_number' => 'MEET-TEST-POSTPONED-003',
            'title' => 'جلسه ارزیابی عملکرد سالانه',
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $techExec->id,
            'chairperson_employee_id' => $techDirector->employee_id,
            'meeting_date' => now()->addDays(12)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'location' => 'تالار VIP',
            'status' => 'POSTPONED',
        ]);
        MeetingUnit::create(['meeting_id' => $m3->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);

        // Test 1: Fetch list and assert authoritative backend KPI stats
        $response = $this->actingAs($techDirector, 'sanctum')->getJson('/api/meetings');
        $response->assertOk();
        $response->assertJsonPath('stats.total', 3);
        $response->assertJsonPath('stats.upcoming', 1);
        $response->assertJsonPath('stats.completed', 1);
        $response->assertJsonPath('meta.total', 3);

        // Test 2: Server-side search by title
        $searchRes = $this->actingAs($techDirector, 'sanctum')->getJson('/api/meetings?search=بودجه');
        $searchRes->assertOk();
        $searchRes->assertJsonCount(1, 'data');
        $searchRes->assertJsonPath('data.0.meeting_number', 'MEET-TEST-COMPLETED-002');
        // Stats remain authoritative over whole scope
        $searchRes->assertJsonPath('stats.total', 3);

        // Test 3: Server-side filter by status
        $statusRes = $this->actingAs($techDirector, 'sanctum')->getJson('/api/meetings?status=SCHEDULED');
        $statusRes->assertOk();
        $statusRes->assertJsonCount(1, 'data');
        $statusRes->assertJsonPath('data.0.meeting_number', 'MEET-TEST-UPCOMING-001');

        // Test 4: Server-side pagination bounds and metadata
        $pageRes = $this->actingAs($techDirector, 'sanctum')->getJson('/api/meetings?page=1&per_page=2');
        $pageRes->assertOk();
        $pageRes->assertJsonCount(2, 'data');
        $pageRes->assertJsonPath('meta.current_page', 1);
        $pageRes->assertJsonPath('meta.last_page', 2);
        $pageRes->assertJsonPath('meta.per_page', 2);
        $pageRes->assertJsonPath('meta.total', 3);
    }
}
