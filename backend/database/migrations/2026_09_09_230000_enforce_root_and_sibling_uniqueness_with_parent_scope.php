<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop old index if it exists in schema
        $existingIndexes = collect(Schema::getIndexes('organizational_units'))->pluck('name')->all();
        if (in_array('org_units_org_parent_name_unique', $existingIndexes, true)) {
            Schema::table('organizational_units', function (Blueprint $table): void {
                $table->dropUnique('org_units_org_parent_name_unique');
            });
        }

        // 2. Add parent_scope_id and unique constraint
        Schema::table('organizational_units', function (Blueprint $table) use ($existingIndexes): void {
            if (!Schema::hasColumn('organizational_units', 'parent_scope_id')) {
                $table->unsignedBigInteger('parent_scope_id')
                    ->virtualAs('COALESCE(parent_id, 0)')
                    ->after('parent_id');
            }

            if (!in_array('org_units_org_parent_scope_name_unique', $existingIndexes, true)) {
                $table->unique(
                    ['organization_id', 'parent_scope_id', 'name'],
                    'org_units_org_parent_scope_name_unique'
                );
            }
        });
    }

    public function down(): void
    {
        $existingIndexes = collect(Schema::getIndexes('organizational_units'))->pluck('name')->all();

        Schema::table('organizational_units', function (Blueprint $table) use ($existingIndexes): void {
            if (in_array('org_units_org_parent_scope_name_unique', $existingIndexes, true)) {
                $table->dropUnique('org_units_org_parent_scope_name_unique');
            }

            if (Schema::hasColumn('organizational_units', 'parent_scope_id')) {
                $table->dropColumn('parent_scope_id');
            }

            if (!in_array('org_units_org_parent_name_unique', $existingIndexes, true)) {
                $table->unique(
                    ['organization_id', 'parent_id', 'name'],
                    'org_units_org_parent_name_unique'
                );
            }
        });
    }
};
