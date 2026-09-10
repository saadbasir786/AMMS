<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class AmmsRoleDefinition extends Model
{
    public const SYSTEM = 'SYSTEM';
    public const CUSTOM = 'CUSTOM';
    public const ACTIVE = 'ACTIVE';
    public const INACTIVE = 'INACTIVE';

    protected $fillable = ['role_id', 'label', 'description', 'role_kind', 'status', 'created_by', 'updated_by'];

    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function isSystem(): bool { return $this->role_kind === self::SYSTEM; }
    public function isCustom(): bool { return $this->role_kind === self::CUSTOM; }
    public function isActive(): bool { return $this->status === self::ACTIVE; }
}
