<?php

namespace App\Actions\Biometrics;

use App\Models\EmployeeBiometricTemplate;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class RevokeFingerprintTemplate
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, EmployeeBiometricTemplate $template, string $reason): EmployeeBiometricTemplate
    {
        return DB::transaction(function () use ($actor, $template, $reason): EmployeeBiometricTemplate {
            $locked = EmployeeBiometricTemplate::query()->with(['employee', 'sourceTerminal'])->lockForUpdate()->findOrFail($template->id);
            if ($locked->status !== EmployeeBiometricTemplate::ACTIVE) {
                abort(response()->json(['success' => false, 'code' => 'BIOMETRIC_TEMPLATE_REVOKED', 'message' => 'قالب بایومتریک فعال نیست.', 'errors' => []], 422));
            }
            $locked->forceFill([
                'status' => EmployeeBiometricTemplate::REVOKED,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revoke_reason' => $reason,
            ])->saveQuietly();
            $this->audit->record('BIOMETRIC_TEMPLATE_REVOKED', $actor, $locked->sourceTerminal, null, null, null, [
                'employee_id' => $locked->employee_id,
                'template_uuid' => $locked->uuid,
                'finger_position' => $locked->finger_position,
                'version_number' => $locked->version_number,
                'reason' => $reason,
            ]);
            return $locked->fresh(['employee', 'sourceTerminal', 'revokedBy']);
        });
    }
}
