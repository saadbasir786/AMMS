<?php

namespace App\Http\Middleware;

use App\Support\TerminalCredentialManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTerminal
{
    public function __construct(private TerminalCredentialManager $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        $secret = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        $credential = $secret !== '' ? $this->credentials->resolve($secret) : null;

        if (!$credential) {
            Log::warning('FLOW09 terminal authentication failure', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'has_bearer' => $secret !== '',
            ]);
            return response()->json([
                'success' => false,
                'code' => 'TERMINAL_UNAUTHORIZED',
                'message' => 'اعتبارنامهٔ ترمینال معتبر نیست.',
                'errors' => new \stdClass(),
            ], 401);
        }

        $this->credentials->touch($credential);
        $request->attributes->set('terminal', $credential->terminal);
        $request->attributes->set('terminalCredential', $credential);

        return $next($request);
    }
}
