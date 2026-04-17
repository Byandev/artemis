<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/leaderboards', function () {
    return Inertia::render('workspaces/public/leaderboard');
});

Route::get('/changelog', function () {
    return Inertia::render('workspaces/changelog');
})->name('changelog');

Route::get('/calculator', function () {
    return Inertia::render('calculator');
})->name('calculator');

Route::get('/rts-calculator', function () {
    return Inertia::render('rts-calculator');
})->name('rts.calculator');

Route::get('/privacy', function () {
    return Inertia::render('legal/privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('legal/terms');
})->name('terms');

Route::get('/data-policy', function () {
    return Inertia::render('legal/data-policy');
})->name('data-policy');

Route::get('/security', function () {
    return Inertia::render('legal/security');
})->name('security');

Route::get('/pitch', function () {
    return Inertia::render('pitch');
})->name('pitch');

Route::get('/about', function () {
    return Inertia::render('about');
})->name('about');

Route::get('/contact', function () {
    return Inertia::render('contact');
})->name('contact');

Route::get('/blog', function () {
    return Inertia::render('blog/index');
})->name('blog.index');

Route::get('/blog/{slug}', function (string $slug) {
    return Inertia::render('blog/' . $slug);
})->name('blog.show')->where('slug', '[a-z0-9\-]+');

Route::get('/design-guidelines', function () {
    return view('design-guidelines');
});

Route::get('/auth/facebook/callback', [\App\Http\Controllers\Integrations\FacebookController::class, 'callback']);

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', function () {
        $user = auth()->user();

        // Use the last selected workspace from session, otherwise fall back to first owned workspace
        $sessionWorkspaceId = session('current_workspace_id');

        $workspace = ($sessionWorkspaceId
            ? $user->workspaces()->where('workspaces.id', $sessionWorkspaceId)->first()
            : null)
            ?? $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->first();

        if ($workspace) {
            return redirect()->route('workspace.dashboard', $workspace->slug);
        }

        return redirect()->route('workspaces.setup');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/workspaces.php';
require __DIR__.'/browser-api.php';
