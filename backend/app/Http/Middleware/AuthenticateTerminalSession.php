<?php

namespace App\Http\Middleware;

use App\Models\TerminalMeetingSession;
use App\Support\TerminalAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTerminalSession
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        $secret = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        $session = $secret !== ''
            ? TerminalMeetingSession::query()->with(['terminal', 'assignment', 'meeting'])->where('session_token_hash', hash('sha256', $secret))->first()
            : null;

        $routeSession = $request->route('session');
        $routeSessionUuid = is_object($routeSession) ? ($routeSession->uuid ?? null) : $routeSession;
        if (!$session || ($routeSessionUuid && $routeSessionUuid !== $session->uuid) || !$session->terminal || $session->terminal->status !== 'ACTIVE') {
            Log::warning('FLOW09 terminal session authentication failure', ['ip' => $request->ip(), 'path' => $request->path()]);
            return response()->json(['success' => false, 'code' => 'TERMINAL_SESSION_UNAUTHORIZED', 'message' => 'نشست ترمینال معتبر نیست.', 'errors' => new \stdClass()], 401);
        }

        if ($session->isExpired() && $session->status === 'ACTIVE') {
            $session->forceFill(['status' => 'EXPIRED'])->saveQuietly();
        }

        if (!$session->isEffectivelyActive()) {
            return response()->json(['success' => false, 'code' => 'TERMINAL_SESSION_INACTIVE', 'message' => 'نشست ترمینال فعال نیست.', 'errors' => new \stdClass()], 401);
        }

        $meeting = $session->meeting;
        $stale = [
            'schedule_changed' => (int) $session->schedule_version_id !== (int) $meeting->current_schedule_version_id,
            'participants_changed' => (int) $session->participant_version_id !== (int) $meeting->current_participant_version_id,
        ];
        if ($stale['schedule_changed'] || $stale['participants_changed']) {
            $session->forceFill(['status' => 'REVOKED', 'closed_at' => now(), 'close_reason' => 'BOUND_VERSION_CHANGED'])->saveQuietly();
            $this->audit->record('TERMINAL_SESSION_REVOKED', null, $session->terminal, $meeting, $session->assignment, $session, ['reason' => 'BOUND_VERSION_CHANGED', ...$stale]);
            return response()->json(['success' => false, 'code' => 'TERMINAL_SESSION_STALE', 'message' => 'نسخهٔ جلسه پس از فعال‌سازی تغییر کرده است.', 'errors' => $stale], 422);
        }

        $request->attributes->set('terminalSession', $session);
        $request->attributes->set('terminal', $session->terminal);

        return $next($request);
    }
}
