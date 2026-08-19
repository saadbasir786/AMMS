<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

$unit = OrganizationalUnit::where('id', 2)->firstOrFail();
$role = Role::where(['name' => 'EXECUTIVE_MANAGEMENT', 'guard_name' => 'sanctum'])->firstOrFail();
$employee = Employee::firstOrCreate(
    ['employee_number' => 'FLOW07-FIN-COUNTER-COORDINATOR'],
    ['first_name' => 'هماهنگ‌کننده', 'last_name' => 'peer مالی', 'father_name' => '', 'organizational_unit_id' => $unit->id, 'position_id' => Position::query()->value('id'), 'email' => 'finance.peer.coordinator@example.test', 'phone' => '', 'status' => 'ACTIVE']
);
$employee->forceFill(['organizational_unit_id' => $unit->id, 'status' => 'ACTIVE'])->save();
$user = User::firstOrCreate(
    ['email' => 'finance.peer.coordinator@example.test'],
    ['name' => 'هماهنگ‌کننده peer مالی', 'password' => Hash::make('ChangeMe!2026'), 'employee_id' => $employee->id, 'status' => 'ACTIVE']
);
$user->forceFill(['employee_id' => $employee->id, 'status' => 'ACTIVE'])->save();
$user->assignRole($role);
AmmsRoleScope::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'organizational_unit_id' => $unit->id]);
$meeting = Meeting::where('meeting_number', 'FLOW07-COUNTER-BROWSER-MEETING')->firstOrFail();
MeetingParticipant::firstOrCreate(
    ['meeting_id' => $meeting->id, 'employee_id' => $employee->id],
    ['organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']
);
echo json_encode(['email' => $user->email, 'user_id' => $user->id, 'employee_id' => $employee->id, 'unit_id' => $unit->id, 'meeting_id' => $meeting->id], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
