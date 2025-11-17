<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\FetchAdAccounts;
use App\Models\FacebookAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FacebookController extends Controller
{
    public function callback(Request $request)
    {
        $response = Http::asForm()->post('https://graph.facebook.com/v22.0/oauth/access_token', [
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'redirect_uri' => config('services.facebook.redirect'),
            'code' => $request->input('code'),
        ]);

        if (! $response->successful()) {
            return redirect('/');
        }

        $state = json_decode($request->input('state'));

        $data = $response->json();
        $access_token = $data['access_token'];

        $response = Http::get('https://graph.facebook.com/v22.0/me', [
            'fields' => 'id,name,email,picture',
            'access_token' => $access_token,
        ]);

        if (! $response->successful()) {
            return redirect('/');
        }

        $data = $response->json();

        $facebookAccount = FacebookAccount::updateOrCreate([
            'id' => $data['id'],
        ], [
            'user_id' => $state->auth_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'picture_url' => $data['picture']['data']['url'] ?? null,
            'access_token' => $access_token,
        ]);

        $workspace = Workspace::find($state->workspace_id);

        $facebookAccount->workspaces()->sync($state->workspace_id);

        dispatch(new FetchAdAccounts($facebookAccount));

        return redirect("workspaces/$workspace->slug/facebook-accounts");
    }
}
