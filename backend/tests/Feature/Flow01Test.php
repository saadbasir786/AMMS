<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\MeetingRequestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Flow01Test extends TestCase
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

        $coordinator = User::where('email','tech.exec@example.test')->firstOrFail();
        $this->actingAs($coordinator, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk();
        $schedule = ['meeting_date'=>now()->addWeek()->format('Y-m-d'),'start_time'=>'09:00','duration_minutes'=>60,'location'=>'اتاق جلسات تکنالوژی','chairperson_employee_id'=>$employees[0],'participant_ids'=>$employees];
        $this->actingAs($coordinator, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule",$schedule)->assertOk()->assertJsonPath('data.status','SCHEDULE_CONFIRMED');
        $finalize = ['meeting_date'=>$schedule['meeting_date'],'start_time'=>'09:00','end_time'=>'10:00','location'=>$schedule['location'],'chairperson_employee_id'=>$employees[0],'agenda'=>'بررسی پلان توسعه سیستم‌ها'];
        $this->actingAs($coordinator, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize",$finalize)->assertOk();
        $this->actingAs($coordinator, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize",$finalize)->assertStatus(422)->assertJsonPath('code','SCHEDULE_INCOMPLETE');
        $this->assertSame(1, Meeting::where('meeting_request_id',$request->id)->count());
        $this->assertSame('MT-'.now()->format('Y').'-00001', Meeting::where('meeting_request_id',$request->id)->firstOrFail()->meeting_number);
        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $this->assertSame('MR-'.now()->format('Y').'-00001', $request->fresh()->request_number);
        $this->assertGreaterThanOrEqual(5, $request->actions()->count());
        $this->assertGreaterThanOrEqual(1, $director->fresh()->notifications()->where('title','جلسه ایجاد شد')->count());
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
}
