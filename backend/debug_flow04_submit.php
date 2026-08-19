<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::where('email','tech.exec@example.test')->firstOrFail();
$request = App\Models\MeetingRequest::where('flow_type','DIRECTORATE_WITH_HIGH_AUTHORITY')->latest('id')->first();
$unit = App\Models\OrganizationalUnit::where('code','TECH')->first();
$policy = app(App\Policies\MeetingRequestPolicy::class);
$roleScopes = $user->roleScopes()->with('role')->get()->map(fn($s)=>['role'=>$s->role?->name,'unit'=>$s->organizational_unit_id])->all();
echo json_encode([
 'request'=>$request?->only(['id','flow_type','created_by','requesting_unit_id','status']),
 'user'=>$user->only(['id','email','is_active']),
 'unit'=>$unit?->only(['id','code','unit_type','status']),
 'can_create'=>$user->can('meeting-request.create'),
 'can_update'=>$user->can('meeting-request.update'),
 'has_role'=>$user->hasRole('EXECUTIVE_MANAGEMENT','sanctum'),
 'has_scope'=>$user->hasScopedRole('EXECUTIVE_MANAGEMENT',$unit?->id),
 'spatie_roles'=>$user->roles()->get(['id','name','guard_name'])->toArray(),
 'scope_rows'=>$user->roleScopes()->get(['id','role_id','organizational_unit_id'])->toArray(),
 'policy_edit'=>$request ? $policy->flow04RequesterEdit($user,$request) : null,
 'role_scopes'=>$roleScopes,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
