<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['organizations', 'users', 'employees', 'meetings', 'meeting_schedule_versions', 'meeting_participant_versions', 'terminals', 'employee_biometric_templates', 'meeting_attendance_records'] as $table) {
    echo $table.'='.Illuminate\Support\Facades\DB::table($table)->count().PHP_EOL;
}
