<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->foreignId('confirmed_chairperson_employee_id')
                ->nullable()
                ->after('proposed_location')
                ->constrained('employees')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_chairperson_employee_id');
        });
    }
};
