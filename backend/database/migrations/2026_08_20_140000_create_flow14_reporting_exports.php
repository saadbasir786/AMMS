<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('re_uuid_uq');
            $table->foreignId('organization_id')->constrained('organizations', 'id', 're_org_fk')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users', 'id', 're_user_fk')->restrictOnDelete();
            $table->string('report_type', 64);
            $table->string('format', 8);
            $table->json('filter_payload');
            $table->json('scope_descriptor');
            $table->string('status', 16)->default('PENDING');
            $table->string('definition_version', 64);
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status'], 're_org_status_idx');
            $table->index(['requested_by_user_id', 'status'], 're_user_status_idx');
            $table->index(['report_type', 'format', 'status'], 're_type_format_status_idx');
            $table->index(['expires_at', 'status'], 're_expiry_status_idx');
        });

        Schema::create('report_export_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_export_id')->constrained('report_exports', 'id', 'rea_export_fk')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users', 'id', 'rea_actor_fk')->nullOnDelete();
            $table->string('action', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['report_export_id', 'action'], 'rea_export_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_audits');
        Schema::dropIfExists('report_exports');
    }
};
