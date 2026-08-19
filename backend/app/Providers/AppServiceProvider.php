<?php

namespace App\Providers;

use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Policies\BiometricPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('biometric.view', fn ($user, Employee $employee): bool => app(BiometricPolicy::class)->view($user, $employee));
        Gate::define('biometric.enroll', fn ($user, Employee $employee): bool => app(BiometricPolicy::class)->enroll($user, $employee));
        Gate::define('biometric.reenroll', fn ($user, Employee $employee): bool => app(BiometricPolicy::class)->reenroll($user, $employee));
        Gate::define('biometric.revoke', fn ($user, EmployeeBiometricTemplate $template): bool => app(BiometricPolicy::class)->revoke($user, $template));
        Gate::define('biometric.cancel', fn ($user, BiometricEnrollmentSession $session): bool => app(BiometricPolicy::class)->cancel($user, $session));
    }
}
