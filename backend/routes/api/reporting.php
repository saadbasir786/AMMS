<?php

use App\Http\Controllers\Api\Reporting\ReportingController;
use App\Http\Controllers\Api\Reporting\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Executive & Directorate Reporting Dashboards
    Route::get('/reporting/dashboard/executive', [ReportingController::class, 'executive']);
    Route::get('/reporting/dashboard/directorate/{unit?}', [ReportingController::class, 'directorate']);
    Route::get('/reporting/dashboard/personal', [ReportingController::class, 'personal']);
    Route::get('/reporting/dashboard/authority', [ReportingController::class, 'authority']);
    Route::get('/reporting/dashboard/technical', [ReportingController::class, 'technical']);

    // Register & Activity Reports
    Route::get('/reporting/meetings', [ReportingController::class, 'meetings']);
    Route::get('/reporting/meeting-requests', [ReportingController::class, 'requests']);
    Route::get('/reporting/attendance', [ReportingController::class, 'attendance']);
    Route::get('/reporting/decisions', [ReportingController::class, 'decisions']);
    Route::get('/reporting/action-items', [ReportingController::class, 'actionItems']);
    Route::get('/reporting/exceptions', [ReportingController::class, 'exceptions']);
    Route::get('/reporting/high-authority-meetings', [ReportingController::class, 'highAuthorityMeetings']);

    // Asynchronous Export Jobs
    Route::post('/reporting/exports', [ReportExportController::class, 'store']);
    Route::get('/reporting/exports', [ReportExportController::class, 'index']);
    Route::get('/reporting/exports/{export}', [ReportExportController::class, 'show']);
    Route::get('/reporting/exports/{export}/download', [ReportExportController::class, 'download']);
});
