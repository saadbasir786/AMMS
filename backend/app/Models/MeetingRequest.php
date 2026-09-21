<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingRequest extends Model
{
    protected $fillable = ['request_number','flow_type','requesting_unit_id','created_by','subject','purpose','description','directive','meeting_justification','requested_outcome','proposed_agenda','priority','preferred_date','preferred_start_time','estimated_duration_minutes','proposed_location','scheduled_date','scheduled_start_time','scheduled_duration_minutes','scheduled_location','scheduled_venue_type','scheduled_venue_id','scheduled_venue_snapshot','scheduled_chairperson_employee_id','status','coordinator_id','approved_by','approved_at','lead_coordinator_unit_id','lead_coordinator_user_id','requester_approved_by','requester_approved_at','target_approved_by','target_approved_at','requester_participants_confirmed_by','requester_participants_confirmed_at','target_participants_confirmed_by','target_participants_confirmed_at','schedule_confirmed_by','schedule_confirmed_at'];
    protected $casts = ['preferred_date'=>'date','scheduled_date'=>'date','scheduled_venue_snapshot'=>'array','approved_at'=>'datetime','requester_approved_at'=>'datetime','target_approved_at'=>'datetime','requester_participants_confirmed_at'=>'datetime','target_participants_confirmed_at'=>'datetime','schedule_confirmed_at'=>'datetime'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function requestingUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'requesting_unit_id'); }
    public function coordinator(): BelongsTo { return $this->belongsTo(User::class, 'coordinator_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function scheduledChairperson(): BelongsTo { return $this->belongsTo(Employee::class, 'scheduled_chairperson_employee_id'); }
    public function participants(): HasMany { return $this->hasMany(MeetingRequestParticipant::class); }
    public function attachments(): HasMany { return $this->hasMany(MeetingRequestAttachment::class); }
    public function actions(): HasMany { return $this->hasMany(MeetingRequestAction::class)->orderBy('created_at'); }
    public function meeting(): HasOne { return $this->hasOne(Meeting::class); }
    public function leadCoordinatorUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'lead_coordinator_unit_id'); }
    public function leadCoordinator(): BelongsTo { return $this->belongsTo(User::class, 'lead_coordinator_user_id'); }
    public function requesterApprover(): BelongsTo { return $this->belongsTo(User::class, 'requester_approved_by'); }
    public function targetApprover(): BelongsTo { return $this->belongsTo(User::class, 'target_approved_by'); }
    public function scheduleProposals(): HasMany { return $this->hasMany(MeetingScheduleProposal::class); }
    public function units(): HasMany { return $this->hasMany(MeetingRequestUnit::class); }
    public function nominations(): HasMany { return $this->hasMany(MeetingRequestParticipantNomination::class); }
    public function unitReviews(): HasMany { return $this->hasMany(MeetingRequestUnitReview::class); }
    public function participantConfirmations(): HasMany { return $this->hasMany(MeetingRequestUnitParticipantConfirmation::class); }
    public function authorityContext(): HasOne { return $this->hasOne(MeetingRequestAuthorityContext::class); }
    public function authorityScheduleAcknowledgements(): HasMany { return $this->hasMany(MeetingAuthorityScheduleAcknowledgement::class); }
    public function authorityScheduleUnitAcknowledgements(): HasMany { return $this->hasMany(MeetingAuthorityScheduleUnitAcknowledgement::class); }
    public function authorityParticipantConfirmations(): HasMany { return $this->hasMany(MeetingAuthorityParticipantConfirmation::class); }
    public function directiveVersions(): HasMany { return $this->hasMany(MeetingRequestDirectiveVersion::class); }
    public function currentDirectiveVersion(): HasOne { return $this->hasOne(MeetingRequestDirectiveVersion::class)->where('is_current', true); }
    public function unitResponses(): HasMany { return $this->hasMany(MeetingRequestUnitResponse::class); }
    public function directiveClarifications(): HasMany { return $this->hasMany(MeetingRequestDirectiveClarification::class); }
}
