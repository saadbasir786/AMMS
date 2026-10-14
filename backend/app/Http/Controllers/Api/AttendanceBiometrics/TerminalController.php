<?php

namespace App\Http\Controllers\Api\AttendanceBiometrics;

use App\Actions\Terminals\IssueTerminalCredential;
use App\Actions\Terminals\IssueTerminalPairingCode;
use App\Actions\Terminals\PairTerminalWithCode;
use App\Actions\Terminals\RegisterTerminal;
use App\Actions\Terminals\RevokeTerminal;
use App\Actions\Terminals\RotateTerminalCredential;
use App\Actions\Terminals\UpdateTerminal;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\StoreTerminalRequest;
use App\Http\Requests\AttendanceBiometrics\TerminalRevokeRequest;
use App\Http\Requests\AttendanceBiometrics\UpdateTerminalRequest;
use App\Http\Requests\AttendanceBiometrics\PairTerminalRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\TerminalResource;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function index(PaginatedRequest $request)
    {
        $this->authorize('viewAny', Terminal::class);
        $terminals = Terminal::query()->with(['organization', 'currentSession'])->withCount('assignments')
            ->orderByDesc('id')->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return TerminalResource::collection($terminals)->additional(['success' => true, 'message' => 'فهرست ترمینال‌ها.']);
    }

    public function store(StoreTerminalRequest $request, RegisterTerminal $action): JsonResponse
    {
        $this->authorize('register', Terminal::class);
        $terminal = $action->execute($request->user(), $request->validated());

        return (new TerminalResource($terminal))->additional(['success' => true, 'message' => 'ترمینال ثبت شد.'])->response()->setStatusCode(201);
    }

    public function show(Terminal $terminal): TerminalResource
    {
        $this->authorize('view', $terminal);

        return new TerminalResource($terminal->load(['organization', 'currentSession', 'activeCredential', 'assignments.meeting', 'assignments.sessions']));
    }

    public function update(UpdateTerminalRequest $request, Terminal $terminal, UpdateTerminal $action): TerminalResource
    {
        $this->authorize('manage', $terminal);

        return new TerminalResource($action->execute($request->user(), $terminal, $request->validated()));
    }

    public function issueCredential(Request $request, Terminal $terminal, IssueTerminalCredential $action): JsonResponse
    {
        $this->authorize('manage', $terminal);
        $issued = $action->execute($request->user(), $terminal);

        return response()->json([
            'success' => true,
            'message' => 'اعتبارنامه صادر شد. این کلید فقط یک‌بار نمایش داده می‌شود.',
            'data' => ['terminal' => new TerminalResource($terminal->fresh()), 'credential' => ['id' => $issued['credential']->id, 'token' => $issued['token']]],
        ], 201);
    }

    public function issuePairingCode(Request $request, Terminal $terminal, IssueTerminalPairingCode $action): JsonResponse
    {
        $this->authorize('manage', $terminal);
        $issued = $action->execute($request->user(), $terminal);

        return response()->json([
            'success' => true,
            'message' => 'کد اتصال ترمینال صادر شد و سه دقیقه اعتبار دارد.',
            'data' => ['pairing_code' => $issued['code'], 'expires_at' => $issued['expires_at']],
        ], 201);
    }

    public function pair(PairTerminalRequest $request, PairTerminalWithCode $action): JsonResponse
    {
        $paired = $action->execute($request->validated()['pairing_code']);
        if (!$paired) {
            return response()->json([
                'success' => false,
                'code' => 'TERMINAL_PAIRING_CODE_INVALID',
                'message' => 'کد اتصال نامعتبر، مصرف‌شده یا منقضی شده است.',
                'errors' => ['pairing_code' => ['کد اتصال معتبر نیست.']],
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'ترمینال با موفقیت به AMMS متصل شد.',
            'data' => [
                'terminal' => new TerminalResource($paired['terminal']),
                'credential' => $paired['credential'],
            ],
        ]);
    }

    public function rotateCredential(Request $request, Terminal $terminal, RotateTerminalCredential $action): JsonResponse
    {
        $this->authorize('rotateCredential', $terminal);
        $issued = $action->execute($request->user(), $terminal);

        return response()->json([
            'success' => true,
            'message' => 'اعتبارنامه تعویض شد. این کلید فقط یک‌بار نمایش داده می‌شود.',
            'data' => ['terminal' => new TerminalResource($terminal->fresh()), 'credential' => ['id' => $issued['credential']->id, 'token' => $issued['token']]],
        ], 201);
    }

    public function revoke(TerminalRevokeRequest $request, Terminal $terminal, RevokeTerminal $action): TerminalResource
    {
        $this->authorize('revoke', $terminal);

        return new TerminalResource($action->execute($request->user(), $terminal, $request->validated()['reason']));
    }
}
