<?php

namespace App\Http\Controllers\Settings;

use App\Enums\IntegrationService;
use App\Exceptions\WelleAuthException;
use App\Exceptions\WelleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WelleIntegrationUpdateRequest;
use App\Jobs\FetchWelleProgress;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Welle\WelleClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Third-party accounts, one section per provider. Currently just Welle, whose
 * credentials belong to the signed-in user while the `welle_module_enabled`
 * toggle super admins flip decides whether a workspace sees the page at all.
 */
class IntegrationSettingsController extends Controller
{
    /**
     * How much history a newly connected account is filled in with, counted in
     * calendar months and including the month in progress. Two of them is what
     * My ESC's month picker can reach back to.
     */
    private const BACKFILL_MONTHS = 2;

    /**
     * Show the user's connected integrations for this workspace.
     */
    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $welle = $request->user()->integrationFor(IntegrationService::Welle);

        return Inertia::render('settings/integrations', [
            'workspace' => $workspace,
            // The encrypted token never leaves the server, and there is no
            // address to show either — the page learns only that something is
            // connected, and how the last unattended fetch went.
            'welle' => [
                'connected' => (bool) $welle?->hasToken(),
                'last_synced_at' => $welle?->last_synced_at?->toIso8601String(),
                'last_error' => $welle?->last_error,
            ],
        ]);
    }

    /**
     * Connect the signed-in user's Welle account.
     *
     * The password is sent straight to Welle, exchanged for an API token, and
     * then dropped — it is never written down. That is the whole point of doing
     * the exchange here rather than storing credentials and replaying them on
     * every fetch: what sits in the database is a token Welle can revoke, not a
     * password the person probably uses elsewhere.
     */
    public function updateWelle(
        WelleIntegrationUpdateRequest $request,
        Workspace $workspace,
        WelleClient $client,
    ): RedirectResponse {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $validated = $request->validated();

        try {
            $token = $client->login($validated['welle_email'], $validated['welle_password']);
        } catch (WelleAuthException) {
            // Welle answers a missing account and a wrong password with the
            // same error on purpose, so that someone cannot probe which
            // addresses have accounts. There is nothing here to tell them
            // apart with, and guessing at one would be wrong half the time.
            throw ValidationException::withMessages([
                'welle_password' => 'Incorrect email or password.',
            ]);
        } catch (WelleException) {
            // Welle being unreachable is not the person's mistake, and saying
            // so on the password field would read as one.
            throw ValidationException::withMessages([
                'welle_email' => 'Could not reach Welle just now. Try again in a moment.',
            ]);
        }

        $request->user()->integrations()->updateOrCreate(
            ['service' => IntegrationService::Welle],
            [
                'token' => $token,
                // A fresh token makes any previous "reconnect" stale.
                'last_error' => null,
            ],
        );

        $this->backfill($request->user());

        return Redirect::route('integrations.edit', ['workspace' => $workspace->slug])
            ->with('status', 'welle-connected');
    }

    /**
     * Disconnect the Welle account, dropping the token and its sync state with
     * it — there is nothing else to clear, which is the point of the token
     * living in its own row.
     */
    public function destroyWelle(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $request->user()->integrations()->forService(IntegrationService::Welle)->delete();

        return Redirect::route('integrations.edit', ['workspace' => $workspace->slug])
            ->with('status', 'welle-disconnected');
    }

    /**
     * Fill in this user's recent Welle history, so My ESC has something to show
     * before the first unattended run.
     *
     * Only the person who just connected: Welle credentials are personal, and
     * the token stored a moment ago is the only one this request has any
     * business reading with. Everyone else is reached by the nightly command.
     *
     * Worth doing because the unattended run reads `progress/week`, which knows
     * only the week containing today — so without this, connecting on a Monday
     * leaves the page empty until tomorrow morning, and the weeks before this
     * one never arrive at all.
     *
     * One job per calendar month, chained rather than dispatched side by side:
     * nothing settles how wide a window Welle's range endpoint will answer, and
     * a month is a span the page itself asks for. Chaining also keeps the jobs
     * off each other's per-user WithoutOverlapping lock, where they would spend
     * their attempts being released rather than fetching — and a chunk that ran
     * out of attempts stamps "could not reach Welle" on a connection that is
     * perfectly fine.
     *
     * Re-running a window only corrects the days inside it, so a reconnect
     * repeating this costs nothing and is usually wanted: a token that had
     * stopped working left a gap behind it.
     */
    private function backfill(User $user): void
    {
        $today = CarbonImmutable::today();
        $firstMonth = $today->startOfMonth()->subMonths(self::BACKFILL_MONTHS - 1);

        $jobs = [];

        for ($month = $firstMonth; $month <= $today; $month = $month->addMonth()) {
            $jobs[] = new FetchWelleProgress(
                (int) $user->getKey(),
                $month->toDateString(),
                // The month in progress ends today: Welle marks the rest of it
                // as future days, and the job drops those anyway.
                $month->endOfMonth()->min($today)->toDateString(),
            );
        }

        Bus::chain($jobs)->dispatch();
    }

    /** Guard against editing from a workspace the user is not a member of. */
    private function ensureMember(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );
    }

    /** The page and its writes only exist while the Welle module is on. */
    private function ensureWelleEnabled(Workspace $workspace): void
    {
        abort_unless($workspace->welle_module_enabled, 404);
    }
}
