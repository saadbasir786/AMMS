<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's canonical database.
     *
     * Seeds the authoritative hierarchical organization chart, positions,
     * employees, and user access roles across the General Presidency hierarchy.
     */
    public function run(): void
    {
        $this->call([
            OrganizationStructureSeeder::class,
            PositionSeeder::class,
            EmployeeSeeder::class,
            UserAccessSeeder::class,
        ]);
    }
}
