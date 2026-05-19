<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Inertia\Inertia;
use Illuminate\Http\Request;

class BlogController extends Controller
{

    public function index()
    {
        $posts = BlogPost::published()
            ->orderBy('published_at', 'desc')
            ->paginate(10)
            ->through(fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'published_at' => $post->published_at->format('M d, Y'),
                'featured_image' => $post->getFirstMediaUrl('featured_image'),
            ]);

        return Inertia::render('blog/index', [
            'posts' => $posts
        ]);
    }


    public function show($slug)
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('blog/show', [
            'post' => [
                'title' => $post->title,
                'content' => $post->content,
                'published_at' => $post->published_at->format('M d, Y'),
                'featured_image' => $post->getFirstMediaUrl('featured_image'),
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'excerpt' => $post->excerpt,
            ]
        ]);
    }
}