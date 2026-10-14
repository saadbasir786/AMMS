<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_biometric_templates', function (Blueprint $table): void {
            $table->foreignId('terminal_meeting_session_id')
                ->nullable()
                ->after('source_terminal_id')
                ->constrained('terminal_meeting_sessions')
                ->restrictOnDelete();
            $table->foreignId('enrolled_by_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('employee_biometric_templates')->whereNull('enrolled_by_user_id')->exists()) {
            throw new RuntimeException('Cannot roll back meeting-session biometric templates until every row has an enrolled_by_user_id.');
        }

        Schema::table('employee_biometric_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terminal_meeting_session_id');
            $table->foreignId('enrolled_by_user_id')->nullable(false)->change();
        });
    }
};
