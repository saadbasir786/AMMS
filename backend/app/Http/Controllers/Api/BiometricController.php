<?php

namespace App\Http\Controllers\Api;

use App\Actions\Biometrics\CancelBiometricEnrollment;
use App\Actions\Biometrics\CompleteBiometricEnrollment;
use App\Actions\Biometrics\RevokeFingerprintTemplate;
use App\Actions\Biometrics\StartBiometricEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricReasonRequest;
use App\Http\Requests\StartBiometricEnrollmentRequest;
use App\Http\Resources\BiometricEnrollmentSessionResource;
use App\Http\Resources\BiometricTemplateResource;
use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use Illuminate\Http\JsonResponse;

class BiometricController extends Controller
{
    public function employee(Employee $employee): JsonResponse
    {
        $this->authorize('biometric-scoped.view', $employee);
        $templates = $employee->biometricTemplates()->with(['sourceTerminal', 'enrolledBy'])->latest('id')->get();
        $required = (int) config('biometric.minimum_active_fingers', 2);
        $active = $templates->where('status', EmployeeBiometricTemplate::ACTIVE)->unique('finger_position')->count();
        $status = $active === 0 ? 'NOT_ENROLLED' : ($active < $required ? 'PARTIALLY_ENROLLED' : 'ENROLLED');
        return response()->json(['success' => true, 'data' => [
            'employee' => ['id' => $employee->id, 'employee_number' => $employee->employee_number, 'full_name' => $employee->full_name, 'organizational_unit_id' => $employee->organizational_unit_id],
            'biometric_status' => $status,
            'required_active_fingers' => $required,
            'active_finger_count' => $active,
            'templates' => BiometricTemplateResource::collection($templates),
        ]]);
    }

    public function start(StartBiometricEnrollmentRequest $request, StartBiometricEnrollment $action): BiometricEnrollmentSessionResource
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $this->authorize('biometric-scoped.enroll', $employee);
        $terminal = $request->filled('terminal_id') ? \App\Models\Terminal::query()->findOrFail($request->integer('terminal_id')) : null;
        return new BiometricEnrollmentSessionResource($action->execute($request->user(), $employee, $terminal, $request->integer('required_finger_count') ?: null));
    }

    public function complete(BiometricEnrollmentSession $enrollment, CompleteBiometricEnrollment $action): BiometricEnrollmentSessionResource
    {
        $this->authorize('biometric-scoped.enroll', $enrollment->employee);
        return new BiometricEnrollmentSessionResource($action->execute(request()->user(), $enrollment));
    }

    public function cancel(BiometricReasonRequest $request, BiometricEnrollmentSession $enrollment, CancelBiometricEnrollment $action): BiometricEnrollmentSessionResource
    {
        $this->authorize('biometric-scoped.cancel', $enrollment);
        return new BiometricEnrollmentSessionResource($action->execute($request->user(), $enrollment, $request->string('reason')->toString()));
    }

    public function revoke(BiometricReasonRequest $request, EmployeeBiometricTemplate $template, RevokeFingerprintTemplate $action): BiometricTemplateResource
    {
        $this->authorize('biometric-scoped.revoke', $template);
        return new BiometricTemplateResource($action->execute($request->user(), $template, $request->string('reason')->toString()));
    }
}
