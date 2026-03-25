<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Get current workspace from URL route parameter
        $currentWorkspace = null;
        if ($request->user() && $request->route('workspace')) {
            $currentWorkspace = $request->route('workspace');
        }

        // Get first 3 workspaces of the authenticated user
        $workspaces = $request->user()
            ? $request->user()->workspaces()->limit(3)->get()
            : collect();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'workspaces' => $workspaces,
            'currentWorkspace' => $currentWorkspace,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Minimal Ziggy config just for current location tracking
            'ziggy' => [
                'location' => $request->url(),
            ],
        ];
    }
}
