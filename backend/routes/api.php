<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AMMS API Modular Routes
|--------------------------------------------------------------------------
|
| The API routes are organized by domain/feature modules mirroring
| the frontend architecture. Each domain route file defines its own
| endpoints, controllers, and middleware groups.
|
*/

// Authentication & Profile (Public & Protected)
require __DIR__.'/api/auth.php';

// Organization & Technical Administration
require __DIR__.'/api/organization.php';

// Meeting Requests (Internal, Bilateral, Multi-Directorate, High Authority, Authority-Initiated)
require __DIR__.'/api/meeting-requests.php';

// Meetings Management, Execution, Minutes, Rescheduling, Participant Changes & Exceptions
require __DIR__.'/api/meetings.php';

// Attendance, Biometrics & Terminal Hardware API
require __DIR__.'/api/attendance-biometrics.php';

// Action Items & Decision Follow-Up
require __DIR__.'/api/action-items.php';

// Reporting Dashboards, Registers & Export Jobs
require __DIR__.'/api/reporting.php';
