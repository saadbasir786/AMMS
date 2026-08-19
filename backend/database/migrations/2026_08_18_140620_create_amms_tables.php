<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('type')->default('GOVERNMENT');
            $table->string('status')->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('unit_type')->default('OTHER');
            $table->string('status')->default('ACTIVE')->index();
            $table->timestamps();
            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('code')->nullable();
            $table->string('position_type')->nullable();
            $table->string('status')->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('father_name')->nullable();
            $table->foreignId('organizational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('ACTIVE')->index();
            $table->timestamps();
            $table->index(['organizational_unit_id', 'status']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->foreignId('organizational_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role', 'organizational_unit_id'], 'user_roles_unique_scope');
        });

        Schema::create('meeting_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->nullable()->unique();
            $table->string('flow_type')->default('INTERNAL_DIRECTORATE')->index();
            $table->foreignId('requesting_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('subject');
            $table->string('purpose');
            $table->text('description')->nullable();
            $table->string('priority')->default('NORMAL')->index();
            $table->date('preferred_date')->nullable();
            $table->time('preferred_start_time')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->string('proposed_location')->nullable();
            $table->string('status')->default('DRAFT')->index();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['requesting_unit_id', 'status']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('meeting_request_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('participant_type')->default('PROPOSED');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['meeting_request_id', 'employee_id', 'participant_type'], 'request_participant_unique');
        });

        Schema::create('meeting_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_request_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['meeting_request_id', 'created_at']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('meeting_number')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('organizing_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('chairperson_employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location');
            $table->text('agenda')->nullable();
            $table->string('status')->default('SCHEDULED')->index();
            $table->timestamps();
            $table->index(['organizing_unit_id', 'meeting_date']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->constrained()->restrictOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'employee_id']);
        });

        Schema::create('amms_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amms_notifications');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('meeting_request_actions');
        Schema::dropIfExists('meeting_request_attachments');
        Schema::dropIfExists('meeting_request_participants');
        Schema::dropIfExists('meeting_requests');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('organizations');
    }
};
