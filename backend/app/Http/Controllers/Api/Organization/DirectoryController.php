<?php

namespace App\Http\Controllers\Api\Organization;

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
use App\Support\MeetingActionCapabilities;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function dashboard(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $user = $request->user();
        $base = $this->scopeMeetingRequests(MeetingRequest::query(), $user);
        $meetingBase = $this->scopeMeetings(Meeting::query(), $user);
        $data = [
            'my_requests' => (clone $base)->where('created_by', $user->id)->count(),
            'returned' => (clone $base)->where('status', 'RETURNED_FOR_REVISION')->count(),
            'pending_approval' => (clone $base)->where('status', 'PENDING_INTERNAL_APPROVAL')->count(),
            'approved_for_scheduling' => (clone $base)->whereIn('status', ['APPROVED','SCHEDULING','SCHEDULE_CONFIRMED'])->count(),
            'upcoming_meetings' => (clone $meetingBase)->where('meeting_date', '>=', today())->count(),
        ];
        return response()->json(['success'=>true,'data'=>$data,'message'=>'داشبورد کاربر.']);
    }

    public function approvals(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $user = $request->user();
        $directorUnitIds = $user->scopedRoleUnitIds('DIRECTOR');
        $query = MeetingRequest::with(['requestingUnit','creator.employee','participants.employee'])
            ->where('status','PENDING_INTERNAL_APPROVAL')
            ->whereIn('requesting_unit_id', $directorUnitIds ?: [-1]);
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

        $baseQuery = $this->scopeMeetings(Meeting::query(), $user);
        $totalCount = (clone $baseQuery)->count();
        $upcomingCount = (clone $baseQuery)
            ->where('meeting_date', '>=', today())
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->count();
        $completedCount = (clone $baseQuery)->whereIn('status', ['HELD', 'COMPLETED'])->count();

        $query = $this->scopeMeetings(
            Meeting::with(['request.requestingUnit','request.authorityContext.authorityEmployee','authorityContext.authorityEmployee','authorityContext.authorityPosition','authorityContext.authorityOfficeUnit','chairperson','participants.employee.unit','currentScheduleVersion','activeRescheduleCase','currentParticipantVersion.members.employee.unit','activeParticipantChangeCase.items.oldEmployee','activeParticipantChangeCase.items.newEmployee','terminalAssignments.terminal','terminalAssignments.sessions','currentTerminalSession']),
            $user
        );

        if ($request->filled('search')) {
            $term = '%' . $request->string('search')->trim() . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                  ->orWhere('meeting_number', 'like', $term)
                  ->orWhere('location', 'like', $term);
            });
        }

        if ($request->filled('status') && $request->string('status') !== 'ALL') {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('unit_id')) {
            $unitId = $request->integer('unit_id');
            $query->where(function ($q) use ($unitId): void {
                $q->where('organizing_unit_id', $unitId)
                  ->orWhereHas('units', fn ($uq) => $uq->where('organizational_unit_id', $unitId))
                  ->orWhereHas('request', fn ($rq) => $rq->where('requesting_unit_id', $unitId));
            });
        }

        if ($request->boolean('upcoming_only')) {
            $query->where('meeting_date', '>=', today());
        }

        $query->orderByDesc('meeting_date')->orderByDesc('start_time');

        $perPage = min(max($request->integer('per_page', $request->integer('limit', 15)), 1), 50);
        $paginator = $query->paginate($perPage);

        return MeetingResource::collection($paginator)->additional([
            'success' => true,
            'message' => 'جلسات رسمی.',
            'stats' => [
                'total' => $totalCount,
                'upcoming' => $upcomingCount,
                'completed' => $completedCount,
            ],
        ]);
    }

    public function meetingActionCapabilities(Request $request, Meeting $meeting, MeetingActionCapabilities $capabilities)
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'success' => true,
            'data' => $capabilities->resolve($meeting, $request->user()),
        ]);
    }

    public function meeting(Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        return (new MeetingResource($meeting->load(['request.actions.actor','request.requestingUnit','request.authorityContext.authorityEmployee','authorityContext.organizationAuthority','authorityContext.authorityEmployee','authorityContext.authorityPosition','authorityContext.authorityOfficeUnit','units.unit','chairperson','participants.employee.unit','currentScheduleVersion','activeRescheduleCase.proposals.acknowledgements.unit','activeRescheduleCase.alternativeRequests.unit','scheduleVersions','rescheduleActions.actor','currentParticipantVersion.members.employee.unit','participantVersions.members.employee.unit','activeParticipantChangeCase.items.oldEmployee','activeParticipantChangeCase.items.newEmployee','activeParticipantChangeCase.ownerUnit','terminalAssignments.terminal','terminalAssignments.sessions','currentTerminalSession'])))->additional(['success'=>true,'message'=>'جزئیات جلسه.']);
    }

    public function notifications(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $baseQuery = $request->user()->notifications();
        $totalCount = (clone $baseQuery)->count();
        $unreadCount = (clone $baseQuery)->whereNull('read_at')->count();

        $query = (clone $baseQuery)->latest();
        if ($request->query('filter') === 'UNREAD' || $request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }
        $perPage = min(max($request->integer('per_page', $request->integer('limit', 15)), 1), 50);
        $paginator = $query->paginate($perPage);

        return NotificationResource::collection($paginator)->additional([
            'success' => true,
            'message' => 'اعلان‌های شما.',
            'unread_count' => $unreadCount,
            'total_count' => $totalCount,
        ]);
    }

    public function markNotificationRead(Request $request, AmmsNotification $notification)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        abort_unless($notification->user_id === $request->user()->id, 403, 'FORBIDDEN');
        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }
        $unreadCount = $request->user()->notifications()->whereNull('read_at')->count();

        return (new NotificationResource($notification))->additional([
            'success' => true,
            'message' => 'اعلان خوانده شد.',
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'کلیه اعلان‌ها به عنوان خوانده‌شده علامت‌گذاری شدند.',
            'unread_count' => 0,
        ]);
    }

    public function unreadCount(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $count = $request->user()->notifications()->whereNull('read_at')->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    private function scopeMeetingRequests($query, $user)
    {
        $unitIds = array_unique(array_merge($user->scopedRoleUnitIds('DIRECTOR'), $user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT')));
        $officeIds = array_unique(array_merge($user->scopedRoleUnitIds('AUTHORITY_OFFICE'), $user->scopedRoleUnitIds('EXECUTIVE_SECRETARY')));

        return $query->where(function ($q) use ($user, $unitIds, $officeIds): void {
            $q->whereIn('requesting_unit_id', $unitIds ?: [-1]);
            if ($unitIds) $q->orWhereHas('units', fn ($units) => $units->whereIn('role', ['REQUESTER','TARGET','PARTICIPATING'])->whereIn('organizational_unit_id', $unitIds));
            if ($officeIds) $q->orWhereHas('authorityContext', fn ($context) => $context->whereIn('authority_office_unit_id', $officeIds));
            if ($user->employee_id) $q->orWhereHas('authorityContext', fn ($context) => $context->where('authority_employee_id', $user->employee_id));
        });
    }

    private function scopeMeetings($query, $user)
    {
        $unitIds = array_unique(array_merge($user->scopedRoleUnitIds('DIRECTOR'), $user->scopedRoleUnitIds('DEPUTY_DIRECTOR'), $user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT')));
        $officeIds = array_unique(array_merge($user->scopedRoleUnitIds('AUTHORITY_OFFICE'), $user->scopedRoleUnitIds('EXECUTIVE_SECRETARY')));

        return $query->where(function ($q) use ($user, $unitIds, $officeIds): void {
            $q->whereIn('organizing_unit_id', $unitIds ?: [-1]);
            if ($unitIds) {
                $q->orWhereHas('units', fn ($units) => $units
                    ->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
                    ->whereIn('organizational_unit_id', $unitIds));
            }
            if ($user->employee_id) {
                $q->orWhereHas('participants', fn ($participants) => $participants->where('employee_id', $user->employee_id));
                $q->orWhereHas('authorityContext', fn ($context) => $context->where('authority_employee_id', $user->employee_id));
            }
            if ($officeIds) $q->orWhereHas('authorityContext', fn ($context) => $context->whereIn('authority_office_unit_id', $officeIds));
        });
    }
}
