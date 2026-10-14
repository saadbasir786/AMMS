<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_pairing_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terminal_id')->constrained('terminals', 'id', 'tpc_terminal_fk')->restrictOnDelete();
            $table->string('code_hash', 64)->unique('terminal_pairing_codes_hash_unique');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users', 'id', 'tpc_issuer_fk')->restrictOnDelete();
            $table->timestamps();
            $table->index(['terminal_id', 'expires_at', 'consumed_at'], 'terminal_pairing_active_idx');
            $table->index('expires_at', 'terminal_pairing_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_pairing_codes');
    }
};
