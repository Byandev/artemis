<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\MetaAds\Models\User as MetaUser;

class MetaOAuthController extends Controller
{
    public function redirect(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $state = Str::random(40);

        $request->session()->put('meta_oauth.state', $state);
        $request->session()->put('meta_oauth.workspace_id', $workspace->id);

        $authUrl = sprintf('%s/%s/dialog/oauth?', rtrim((string) config('metaads.graph_base_url'), '/'), config('metaads.graph_version'))
            .http_build_query([
                'client_id' => config('metaads.app_id'),
                'redirect_uri' => config('metaads.redirect_uri'),
                'state' => $state,
                'scope' => implode(',', config('metaads.oauth_scopes', [])),
                'response_type' => 'code',
            ]);

        // Facebook hosts the dialog at www.facebook.com, not graph.facebook.com.
        $authUrl = str_replace('graph.facebook.com', 'www.facebook.com', $authUrl);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('meta_oauth.state');
        $workspaceId = $request->session()->pull('meta_oauth.workspace_id');

        if (! $expectedState || $request->query('state') !== $expectedState || ! $workspaceId) {
            abort(400, 'Invalid OAuth state');
        }

        if ($error = $request->query('error_description') ?? $request->query('error')) {
            return redirect()->route('workspaces.metaads.integrations', Workspace::findOrFail($workspaceId))
                ->with('error', 'Meta connection failed: '.$error);
        }

        $code = $request->query('code');
        abort_unless($code, 400, 'Missing authorization code');

        $shortToken = $this->exchangeCode($code);
        $longToken = $this->exchangeForLongLivedToken($shortToken['access_token']);

        $profile = $this->fetchMe($longToken['access_token']);

        $metaUser = MetaUser::updateOrCreate(
            ['id' => $profile['id']],
            [
                'name' => $profile['name'] ?? 'Unknown',
                'email' => $profile['email'] ?? null,
                'access_token' => $longToken['access_token'],
                'token_expires_at' => isset($longToken['expires_in'])
                    ? Carbon::now()->addSeconds((int) $longToken['expires_in'])
                    : null,
            ],
        );

        $workspace = Workspace::findOrFail($workspaceId);

        $workspace->metaUsers()->syncWithoutDetaching([
            $metaUser->id => ['connected_by_user_id' => $request->user()->id],
        ]);

        return redirect()->route('workspaces.metaads.integrations', $workspace)
            ->with('success', "Connected Meta account: {$metaUser->name}");
    }

    private function exchangeCode(string $code): array
    {
        $response = Http::acceptJson()
            ->get(sprintf(
                '%s/%s/oauth/access_token',
                rtrim((string) config('metaads.graph_base_url'), '/'),
                config('metaads.graph_version'),
            ), [
                'client_id' => config('metaads.app_id'),
                'client_secret' => config('metaads.app_secret'),
                'redirect_uri' => config('metaads.redirect_uri'),
                'code' => $code,
            ])
            ->throw()
            ->json();

        return $response;
    }

    private function exchangeForLongLivedToken(string $shortToken): array
    {
        return Http::acceptJson()
            ->get(sprintf(
                '%s/%s/oauth/access_token',
                rtrim((string) config('metaads.graph_base_url'), '/'),
                config('metaads.graph_version'),
            ), [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('metaads.app_id'),
                'client_secret' => config('metaads.app_secret'),
                'fb_exchange_token' => $shortToken,
            ])
            ->throw()
            ->json();
    }

    private function fetchMe(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->get(sprintf(
                '%s/%s/me',
                rtrim((string) config('metaads.graph_base_url'), '/'),
                config('metaads.graph_version'),
            ), [
                'fields' => 'id,name,email',
            ])
            ->throw()
            ->json();
    }
}
