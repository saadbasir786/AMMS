<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table): void {
            $table->unique(['organization_id', 'code'], 'org_units_org_code_unique');
            $table->unique(['organization_id', 'parent_id', 'name'], 'org_units_org_parent_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table): void {
            $table->dropUnique('org_units_org_code_unique');
            $table->dropUnique('org_units_org_parent_name_unique');
        });
    }
};
