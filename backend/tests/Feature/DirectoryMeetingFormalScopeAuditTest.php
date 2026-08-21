<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DirectoryMeetingFormalScopeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_formal_participating_director_sees_meeting_in_directory_list(): void
    {
        $tech = $this->unit('TECH');
        $finance = $this->unit('FIN');
        $techExec = $this->user('tech.exec@example.test');
        $financeDirector = $this->user('finance.director@example.test');
        $this->grant($financeDirector, 'meeting.view');

        $request = MeetingRequest::create([
            'request_number' => 'AUD-DIR-FORMAL-001',
            'flow_type' => 'INTER_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $techExec->id,
            'subject' => 'Directory formal unit visibility',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => 'AUD-DIR-MEETING-001',
            'title' => 'Directory formal unit visibility',
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $techExec->id,
            'chairperson_employee_id' => $this->user('tech.director@example.test')->employee_id,
            'meeting_date' => now()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'Audit Hall',
            'status' => 'SCHEDULED',
        ]);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING']);

        $this->assertTrue(Gate::forUser($financeDirector)->allows('view', $meeting->fresh(['request', 'units', 'participants', 'authorityContext'])));

        Sanctum::actingAs($financeDirector, ['*']);
        $this->getJson('/api/meetings')
            ->assertOk()
            ->assertJsonFragment(['meeting_number' => 'AUD-DIR-MEETING-001']);
    }

    public function test_system_admin_has_no_business_meeting_directory_bypass(): void
    {
        $tech = $this->unit('TECH');
        $techExec = $this->user('tech.exec@example.test');
        $systemAdmin = $this->user('system.admin@example.test');
        $this->grant($systemAdmin, 'meeting.view');

        $request = MeetingRequest::create([
            'request_number' => 'AUD-DIR-SYSADMIN-001',
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $techExec->id,
            'subject' => 'System admin directory boundary',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => 'AUD-DIR-SYSADMIN-MEETING-001',
            'title' => 'System admin directory boundary',
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $techExec->id,
            'chairperson_employee_id' => $this->user('tech.director@example.test')->employee_id,
            'meeting_date' => now()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'Audit Hall',
            'status' => 'SCHEDULED',
        ]);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);

        Sanctum::actingAs($systemAdmin, ['*']);
        $this->getJson('/api/meetings')
            ->assertOk()
            ->assertJsonMissing(['meeting_number' => 'AUD-DIR-SYSADMIN-MEETING-001']);
    }

    private function grant(User $user, string $permission): void
    {
        $model = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        $user->givePermissionTo($model);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function unit(string $code): OrganizationalUnit
    {
        return OrganizationalUnit::where('code', $code)->firstOrFail();
    }
}
