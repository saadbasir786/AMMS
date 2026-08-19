<?php

namespace Tests\Feature;

use App\Actions\Biometrics\CompleteBiometricEnrollment;
use App\Actions\Biometrics\ReEnrollFingerprint;
use App\Actions\Biometrics\RevokeFingerprintTemplate;
use App\Actions\Biometrics\StartBiometricEnrollment;
use App\Actions\Biometrics\SubmitFingerprintTemplate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow10ABiometricLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_two_finger_completion_and_explicit_reenrollment_preserve_history(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $start = app(StartBiometricEnrollment::class)->execute($actor, $employee);
        $first = app(SubmitFingerprintTemplate::class)->execute($actor, $start, 'RIGHT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'TEST_TEMPLATE_EMPLOYEE_12_RIGHT_INDEX', 95.0);
        $this->assertSame('PARTIALLY_ENROLLED', $this->statusFor($employee));
        $second = app(SubmitFingerprintTemplate::class)->execute($actor, $start, 'LEFT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'TEST_TEMPLATE_EMPLOYEE_12_LEFT_INDEX', 95.0);
        app(CompleteBiometricEnrollment::class)->execute($actor, $start);
        $this->assertSame('ENROLLED', $this->statusFor($employee));
        $this->assertSame('ACTIVE', $first->fresh()->status);
        $this->assertSame('ACTIVE', $second->fresh()->status);

        $reenrollment = app(StartBiometricEnrollment::class)->execute($actor, $employee);
        $replacement = app(ReEnrollFingerprint::class)->execute($actor, $reenrollment, 'RIGHT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'TEST_TEMPLATE_EMPLOYEE_12_RIGHT_INDEX_V2', 96.0);
        $this->assertSame('SUPERSEDED', $first->fresh()->status);
        $this->assertSame('ACTIVE', $replacement->fresh()->status);
        $this->assertNotSame($first->template_checksum, $replacement->template_checksum);

        app(RevokeFingerprintTemplate::class)->execute($actor, $replacement, 'آزمایش ابطال قالب');
        $this->assertSame('REVOKED', $replacement->fresh()->status);
    }

    private function statusFor(Employee $employee): string
    {
        $active = $employee->biometricTemplates()->where('status', 'ACTIVE')->distinct('finger_position')->count('finger_position');
        return $active === 0 ? 'NOT_ENROLLED' : ($active < 2 ? 'PARTIALLY_ENROLLED' : 'ENROLLED');
    }
}
