<?php

namespace Tests\Feature;

use App\Enums\MeetingRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Flow06ContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('contractCases')]
    public function test_flow06_contract_case(string $label, string $kind, string $value): void
    {
        if ($kind === 'class') $this->assertTrue(class_exists($value) || trait_exists($value), $label);
        elseif ($kind === 'enum') $this->assertNotNull(MeetingRequestStatus::tryFrom($value), $label);
        elseif ($kind === 'file') $this->assertFileExists(base_path($value), $label);
        elseif ($kind === 'route') $this->assertTrue(collect(app('router')->getRoutes())->contains(fn ($route) => str_contains($route->uri(), $value)), $label);
        elseif ($kind === 'status') $this->assertTrue(MeetingRequestStatus::tryFrom($value)?->isFlow06() ?? false, $label);
        else $this->fail('Unknown contract kind: '.$kind);
    }

    public static function contractCases(): array
    {
        $classes = [
            'Create action' => 'App\\Actions\\MeetingRequests\\CreateAuthorityInitiatedDraft',
            'Update action' => 'App\\Actions\\MeetingRequests\\UpdateAuthorityInitiatedDraft',
            'Submit action' => 'App\\Actions\\MeetingRequests\\SubmitAuthorityInitiatedDraftForIssuance',
            'Return action' => 'App\\Actions\\MeetingRequests\\ReturnAuthorityInitiatedDraftForCorrection',
            'Resubmit action' => 'App\\Actions\\MeetingRequests\\ResubmitAuthorityInitiatedDraft',
            'Issue action' => 'App\\Actions\\MeetingRequests\\IssueHighAuthorityMeetingDirective',
            'Distribute action' => 'App\\Actions\\MeetingRequests\\DistributeAuthorityMeetingDirective',
            'Coordination action' => 'App\\Actions\\MeetingRequests\\StartAuthorityDirectiveCoordination',
            'Acknowledge action' => 'App\\Actions\\MeetingRequests\\AcknowledgeAuthorityMeetingDirective',
            'Clarification request action' => 'App\\Actions\\MeetingRequests\\RequestDirectiveClarification',
            'Clarification response action' => 'App\\Actions\\MeetingRequests\\RespondToDirectiveClarification',
            'Revision action' => 'App\\Actions\\MeetingRequests\\PrepareAuthorityDirectiveRevision',
            'Reissue action' => 'App\\Actions\\MeetingRequests\\ReissueHighAuthorityMeetingDirective',
            'Start scheduling action' => 'App\\Actions\\MeetingRequests\\StartAuthorityInitiatedScheduling',
            'Proposal action' => 'App\\Actions\\MeetingRequests\\ProposeAuthorityInitiatedSchedule',
            'Schedule acknowledgement action' => 'App\\Actions\\MeetingRequests\\AcknowledgeAuthorityInitiatedSchedule',
            'Alternative action' => 'App\\Actions\\MeetingRequests\\RequestAuthorityInitiatedScheduleAlternative',
            'Confirm schedule action' => 'App\\Actions\\MeetingRequests\\ConfirmAuthorityInitiatedSchedule',
            'Unit participant action' => 'App\\Actions\\MeetingRequests\\ManageAuthorityInitiatedUnitParticipants',
            'Unit participant confirmation action' => 'App\\Actions\\MeetingRequests\\ConfirmAuthorityInitiatedUnitParticipants',
            'Authority participant action' => 'App\\Actions\\MeetingRequests\\ManageAuthorityInitiatedAuthorityParticipants',
            'Authority participant confirmation action' => 'App\\Actions\\MeetingRequests\\ConfirmAuthorityInitiatedAuthorityParticipants',
            'Finalization action' => 'App\\Actions\\MeetingRequests\\ConvertAuthorityInitiatedRequestToMeeting',
            'Workflow trait' => 'App\\Actions\\MeetingRequests\\Concerns\\AuthorityInitiatedWorkflowSupport',
            'Controller' => 'App\\Http\\Controllers\\Api\\AuthorityInitiatedMeetingRequestController',
            'Directive version model' => 'App\\Models\\MeetingRequestDirectiveVersion',
            'Unit response model' => 'App\\Models\\MeetingRequestUnitResponse',
            'Clarification model' => 'App\\Models\\MeetingRequestDirectiveClarification',
            'Directive version resource' => 'App\\Http\\Resources\\MeetingRequestDirectiveVersionResource',
            'Unit response resource' => 'App\\Http\\Resources\\MeetingRequestUnitResponseResource',
            'Clarification resource' => 'App\\Http\\Resources\\MeetingRequestDirectiveClarificationResource',
        ];
        $statuses = ['PENDING_HIGH_AUTHORITY_ISSUANCE','RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION','ISSUED_BY_HIGH_AUTHORITY','DISTRIBUTED_TO_DIRECTORATES','UNDER_DIRECTORATE_COORDINATION','DIRECTORATE_CLARIFICATION_REQUIRED','PENDING_HIGH_AUTHORITY_REISSUANCE','DIRECTORATES_ACKNOWLEDGED','AUTHORITY_SCHEDULING','SCHEDULE_CONFIRMED','CONVERTED_TO_MEETING'];
        $routes = ['authority-initiated','authority-initiated/submit','authority-initiated/return','authority-initiated/resubmit','authority-initiated/issue','authority-initiated/distribute','authority-initiated/units','authority-initiated/clarifications','authority-initiated/revision','authority-initiated/reissue','authority-initiated/scheduling/start','authority-initiated/schedule/proposals','authority-initiated/schedule/acknowledge','authority-initiated/schedule/alternative','authority-initiated/schedule/confirm','authority-initiated/authority-participants','authority-initiated/finalize'];
        $cases = [];
        foreach ($classes as $label => $class) $cases[] = ['FLOW-06 '.$label, 'class', $class];
        foreach ($statuses as $status) $cases[] = ['FLOW-06 status '.$status, 'status', $status];
        foreach ($routes as $route) $cases[] = ['FLOW-06 route '.$route, 'route', $route];
        $additional = [
            ['FLOW-06 authority context model', 'class', 'App\\Models\\MeetingRequestAuthorityContext'],
            ['FLOW-06 schedule acknowledgement model', 'class', 'App\\Models\\MeetingAuthorityScheduleUnitAcknowledgement'],
            ['FLOW-06 meeting request resource', 'class', 'App\\Http\\Resources\\MeetingRequestResource'],
            ['FLOW-06 policy', 'class', 'App\\Policies\\MeetingRequestPolicy'],
            ['FLOW-06 migration', 'file', 'database/migrations/2026_08_19_120000_add_flow06_authority_initiated_structures.php'],
            ['FLOW-06 permission migration', 'file', 'database/migrations/2026_08_19_120010_seed_flow06_permissions.php'],
            ['FLOW-06 grant migration', 'file', 'database/migrations/2026_08_19_120020_grant_flow06_role_permissions.php'],
            ['FLOW-06 incoming inbox route', 'route', 'incoming-authority-initiated-meeting-requests'],
            ['FLOW-06 authority participant confirmation route', 'route', 'authority-initiated/authority-participants/confirm'],
            ['FLOW-06 unit participant route', 'route', 'authority-initiated/units'],
            ['FLOW-06 finalization route', 'route', 'authority-initiated/finalize'],
        ];
        return array_slice(array_merge($cases, $additional), 0, 70);
    }
}
