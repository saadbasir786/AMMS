<?php

namespace App\Http\Controllers\Api\AttendanceBiometrics;

use App\Actions\Biometrics\EnrollMeetingParticipantFingerprintTemplate;
use App\Actions\Biometrics\SubmitFingerprintTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\SubmitFingerprintTemplateRequest;
use App\Http\Requests\AttendanceBiometrics\SubmitMeetingParticipantFingerprintTemplateRequest;
use App\Http\Resources\BiometricEnrollmentSessionResource;
use App\Http\Resources\BiometricTemplateResource;
use App\Models\BiometricEnrollmentSession;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Support\BiometricFingerPosition;
use Illuminate\Http\JsonResponse;

class TerminalBiometricEnrollmentController extends Controller
{
    public function current(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->attributes->get('terminal');
        $enrollment = BiometricEnrollmentSession::query()->with('employee')->active()->where('terminal_id', $terminal->id)->latest('id')->first();
        if (!$enrollment || !$enrollment->isActive()) {
            return response()->json(['success' => true, 'data' => ['enrollment' => null, 'finger_positions' => BiometricFingerPosition::ALL]]);
        }
        return response()->json(['success' => true, 'data' => ['enrollment' => new BiometricEnrollmentSessionResource($enrollment), 'finger_positions' => BiometricFingerPosition::ALL]]);
    }

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
        return new BiometricTemplateResource($action->execute($actor, $enrollment, $data['finger_position'], $data['template_vendor'], $data['template_format'], $data['algorithm_version'], $data['template_data'], isset($data['quality_score']) ? (float) $data['quality_score'] : null, $data['quality_accepted'] ?? true, $data['replace_active'] ?? false, $data['template_encoding'] ?? 'RAW_UTF8'));
    }

    public function submitForMeetingParticipant(
        SubmitMeetingParticipantFingerprintTemplateRequest $request,
        EnrollMeetingParticipantFingerprintTemplate $action,
    ): JsonResponse {
        /** @var TerminalMeetingSession $session */
        $session = $request->attributes->get('terminalSession');
        $result = $action->execute($session, $request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'template' => (new BiometricTemplateResource($result['template']))->resolve($request),
                'progress' => $result['progress'],
            ],
        ]);
    }
}
