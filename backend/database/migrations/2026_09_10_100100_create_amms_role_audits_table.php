<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amms_role_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['role_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amms_role_audits');
    }
};
