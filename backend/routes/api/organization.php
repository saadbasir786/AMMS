<?php

use App\Http\Controllers\Api\Organization\AdminController;
use App\Http\Controllers\Api\Organization\DirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Directory & Workspace Hub
    Route::get('/dashboard', [DirectoryController::class, 'dashboard']);
    Route::get('/approvals', [DirectoryController::class, 'approvals']);
    Route::get('/notifications', [DirectoryController::class, 'notifications']);
    Route::post('/notifications/{notification}/read', [DirectoryController::class, 'markNotificationRead']);
    Route::get('/organizational-units', [DirectoryController::class, 'units']);
    Route::get('/employees', [DirectoryController::class, 'employees']);

    // Admin & Organizational Structure Management
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::post('/admin/users', [AdminController::class, 'storeUser']);
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::post('/admin/users/{user}/roles', [AdminController::class, 'assignRole']);
    Route::get('/admin/employees', [AdminController::class, 'employees']);
    Route::post('/admin/employees', [AdminController::class, 'storeEmployee']);
    Route::put('/admin/employees/{employee}', [AdminController::class, 'updateEmployee']);
    Route::post('/admin/employees/{employee}/deactivate', [AdminController::class, 'deactivateEmployee']);
    Route::get('/admin/organizations', [AdminController::class, 'organizations']);
    Route::get('/admin/organizational-units', [AdminController::class, 'units']);
    Route::post('/admin/organizational-units', [AdminController::class, 'storeUnit']);
    Route::get('/admin/positions', [AdminController::class, 'positions']);
    Route::post('/admin/positions', [AdminController::class, 'storePosition']);
    Route::put('/admin/positions/{position}', [AdminController::class, 'updatePosition']);
});
