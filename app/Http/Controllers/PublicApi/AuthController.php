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
     * Verify a set of Artemis credentials on behalf of a first-party app.
     *
     * No API key required: the caller posts the user's email + password and
     * gets back the user when the credentials are good. No session or token is
     * issued — the calling app signs the user into its own session from this.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
        ]);
    }

    /**
     * A real bcrypt hash of a value nobody can supply, used only to burn the
     * same CPU as a genuine check when the email doesn't exist.
     */
    private const DUMMY_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
}
