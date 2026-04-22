<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Workspaces/Index', [
            'workspaces' => Workspace::query()
                ->with('owner:id,name')
                ->withCount('pages')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                })
                ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
                ->paginate(15)
                ->withQueryString(),

            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }
}

