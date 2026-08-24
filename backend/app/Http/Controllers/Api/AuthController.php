<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        $user = User::with([
            'employee.unit',
            'roleScopes.role',
            'roleScopes.unit',
            'capabilityScopes' => fn ($query) => $query->active()->with('unit'),
        ])->where('email', $credentials['email'])->first();
        if (!$user || !$user->isActive() || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['معلومات ورود نادرست است یا حساب شما غیرفعال می‌باشد.']]);
        }
        $token = $user->createToken('amms-web')->plainTextToken;
        return response()->json(['success'=>true,'data'=>['token'=>$token,'user'=>new UserResource($user)],'message'=>'ورود موفقانه انجام شد.']);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['success'=>true,'data'=>null,'message'=>'از سیستم خارج شدید.']);
    }

    public function me(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $user = $request->user()->load([
            'employee.unit',
            'roleScopes.role',
            'roleScopes.unit',
            'capabilityScopes' => fn ($query) => $query->active()->with('unit'),
        ]);
        return (new UserResource($user))->additional(['success'=>true,'message'=>'اطلاعات کاربر.']);
    }
}
