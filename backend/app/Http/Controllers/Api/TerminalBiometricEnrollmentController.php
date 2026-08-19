<?php

namespace App\Http\Controllers\Api;

use App\Actions\Biometrics\SubmitFingerprintTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitFingerprintTemplateRequest;
use App\Http\Resources\BiometricTemplateResource;
use App\Models\BiometricEnrollmentSession;
use App\Models\Terminal;

class TerminalBiometricEnrollmentController extends Controller
{
    public function submit(SubmitFingerprintTemplateRequest $request, BiometricEnrollmentSession $enrollment, SubmitFingerprintTemplate $action): BiometricTemplateResource
    {
        /** @var Terminal $terminal */
        $terminal = $request->attributes->get('terminal');
        if (!$enrollment->terminal_id || (int) $enrollment->terminal_id !== (int) $terminal->id) {
            abort(response()->json(['success' => false, 'code' => 'BIOMETRIC_ENROLLMENT_SESSION_NOT_FOUND', 'message' => 'نشست ثبت بایومتریک برای این ترمینال نیست.', 'errors' => []], 404));
        }
        $actor = $enrollment->startedBy;
        if (!$actor) abort(response()->json(['success' => false, 'code' => 'BIOMETRIC_ENROLLMENT_SESSION_NOT_FOUND', 'message' => 'مالک نشست ثبت بایومتریک یافت نشد.', 'errors' => []], 404));
        $data = $request->validated();
        return new BiometricTemplateResource($action->execute($actor, $enrollment, $data['finger_position'], $data['template_vendor'], $data['template_format'], $data['algorithm_version'], $data['template_data'], isset($data['quality_score']) ? (float) $data['quality_score'] : null, $data['quality_accepted'] ?? true));
    }
}
