<?php

use App\Http\Controllers\Api\Meetings\MeetingExceptionController;
use App\Http\Controllers\Api\Meetings\MeetingExecutionController;
use App\Http\Controllers\Api\Meetings\MeetingMinutesController;
use App\Http\Controllers\Api\Meetings\MeetingParticipantChangeController;
use App\Http\Controllers\Api\Meetings\MeetingRescheduleController;
use App\Http\Controllers\Api\Meetings\MinutesApprovalDelegationController;
use App\Http\Controllers\Api\Organization\DirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Meetings Overview & Details
    Route::get('/meetings', [DirectoryController::class, 'meetings']);
    Route::get('/meetings/{meeting}/action-capabilities', [DirectoryController::class, 'meetingActionCapabilities']);
    Route::get('/meetings/{meeting}', [DirectoryController::class, 'meeting']);

    // Directorate minutes-review delegation
    Route::get('/minutes-approval-delegations', [MinutesApprovalDelegationController::class, 'index']);
    Route::post('/minutes-approval-delegations', [MinutesApprovalDelegationController::class, 'store']);
    Route::delete('/minutes-approval-delegations/{capabilityScope}', [MinutesApprovalDelegationController::class, 'revoke']);

    // Execution Lifecycle (Start / Adjourn / Complete)
    Route::get('/meetings/{meeting}/execution', [MeetingExecutionController::class, 'show']);
    Route::post('/meetings/{meeting}/execution/start', [MeetingExecutionController::class, 'start']);
    Route::post('/meetings/{meeting}/execution/end', [MeetingExecutionController::class, 'end']);

    // Official Minutes Drafting & Review
    Route::get('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'show']);
    Route::post('/meetings/{meeting}/minutes/draft', [MeetingMinutesController::class, 'create']);
    Route::put('/meetings/{meeting}/minutes/draft', [MeetingMinutesController::class, 'update']);
    Route::post('/meetings/{meeting}/minutes/submit', [MeetingMinutesController::class, 'submit']);
    Route::post('/meetings/{meeting}/minutes/reviews/correction', [MeetingMinutesController::class, 'correction']);
    Route::post('/meetings/{meeting}/minutes/reviews/{unit}/acknowledge', [MeetingMinutesController::class, 'acknowledge']);
    Route::post('/meetings/{meeting}/minutes/authority-approve', [MeetingMinutesController::class, 'authorityApprove']);
    Route::post('/meetings/{meeting}/minutes/finalize', [MeetingMinutesController::class, 'finalize']);
    Route::get('/meetings/{meeting}/minutes/final.pdf', [MeetingMinutesController::class, 'finalPdf']);

    // Rescheduling Governance
    Route::get('/meetings/{meeting}/reschedule', [MeetingRescheduleController::class, 'workspace']);
    Route::get('/meetings/{meeting}/schedule-history', [MeetingRescheduleController::class, 'history']);
    Route::post('/meetings/{meeting}/reschedule-cases', [MeetingRescheduleController::class, 'createCase']);
    Route::post('/meetings/{meeting}/reschedule/proposals', [MeetingRescheduleController::class, 'peerProposal']);
    Route::post('/meetings/{meeting}/reschedule/authority-proposals', [MeetingRescheduleController::class, 'authorityProposal']);
    Route::post('/meetings/{meeting}/reschedule/proposals/{proposal}/acknowledge', [MeetingRescheduleController::class, 'acknowledge']);
    Route::post('/meetings/{meeting}/reschedule/proposals/{proposal}/request-alternative', [MeetingRescheduleController::class, 'requestAlternative']);
    Route::post('/meetings/{meeting}/reschedule/alternative-requests/{alternative}/resolve', [MeetingRescheduleController::class, 'resolveAlternative']);
    Route::post('/meetings/{meeting}/reschedule/confirm', [MeetingRescheduleController::class, 'confirm']);

    // Participant Changes Governance
    Route::get('/meetings/{meeting}/participant-change/employees', [MeetingParticipantChangeController::class, 'employees']);
    Route::get('/meetings/{meeting}/participant-change', [MeetingParticipantChangeController::class, 'workspace']);
    Route::get('/meetings/{meeting}/participant-history', [MeetingParticipantChangeController::class, 'history']);
    Route::post('/meetings/{meeting}/participant-change-cases', [MeetingParticipantChangeController::class, 'createCase']);
    Route::put('/meetings/{meeting}/participant-change/{case}', [MeetingParticipantChangeController::class, 'updateCase']);
    Route::post('/meetings/{meeting}/participant-change/{case}/items', [MeetingParticipantChangeController::class, 'addItem']);
    Route::post('/meetings/{meeting}/participant-change/{case}/remove-item', [MeetingParticipantChangeController::class, 'removeItem']);
    Route::delete('/meetings/{meeting}/participant-change/{case}/items/{item}', [MeetingParticipantChangeController::class, 'deleteItem']);
    Route::post('/meetings/{meeting}/participant-change/{case}/submit', [MeetingParticipantChangeController::class, 'submit']);
    Route::post('/meetings/{meeting}/participant-change/{case}/return', [MeetingParticipantChangeController::class, 'returnForCorrection']);
    Route::post('/meetings/{meeting}/participant-change/{case}/reject', [MeetingParticipantChangeController::class, 'reject']);
    Route::post('/meetings/{meeting}/participant-change/{case}/refresh-baseline', [MeetingParticipantChangeController::class, 'refreshBaseline']);
    Route::post('/meetings/{meeting}/participant-change/{case}/confirm', [MeetingParticipantChangeController::class, 'confirm']);

    // Meeting Exceptions (Postponement / Emergency / Cancellation)
    Route::get('/meetings/{meeting}/exceptions', [MeetingExceptionController::class, 'index']);
    Route::post('/meetings/{meeting}/exceptions', [MeetingExceptionController::class, 'store']);
    Route::get('/meeting-exceptions/{case}', [MeetingExceptionController::class, 'show']);
    Route::put('/meeting-exceptions/{case}/draft', [MeetingExceptionController::class, 'updateDraft']);
    Route::post('/meeting-exceptions/{case}/submit', [MeetingExceptionController::class, 'submit']);
    Route::post('/meeting-exception-reviews/{review}/acknowledge', [MeetingExceptionController::class, 'acknowledge']);
    Route::post('/meeting-exception-reviews/{review}/approve', [MeetingExceptionController::class, 'approve']);
    Route::post('/meeting-exception-reviews/{review}/correction', [MeetingExceptionController::class, 'correction']);
    Route::post('/meeting-exceptions/{case}/reject', [MeetingExceptionController::class, 'reject']);
    Route::post('/meeting-exceptions/{case}/withdraw', [MeetingExceptionController::class, 'withdraw']);
    Route::post('/meeting-exceptions/{case}/apply', [MeetingExceptionController::class, 'apply']);
    Route::post('/meeting-exceptions/{case}/resume', [MeetingExceptionController::class, 'resume']);
});
