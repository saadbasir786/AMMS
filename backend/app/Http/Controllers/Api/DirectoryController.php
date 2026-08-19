<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\MeetingRequestResource;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\OrganizationalUnitResource;
use App\Models\AmmsNotification;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationAuthority;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function dashboard(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $user = $request->user();
        $base = MeetingRequest::query();
        if (!$user->hasScopedRole('SYSTEM_ADMIN')) $base->where(fn($q) => $q->where('created_by', $user->id)->orWhereIn('requesting_unit_id', array_unique(array_merge($user->scopedRoleUnitIds('DIRECTOR'), $user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'), $user->scopedRoleUnitIds('MEETING_COORDINATOR'))) ?: [-1]));
        $data = [
            'my_requests' => (clone $base)->where('created_by', $user->id)->count(),
            'returned' => (clone $base)->where('status', 'RETURNED_FOR_REVISION')->count(),
            'pending_approval' => (clone $base)->where('status', 'PENDING_INTERNAL_APPROVAL')->count(),
            'approved_for_scheduling' => (clone $base)->whereIn('status', ['APPROVED','SCHEDULING','SCHEDULE_CONFIRMED'])->count(),
            'upcoming_meetings' => Meeting::where('meeting_date', '>=', today())->count(),
        ];
        return response()->json(['success'=>true,'data'=>$data,'message'=>'داشبورد کاربر.']);
    }

    public function approvals(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $user = $request->user();
        $query = MeetingRequest::with(['requestingUnit','creator.employee','participants.employee'])->where('status','PENDING_INTERNAL_APPROVAL');
        if (!$user->hasScopedRole('SYSTEM_ADMIN')) $query->whereIn('requesting_unit_id', $user->scopedRoleUnitIds('DIRECTOR') ?: [-1]);
        return MeetingRequestResource::collection($query->latest()->paginate(15))->additional(['success'=>true,'message'=>'درخواست‌های منتظر تأیید.']);
    }

    public function units(Request $request)
    {
        $this->authorize('viewAny', OrganizationalUnit::class);
        return OrganizationalUnitResource::collection(OrganizationalUnit::with(['parent','children','organization'])->where('status','ACTIVE')->orderBy('name')->get())->additional(['success'=>true,'message'=>'ساختار تشکیلاتی.']);
    }

    public function employees(Request $request)
    {
        $this->authorize('viewAny', Employee::class);
        $user = $request->user();
        $employeesQuery = Employee::with(['unit','position'])->where('status','ACTIVE');
        $authorityOfficeIds = array_unique(array_merge($user->scopedRoleUnitIds('AUTHORITY_OFFICE'), $user->scopedRoleUnitIds('EXECUTIVE_SECRETARY')));
        if (!$user->hasScopedRole('SYSTEM_ADMIN') && $authorityOfficeIds && $user->can('authority-participant.manage') && !$user->can('employee.manage') && !$user->can('meeting-request.view')) {
            $authorityPositionIds = OrganizationAuthority::query()->whereIn('authority_office_unit_id', $authorityOfficeIds)->where('status', 'ACTIVE')->pluck('authority_position_id')->all();
            $employeesQuery->where(fn ($q) => $q->whereIn('organizational_unit_id', $authorityOfficeIds)->orWhereIn('position_id', $authorityPositionIds ?: [-1]));
        }
        $employees = $employeesQuery->when($request->filled('unit_id'), fn($q) => $q->where('organizational_unit_id', $request->integer('unit_id')))->orderBy('first_name')->get();
        return EmployeeResource::collection($employees)->additional(['success'=>true,'message'=>'فهرست کارمندان فعال.']);
    }

    public function meetings(Request $request)
    {
        $this->authorize('viewAny', Meeting::class);
        $user = $request->user();
        $query = Meeting::with(['request.requestingUnit','request.authorityContext.authorityEmployee','authorityContext.authorityEmployee','authorityContext.authorityPosition','authorityContext.authorityOfficeUnit','chairperson','participants.employee.unit'])->where('meeting_date','>=',today())->orderBy('meeting_date');
        if (!$user->hasScopedRole('SYSTEM_ADMIN')) $query->where(fn($q) => $q->where('coordinator_id',$user->id)->orWhereIn('organizing_unit_id', array_unique(array_merge($user->scopedRoleUnitIds('DIRECTOR'), $user->scopedRoleUnitIds('MEETING_COORDINATOR'))) ?: [-1]));
        return MeetingResource::collection($query->paginate(15))->additional(['success'=>true,'message'=>'جلسات رسمی.']);
    }

    public function meeting(Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        return (new MeetingResource($meeting->load(['request.actions.actor','request.requestingUnit','request.authorityContext.authorityEmployee','authorityContext.organizationAuthority','authorityContext.authorityEmployee','authorityContext.authorityPosition','authorityContext.authorityOfficeUnit','units.unit','chairperson','participants.employee.unit'])))->additional(['success'=>true,'message'=>'جزئیات جلسه.']);
    }

    public function notifications(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        return NotificationResource::collection($request->user()->notifications()->latest()->paginate(20))->additional(['success'=>true,'message'=>'اعلان‌های شما.']);
    }

    public function markNotificationRead(Request $request, AmmsNotification $notification)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        abort_unless($notification->user_id === $request->user()->id, 403, 'FORBIDDEN');
        $notification->update(['read_at'=>now()]);
        return (new NotificationResource($notification))->additional(['success'=>true,'message'=>'اعلان خوانده شد.']);
    }
}
