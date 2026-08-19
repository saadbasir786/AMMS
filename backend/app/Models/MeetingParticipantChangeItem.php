<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipantChangeItem extends Model
{
    protected $fillable = [
        'change_case_id',
        'operation',
        'old_employee_id',
        'new_employee_id',
        'participant_role',
    ];

    public function changeCase(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipantChangeCase::class, 'change_case_id');
    }

    public function oldEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'old_employee_id');
    }

    public function newEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'new_employee_id');
    }
}
