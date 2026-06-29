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
use Illuminate\Validation\ValidationException;
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
    public function update(Request $request, ?Workspace $workspace = null): RedirectResponse
    {
        // Account password is a global setting, but the workspace activity log
        // filters by workspace_id. Account settings post to the workspace-less
        // /settings/password route, so fall back to the user's active workspace
        // (session) or their first one — otherwise the change is only visible in
        // the global admin log, never the workspace one the user is looking at.
        $workspace ??= Workspace::find($request->session()->get('current_workspace_id'))
            ?? $request->user()->workspaces()->first();

        try {
            $validated = $request->validate([
                'current_password' => ['required', 'current_password'],
                'password' => ['required', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            // Audit rejected attempts too — a wrong current password in
            // particular may be someone trying to take over the session. Only
            // the failing field names are stored, never the values entered.
            Activity::build()->asUser()
                ->category(LogCategory::Security)
                ->action('account.password.change_failed')
                ->status(LogStatus::Warning)
                ->user($request->user()->getKey())
                ->workspace($workspace)
                ->message('Password change was rejected')
                ->metadata(['reasons' => array_keys($e->errors())])
                ->save();

            throw $e;
        }

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
            ->workspace($workspace)
            ->message('Account password was changed')
            ->save();

        return back();
    }
}
