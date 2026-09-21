<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location_details')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('schedule_conflict_locks', function (Blueprint $table): void {
            $table->date('schedule_date')->primary();
            $table->timestamp('created_at')->useCurrent();
        });

        foreach (['meetings', 'meeting_schedule_versions', 'meeting_schedule_proposals', 'meeting_reschedule_proposals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('venue_type', 30)->default('EXTERNAL_LOCATION')->index();
                $table->foreignId('venue_id')->nullable()->constrained('meeting_venues')->nullOnDelete();
                $table->json('venue_snapshot')->nullable();
            });
        }

        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->string('scheduled_venue_type', 30)->default('EXTERNAL_LOCATION')->index();
            $table->foreignId('scheduled_venue_id')->nullable()->constrained('meeting_venues')->nullOnDelete();
            $table->json('scheduled_venue_snapshot')->nullable();
        });

        DB::table('meetings')->whereNull('venue_type')->update(['venue_type' => 'EXTERNAL_LOCATION']);
        DB::table('meeting_schedule_versions')->whereNull('venue_type')->update(['venue_type' => 'EXTERNAL_LOCATION']);
        DB::table('meeting_schedule_proposals')->whereNull('venue_type')->update(['venue_type' => 'EXTERNAL_LOCATION']);
        DB::table('meeting_reschedule_proposals')->whereNull('venue_type')->update(['venue_type' => 'EXTERNAL_LOCATION']);
        DB::table('meeting_requests')->whereNull('scheduled_venue_type')->update(['scheduled_venue_type' => 'EXTERNAL_LOCATION']);

        foreach (['meetings', 'meeting_schedule_versions', 'meeting_schedule_proposals', 'meeting_reschedule_proposals'] as $tableName) {
            DB::table($tableName)->whereNull('venue_snapshot')->whereNotNull('location')->orderBy('id')->chunkById(200, function ($rows) use ($tableName): void {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update([
                        'venue_snapshot' => json_encode([
                            'type' => 'EXTERNAL_LOCATION',
                            'location' => $row->location,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropForeign(['scheduled_venue_id']);
            $table->dropColumn(['scheduled_venue_type', 'scheduled_venue_id', 'scheduled_venue_snapshot']);
        });

        foreach (['meeting_reschedule_proposals', 'meeting_schedule_proposals', 'meeting_schedule_versions', 'meetings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['venue_id']);
                $table->dropColumn(['venue_type', 'venue_id', 'venue_snapshot']);
            });
        }

        Schema::dropIfExists('schedule_conflict_locks');
        Schema::dropIfExists('meeting_venues');
    }
};
