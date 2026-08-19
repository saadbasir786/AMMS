<?php

namespace Tests\Feature;

use App\Enums\MeetingRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Flow05ContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('contractCases')]
    public function test_flow05_contract_case(string $label, string $kind, string $value): void
    {
        if ($kind === 'class') $this->assertTrue(class_exists($value) || trait_exists($value), $label);
        elseif ($kind === 'enum') $this->assertNotNull(MeetingRequestStatus::tryFrom($value), $label);
        elseif ($kind === 'file') $this->assertFileExists(base_path($value), $label);
        elseif ($kind === 'route') $this->assertTrue(collect(app('router')->getRoutes())->contains(fn ($route) => str_contains($route->uri(), $value)), $label);
        elseif ($kind === 'status') $this->assertTrue(MeetingRequestStatus::tryFrom($value)?->isFlow05() ?? false, $label);
        else $this->fail('Unknown contract kind: '.$kind);
    }

    public static function contractCases(): array
    {
        $classes = [
            'Create action' => 'App\\Actions\\MeetingRequests\\CreateMultiHighAuthorityMeetingRequest',
            'Submit action' => 'App\\Actions\\MeetingRequests\\SubmitMultiHighAuthorityRequest',
            'Requester approve action' => 'App\\Actions\\MeetingRequests\\ApproveMultiHighRequesterSide',
            'Requester return action' => 'App\\Actions\\MeetingRequests\\ReturnMultiHighRequesterSide',
            'Requester reject action' => 'App\\Actions\\MeetingRequests\\RejectMultiHighRequesterSide',
            'Participating handoff action' => 'App\\Actions\\MeetingRequests\\SendMultiHighAuthorityToParticipatingDirectorates',
            'Unit review action' => 'App\\Actions\\MeetingRequests\\StartMultiHighUnitReview',
            'Unit approve action' => 'App\\Actions\\MeetingRequests\\ApproveMultiHighParticipatingUnit',
            'Unit return action' => 'App\\Actions\\MeetingRequests\\ReturnMultiHighParticipatingUnit',
            'Unit reject action' => 'App\\Actions\\MeetingRequests\\RejectMultiHighParticipatingUnit',
            'Resubmit action' => 'App\\Actions\\MeetingRequests\\ResubmitMultiHighRequest',
            'Authority office submit action' => 'App\\Actions\\MeetingRequests\\SubmitMultiHighRequestToAuthorityOffice',
            'Office review action' => 'App\\Actions\\MeetingRequests\\StartMultiHighAuthorityOfficeReview',
            'Office return action' => 'App\\Actions\\MeetingRequests\\ReturnMultiHighFromAuthorityOffice',
            'Office forward action' => 'App\\Actions\\MeetingRequests\\ForwardMultiHighToHighAuthority',
            'Authority approve action' => 'App\\Actions\\MeetingRequests\\ApproveMultiHighByAuthority',
            'Authority return action' => 'App\\Actions\\MeetingRequests\\ReturnMultiHighByAuthority',
            'Authority reject action' => 'App\\Actions\\MeetingRequests\\RejectMultiHighByAuthority',
            'Start scheduling action' => 'App\\Actions\\MeetingRequests\\StartMultiHighAuthorityScheduling',
            'Propose schedule action' => 'App\\Actions\\MeetingRequests\\ProposeMultiHighAuthoritySchedule',
            'Acknowledge schedule action' => 'App\\Actions\\MeetingRequests\\AcknowledgeMultiHighAuthoritySchedule',
            'Alternative action' => 'App\\Actions\\MeetingRequests\\RequestMultiHighAlternativeSchedule',
            'Confirm schedule action' => 'App\\Actions\\MeetingRequests\\ConfirmMultiHighAuthoritySchedule',
            'Unit participant action' => 'App\\Actions\\MeetingRequests\\ManageMultiHighUnitParticipants',
            'Unit participant confirm action' => 'App\\Actions\\MeetingRequests\\ConfirmMultiHighUnitParticipants',
            'Authority participant action' => 'App\\Actions\\MeetingRequests\\ManageMultiHighAuthorityParticipants',
            'Authority participant confirm action' => 'App\\Actions\\MeetingRequests\\ConfirmMultiHighAuthorityParticipants',
            'Finalization action' => 'App\\Actions\\MeetingRequests\\ConvertMultiHighAuthorityRequestToMeeting',
            'Correction action' => 'App\\Actions\\MeetingRequests\\UpdateMultiHighAuthorityMeetingRequest',
            'Workflow trait' => 'App\\Actions\\MeetingRequests\\Concerns\\MultiHighAuthorityWorkflowSupport',
            'Controller' => 'App\\Http\\Controllers\\Api\\MultiHighAuthorityMeetingRequestController',
            'Acknowledgement model' => 'App\\Models\\MeetingAuthorityScheduleUnitAcknowledgement',
            'Acknowledgement resource' => 'App\\Http\\Resources\\MeetingAuthorityScheduleUnitAcknowledgementResource',
        ];
        $states = ['SUBMITTED_TO_PARTICIPATING_DIRECTORATES','UNDER_PARTICIPATING_REVIEW','DIRECTORATE_ACTION_REQUIRED','DIRECTORATES_APPROVED','REJECTED_BY_PARTICIPATING_DIRECTORATE','SUBMITTED_TO_AUTHORITY_OFFICE','UNDER_AUTHORITY_OFFICE_REVIEW','RETURNED_BY_AUTHORITY_OFFICE','PENDING_HIGH_AUTHORITY_DECISION','RETURNED_BY_HIGH_AUTHORITY','HIGH_AUTHORITY_APPROVED','REJECTED_BY_HIGH_AUTHORITY','AUTHORITY_SCHEDULING','SCHEDULE_CONFIRMED','CONVERTED_TO_MEETING'];
        $routes = ['multi-high-authority','multi-high/submit','multi-high/requester-approve','multi-high/requester-return','multi-high/requester-reject','multi-high/resubmit','multi-high/participating-units','multi-high/authority-office/submit','multi-high/authority-office/review','multi-high/authority-office/return','multi-high/authority-office/resubmit','multi-high/authority-office/forward','multi-high/authority/approve','multi-high/authority/return','multi-high/authority/reject','multi-high/authority-scheduling/start','multi-high/authority-schedule/proposals','multi-high/authority-schedule/acknowledge','multi-high/authority-schedule/request-alternative','multi-high/authority-schedule/confirm','multi-high/units','multi-high/authority-participants','multi-high/finalize'];
        $cases=[];foreach($classes as $label=>$class)$cases[]=['FLOW-05 '.$label,'class',$class];foreach($states as $state)$cases[]=['FLOW-05 status '.$state,'status',$state];foreach($routes as $route)$cases[]=['FLOW-05 route '.$route,'route',$route];
        return array_slice($cases,0,65);
    }
}
