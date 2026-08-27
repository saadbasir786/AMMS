<?php

use App\Http\Controllers\Api\AttendanceBiometrics\AttendanceController;
use App\Http\Controllers\Api\AttendanceBiometrics\BiometricController;
use App\Http\Controllers\Api\AttendanceBiometrics\MeetingTerminalController;
use App\Http\Controllers\Api\AttendanceBiometrics\TerminalApiController;
use App\Http\Controllers\Api\AttendanceBiometrics\TerminalBiometricEnrollmentController;
use App\Http\Controllers\Api\AttendanceBiometrics\TerminalController;
use Illuminate\Support\Facades\Route;

// Operational & Management Endpoints (Sanctum Auth)
Route::middleware('auth:sanctum')->group(function () {
    // Biometrics Enrollment & Templates
    Route::get('/employees/{employee}/biometrics', [BiometricController::class, 'employee']);
    Route::post('/biometric/enrollment-sessions', [BiometricController::class, 'start']);
    Route::post('/biometric/enrollment-sessions/{enrollment}/complete', [BiometricController::class, 'complete']);
    Route::post('/biometric/enrollment-sessions/{enrollment}/cancel', [BiometricController::class, 'cancel']);
    Route::post('/biometric/templates/{template}/revoke', [BiometricController::class, 'revoke']);

    // Attendance Management
    Route::get('/meetings/{meeting}/attendance', [AttendanceController::class, 'index']);
    Route::post('/meetings/{meeting}/attendance/manual', [AttendanceController::class, 'manual']);

    // Terminal Registry & Credential Management
    Route::get('/terminals', [TerminalController::class, 'index']);
    Route::post('/terminals', [TerminalController::class, 'store']);
    Route::get('/terminals/{terminal}', [TerminalController::class, 'show']);
    Route::patch('/terminals/{terminal}', [TerminalController::class, 'update']);
    Route::post('/terminals/{terminal}/credentials', [TerminalController::class, 'issueCredential']);
    Route::post('/terminals/{terminal}/credentials/rotate', [TerminalController::class, 'rotateCredential']);
    Route::post('/terminals/{terminal}/revoke', [TerminalController::class, 'revoke']);

    // Meeting Terminal Assignments
    Route::get('/meetings/{meeting}/terminals', [MeetingTerminalController::class, 'workspace']);
    Route::get('/meetings/{meeting}/terminal-assignments', [MeetingTerminalController::class, 'workspace']);
    Route::post('/meetings/{meeting}/terminal-assignments', [MeetingTerminalController::class, 'assign']);
    Route::post('/meetings/{meeting}/terminal-assignments/{assignment}/refresh', [MeetingTerminalController::class, 'refresh']);
    Route::post('/meetings/{meeting}/terminal-assignments/{assignment}/revoke', [MeetingTerminalController::class, 'revoke']);
});

// Terminal Device API (Hardware Client Authentication)
Route::prefix('terminal/v1')->middleware(['terminal.auth', 'throttle:30,1'])->group(function (): void {
    Route::get('/me', [TerminalApiController::class, 'me']);
    Route::post('/heartbeat', [TerminalApiController::class, 'heartbeat']);
    Route::get('/assignments/current', [TerminalApiController::class, 'currentAssignments']);
    Route::post('/assignments/{assignment:uuid}/activate', [TerminalApiController::class, 'activate'])->middleware('throttle:10,1');
    Route::get('/enrollment/current', [TerminalBiometricEnrollmentController::class, 'current']);
    Route::post('/enrollment/{enrollment:uuid}/template', [TerminalBiometricEnrollmentController::class, 'submit']);
});

// Active Terminal Meeting Session Context
Route::prefix('terminal/v1')->middleware(['terminal.session.auth', 'throttle:60,1'])->group(function (): void {
    Route::get('/sessions/{session:uuid}/manifest', [TerminalApiController::class, 'manifest']);
    Route::get('/sessions/{session:uuid}/biometric-candidates', [TerminalApiController::class, 'biometricCandidates']);
    Route::post('/sessions/{session:uuid}/attendance/fingerprint', [TerminalApiController::class, 'fingerprintAttendance']);
    Route::post('/sessions/{session:uuid}/close', [TerminalApiController::class, 'close']);
});
