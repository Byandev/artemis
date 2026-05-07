<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/blog-posts/index', [
            'posts' => BlogPost::orderBy('created_at', 'desc')->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/blog-posts/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:blog_posts,slug',
            'excerpt' => 'required|string',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
            'featured_image' => 'nullable|image|max:2048', // 2MB Max
        ]);

        $post = BlogPost::create($validated);

        if ($request->hasFile('featured_image')) {
            $post->addMediaFromRequest('featured_image')->toMediaCollection('featured_image');
        }

        return redirect()->route('admin/blog-posts.index')->with('success', 'Post created successfully.');
    }

    public function edit(BlogPost $blogPost)
    {
        return Inertia::render('admin/blog-posts/edit', [
            'post' => $blogPost->load('media'),
            'image_url' => $blogPost->getFirstMediaUrl('featured_image')
        ]);
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => "required|string|unique:blog_posts,slug,{$blogPost->id}",
            'excerpt' => 'required|string',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
        ]);

        $blogPost->update($validated);

        if ($request->hasFile('featured_image')) {
            $blogPost->addMediaFromRequest('featured_image')->toMediaCollection('featured_image');
        }

        return redirect()->route('admin/blog-posts.index')->with('success', 'Post updated.');
    }

    public function destroy(BlogPost $blogPost)
    {
        $blogPost->delete();
        return redirect()->back()->with('success', 'Post moved to trash.');
    }

    // Toggle Methods
    public function publish(BlogPost $blogPost)
    {
        $blogPost->update(['status' => 'published', 'published_at' => now()]);
        return redirect()->back();
    }

    public function unpublish(BlogPost $blogPost)
    {
        $blogPost->update(['status' => 'draft']);
        return redirect()->back();
    }
}
