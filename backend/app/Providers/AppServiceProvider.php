<?php

namespace App\Providers;

use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\MeetingActionItemEvidence;
use App\Policies\BiometricPolicy;
use App\Policies\MeetingDecisionFollowUpPolicy;
use App\Policies\MeetingActionItemPolicy;
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
        Gate::policy(MeetingDecisionFollowUp::class, MeetingDecisionFollowUpPolicy::class);
        Gate::policy(MeetingActionItem::class, MeetingActionItemPolicy::class);
        Gate::policy(MeetingActionItemExtensionRequest::class, MeetingActionItemPolicy::class);
        Gate::policy(MeetingActionItemEvidence::class, MeetingActionItemPolicy::class);
    }
}
