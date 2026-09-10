<?php

use App\Http\Middleware\AuthenticateTerminal;
use App\Http\Middleware\AuthenticateTerminalSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api')
                ->group(base_path('routes/admin-capabilities.php'));
        },
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['terminal.auth' => AuthenticateTerminal::class, 'terminal.session.auth' => AuthenticateTerminalSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            if ($e instanceof AuthenticationException) {
                return response()->json(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'برای ادامه، وارد حساب کاربری شوید.', 'errors' => new stdClass], 401);
            }
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            if ($e instanceof ValidationException) {
                return response()->json(['success' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'اطلاعات واردشده معتبر نیست.', 'errors' => $e->errors()], 422);
            }
            $message = $e->getMessage() ?: 'خطای غیرمنتظره در سیستم رخ داد.';
            $code = is_string($message) && preg_match('/^[A-Z0-9_]+$/', $message) ? $message : ($e instanceof AuthorizationException || $status === 403 ? 'FORBIDDEN' : ($status === 401 ? 'UNAUTHORIZED' : ($status === 422 ? 'VALIDATION_ERROR' : 'SERVER_ERROR')));

            return response()->json(['success' => false, 'code' => $code, 'message' => preg_match('/^[A-Z0-9_]+$/', $message) ? 'این عملیات برای شما مجاز نیست.' : $message, 'errors' => new stdClass], $status);
        });
    })->create();
