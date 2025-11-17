<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Workspace $workspace)
    {
        $products = Product::ofWorkspace($workspace)
            ->orderBy('created_at', 'desc')
            ->paginate();

        return Inertia::render('workspaces/products/index', [
            'products' => $products,
            'workspace' => $workspace,
        ]);
    }

    public function create(Workspace $workspace)
    {
        return Inertia::render('workspaces/products/create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $product = Product::create([
            'title' => $request->title,
            'description' => $request->description,
            'workspace_id' => $workspace->id,
            'owner_id' => auth()->user()->id,
        ]);

        $product->addMediaFromRequest('image')
            ->toMediaCollection('PRODUCT_IMAGE');

        return redirect(route('workspaces.products.index', $workspace->slug));
    }
}
