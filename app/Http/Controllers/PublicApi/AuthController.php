<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Log a user in on behalf of a first-party app (WellSync).
     *
     * No API key required: the caller posts the user's email + password and
     * gets back the user plus a Sanctum bearer token. That token is what
     * authenticates subsequent writes — the user is resolved from it, so no
     * later request needs to name an email.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // Hash::check against a dummy hash when the email is unknown so a miss
        // costs the same time as a wrong password and can't be used to probe
        // which emails exist.
        $passwordValid = $user
            ? Hash::check($request->input('password'), $user->password)
            : Hash::check($request->input('password'), self::DUMMY_HASH);

        if (! $user || ! $passwordValid) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        $device = $request->input('device_name') ?: 'wellsync';

        // One live token per device name, so repeat logins from the same app
        // don't pile up tokens that stay valid forever.
        $user->tokens()->where('name', $device)->delete();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
            'token' => $user->createToken($device)->plainTextToken,
        ]);
    }

    /**
     * Return the authenticated user plus their ESC streaks.
     *
     * A lightweight profile/summary endpoint so the app can render the streak
     * header (and greet the user) without having just written a record.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
            'streak' => [
                'current' => (int) $user->current_streak,
                'longest' => (int) $user->longest_streak,
            ],
        ]);
    }

    /**
     * Revoke the token used to make this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * A real bcrypt hash of a value nobody can supply, used only to burn the
     * same CPU as a genuine check when the email doesn't exist.
     */
    private const DUMMY_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
}
