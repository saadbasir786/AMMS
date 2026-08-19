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

$actor = User::where('email', 'peer.coordinator@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
$hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
$requestNumber = 'FLOW07-COUNTER-BROWSER-001';
$meetingNumber = 'FLOW07-COUNTER-BROWSER-MEETING';

$existing = MeetingRequest::where('request_number', $requestNumber)->first();
if ($existing?->meeting) {
    $meeting = $existing->meeting;
} else {
    $meeting = DB::transaction(function () use ($actor, $tech, $finance, $hr, $requestNumber, $meetingNumber): Meeting {
        $request = MeetingRequest::create([
            'request_number' => $requestNumber,
            'flow_type' => 'MULTI_DIRECTORATE',
            'created_by' => $actor->id,
            'subject' => 'آزمون مرورگر پیشنهاد بدیل تغییر زمان جلسهٔ چند ریاستی',
            'purpose' => 'COORDINATION',
            'description' => 'Fixture مستقل برای بررسی Peer Counter-Proposal در FLOW-07.',
            'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING',
            'coordinator_id' => $actor->id,
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => $meetingNumber,
            'title' => $request->subject,
            'description' => $request->description,
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $actor->id,
            'chairperson_employee_id' => Employee::where('organizational_unit_id', $tech->id)->firstOrFail()->id,
            'meeting_date' => now()->addDays(16)->format('Y-m-d'),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'location' => 'تالار پیشنهادهای مشترک',
            'status' => 'SCHEDULED',
        ]);
        foreach ([$tech, $finance, $hr] as $formalUnit) {
            MeetingUnit::create([
                'meeting_id' => $meeting->id,
                'organizational_unit_id' => $formalUnit->id,
                'role' => 'PARTICIPATING',
            ]);
            $employee = Employee::where('organizational_unit_id', $formalUnit->id)->firstOrFail();
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $employee->id,
                'organizational_unit_id' => $formalUnit->id,
                'role' => 'PARTICIPATING',
            ]);
        }
        $actorEmployee = $actor->employee;
        if ($actorEmployee && ! MeetingParticipant::where('meeting_id', $meeting->id)->where('employee_id', $actorEmployee->id)->exists()) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $actorEmployee->id,
                'organizational_unit_id' => $tech->id,
                'role' => 'PARTICIPATING',
            ]);
        }
        return $meeting;
    });
}

(new BackfillMeetingScheduleVersions)();
echo json_encode([
    'request_id' => $meeting->meeting_request_id,
    'meeting_id' => $meeting->id,
    'meeting_number' => $meeting->meeting_number,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
