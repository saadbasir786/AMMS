<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Meeting;
foreach (Meeting::with(['request','units','authorityContext'])->orderBy('id')->get() as $meeting) {
    echo json_encode([
        'id' => $meeting->id,
        'flow_type' => $meeting->request?->flow_type,
        'unit_count' => $meeting->units->count(),
        'units' => $meeting->units->map(fn ($unit) => [$unit->organizational_unit_id, $unit->role])->all(),
        'authority_context' => (bool) $meeting->authorityContext,
        'participant_version_id' => $meeting->current_participant_version_id,
    ], JSON_UNESCAPED_UNICODE), PHP_EOL;
}
