<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\Meetings\BackfillMeetingScheduleVersions;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
$authorityUser = User::where('email', 'high.authority@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('id', 1)->firstOrFail();
$finance = OrganizationalUnit::where('id', 2)->firstOrFail();
$hr = OrganizationalUnit::where('id', 3)->firstOrFail();
$office = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
$authority = OrganizationAuthority::where('authority_office_unit_id', $office->id)->firstOrFail();
$requestNumber = 'FLOW07-CONFLICT-BROWSER-001';
$meetingNumber = 'FLOW07-CONFLICT-BROWSER-MEETING';

$existing = MeetingRequest::where('request_number', $requestNumber)->first();
if ($existing?->meeting) {
    $meeting = $existing->meeting;
} else {
    $meeting = DB::transaction(function () use ($secretary, $authorityUser, $tech, $finance, $hr, $office, $authority, $requestNumber, $meetingNumber): Meeting {
        $request = MeetingRequest::create([
            'request_number' => $requestNumber,
            'flow_type' => 'HIGH_AUTHORITY_INITIATED',
            'created_by' => $secretary->id,
            'subject' => 'آزمون تداخل برنامه مقام در تغییر زمان جلسه',
            'purpose' => 'COORDINATION',
            'description' => 'Fixture مستقل برای بررسی conflict guard از UI/API احراز‌شده.',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
            'coordinator_id' => $secretary->id,
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => $meetingNumber,
            'title' => $request->subject,
            'description' => $request->description,
            'organizing_unit_id' => $office->id,
            'coordinator_id' => $secretary->id,
            'chairperson_employee_id' => $authorityUser->employee_id,
            'meeting_date' => '2026-09-05',
            'start_time' => '15:00',
            'end_time' => '16:00',
            'location' => 'تالار conflict fixture',
            'status' => 'SCHEDULED',
        ]);
        foreach ([$tech, $finance, $hr] as $formalUnit) {
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $formalUnit->id, 'role' => 'PARTICIPATING']);
            $employee = Employee::where('organizational_unit_id', $formalUnit->id)->firstOrFail();
            MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $formalUnit->id, 'role' => 'PARTICIPATING']);
        }
        MeetingAuthorityContext::create([
            'meeting_id' => $meeting->id,
            'organization_authority_id' => $authority->id,
            'authority_type' => $authority->authority_type,
            'authority_position_id' => $authority->authority_position_id,
            'authority_employee_id' => $authorityUser->employee_id,
            'authority_office_unit_id' => $office->id,
        ]);
        return $meeting;
    });
}

(new BackfillMeetingScheduleVersions)();
echo json_encode(['request_id' => $meeting->meeting_request_id, 'meeting_id' => $meeting->id, 'meeting_number' => $meeting->meeting_number, 'conflict_target' => ['meeting_id' => 4, 'date' => '2026-09-05', 'start' => '09:00', 'end' => '10:30', 'authority_employee_id' => 9]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
