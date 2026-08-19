<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amms_role_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id','role_id','organizational_unit_id'], 'amms_role_scopes_unique');
            $table->index(['organizational_unit_id','role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amms_role_scopes');
    }
};
