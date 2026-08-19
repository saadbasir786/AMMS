<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\Meetings\BackfillMeetingScheduleVersions;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$techCoordinator = User::where('email', 'peer.coordinator@example.test')->firstOrFail();
$financeCoordinator = User::where('email', 'finance.peer.coordinator@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('id', 1)->firstOrFail();
$finance = OrganizationalUnit::where('id', 2)->firstOrFail();
$hr = OrganizationalUnit::where('id', 3)->firstOrFail();
$requestNumber = 'FLOW07-COUNTER-ACK-BROWSER-001';
$meetingNumber = 'FLOW07-COUNTER-ACK-BROWSER-MEETING';

$existing = MeetingRequest::where('request_number', $requestNumber)->first();
if ($existing?->meeting) {
    $meeting = $existing->meeting;
} else {
    $meeting = DB::transaction(function () use ($techCoordinator, $financeCoordinator, $tech, $finance, $hr, $requestNumber, $meetingNumber): Meeting {
        $request = MeetingRequest::create([
            'request_number' => $requestNumber,
            'flow_type' => 'MULTI_DIRECTORATE',
            'created_by' => $techCoordinator->id,
            'subject' => 'آزمون انتقال‌ناپذیری تأییدهای پیشنهاد peer',
            'purpose' => 'COORDINATION',
            'description' => 'Fixture مستقل برای بررسی acknowledgementهای Proposal 1 و Counter-Proposal 2.',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
            'coordinator_id' => $techCoordinator->id,
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => $meetingNumber,
            'title' => $request->subject,
            'description' => $request->description,
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $techCoordinator->id,
            'chairperson_employee_id' => Employee::where('organizational_unit_id', $tech->id)->firstOrFail()->id,
            'meeting_date' => now()->addDays(17)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => 'تالار انتقال‌ناپذیری تأییدها',
            'status' => 'SCHEDULED',
        ]);
        foreach ([$tech, $finance, $hr] as $formalUnit) {
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $formalUnit->id, 'role' => 'PARTICIPATING']);
            $employee = Employee::where('organizational_unit_id', $formalUnit->id)->firstOrFail();
            MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $formalUnit->id, 'role' => 'PARTICIPATING']);
        }
        foreach ([$techCoordinator->employee, $financeCoordinator->employee] as $employee) {
            if ($employee && ! MeetingParticipant::where('meeting_id', $meeting->id)->where('employee_id', $employee->id)->exists()) {
                MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
            }
        }
        return $meeting;
    });
}

(new BackfillMeetingScheduleVersions)();
echo json_encode(['request_id' => $meeting->meeting_request_id, 'meeting_id' => $meeting->id, 'meeting_number' => $meeting->meeting_number], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
