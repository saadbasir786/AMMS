<?php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\Terminal;
use App\Models\MeetingTerminalAssignment;
use App\Models\TerminalMeetingSession;
use App\Models\TerminalAuditEvent;
use Illuminate\Support\Facades\Schema;

$terminal = Terminal::where('code', 'SVT3000-01')->first();
$meeting = Meeting::where('id', 18)->with(['request'])->first();
$payload = [
    'terminal_count' => Terminal::count(),
    'terminal_fixture' => $terminal ? ['id' => $terminal->id, 'uuid' => $terminal->uuid, 'code' => $terminal->code, 'status' => $terminal->status, 'current_session_id' => $terminal->current_session_id] : null,
    'credential_plaintext_columns' => array_values(array_intersect(['token', 'raw_token', 'secret', 'plaintext_token'], Schema::getColumnListing('terminal_credentials'))),
    'assignment_count' => MeetingTerminalAssignment::count(),
    'assigned_count' => MeetingTerminalAssignment::where('status', 'ASSIGNED')->count(),
    'stale_count' => MeetingTerminalAssignment::where('status', 'STALE')->count(),
    'active_session_count' => TerminalMeetingSession::where('status', 'ACTIVE')->count(),
    'session_count' => TerminalMeetingSession::count(),
    'meeting_18' => $meeting ? ['status' => $meeting->status, 'request_status' => $meeting->request?->status, 'current_schedule_version_id' => $meeting->current_schedule_version_id, 'current_participant_version_id' => $meeting->current_participant_version_id, 'assignment_count' => $meeting->terminalAssignments()->count()] : null,
    'request_meeting_duplicate_count' => Meeting::query()->select('meeting_request_id')->whereNotNull('meeting_request_id')->groupBy('meeting_request_id')->havingRaw('COUNT(*) > 1')->count(),
    'audit_event_count' => TerminalAuditEvent::count(),
    'audit_event_types' => TerminalAuditEvent::query()->select('action')->selectRaw('COUNT(*) as count')->groupBy('action')->orderBy('action')->get()->mapWithKeys(fn ($row) => [$row->action => (int) $row->count])->all(),
    'generated_at' => now()->toIso8601String(),
];

file_put_contents(base_path('../flow09_final_invariants.json'), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo 'flow09_final_invariants.json written'.PHP_EOL;
print_r($payload);
