<?php

use App\Http\Controllers\Api\Organization\AdminController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/users/{user}/capabilities', [AdminController::class, 'grantCapability']);
Route::delete('/admin/users/{user}/capabilities/{capabilityScope}', [AdminController::class, 'revokeCapability']);
