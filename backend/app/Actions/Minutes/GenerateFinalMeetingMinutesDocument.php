<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\MeetingMinuteDocument;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesDocumentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateFinalMeetingMinutesDocument
{
    public function __construct(private MinutesDocumentBuilder $builder) {}

    public function execute(User $actor, MeetingMinutes $minutes): array
    {
        return DB::transaction(function () use ($actor, $minutes): array {
            $locked = MeetingMinutes::query()->lockForUpdate()->findOrFail($minutes->id);
            if ($locked->status !== MeetingMinutes::FINALIZED) abort(422, 'MINUTES_NOT_FINALIZED');
            $version = $locked->currentVersion()->lockForUpdate()->firstOrFail();
            $existing = MeetingMinuteDocument::query()->where('meeting_minutes_id', $locked->id)->lockForUpdate()->first();
            $built = $this->builder->build($version);
            if ($existing) {
                if (!hash_equals($existing->content_sha256, $built['checksum'])) abort(422, 'FINAL_DOCUMENT_INTEGRITY_MISMATCH');
                return ['document' => $existing->fresh(['version']), 'bytes' => $built['bytes']];
            }
            $document = MeetingMinuteDocument::create([
                'uuid' => (string) Str::uuid(),
                'meeting_minutes_id' => $locked->id,
                'meeting_minute_version_id' => $version->id,
                'file_name' => $built['file_name'],
                'mime_type' => 'application/pdf',
                'content_sha256' => $built['checksum'],
                'generated_at' => now(),
                'generated_by_user_id' => $actor->id,
            ]);
            return ['document' => $document->load('version'), 'bytes' => $built['bytes']];
        });
    }
}
