<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternalMeetingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_technology_flow_can_be_approved_scheduled_and_converted_once(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $director = User::where('email','tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number',['EMP-TECH-001','EMP-TECH-003','EMP-TECH-004'])->pluck('id')->all();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'جلسه بررسی پلان توسعه سیستم‌ها','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>$employees])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk()->assertJsonPath('data.status','PENDING_INTERNAL_APPROVAL');
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk()->assertJsonPath('data.status','APPROVED');

        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk()->assertJsonPath('data.status','SCHEDULING');
        $schedule = ['meeting_date'=>now()->addWeek()->format('Y-m-d'),'start_time'=>'09:00','duration_minutes'=>60,'location'=>'اتاق جلسات تکنالوژی','chairperson_employee_id'=>$employees[0],'participant_ids'=>$employees];
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule",$schedule)->assertOk()->assertJsonPath('data.status','SCHEDULE_CONFIRMED');
        $finalize = ['meeting_date'=>$schedule['meeting_date'],'start_time'=>'09:00','end_time'=>'10:00','location'=>$schedule['location'],'chairperson_employee_id'=>$employees[0],'agenda'=>'بررسی پلان توسعه سیستم‌ها'];
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize",$finalize)->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize",$finalize)->assertForbidden();
        $this->assertSame(1, Meeting::where('meeting_request_id',$request->id)->count());
        $this->assertSame('MT-'.now()->format('Y').'-00001', Meeting::where('meeting_request_id',$request->id)->firstOrFail()->meeting_number);
        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $meeting = Meeting::where('meeting_request_id',$request->id)->firstOrFail();
        $this->assertNotNull($meeting->current_schedule_version_id);
        $this->assertDatabaseHas('meeting_units', [
            'meeting_id' => $meeting->id,
            'organizational_unit_id' => $tech->id,
            'role' => 'REQUESTING',
        ]);
        $this->actingAs($exec, 'sanctum')->postJson("/api/meetings/{$meeting->id}/reschedule-cases", [
            'reason' => 'نیاز به هماهنگی مجدد زمان جلسه.',
        ])->assertCreated();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meetings/{$meeting->id}/execution/start")->assertOk();
        $this->assertSame('MR-'.now()->format('Y').'-00001', $request->fresh()->request_number);
        $this->assertGreaterThanOrEqual(5, $request->actions()->count());
        $this->assertGreaterThanOrEqual(1, $director->fresh()->notifications()->where('title','جلسه ایجاد شد')->count());
    }

    public function test_schedule_cannot_be_confirmed_before_scheduling_starts(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $director = User::where('email','tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number',['EMP-TECH-001','EMP-TECH-003'])->pluck('id')->all();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'آزمون ترتیب زمان‌بندی','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>$employees])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();

        $schedule = ['meeting_date'=>now()->addWeek()->format('Y-m-d'),'start_time'=>'09:00','duration_minutes'=>60,'location'=>'اتاق جلسات تکنالوژی','chairperson_employee_id'=>$employees[0],'participant_ids'=>$employees];
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule", $schedule)
            ->assertStatus(422)
            ->assertJsonPath('code','SCHEDULING_NOT_STARTED');
        $this->assertSame('APPROVED', $request->fresh()->status);
    }

    public function test_system_admin_cannot_list_business_meeting_requests(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $system = User::where('email','system.admin@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employee = Employee::where('employee_number','EMP-TECH-003')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'درخواست محرمانه کاری','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>[$employee->id]])->assertCreated();

        $response = $this->actingAs($system, 'sanctum')->getJson('/api/meeting-requests')->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_creator_without_current_executive_scope_cannot_schedule(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $director = User::where('email','tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employee = Employee::where('employee_number','EMP-TECH-003')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'آزمون سلب صلاحیت','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>[$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();

        $exec->roleScopes()->delete();
        $exec->syncRoles([]);

        $this->actingAs($exec->fresh(), 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertStatus(403);
        $this->assertSame('APPROVED', $request->fresh()->status);
    }

    public function test_seeded_finance_executive_position_belongs_to_finance_management(): void
    {
        $financeExec = Employee::where('employee_number','EMP-FIN-002')->with('position')->firstOrFail();
        $financeExecUnit = OrganizationalUnit::where('code','FIN-EXEC')->firstOrFail();

        $this->assertSame($financeExecUnit->id, $financeExec->organizational_unit_id);
        $this->assertSame($financeExecUnit->id, $financeExec->position->organizational_unit_id);
        $this->assertSame('EXECUTIVE_MANAGEMENT', $financeExec->position->position_type);
    }

    public function test_login_contract_and_disabled_account_rejection(): void
    {
        $response = $this->postJson('/api/login', ['email'=>'tech.exec@example.test','password'=>'ChangeMe!2026']);
        $response->assertOk()->assertJsonStructure(['success','data'=>['token','user'=>['id','email','status','roles']],'message']);
        User::where('email','tech.exec@example.test')->update(['status'=>'INACTIVE']);
        $this->postJson('/api/login', ['email'=>'tech.exec@example.test','password'=>'ChangeMe!2026'])->assertStatus(422)->assertJsonPath('code','VALIDATION_ERROR')->assertJsonStructure(['errors']);
    }

    public function test_attachment_download_requires_request_access(): void
    {
        Storage::fake('local');
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $financeExec = User::where('email','finance.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employee = Employee::where('employee_number','EMP-TECH-003')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'ضمیمهٔ امن','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>[$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $upload = $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/attachments", ['attachment'=>UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]);
        $upload->assertCreated();
        $attachment = $request->attachments()->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->actingAs($financeExec, 'sanctum')->getJson("/api/meeting-request-attachments/{$attachment->id}/download")->assertStatus(403);
        $this->actingAs($exec, 'sanctum')->get("/api/meeting-request-attachments/{$attachment->id}/download")->assertOk();
    }

    public function test_inactive_user_cannot_submit_request(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employee = Employee::where('employee_number','EMP-TECH-003')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'درخواست غیرفعال','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>[$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $exec->update(['status'=>'INACTIVE']);
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertStatus(403);
    }

    public function test_non_admin_cannot_access_admin_directory(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->getJson('/api/admin/users')->assertStatus(403);
    }

    public function test_finance_director_cannot_approve_technology_request(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email','finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employee = Employee::where('employee_number','EMP-TECH-003')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', ['requesting_unit_id'=>$tech->id,'subject'=>'درخواست داخلی تکنالوژی','purpose'=>'COORDINATION','priority'=>'NORMAL','participant_ids'=>[$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertStatus(403)->assertJsonPath('code','UNAUTHORIZED_APPROVER');
    }

    public function test_request_action_capabilities_are_scoped_to_the_current_workflow_step(): void
    {
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'قابلیت‌های اقدامات قابل انجام',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$employee->id],
        ])->assertCreated();

        $request = MeetingRequest::latest('id')->firstOrFail();

        $this->actingAs($exec, 'sanctum')
            ->getJson("/api/meeting-requests/{$request->id}/action-capabilities")
            ->assertOk()
            ->assertJsonPath('data.edit', true)
            ->assertJsonPath('data.submit', true)
            ->assertJsonPath('data.approve', false);

        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();

        $this->actingAs($director, 'sanctum')
            ->getJson("/api/meeting-requests/{$request->id}/action-capabilities")
            ->assertOk()
            ->assertJsonPath('data.approve', true)
            ->assertJsonPath('data.return_for_revision', true)
            ->assertJsonPath('data.reject', true)
            ->assertJsonPath('data.start_scheduling', false);

        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();

        $this->actingAs($exec, 'sanctum')
            ->getJson("/api/meeting-requests/{$request->id}/action-capabilities")
            ->assertOk()
            ->assertJsonPath('data.start_scheduling', true)
            ->assertJsonPath('data.approve', false);
    }
}
