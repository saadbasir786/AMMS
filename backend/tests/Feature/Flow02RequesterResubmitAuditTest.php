<?php

namespace Tests\Feature;

use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow02RequesterResubmitAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_requester_return_correction_resubmit_and_reapproval_path_is_complete(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_id' => $finance->id,
            'subject' => 'درخواست برای بررسی مسیر برگشت داخلی',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
        ])->assertCreated();

        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")
            ->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');

        $this->actingAs($techDirector, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/requester-return", ['reason' => 'شرح درخواست تکمیل شود.'])
            ->assertOk()->assertJsonPath('data.status', 'RETURNED_TO_REQUESTER');

        $this->actingAs($techExec, 'sanctum')
            ->putJson("/api/meeting-requests/{$request->id}", [
                'description' => 'شرح اصلاح‌شده بدون تغییر موضوع یا هدف.',
                'priority' => 'NORMAL',
            ])
            ->assertOk()->assertJsonPath('data.status', 'RETURNED_TO_REQUESTER');

        $this->actingAs($techExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/resubmit-requester")
            ->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');

        $this->assertDatabaseHas('meeting_request_actions', [
            'meeting_request_id' => $request->id,
            'action' => 'REQUESTER_RESUBMITTED',
        ]);

        $this->actingAs($techDirector, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/requester-approve")
            ->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_TARGET');
    }
}
