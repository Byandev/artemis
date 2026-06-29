<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(?Workspace $workspace = null): Response
    {
        return Inertia::render('settings/password', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Dedicated security entry — the generic model observer only records a
        // vague "user updated", so changing a password gets its own audit line
        // (mirrors the password.reset event from the forgot-password flow).
        Activity::build()->asUser()
            ->category(LogCategory::Security)
            ->action('account.password.changed')
            ->status(LogStatus::Success)
            ->user($request->user()->getKey())
            ->message('Account password was changed')
            ->save();

        return back();
    }
}
