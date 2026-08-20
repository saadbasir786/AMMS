<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends \Illuminate\Database\Eloquent\Model
{
    public const PENDING = 'PENDING';
    public const RUNNING = 'RUNNING';
    public const COMPLETED = 'COMPLETED';
    public const FAILED = 'FAILED';
    public const EXPIRED = 'EXPIRED';

    protected $table = 'report_exports';

    protected $fillable = [
        'uuid', 'organization_id', 'requested_by_user_id', 'report_type', 'format',
        'filter_payload', 'scope_descriptor', 'status', 'definition_version',
        'requested_at', 'started_at', 'completed_at', 'expires_at', 'storage_disk',
        'storage_path', 'file_name', 'file_size', 'checksum', 'error_code',
    ];

    protected $casts = [
        'filter_payload' => 'array',
        'scope_descriptor' => 'array',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected $hidden = ['storage_disk', 'storage_path', 'checksum'];

    public function getRouteKeyName(): string { return 'uuid'; }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED, self::EXPIRED], true);
    }
}
