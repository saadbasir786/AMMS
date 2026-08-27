<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('meetings')
            ->whereNull('current_schedule_version_id')
            ->orderBy('id')
            ->eachById(function (object $meeting): void {
                if (!filled($meeting->meeting_date)
                    || !filled($meeting->start_time)
                    || !filled($meeting->end_time)
                    || trim((string) $meeting->location) === '') {
                    throw new RuntimeException('MISSING_MEETING_SCHEDULE_FIELDS: '.$meeting->id);
                }

                $versionId = DB::table('meeting_schedule_versions')
                    ->where('meeting_id', $meeting->id)
                    ->where('version_number', 1)
                    ->value('id');

                if (!$versionId) {
                    $versionId = DB::table('meeting_schedule_versions')->insertGetId([
                        'meeting_id' => $meeting->id,
                        'version_number' => 1,
                        'meeting_date' => $meeting->meeting_date,
                        'start_time' => $meeting->start_time,
                        'end_time' => $meeting->end_time,
                        'location' => $meeting->location,
                        'source_reschedule_case_id' => null,
                        'created_by_user_id' => $meeting->coordinator_id,
                        'created_at' => now(),
                    ]);
                }

                DB::table('meetings')
                    ->where('id', $meeting->id)
                    ->update(['current_schedule_version_id' => $versionId]);
            });
    }

    public function down(): void
    {
        // Snapshot data is retained for audit and execution history.
    }
};
