<?php

declare(strict_types=1);

use App\Support\AmmsRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amms_role_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->string('role_kind', 20);
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['role_kind', 'status']);
        });

        $now = now();
        foreach (AmmsRole::all() as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'sanctum')->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('amms_role_definitions')->insertOrIgnore([
                'role_id' => $roleId,
                'label' => AmmsRole::labels()[$roleName] ?? $roleName,
                'role_kind' => 'SYSTEM',
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amms_role_definitions');
    }
};
