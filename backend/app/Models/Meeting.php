<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Meeting extends Model { protected $fillable = ['meeting_request_id','meeting_number','title','description','organizing_unit_id','coordinator_id','chairperson_employee_id','meeting_date','start_time','end_time','location','agenda','status','current_schedule_version_id','active_reschedule_case_id','current_participant_version_id','active_participant_change_case_id','current_terminal_session_id']; protected $casts = ['meeting_date'=>'date']; public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); } public function chairperson(): BelongsTo { return $this->belongsTo(Employee::class, 'chairperson_employee_id'); } public function participants(): HasMany { return $this->hasMany(MeetingParticipant::class); }
    public function units(): HasMany { return $this->hasMany(MeetingUnit::class); }
    public function authorityContext(): HasOne { return $this->hasOne(MeetingAuthorityContext::class); }
    public function currentScheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'current_schedule_version_id'); }
    public function activeRescheduleCase(): BelongsTo { return $this->belongsTo(MeetingRescheduleCase::class, 'active_reschedule_case_id'); }
    public function scheduleVersions(): HasMany { return $this->hasMany(MeetingScheduleVersion::class); }
    public function rescheduleCases(): HasMany { return $this->hasMany(MeetingRescheduleCase::class); }
    public function rescheduleActions(): HasMany { return $this->hasMany(MeetingAction::class); }
    public function currentParticipantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'current_participant_version_id'); }
    public function participantVersions(): HasMany { return $this->hasMany(MeetingParticipantVersion::class); }
    public function activeParticipantChangeCase(): BelongsTo { return $this->belongsTo(MeetingParticipantChangeCase::class, 'active_participant_change_case_id'); }
    public function participantChangeCases(): HasMany { return $this->hasMany(MeetingParticipantChangeCase::class); }
    public function terminalAssignments(): HasMany { return $this->hasMany(MeetingTerminalAssignment::class); }
    public function terminalSessions(): HasMany { return $this->hasMany(TerminalMeetingSession::class); }
    public function currentTerminalSession(): BelongsTo { return $this->belongsTo(TerminalMeetingSession::class, 'current_terminal_session_id'); }
    public function attendanceRecords(): HasMany { return $this->hasMany(MeetingAttendanceRecord::class); }
    public function executionSession(): HasOne { return $this->hasOne(MeetingExecutionSession::class); }
    public function minutes(): HasOne { return $this->hasOne(MeetingMinutes::class); }
}