<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
$result = [
    'generated_at' => now()->toIso8601String(),
    'meeting_count' => Meeting::count(),
    'request_meeting_duplicates' => Meeting::query()->selectRaw('meeting_request_id, COUNT(*) as count')->groupBy('meeting_request_id')->having('count','>',1)->count(),
    'meetings' => Meeting::with(['request','participants','participantVersions.members','activeParticipantChangeCase'])->orderBy('id')->get()->map(fn ($meeting) => [
        'id' => $meeting->id,
        'meeting_number' => $meeting->meeting_number,
        'meeting_request_id' => $meeting->meeting_request_id,
        'request_status' => $meeting->request?->status,
        'status' => $meeting->status,
        'participant_count' => $meeting->participants->count(),
        'participant_version_count' => $meeting->participantVersions->count(),
        'current_participant_version_id' => $meeting->current_participant_version_id,
        'current_participant_version_number' => $meeting->currentParticipantVersion?->version_number,
        'current_version_member_count' => $meeting->currentParticipantVersion?->members->count(),
        'active_case_id' => $meeting->active_participant_change_case_id,
        'active_case_status' => $meeting->activeParticipantChangeCase?->status,
    ])->values()->all(),
    'change_cases' => MeetingParticipantChangeCase::query()->select(['id','meeting_id','owner_type','owner_unit_id','status','baseline_participant_version_id','baseline_schedule_version_id','created_at','submitted_at','confirmed_at'])->orderBy('id')->get()->all(),
];
file_put_contents(__DIR__.'/../../flow08_final_invariants.json', json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['meeting_count'=>$result['meeting_count'],'request_meeting_duplicates'=>$result['request_meeting_duplicates'],'change_case_count'=>count($result['change_cases'])], JSON_UNESCAPED_UNICODE), PHP_EOL;
