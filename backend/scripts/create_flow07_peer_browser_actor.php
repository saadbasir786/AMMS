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

$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$role = Role::where(['name' => 'EXECUTIVE_MANAGEMENT', 'guard_name' => 'sanctum'])->firstOrFail();
$coordinatorRole = Role::where(['name' => 'MEETING_COORDINATOR', 'guard_name' => 'sanctum'])->firstOrFail();
$employee = Employee::firstOrCreate(
    ['employee_number' => 'FLOW07-PEER-COORDINATOR'],
    ['first_name' => 'هماهنگ‌کننده', 'last_name' => 'peer', 'father_name' => '', 'organizational_unit_id' => $unit->id, 'position_id' => Position::query()->value('id'), 'email' => 'peer.coordinator@example.test', 'phone' => '', 'status' => 'ACTIVE']
);
$user = User::firstOrCreate(
    ['email' => 'peer.coordinator@example.test'],
    ['name' => 'هماهنگ‌کننده peer تکنالوژی', 'password' => Hash::make('ChangeMe!2026'), 'employee_id' => $employee->id, 'status' => 'ACTIVE']
);
$user->forceFill(['employee_id' => $employee->id, 'status' => 'ACTIVE'])->save();
$user->assignRole([$role, $coordinatorRole]);
AmmsRoleScope::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'organizational_unit_id' => $unit->id]);
AmmsRoleScope::firstOrCreate(['user_id' => $user->id, 'role_id' => $coordinatorRole->id, 'organizational_unit_id' => $unit->id]);
if ($meeting = Meeting::find(6)) {
    $meeting->forceFill(['coordinator_id' => $user->id])->save();
    $meeting->request?->forceFill(['coordinator_id' => $user->id])->save();
    MeetingParticipant::firstOrCreate(['meeting_id' => $meeting->id, 'employee_id' => $employee->id], ['organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
}
echo json_encode(['email' => $user->email, 'user_id' => $user->id, 'employee_id' => $employee->id, 'unit_id' => $unit->id], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
