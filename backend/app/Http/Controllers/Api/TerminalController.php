<?php

namespace App\Http\Controllers\Api;

use App\Actions\Terminals\IssueTerminalCredential;
use App\Actions\Terminals\RegisterTerminal;
use App\Actions\Terminals\RevokeTerminal;
use App\Actions\Terminals\RotateTerminalCredential;
use App\Actions\Terminals\UpdateTerminal;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTerminalRequest;
use App\Http\Requests\TerminalRevokeRequest;
use App\Http\Requests\UpdateTerminalRequest;
use App\Http\Resources\TerminalMeetingSessionResource;
use App\Http\Resources\TerminalResource;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Terminal::class);
        $terminals = Terminal::query()->with(['organization', 'currentSession'])->withCount('assignments')->latest('id')->paginate(20);
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
