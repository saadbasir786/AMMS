<?php

use App\Http\Controllers\Api\Organization\AdminController;
use App\Http\Controllers\Api\Organization\DirectoryController;
use App\Http\Controllers\Api\Organization\MetadataController;
use App\Http\Controllers\Api\Organization\MeetingVenueController;
use App\Http\Controllers\Api\Organization\RoleManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/meta/amms', [MetadataController::class, 'amms']);
    // Directory & Workspace Hub
    Route::get('/dashboard', [DirectoryController::class, 'dashboard']);
    Route::get('/approvals', [DirectoryController::class, 'approvals']);
    Route::get('/notifications', [DirectoryController::class, 'notifications']);
    Route::get('/notifications/unread-count', [DirectoryController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [DirectoryController::class, 'markAllNotificationsRead']);
    Route::post('/notifications/{notification}/read', [DirectoryController::class, 'markNotificationRead']);
    Route::get('/organizational-units/tree', [DirectoryController::class, 'tree']);
    Route::get('/organizational-units', [DirectoryController::class, 'units']);
    Route::get('/employees', [DirectoryController::class, 'employees']);
    Route::get('/meeting-venues', [DirectoryController::class, 'meetingVenues']);
    Route::get('/admin/meeting-venues', [MeetingVenueController::class, 'index']);
    Route::post('/admin/meeting-venues', [MeetingVenueController::class, 'store']);
    Route::put('/admin/meeting-venues/{meetingVenue}', [MeetingVenueController::class, 'update']);
    Route::post('/admin/meeting-venues/{meetingVenue}/activate', [MeetingVenueController::class, 'activate']);
    Route::post('/admin/meeting-venues/{meetingVenue}/deactivate', [MeetingVenueController::class, 'deactivate']);

    // Admin & Organizational Structure Management
    Route::get('/admin/permissions', [RoleManagementController::class, 'permissions']);
    Route::get('/admin/roles', [RoleManagementController::class, 'index']);
    Route::post('/admin/roles', [RoleManagementController::class, 'store']);
    Route::get('/admin/roles/{role}', [RoleManagementController::class, 'show']);
    Route::get('/admin/roles/{role}/audits', [RoleManagementController::class, 'audits']);
    Route::put('/admin/roles/{role}', [RoleManagementController::class, 'update']);
    Route::post('/admin/roles/{role}/permissions', [RoleManagementController::class, 'permissionsUpdate']);
    Route::post('/admin/roles/{role}/activate', [RoleManagementController::class, 'activate']);
    Route::post('/admin/roles/{role}/deactivate', [RoleManagementController::class, 'deactivate']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::post('/admin/users', [AdminController::class, 'storeUser']);
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::get('/admin/users/{user}/access-audits', [AdminController::class, 'accessAudits']);
    Route::post('/admin/users/{user}/roles', [AdminController::class, 'assignRole']);
    Route::delete('/admin/users/{user}/roles/{roleScope}', [AdminController::class, 'revokeRole']);
    Route::get('/admin/employees', [AdminController::class, 'employees']);
    Route::post('/admin/employees', [AdminController::class, 'storeEmployee']);
    Route::put('/admin/employees/{employee}', [AdminController::class, 'updateEmployee']);
    Route::post('/admin/employees/{employee}/deactivate', [AdminController::class, 'deactivateEmployee']);
    Route::get('/admin/organizations', [AdminController::class, 'organizations']);
    Route::get('/admin/organizational-units/tree', [AdminController::class, 'tree']);
    Route::get('/admin/organizational-units', [AdminController::class, 'units']);
    Route::post('/admin/organizational-units', [AdminController::class, 'storeUnit']);
    Route::put('/admin/organizational-units/{unit}', [AdminController::class, 'updateUnit']);
    Route::get('/admin/positions', [AdminController::class, 'positions']);
    Route::post('/admin/positions', [AdminController::class, 'storePosition']);
    Route::put('/admin/positions/{position}', [AdminController::class, 'updatePosition']);
});
