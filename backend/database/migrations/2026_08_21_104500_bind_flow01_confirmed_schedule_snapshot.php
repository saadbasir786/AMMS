<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->date('scheduled_date')->nullable()->after('proposed_location');
            $table->time('scheduled_start_time')->nullable()->after('scheduled_date');
            $table->unsignedInteger('scheduled_duration_minutes')->nullable()->after('scheduled_start_time');
            $table->string('scheduled_location')->nullable()->after('scheduled_duration_minutes');
            $table->foreignId('scheduled_chairperson_employee_id')
                ->nullable()
                ->after('scheduled_location')
                ->constrained('employees')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_chairperson_employee_id');
            $table->dropColumn(['scheduled_date','scheduled_start_time','scheduled_duration_minutes','scheduled_location']);
        });
    }
};
