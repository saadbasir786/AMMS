<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesVersionBuilder;
use Illuminate\Support\Facades\DB;

class UpdateMeetingMinutesDraft
{
    public function __construct(private MinutesVersionBuilder $builder) {}

    public function execute(User $actor, MeetingMinutes $minutes, array $data): MeetingMinutes
    {
        return DB::transaction(function () use ($actor, $minutes, $data): MeetingMinutes {
            $locked = MeetingMinutes::query()->lockForUpdate()->findOrFail($minutes->id);
            if ($locked->status === MeetingMinutes::FINALIZED) abort(422, 'MINUTES_ALREADY_FINALIZED');
            $current = $locked->current_version_id ? $locked->currentVersion()->lockForUpdate()->first() : null;
            if ($current && $current->submitted_at !== null) {
                if ($locked->status !== MeetingMinutes::RETURNED_FOR_CORRECTION) abort(422, 'MINUTES_VERSION_STALE');
                $version = $this->builder->create($locked->fresh(['meeting']), $actor, $data, ((int) $current->version_number) + 1);
                $locked->forceFill(['current_version_id' => $version->id, 'status' => MeetingMinutes::DRAFT])->saveQuietly();
                return $locked->fresh(['meeting', 'currentVersion.items', 'currentVersion.decisions', 'currentVersion.attendanceSnapshots']);
            }
            $versionNumber = $current ? (int) $current->version_number : 1;
            if ($current) {
                $locked->forceFill(['current_version_id' => null])->saveQuietly();
                $current->items()->delete();
                $current->decisions()->delete();
                $current->attendanceSnapshots()->delete();
                $current->reviews()->delete();
                $current->delete();
            }
            $version = $this->builder->create($locked->fresh(['meeting']), $actor, $data, $versionNumber);
            $locked->forceFill(['current_version_id' => $version->id, 'status' => MeetingMinutes::DRAFT])->saveQuietly();
            return $locked->fresh(['meeting', 'currentVersion.items', 'currentVersion.decisions', 'currentVersion.attendanceSnapshots']);
        });
    }
}
