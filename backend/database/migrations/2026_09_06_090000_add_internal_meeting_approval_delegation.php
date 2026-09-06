<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amms_capability_scopes', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('granted_at');
            $table->text('grant_reason')->nullable()->after('expires_at');
            $table->text('revocation_reason')->nullable()->after('revoked_at');
            $table->index(['capability', 'organizational_unit_id', 'expires_at'], 'amms_capability_expiry_scope_idx');
        });

        Permission::firstOrCreate([
            'name' => 'meeting-request.approve-delegated',
            'guard_name' => 'sanctum',
        ]);
    }

    public function down(): void
    {
        Schema::table('amms_capability_scopes', function (Blueprint $table): void {
            $table->dropIndex('amms_capability_expiry_scope_idx');
            $table->dropColumn(['expires_at', 'grant_reason', 'revocation_reason']);
        });
    }
};
