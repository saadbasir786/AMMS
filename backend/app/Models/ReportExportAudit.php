<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReportExportAudit extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;
    protected $table = 'report_export_audits';
    protected $fillable = ['report_export_id', 'actor_user_id', 'action', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function export(): BelongsTo { return $this->belongsTo(ReportExport::class, 'report_export_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Report export audits are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Report export audits are immutable.'));
    }
}
