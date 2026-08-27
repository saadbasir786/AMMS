<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('meeting_units')
            ->where('role', 'REQUESTER')
            ->update([
                'role' => 'REQUESTING',
                'updated_at' => now(),
            ]);

        DB::table('meeting_units')
            ->where('role', 'TARGET')
            ->update([
                'role' => 'PARTICIPATING',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Canonical meeting-side roles are retained for authorization and audit integrity.
    }
};
