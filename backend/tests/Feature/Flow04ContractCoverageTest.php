<?php

namespace Tests\Feature;

use App\Enums\MeetingRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Flow04ContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[DataProvider('flow04ContractCases')]
    public function test_prompt04_required_contract_case_is_present(string $kind, string $value, string $method = ''): void
    {
        if ($kind === 'table') {
            $this->assertTrue(Schema::hasTable($value), "Missing FLOW-04 table: {$value}");
            return;
        }
        if ($kind === 'class') {
            $this->assertTrue(class_exists($value), "Missing FLOW-04 class: {$value}");
            return;
        }
        if ($kind === 'permission') {
            $this->assertDatabaseHas('permissions', ['name' => $value, 'guard_name' => 'sanctum']);
            return;
        }
        if ($kind === 'state') {
            $this->assertSame($value, MeetingRequestStatus::from($value)->value);
            return;
        }
        $this->assertTrue(collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), $value) && ($method === '' || in_array($method, $route->methods(), true))
        ), "Missing FLOW-04 route contract: {$method} {$value}");
    }

    public static function flow04ContractCases(): array
    {
        return [
            '01 organization authority table' => ['table', 'organization_authorities'],
            '02 request authority context table' => ['table', 'meeting_request_authority_contexts'],
            '03 authority schedule acknowledgement table' => ['table', 'meeting_authority_schedule_acknowledgements'],
            '04 authority participant confirmation table' => ['table', 'meeting_authority_participant_confirmations'],
            '05 meeting authority context table' => ['table', 'meeting_authority_contexts'],
            '06 organization authority model' => ['class', 'App\\Models\\OrganizationAuthority'],
            '07 request authority context model' => ['class', 'App\\Models\\MeetingRequestAuthorityContext'],
            '08 schedule acknowledgement model' => ['class', 'App\\Models\\MeetingAuthorityScheduleAcknowledgement'],
            '09 participant confirmation model' => ['class', 'App\\Models\\MeetingAuthorityParticipantConfirmation'],
            '10 meeting authority context model' => ['class', 'App\\Models\\MeetingAuthorityContext'],
            '11 create action' => ['class', 'App\\Actions\\MeetingRequests\\CreateHighAuthorityMeetingRequest'],
            '12 submit action' => ['class', 'App\\Actions\\MeetingRequests\\SubmitHighAuthorityMeetingRequest'],
            '13 requester approval action' => ['class', 'App\\Actions\\MeetingRequests\\ApproveHighAuthorityRequesterSide'],
            '14 office review action' => ['class', 'App\\Actions\\MeetingRequests\\StartAuthorityOfficeReview'],
            '15 office return action' => ['class', 'App\\Actions\\MeetingRequests\\ReturnFromAuthorityOffice'],
            '16 office forward action' => ['class', 'App\\Actions\\MeetingRequests\\ForwardRequestToHighAuthority'],
            '17 authority approval action' => ['class', 'App\\Actions\\MeetingRequests\\ApproveHighAuthorityRequest'],
            '18 authority return action' => ['class', 'App\\Actions\\MeetingRequests\\ReturnByHighAuthority'],
            '19 scheduling start action' => ['class', 'App\\Actions\\MeetingRequests\\StartAuthorityScheduling'],
            '20 proposal action' => ['class', 'App\\Actions\\MeetingRequests\\ProposeAuthoritySchedule'],
            '21 acknowledgement action' => ['class', 'App\\Actions\\MeetingRequests\\AcknowledgeAuthoritySchedule'],
            '22 schedule confirmation action' => ['class', 'App\\Actions\\MeetingRequests\\ConfirmAuthoritySchedule'],
            '23 requester participants action' => ['class', 'App\\Actions\\MeetingRequests\\UpdateHighAuthorityRequesterParticipants'],
            '24 authority participants action' => ['class', 'App\\Actions\\MeetingRequests\\UpdateHighAuthorityParticipants'],
            '25 finalization action' => ['class', 'App\\Actions\\MeetingRequests\\ConvertHighAuthorityRequestToMeeting'],
            '26 request resource' => ['class', 'App\\Http\\Resources\\MeetingRequestResource'],
            '27 authority context resource' => ['class', 'App\\Http\\Resources\\MeetingRequestAuthorityContextResource'],
            '28 create route' => ['route', 'meeting-requests/high-authority', 'POST'],
            '29 requester approve route' => ['route', 'high-authority/requester-approve', 'POST'],
            '30 office review route' => ['route', 'authority-office/review', 'POST'],
            '31 office forward route' => ['route', 'authority-office/forward', 'POST'],
            '32 authority approve route' => ['route', 'authority/approve', 'POST'],
            '33 scheduling start route' => ['route', 'authority-scheduling/start', 'POST'],
            '34 proposal route' => ['route', 'authority-schedule/proposals', 'POST'],
            '35 acknowledgement route' => ['route', 'authority-schedule/proposals/{proposal}/acknowledge', 'POST'],
            '36 schedule confirmation route' => ['route', 'authority-schedule/proposals/{proposal}/confirm', 'POST'],
            '37 requester participants route' => ['route', 'high-authority/requester-participants', 'POST'],
            '38 authority participants route' => ['route', 'authority-participants', 'POST'],
            '39 finalization route' => ['route', 'finalize-high-authority', 'POST'],
            '40 authority view permission' => ['permission', 'authority-request.view'],
            '41 office review permission' => ['permission', 'authority-request.review'],
            '42 office forward permission' => ['permission', 'authority-request.forward'],
            '43 authority decision permission' => ['permission', 'authority-request.decide'],
            '44 schedule view permission' => ['permission', 'authority-schedule.view'],
            '45 schedule acknowledgement permission' => ['permission', 'authority-schedule.acknowledge'],
            '46 participant management permission' => ['permission', 'authority-participant.manage'],
            '47 finalization permission' => ['permission', 'authority-meeting.finalize'],
            '48 approved state' => ['state', 'HIGH_AUTHORITY_APPROVED'],
            '49 converted state' => ['state', 'CONVERTED_TO_MEETING'],
        ];
    }
}
