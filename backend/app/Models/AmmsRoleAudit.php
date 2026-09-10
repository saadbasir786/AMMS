<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class AmmsRoleAudit extends Model
{
    protected $fillable = ['role_id', 'actor_id', 'action', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
