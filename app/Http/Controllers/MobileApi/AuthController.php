<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;

/**
 * Token login for the Creatives Tracker mobile app. Unlike the /api/v1/public routes
 * this is per-user, not per-workspace: the app trades an email + password
 * (plus a 2FA code when the account has one) for a Sanctum personal access
 * token, then sends it as `Authorization: Bearer <token>` on every call.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        // Same rule as the web login: a password alone is not enough for an
        // account with 2FA turned on.
        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            if (! $request->filled('code') && ! $request->filled('recovery_code')) {
                return response()->json([
                    'error' => 'Two-factor code required.',
                    'two_factor_required' => true,
                ], 422);
            }

            if (! $this->passesTwoFactor($request, $user)) {
                return response()->json(['error' => 'Invalid two-factor code.'], 401);
            }
        }

        $token = $user->createToken($request->input('device_name') ?: 'creatives-tracker')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function passesTwoFactor(Request $request, User $user): bool
    {
        if ($request->filled('code')) {
            return app(TwoFactorAuthenticationProvider::class)->verify(
                decrypt($user->two_factor_secret),
                $request->input('code'),
            );
        }

        $recoveryCode = collect($user->recoveryCodes())
            ->first(fn ($code) => hash_equals($code, $request->input('recovery_code')));

        if (! $recoveryCode) {
            return false;
        }

        $user->replaceRecoveryCode($recoveryCode);

        return true;
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
