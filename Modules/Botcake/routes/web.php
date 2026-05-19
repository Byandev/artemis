<?php

use Illuminate\Support\Facades\Route;
<<<<<<< HEAD
=======
use App\Http\Controllers\BlogController;
use App\Http\Controllers\Admin\BlogPostController;
>>>>>>> 0535f1b9 (frontend blogpost)
use Modules\Botcake\Http\Controllers\API\FlowController;
use Modules\Botcake\Http\Controllers\API\SequenceController;
use Modules\Botcake\Http\Controllers\API\SequenceMessageController;

<<<<<<< HEAD
Route::prefix('api/v1/botcake')->name('api.v1.botcake.')->middleware(['auth', 'workspace'])->group(function () {
    Route::apiResource('/flows', FlowController::class)->names('flows.index')->only(['index']);
    Route::apiResource('/sequences', SequenceController::class)->names('sequences.index')->only(['index']);
    Route::apiResource('/sequence-messages', SequenceMessageController::class)->names('sequence-messages.index')->only(['index']);
});
=======
/*
|--------------------------------------------------------------------------
| Botcake API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1/botcake')
    ->name('api.v1.botcake.')
    ->middleware(['auth', 'verified', 'workspace'])
    ->group(function () {

        Route::apiResource('/flows', FlowController::class)
            ->only(['index']);

        Route::apiResource('/sequences', SequenceController::class)
            ->only(['index']);
    });

/*
|--------------------------------------------------------------------------
| Admin Blog Post Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Blog Posts List
        Route::get('/posts', [BlogPostController::class, 'index'])
            ->name('blog-posts.index');

        // Create Page
        Route::get('/posts/create', [BlogPostController::class, 'create'])
            ->name('blog-posts.create');

        // Store Post
        Route::post('/posts', [BlogPostController::class, 'store'])
            ->name('blog-posts.store');

        // Show Single Post
        Route::get('/posts/{blogPost}', [BlogPostController::class, 'show'])
            ->name('blog-posts.show');

        // Edit Page
        Route::get('/posts/{blogPost}/edit', [BlogPostController::class, 'edit'])
            ->name('blog-posts.edit');

        // Update Post
        Route::put('/posts/{blogPost}', [BlogPostController::class, 'update'])
            ->name('blog-posts.update');

        // Delete Post
        Route::delete('/posts/{blogPost}', [BlogPostController::class, 'destroy'])
            ->name('blog-posts.destroy');
    });

/*
|--------------------------------------------------------------------------
| Public Blog Routes
|--------------------------------------------------------------------------
*/

Route::get('/blog', [BlogController::class, 'index'])
    ->name('blog.index');

Route::get('/blog/{blogPost:slug}', [BlogController::class, 'show'])
    ->name('blog.show');
>>>>>>> 0535f1b9 (frontend blogpost)
