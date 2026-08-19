<?php

declare(strict_types=1);

use App\Actions\Biometrics\CancelBiometricEnrollment;
use App\Actions\Terminals\CloseTerminalMeetingSession;
use App\Models\BiometricEnrollmentSession;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingAttendanceRecord;
use App\Models\User;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

TerminalMeetingSession::query()->where('status', 'ACTIVE')->get()->each(function (TerminalMeetingSession $session): void {
    app(CloseTerminalMeetingSession::class)->execute($session, 'FLOW10A final race-fixture cleanup');
});
$cleanupActor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
BiometricEnrollmentSession::query()->active()->get()->each(function (BiometricEnrollmentSession $session) use ($cleanupActor): void {
    app(CancelBiometricEnrollment::class)->execute($cleanupActor, $session, 'FLOW10A final race-fixture cleanup');
});

$artifact = [
    'artifact' => 'FLOW10A_FINAL_MARIADB_INVARIANTS',
    'database' => DB::connection()->getDatabaseName(),
    'active_terminal_sessions' => TerminalMeetingSession::query()->where('status', 'ACTIVE')->count(),
    'active_enrollment_sessions' => BiometricEnrollmentSession::query()->active()->count(),
    'active_terminals' => Terminal::query()->where('status', 'ACTIVE')->count(),
    'active_template_rows' => EmployeeBiometricTemplate::query()->where('status', 'ACTIVE')->count(),
    'revoked_or_superseded_template_history' => EmployeeBiometricTemplate::query()->whereIn('status', ['REVOKED', 'SUPERSEDED'])->count(),
    'attendance_rows' => MeetingAttendanceRecord::query()->count(),
    'duplicate_meeting_employee_identities' => DB::table('meeting_attendance_records')->select('meeting_id', 'employee_id')->groupBy('meeting_id', 'employee_id')->havingRaw('COUNT(*) > 1')->count(),
    'plaintext_probe_columns_present' => collect(['probe_data', 'raw_image', 'sensor_frame'])->filter(fn (string $column): bool => Schema::hasColumn('meeting_attendance_records', $column))->values()->all(),
    'meeting_status_counts' => Meeting::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all(),
];

$path = dirname(__DIR__, 2).'/flow10a_final_mariadb_invariants.json';
file_put_contents($path, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
