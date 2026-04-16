<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $pages = Page::where('workspace_id', $workspace->id)->get(['id', 'name']);

        return response()->json(['pages' => $pages]);
    }
}