<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReportExport;
use App\Models\User;

class ReportExportPolicy
{
    public function view(User $user, ReportExport $export): bool
    {
        return $user->isActive() && $user->id === $export->requested_by_user_id && $user->can('reporting.exports.download');
    }

    public function download(User $user, ReportExport $export): bool
    {
        return $this->view($user, $export)
            && $export->status === ReportExport::COMPLETED
            && (!$export->expires_at || $export->expires_at->isFuture());
    }
}
