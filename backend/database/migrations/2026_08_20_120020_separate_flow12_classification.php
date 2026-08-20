<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_decision_followups', function (Blueprint $table): void {
            $table->string('classification', 32)->default('UNCLASSIFIED')->after('status')->index('mdf_classification_idx');
        });

        DB::table('meeting_decision_followups')
            ->where('status', 'NO_ACTION_REQUIRED')
            ->update(['classification' => 'NO_ACTION_REQUIRED', 'status' => 'CLOSED']);

        DB::table('meeting_decision_followups')
            ->where('status', 'ACTIVE')
            ->update(['classification' => 'ACTION_REQUIRED', 'status' => 'OPEN']);
    }

    public function down(): void
    {
        Schema::table('meeting_decision_followups', function (Blueprint $table): void {
            $table->dropIndex('mdf_classification_idx');
            $table->dropColumn('classification');
        });
    }
};
