<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialAttendanceResolver
{
    public function query(Meeting $meeting): HasMany
    {
        return $meeting->attendanceRecords()->whereDoesntHave('invalidation');
    }

    public function count(Meeting $meeting): int
    {
        return (int) $this->query($meeting)->count();
    }
}
