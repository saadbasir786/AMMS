<?php

use App\Http\Controllers\Api\MeetingRequests\AuthorityInitiatedMeetingRequestController;
use App\Http\Controllers\Api\MeetingRequests\HighAuthorityMeetingRequestController;
use App\Http\Controllers\Api\MeetingRequests\InterDirectorateMeetingRequestController;
use App\Http\Controllers\Api\MeetingRequests\InternalMeetingApprovalDelegationController;
use App\Http\Controllers\Api\MeetingRequests\MeetingRequestAttachmentController;
use App\Http\Controllers\Api\MeetingRequests\MeetingRequestController;
use App\Http\Controllers\Api\MeetingRequests\MultiDirectorateMeetingRequestController;
use App\Http\Controllers\Api\MeetingRequests\MultiHighAuthorityMeetingRequestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Meetings\ScheduleConflictController;

Route::middleware('auth:sanctum')->group(function () {
    // -------------------------------------------------------------------------
    // 1. Internal Meeting Requests
    // -------------------------------------------------------------------------
    Route::get('/meeting-requests', [MeetingRequestController::class, 'index']);
    Route::get('/internal-meeting-approval-delegations', [InternalMeetingApprovalDelegationController::class, 'index']);
    Route::post('/internal-meeting-approval-delegations', [InternalMeetingApprovalDelegationController::class, 'store']);
    Route::delete('/internal-meeting-approval-delegations/{capabilityScope}', [InternalMeetingApprovalDelegationController::class, 'revoke']);
    Route::post('/meeting-requests', [MeetingRequestController::class, 'store']);
    Route::get('/meeting-requests/{meetingRequest}/action-capabilities', [MeetingRequestController::class, 'actionCapabilities']);
    Route::get('/meeting-requests/{meetingRequest}/schedule-conflicts', [ScheduleConflictController::class, 'request']);
    Route::get('/meeting-requests/{meetingRequest}', [MeetingRequestController::class, 'show']);
    Route::put('/meeting-requests/{meetingRequest}', [MeetingRequestController::class, 'update']);
    Route::post('/meeting-requests/{meetingRequest}/submit', [MeetingRequestController::class, 'submit']);
    Route::post('/meeting-requests/{meetingRequest}/approve', [MeetingRequestController::class, 'approve']);
    Route::post('/meeting-requests/{meetingRequest}/return', [MeetingRequestController::class, 'returnForRevision']);
    Route::post('/meeting-requests/{meetingRequest}/reject', [MeetingRequestController::class, 'reject']);
    Route::post('/meeting-requests/{meetingRequest}/start-scheduling', [MeetingRequestController::class, 'startScheduling']);
    Route::post('/meeting-requests/{meetingRequest}/confirm-schedule', [MeetingRequestController::class, 'confirmSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/finalize', [MeetingRequestController::class, 'finalize']);

    // -------------------------------------------------------------------------
    // 2. Bilateral / Inter-Directorate Meeting Requests
    // -------------------------------------------------------------------------
    Route::post('/meeting-requests/inter-directorate', [InterDirectorateMeetingRequestController::class, 'store']);
    Route::get('/incoming-meeting-requests', [InterDirectorateMeetingRequestController::class, 'incoming']);
    Route::post('/meeting-requests/{meetingRequest}/submit-inter-directorate', [InterDirectorateMeetingRequestController::class, 'submit']);
    Route::post('/meeting-requests/{meetingRequest}/requester-approve', [InterDirectorateMeetingRequestController::class, 'requesterApprove']);
    Route::post('/meeting-requests/{meetingRequest}/requester-return', [InterDirectorateMeetingRequestController::class, 'requesterReturn']);
    Route::post('/meeting-requests/{meetingRequest}/requester-reject', [InterDirectorateMeetingRequestController::class, 'requesterReject']);
    Route::post('/meeting-requests/{meetingRequest}/resubmit-requester', [InterDirectorateMeetingRequestController::class, 'resubmitRequester']);
    Route::post('/meeting-requests/{meetingRequest}/target-review', [InterDirectorateMeetingRequestController::class, 'targetReview']);
    Route::post('/meeting-requests/{meetingRequest}/target-approve', [InterDirectorateMeetingRequestController::class, 'targetApprove']);
    Route::post('/meeting-requests/{meetingRequest}/target-return', [InterDirectorateMeetingRequestController::class, 'targetReturn']);
    Route::post('/meeting-requests/{meetingRequest}/target-reject', [InterDirectorateMeetingRequestController::class, 'targetReject']);
    Route::post('/meeting-requests/{meetingRequest}/resubmit-to-target', [InterDirectorateMeetingRequestController::class, 'resubmitTarget']);
    Route::post('/meeting-requests/{meetingRequest}/start-inter-directorate-scheduling', [InterDirectorateMeetingRequestController::class, 'startScheduling']);
    Route::post('/meeting-requests/{meetingRequest}/schedule-proposals', [InterDirectorateMeetingRequestController::class, 'proposeSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/schedule-proposals/{proposal}/accept', [InterDirectorateMeetingRequestController::class, 'acceptSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/participant-nominations', [InterDirectorateMeetingRequestController::class, 'nominate']);
    Route::post('/meeting-requests/{meetingRequest}/requester-participants/confirm', [InterDirectorateMeetingRequestController::class, 'confirmRequesterParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/target-participants/confirm', [InterDirectorateMeetingRequestController::class, 'confirmTargetParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/confirm-inter-directorate-schedule', [InterDirectorateMeetingRequestController::class, 'confirmSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/finalize-inter-directorate', [InterDirectorateMeetingRequestController::class, 'finalize']);

    // -------------------------------------------------------------------------
    // 3. Multi-Directorate Meeting Requests
    // -------------------------------------------------------------------------
    Route::post('/meeting-requests/multi-directorate', [MultiDirectorateMeetingRequestController::class, 'store']);
    Route::get('/incoming-multi-directorate-meeting-requests', [MultiDirectorateMeetingRequestController::class, 'incoming']);
    Route::put('/meeting-requests/{meetingRequest}/multi', [MultiDirectorateMeetingRequestController::class, 'update']);
    Route::post('/meeting-requests/{meetingRequest}/submit-multi', [MultiDirectorateMeetingRequestController::class, 'submit']);
    Route::post('/meeting-requests/{meetingRequest}/multi/requester-approve', [MultiDirectorateMeetingRequestController::class, 'requesterApprove']);
    Route::post('/meeting-requests/{meetingRequest}/multi/requester-return', [MultiDirectorateMeetingRequestController::class, 'requesterReturn']);
    Route::post('/meeting-requests/{meetingRequest}/multi/requester-reject', [MultiDirectorateMeetingRequestController::class, 'requesterReject']);
    Route::post('/meeting-requests/{meetingRequest}/multi/target-units/{unit}/review', [MultiDirectorateMeetingRequestController::class, 'targetReview']);
    Route::post('/meeting-requests/{meetingRequest}/multi/target-units/{unit}/approve', [MultiDirectorateMeetingRequestController::class, 'targetApprove']);
    Route::post('/meeting-requests/{meetingRequest}/multi/target-units/{unit}/return', [MultiDirectorateMeetingRequestController::class, 'targetReturn']);
    Route::post('/meeting-requests/{meetingRequest}/multi/target-units/{unit}/reject', [MultiDirectorateMeetingRequestController::class, 'targetReject']);
    Route::post('/meeting-requests/{meetingRequest}/resubmit-multi', [MultiDirectorateMeetingRequestController::class, 'resubmit']);
    Route::post('/meeting-requests/{meetingRequest}/start-multi-scheduling', [MultiDirectorateMeetingRequestController::class, 'startScheduling']);
    Route::post('/meeting-requests/{meetingRequest}/multi/schedule-proposals', [MultiDirectorateMeetingRequestController::class, 'proposeSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/multi/schedule-proposals/{proposal}/accept', [MultiDirectorateMeetingRequestController::class, 'acceptSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/multi/units/{unit}/participants', [MultiDirectorateMeetingRequestController::class, 'nominate']);
    Route::post('/meeting-requests/{meetingRequest}/multi/units/{unit}/participants/confirm', [MultiDirectorateMeetingRequestController::class, 'confirmParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/confirm-multi-schedule', [MultiDirectorateMeetingRequestController::class, 'confirmSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/finalize-multi', [MultiDirectorateMeetingRequestController::class, 'finalize']);

    // -------------------------------------------------------------------------
    // 4. Directorate with High Authority Meeting Requests
    // -------------------------------------------------------------------------
    Route::post('/meeting-requests/high-authority', [HighAuthorityMeetingRequestController::class, 'store']);
    Route::get('/incoming-high-authority-office-meeting-requests', [HighAuthorityMeetingRequestController::class, 'officeInbox']);
    Route::get('/incoming-high-authority-decisions', [HighAuthorityMeetingRequestController::class, 'authorityInbox']);
    Route::put('/meeting-requests/{meetingRequest}/high-authority', [HighAuthorityMeetingRequestController::class, 'update']);
    Route::post('/meeting-requests/{meetingRequest}/submit-high-authority', [HighAuthorityMeetingRequestController::class, 'submit']);
    Route::post('/meeting-requests/{meetingRequest}/high-authority/requester-approve', [HighAuthorityMeetingRequestController::class, 'requesterApprove']);
    Route::post('/meeting-requests/{meetingRequest}/high-authority/requester-return', [HighAuthorityMeetingRequestController::class, 'requesterReturn']);
    Route::post('/meeting-requests/{meetingRequest}/high-authority/requester-reject', [HighAuthorityMeetingRequestController::class, 'requesterReject']);
    Route::post('/meeting-requests/{meetingRequest}/resubmit-high-authority', [HighAuthorityMeetingRequestController::class, 'resubmit']);
    Route::post('/meeting-requests/{meetingRequest}/authority-office/review', [HighAuthorityMeetingRequestController::class, 'officeReview']);
    Route::post('/meeting-requests/{meetingRequest}/authority-office/return', [HighAuthorityMeetingRequestController::class, 'officeReturn']);
    Route::post('/meeting-requests/{meetingRequest}/authority-office/forward', [HighAuthorityMeetingRequestController::class, 'officeForward']);
    Route::post('/meeting-requests/{meetingRequest}/authority/approve', [HighAuthorityMeetingRequestController::class, 'authorityApprove']);
    Route::post('/meeting-requests/{meetingRequest}/authority/return', [HighAuthorityMeetingRequestController::class, 'authorityReturn']);
    Route::post('/meeting-requests/{meetingRequest}/authority/reject', [HighAuthorityMeetingRequestController::class, 'authorityReject']);
    Route::post('/meeting-requests/{meetingRequest}/authority-scheduling/start', [HighAuthorityMeetingRequestController::class, 'startScheduling']);
    Route::post('/meeting-requests/{meetingRequest}/authority-schedule/proposals', [HighAuthorityMeetingRequestController::class, 'proposeSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/authority-schedule/proposals/{proposal}/acknowledge', [HighAuthorityMeetingRequestController::class, 'acknowledgeSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/authority-schedule/request-alternative', [HighAuthorityMeetingRequestController::class, 'requestAlternative']);
    Route::post('/meeting-requests/{meetingRequest}/authority-schedule/proposals/{proposal}/confirm', [HighAuthorityMeetingRequestController::class, 'confirmSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/high-authority/requester-participants', [HighAuthorityMeetingRequestController::class, 'updateRequesterParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/high-authority/requester-participants/confirm', [HighAuthorityMeetingRequestController::class, 'confirmRequesterParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/authority-participants', [HighAuthorityMeetingRequestController::class, 'updateAuthorityParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/authority-participants/confirm', [HighAuthorityMeetingRequestController::class, 'confirmAuthorityParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/finalize-high-authority', [HighAuthorityMeetingRequestController::class, 'finalize']);

    // -------------------------------------------------------------------------
    // 5. Multi-Directorate with High Authority Meeting Requests
    // -------------------------------------------------------------------------
    Route::post('/meeting-requests/multi-high-authority', [MultiHighAuthorityMeetingRequestController::class, 'store']);
    Route::put('/meeting-requests/{meetingRequest}/multi-high', [MultiHighAuthorityMeetingRequestController::class, 'update']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/submit', [MultiHighAuthorityMeetingRequestController::class, 'submit']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/requester-approve', [MultiHighAuthorityMeetingRequestController::class, 'requesterApprove']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/requester-return', [MultiHighAuthorityMeetingRequestController::class, 'requesterReturn']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/requester-reject', [MultiHighAuthorityMeetingRequestController::class, 'requesterReject']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/resubmit', [MultiHighAuthorityMeetingRequestController::class, 'resubmit']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/participating-units/{unit}/review', [MultiHighAuthorityMeetingRequestController::class, 'unitReview']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/participating-units/{unit}/approve', [MultiHighAuthorityMeetingRequestController::class, 'unitApprove']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/participating-units/{unit}/return', [MultiHighAuthorityMeetingRequestController::class, 'unitReturn']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/participating-units/{unit}/reject', [MultiHighAuthorityMeetingRequestController::class, 'unitReject']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-office/submit', [MultiHighAuthorityMeetingRequestController::class, 'authorityOfficeSubmit']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-office/review', [MultiHighAuthorityMeetingRequestController::class, 'authorityOfficeReview']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-office/return', [MultiHighAuthorityMeetingRequestController::class, 'authorityOfficeReturn']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-office/resubmit', [MultiHighAuthorityMeetingRequestController::class, 'authorityOfficeResubmit']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-office/forward', [MultiHighAuthorityMeetingRequestController::class, 'authorityOfficeForward']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority/approve', [MultiHighAuthorityMeetingRequestController::class, 'authorityApprove']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority/return', [MultiHighAuthorityMeetingRequestController::class, 'authorityReturn']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority/reject', [MultiHighAuthorityMeetingRequestController::class, 'authorityReject']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-scheduling/start', [MultiHighAuthorityMeetingRequestController::class, 'schedulingStart']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-schedule/proposals', [MultiHighAuthorityMeetingRequestController::class, 'schedulePropose']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-schedule/acknowledge', [MultiHighAuthorityMeetingRequestController::class, 'scheduleAcknowledge']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-schedule/request-alternative', [MultiHighAuthorityMeetingRequestController::class, 'scheduleAlternative']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-schedule/confirm', [MultiHighAuthorityMeetingRequestController::class, 'scheduleConfirm']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/units/{unit}/participants', [MultiHighAuthorityMeetingRequestController::class, 'unitParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/units/{unit}/participants/confirm', [MultiHighAuthorityMeetingRequestController::class, 'unitParticipantsConfirm']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-participants', [MultiHighAuthorityMeetingRequestController::class, 'authorityParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/authority-participants/confirm', [MultiHighAuthorityMeetingRequestController::class, 'authorityParticipantsConfirm']);
    Route::post('/meeting-requests/{meetingRequest}/multi-high/finalize', [MultiHighAuthorityMeetingRequestController::class, 'finalize']);

    // -------------------------------------------------------------------------
    // 6. High Authority Initiated Meeting Requests
    // -------------------------------------------------------------------------
    Route::post('/meeting-requests/authority-initiated', [AuthorityInitiatedMeetingRequestController::class, 'store']);
    Route::get('/incoming-authority-initiated-meeting-requests', [AuthorityInitiatedMeetingRequestController::class, 'officeInbox']);
    Route::put('/meeting-requests/{meetingRequest}/authority-initiated', [AuthorityInitiatedMeetingRequestController::class, 'update']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/submit', [AuthorityInitiatedMeetingRequestController::class, 'submit']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/return', [AuthorityInitiatedMeetingRequestController::class, 'returnDraft']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/resubmit', [AuthorityInitiatedMeetingRequestController::class, 'resubmit']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/issue', [AuthorityInitiatedMeetingRequestController::class, 'issue']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/distribute', [AuthorityInitiatedMeetingRequestController::class, 'distribute']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/units/{unit}/coordinate', [AuthorityInitiatedMeetingRequestController::class, 'startCoordination']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/units/{unit}/acknowledge', [AuthorityInitiatedMeetingRequestController::class, 'acknowledge']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/units/{unit}/clarification', [AuthorityInitiatedMeetingRequestController::class, 'requestClarification']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/clarifications/{clarification}/respond', [AuthorityInitiatedMeetingRequestController::class, 'respondClarification']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/revision', [AuthorityInitiatedMeetingRequestController::class, 'prepareRevision']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/reissue', [AuthorityInitiatedMeetingRequestController::class, 'reissue']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/scheduling/start', [AuthorityInitiatedMeetingRequestController::class, 'startScheduling']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/schedule/proposals', [AuthorityInitiatedMeetingRequestController::class, 'proposeSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/schedule/acknowledge', [AuthorityInitiatedMeetingRequestController::class, 'acknowledgeSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/schedule/alternative', [AuthorityInitiatedMeetingRequestController::class, 'scheduleAlternative']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/schedule/confirm', [AuthorityInitiatedMeetingRequestController::class, 'confirmSchedule']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/units/{unit}/participants', [AuthorityInitiatedMeetingRequestController::class, 'unitParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/units/{unit}/participants/confirm', [AuthorityInitiatedMeetingRequestController::class, 'unitParticipantsConfirm']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/authority-participants', [AuthorityInitiatedMeetingRequestController::class, 'authorityParticipants']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/authority-participants/confirm', [AuthorityInitiatedMeetingRequestController::class, 'authorityParticipantsConfirm']);
    Route::post('/meeting-requests/{meetingRequest}/authority-initiated/finalize', [AuthorityInitiatedMeetingRequestController::class, 'finalize']);

    // -------------------------------------------------------------------------
    // 7. Meeting Request Attachments
    // -------------------------------------------------------------------------
    Route::post('/meeting-requests/{meetingRequest}/attachments', [MeetingRequestAttachmentController::class, 'store']);
    Route::get('/meeting-request-attachments/{attachment}/download', [MeetingRequestAttachmentController::class, 'download'])->name('api.meeting-request-attachments.download');
    Route::delete('/meeting-request-attachments/{attachment}', [MeetingRequestAttachmentController::class, 'destroy']);
});
