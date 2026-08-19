<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteVersionDecision extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'uuid', 'meeting_minute_version_id', 'sequence_number', 'decision_number',
        'decision_text', 'responsible_unit_id', 'responsible_employee_id', 'target_date', 'notes',
    ];
    protected $casts = ['target_date' => 'date'];

    public function version(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'meeting_minute_version_id'); }
    public function responsibleUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'responsible_unit_id'); }
    public function responsibleEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'responsible_employee_id'); }
}
