<?php

namespace Tests\Feature;

use App\Actions\MeetingRequests\SubmitMultiHighRequestToAuthorityOffice;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MultiHighAuthorityAdminBypassAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow05_action_denies_system_admin_but_allows_the_scoped_lead_to_reach_business_validation(): void
    {
        $admin = User::where('email', 'system.admin@example.test')->firstOrFail();
        $lead = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();

        $request = MeetingRequest::create([
            'request_number' => 'SEC-FLOW05-DIRECT-ACTION',
            'flow_type' => 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY',
            'requesting_unit_id' => $tech->id,
            'created_by' => $lead->id,
            'subject' => 'FLOW-05 direct authorization audit',
            'purpose' => 'SECURITY_AUDIT',
            'priority' => 'NORMAL',
            'status' => 'DIRECTORATES_APPROVED',
            'lead_coordinator_unit_id' => $tech->id,
            'lead_coordinator_user_id' => $lead->id,
        ]);

        $action = app(SubmitMultiHighRequestToAuthorityOffice::class);

        try {
            $action($admin, $request);
            $this->fail('SYSTEM_ADMIN must not cross the FLOW-05 business-governance boundary.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $action($lead, $request);
            $this->fail('The minimal fixture should stop at business-readiness validation.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('REQUEST_NOT_READY_FOR_AUTHORITY_OFFICE', $exception->getMessage());
        }
    }
}
