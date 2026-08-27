<?php

use App\Http\Controllers\Api\ActionItems\DecisionFollowUpController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Meeting Decision Follow-Up Initialization & Classification
    Route::get('/meetings/{meeting}/follow-up', [DecisionFollowUpController::class, 'meeting']);
    Route::post('/meetings/{meeting}/follow-up/initialize', [DecisionFollowUpController::class, 'initialize']);
    Route::post('/decisions/{decision}/follow-up/classify', [DecisionFollowUpController::class, 'classify']);
    Route::post('/decisions/{decision}/action-items', [DecisionFollowUpController::class, 'storeActionItem']);
    Route::post('/decisions/{decision}/follow-up/close', [DecisionFollowUpController::class, 'close']);

    // Action Items Lifecycle Management
    Route::get('/action-items', [DecisionFollowUpController::class, 'index']);
    Route::get('/action-items/{actionItem}', [DecisionFollowUpController::class, 'show']);
    Route::get('/action-items/{actionItem}/action-capabilities', [DecisionFollowUpController::class, 'actionCapabilities']);
    Route::post('/action-items/{actionItem}/start', [DecisionFollowUpController::class, 'start']);
    Route::post('/action-items/{actionItem}/progress', [DecisionFollowUpController::class, 'progress']);
    Route::post('/action-items/{actionItem}/block', [DecisionFollowUpController::class, 'block']);
    Route::post('/action-items/{actionItem}/unblock', [DecisionFollowUpController::class, 'unblock']);
    Route::post('/action-items/{actionItem}/assignment', [DecisionFollowUpController::class, 'assignment']);
    Route::post('/action-items/{actionItem}/cancel', [DecisionFollowUpController::class, 'cancel']);

    // Deadline Extensions
    Route::post('/action-items/{actionItem}/extensions', [DecisionFollowUpController::class, 'extension']);
    Route::post('/action-item-extensions/{extension}/approve', [DecisionFollowUpController::class, 'approveExtension']);
    Route::post('/action-item-extensions/{extension}/reject', [DecisionFollowUpController::class, 'rejectExtension']);

    // Completion & Verification
    Route::post('/action-items/{actionItem}/completion/submit', [DecisionFollowUpController::class, 'submit']);
    Route::post('/action-items/{actionItem}/completion/verify', [DecisionFollowUpController::class, 'verify']);
    Route::post('/action-items/{actionItem}/completion/return', [DecisionFollowUpController::class, 'returnCompletion']);

    // Evidences & Attachments
    Route::post('/action-items/{actionItem}/evidence', [DecisionFollowUpController::class, 'uploadEvidence']);
    Route::get('/action-items/{actionItem}/evidence/{evidence}', [DecisionFollowUpController::class, 'downloadEvidence'])->name('action-items.evidence.download');
});
