<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'flow_type' => $this->flow_type,
            'requesting_unit_id' => $this->requesting_unit_id,
            'subject' => $this->subject,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'meeting_justification' => $this->meeting_justification,
            'requested_outcome' => $this->requested_outcome,
            'priority' => $this->priority,
            'status' => $this->status,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'preferred_start_time' => $this->preferred_start_time,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'proposed_location' => $this->proposed_location,
            'requesting_unit' => new OrganizationalUnitResource($this->whenLoaded('requestingUnit')),
            'units' => MeetingRequestUnitResource::collection($this->whenLoaded('units')),
            'requester_approved_by' => $this->requester_approved_by,
            'requester_approved_at' => $this->requester_approved_at,
            'target_approved_by' => $this->target_approved_by,
            'target_approved_at' => $this->target_approved_at,
            'lead_coordinator_unit_id' => $this->lead_coordinator_unit_id,
            'lead_coordinator_user_id' => $this->lead_coordinator_user_id,
            'requester_participants_confirmed_at' => $this->requester_participants_confirmed_at,
            'target_participants_confirmed_at' => $this->target_participants_confirmed_at,
            'schedule_confirmed_at' => $this->schedule_confirmed_at,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'coordinator' => new UserResource($this->whenLoaded('coordinator')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'participants' => MeetingRequestParticipantResource::collection($this->whenLoaded('participants')),
            'attachments' => MeetingRequestAttachmentResource::collection($this->whenLoaded('attachments')),
            'actions' => MeetingRequestActionResource::collection($this->whenLoaded('actions')),
            'schedule_proposals' => MeetingScheduleProposalResource::collection($this->whenLoaded('scheduleProposals')),
            'nominations' => MeetingRequestParticipantNominationResource::collection($this->whenLoaded('nominations')),
            'unit_reviews' => MeetingRequestUnitReviewResource::collection($this->whenLoaded('unitReviews')),
            'participant_confirmations' => MeetingRequestUnitParticipantConfirmationResource::collection($this->whenLoaded('participantConfirmations')),
            'authority_context' => new MeetingRequestAuthorityContextResource($this->whenLoaded('authorityContext')),
            'authority_schedule_acknowledgements' => MeetingAuthorityScheduleAcknowledgementResource::collection($this->whenLoaded('authorityScheduleAcknowledgements')),
            'authority_schedule_unit_acknowledgements' => MeetingAuthorityScheduleUnitAcknowledgementResource::collection($this->whenLoaded('authorityScheduleUnitAcknowledgements')),
            'authority_participant_confirmations' => MeetingAuthorityParticipantConfirmationResource::collection($this->whenLoaded('authorityParticipantConfirmations')),
            'meeting' => new MeetingResource($this->whenLoaded('meeting')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
